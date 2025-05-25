<?php
// Start session
session_start();

// For testing purposes, we're not enforcing authentication
// In a real application, you would check if the user is logged in
$user_type = isset($_GET['user_type']) ? $_GET['user_type'] : (isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'student');
$user_id = 1; // Default for testing

if ($user_type == 'student') {
    $user_id = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : 1;
} elseif ($user_type == 'faculty') {
    $user_id = isset($_SESSION['faculty_id']) ? $_SESSION['faculty_id'] : 1;
}

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user info
if ($user_type == 'student') {
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
} elseif ($user_type == 'faculty') {
    $stmt = $conn->prepare("SELECT * FROM faculty WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
}

// Initialize variables
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';
$message_type = '';

// Get course details
$course = null;
if ($course_id > 0) {
    $stmt = $conn->prepare("
        SELECT c.*, f.full_name as faculty_name
        FROM courses c
        LEFT JOIN faculty f ON c.faculty_id = f.id
        WHERE c.id = ?
    ");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $course = $result->fetch_assoc();
    $stmt->close();
    
    if (!$course) {
        $message = "Course not found.";
        $message_type = "danger";
        $course_id = 0;
    }
}

// Check if user has access to this course
$has_access = false;
if ($course_id > 0) {
    if ($user_type == 'faculty' && $course['faculty_id'] == $user_id) {
        $has_access = true;
    } elseif ($user_type == 'student') {
        $stmt = $conn->prepare("
            SELECT * FROM course_enrollment 
            WHERE student_id = ? AND course_id = ?
        ");
        $stmt->bind_param("ii", $user_id, $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $has_access = $result->num_rows > 0;
        $stmt->close();
    }
    
    if (!$has_access) {
        $message = "You don't have access to this course forum.";
        $message_type = "danger";
        $course_id = 0;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $course_id > 0 && $has_access) {
    if (isset($_POST['create_topic'])) {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        
        if (empty($title) || empty($content)) {
            $message = "Title and content are required.";
            $message_type = "danger";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO forum_topics (course_id, title, content, created_by_id, created_by_type) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("issss", $course_id, $title, $content, $user_id, $user_type);
            
            if ($stmt->execute()) {
                $message = "Topic created successfully.";
                $message_type = "success";
                $action = 'list'; // Return to list view
            } else {
                $message = "Error creating topic: " . $conn->error;
                $message_type = "danger";
            }
            
            $stmt->close();
        }
    } elseif (isset($_POST['pin_topic']) && $user_type == 'faculty') {
        $topic_id = (int)$_POST['topic_id'];
        $is_pinned = (int)$_POST['is_pinned'];
        
        $stmt = $conn->prepare("UPDATE forum_topics SET is_pinned = ? WHERE id = ? AND course_id = ?");
        $stmt->bind_param("iii", $is_pinned, $topic_id, $course_id);
        
        if ($stmt->execute()) {
            $message = $is_pinned ? "Topic pinned successfully." : "Topic unpinned successfully.";
            $message_type = "success";
        } else {
            $message = "Error updating topic: " . $conn->error;
            $message_type = "danger";
        }
        
        $stmt->close();
    } elseif (isset($_POST['lock_topic']) && $user_type == 'faculty') {
        $topic_id = (int)$_POST['topic_id'];
        $is_locked = (int)$_POST['is_locked'];
        
        $stmt = $conn->prepare("UPDATE forum_topics SET is_locked = ? WHERE id = ? AND course_id = ?");
        $stmt->bind_param("iii", $is_locked, $topic_id, $course_id);
        
        if ($stmt->execute()) {
            $message = $is_locked ? "Topic locked successfully." : "Topic unlocked successfully.";
            $message_type = "success";
        } else {
            $message = "Error updating topic: " . $conn->error;
            $message_type = "danger";
        }
        
        $stmt->close();
    }
}

// Get forum topics for this course
$topics = [];
if ($course_id > 0 && $action == 'list') {
    $sql = "
        SELECT t.*, 
               CASE 
                   WHEN t.created_by_type = 'student' THEN s.full_name
                   WHEN t.created_by_type = 'faculty' THEN f.full_name
                   ELSE 'Unknown'
               END as author_name,
               (SELECT COUNT(*) FROM forum_replies WHERE topic_id = t.id) as reply_count
        FROM forum_topics t
        LEFT JOIN students s ON t.created_by_id = s.id AND t.created_by_type = 'student'
        LEFT JOIN faculty f ON t.created_by_id = f.id AND t.created_by_type = 'faculty'
        WHERE t.course_id = ?
        ORDER BY t.is_pinned DESC, t.created_at DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $topics[] = $row;
    }
    
    $stmt->close();
}

// Get available courses for navigation
$available_courses = [];
if ($user_type == 'student') {
    $stmt = $conn->prepare("
        SELECT c.* 
        FROM courses c
        JOIN course_enrollment ce ON c.id = ce.course_id
        WHERE ce.student_id = ?
        ORDER BY c.course_name
    ");
    $stmt->bind_param("i", $user_id);
} else {
    $stmt = $conn->prepare("
        SELECT * FROM courses 
        WHERE faculty_id = ?
        ORDER BY course_name
    ");
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $available_courses[] = $row;
}
$stmt->close();

// Handle logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.html");
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Forums - Student Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .topic-card {
            transition: transform 0.2s;
        }
        .topic-card:hover {
            transform: translateY(-3px);
        }
        .pinned-topic {
            border-left: 4px solid #198754;
        }
        .locked-topic {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark <?php echo $user_type == 'faculty' ? 'bg-success' : 'bg-primary'; ?>">
        <div class="container">
            <a class="navbar-brand" href="#">Student Management System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if ($user_type == 'student'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="student_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_profile.php">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_courses.php">My Courses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_grades.php">My Grades</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_attendance.php">My Attendance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_files.php">Files</a>
                    </li>
                    <?php elseif ($user_type == 'faculty'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_students.php">Manage Students</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_courses.php">Manage Courses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_grades.php">Grades</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="attendance_tracking.php">Attendance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_files.php">Files</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        Welcome, <?php echo htmlspecialchars($user['full_name']); ?>
                    </span>
                    <a href="?logout=1" class="btn btn-light btn-sm">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-4">
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($course_id == 0): ?>
            <!-- Course Selection -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-comments me-2"></i>Course Forums</h5>
                        </div>
                        <div class="card-body">
                            <p>Please select a course to view its discussion forum:</p>
                            
                            <div class="row">
                                <?php foreach ($available_courses as $c): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($c['course_code']); ?></h5>
                                                <p class="card-text"><?php echo htmlspecialchars($c['course_name']); ?></p>
                                            </div>
                                            <div class="card-footer bg-white">
                                                <a href="course_forums.php?course_id=<?php echo $c['id']; ?>&user_type=<?php echo $user_type; ?>" class="btn btn-primary w-100">
                                                    <i class="fas fa-comments me-2"></i>View Forum
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if (count($available_courses) == 0): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>You are not enrolled in any courses.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif ($action == 'new_topic' && $has_access): ?>
            <!-- Create New Topic Form -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">
                                <i class="fas fa-plus-circle me-2"></i>Create New Topic in 
                                <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="course_forums.php?course_id=<?php echo $course_id; ?>&user_type=<?php echo $user_type; ?>">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Topic Title</label>
                                    <input type="text" class="form-control" id="title" name="title" required>
                                </div>
                                <div class="mb-3">
                                    <label for="content" class="form-label">Content</label>
                                    <textarea class="form-control" id="content" name="content" rows="6" required></textarea>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <a href="course_forums.php?course_id=<?php echo $course_id; ?>&user_type=<?php echo $user_type; ?>" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Back to Forum
                                    </a>
                                    <button type="submit" name="create_topic" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-2"></i>Post Topic
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif ($action == 'list' && $has_access): ?>
            <!-- Forum Topics List -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <h2>
                        <i class="fas fa-comments me-2"></i>
                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?> Forum
                    </h2>
                    <p class="text-muted">Discuss course topics and ask questions</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="course_forums.php?course_id=<?php echo $course_id; ?>&action=new_topic&user_type=<?php echo $user_type; ?>" class="btn btn-primary">
                        <i class="fas fa-plus-circle me-2"></i>New Topic
                    </a>
                </div>
            </div>

            <?php if (count($topics) > 0): ?>
                <div class="card mb-4">
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($topics as $topic): ?>
                                <div class="list-group-item list-group-item-action <?php echo $topic['is_pinned'] ? 'pinned-topic' : ''; ?> <?php echo $topic['is_locked'] ? 'locked-topic' : ''; ?>">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <h5 class="mb-1">
                                            <?php if ($topic['is_pinned']): ?>
                                                <i class="fas fa-thumbtack text-success me-2" title="Pinned Topic"></i>
                                            <?php endif; ?>
                                            
                                            <?php if ($topic['is_locked']): ?>
                                                <i class="fas fa-lock text-secondary me-2" title="Locked Topic"></i>
                                            <?php endif; ?>
                                            
                                            <a href="forum_topic.php?topic_id=<?php echo $topic['id']; ?>&user_type=<?php echo $user_type; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($topic['title']); ?>
                                            </a>
                                        </h5>
                                        <small class="text-muted">
                                            <?php echo date('M d, Y g:i A', strtotime($topic['created_at'])); ?>
                                        </small>
                                    </div>
                                    <p class="mb-1 text-truncate"><?php echo htmlspecialchars(substr($topic['content'], 0, 150)) . (strlen($topic['content']) > 150 ? '...' : ''); ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small>
                                            By: <?php echo htmlspecialchars($topic['author_name']); ?> 
                                            (<?php echo ucfirst($topic['created_by_type']); ?>)
                                        </small>
                                        <div>
                                            <span class="badge bg-primary rounded-pill">
                                                <i class="fas fa-comments me-1"></i><?php echo $topic['reply_count']; ?> replies
                                            </span>
                                            
                                            <?php if ($user_type == 'faculty'): ?>
                                                <div class="btn-group ms-2">
                                                    <form method="post" action="course_forums.php?course_id=<?php echo $course_id; ?>&user_type=<?php echo $user_type; ?>" class="d-inline">
                                                        <input type="hidden" name="topic_id" value="<?php echo $topic['id']; ?>">
                                                        <input type="hidden" name="is_pinned" value="<?php echo $topic['is_pinned'] ? 0 : 1; ?>">
                                                        <button type="submit" name="pin_topic" class="btn btn-sm btn-outline-secondary" title="<?php echo $topic['is_pinned'] ? 'Unpin Topic' : 'Pin Topic'; ?>">
                                                            <i class="fas <?php echo $topic['is_pinned'] ? 'fa-thumbtack text-success' : 'fa-thumbtack'; ?>"></i>
                                                        </button>
                                                    </form>
                                                    
                                                    <form method="post" action="course_forums.php?course_id=<?php echo $course_id; ?>&user_type=<?php echo $user_type; ?>" class="d-inline">
                                                        <input type="hidden" name="topic_id" value="<?php echo $topic['id']; ?>">
                                                        <input type="hidden" name="is_locked" value="<?php echo $topic['is_locked'] ? 0 : 1; ?>">
                                                        <button type="submit" name="lock_topic" class="btn btn-sm btn-outline-secondary" title="<?php echo $topic['is_locked'] ? 'Unlock Topic' : 'Lock Topic'; ?>">
                                                            <i class="fas <?php echo $topic['is_locked'] ? 'fa-unlock' : 'fa-lock'; ?>"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>No topics have been created yet. Be the first to start a discussion!
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>Student Management System</h5>
                    <p>A comprehensive platform for students and faculty.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>&copy; 2023 Student Management System. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
