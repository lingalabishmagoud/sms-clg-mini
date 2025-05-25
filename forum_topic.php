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
$topic_id = isset($_GET['topic_id']) ? (int)$_GET['topic_id'] : 0;
$message = '';
$message_type = '';

// Get topic details
$topic = null;
$course = null;
if ($topic_id > 0) {
    $stmt = $conn->prepare("
        SELECT t.*, 
               CASE 
                   WHEN t.created_by_type = 'student' THEN s.full_name
                   WHEN t.created_by_type = 'faculty' THEN f.full_name
                   ELSE 'Unknown'
               END as author_name,
               c.id as course_id, c.course_code, c.course_name, c.faculty_id
        FROM forum_topics t
        LEFT JOIN students s ON t.created_by_id = s.id AND t.created_by_type = 'student'
        LEFT JOIN faculty f ON t.created_by_id = f.id AND t.created_by_type = 'faculty'
        LEFT JOIN courses c ON t.course_id = c.id
        WHERE t.id = ?
    ");
    $stmt->bind_param("i", $topic_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $topic = $result->fetch_assoc();
    $stmt->close();
    
    if (!$topic) {
        $message = "Topic not found.";
        $message_type = "danger";
        $topic_id = 0;
    } else {
        // Update view count
        $stmt = $conn->prepare("UPDATE forum_topics SET views = views + 1 WHERE id = ?");
        $stmt->bind_param("i", $topic_id);
        $stmt->execute();
        $stmt->close();
        
        // Store course info
        $course = [
            'id' => $topic['course_id'],
            'course_code' => $topic['course_code'],
            'course_name' => $topic['course_name'],
            'faculty_id' => $topic['faculty_id']
        ];
    }
}

// Check if user has access to this course
$has_access = false;
if ($topic_id > 0 && $topic) {
    if ($user_type == 'faculty' && $topic['faculty_id'] == $user_id) {
        $has_access = true;
    } elseif ($user_type == 'student') {
        $stmt = $conn->prepare("
            SELECT * FROM course_enrollment 
            WHERE student_id = ? AND course_id = ?
        ");
        $stmt->bind_param("ii", $user_id, $topic['course_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $has_access = $result->num_rows > 0;
        $stmt->close();
    }
    
    if (!$has_access) {
        $message = "You don't have access to this topic.";
        $message_type = "danger";
        $topic_id = 0;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $topic_id > 0 && $has_access) {
    if (isset($_POST['post_reply']) && !$topic['is_locked']) {
        $content = trim($_POST['content']);
        
        if (empty($content)) {
            $message = "Reply content is required.";
            $message_type = "danger";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO forum_replies (topic_id, content, created_by_id, created_by_type) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("isis", $topic_id, $content, $user_id, $user_type);
            
            if ($stmt->execute()) {
                $message = "Reply posted successfully.";
                $message_type = "success";
            } else {
                $message = "Error posting reply: " . $conn->error;
                $message_type = "danger";
            }
            
            $stmt->close();
        }
    }
}

// Get replies for this topic
$replies = [];
if ($topic_id > 0 && $has_access) {
    $stmt = $conn->prepare("
        SELECT r.*, 
               CASE 
                   WHEN r.created_by_type = 'student' THEN s.full_name
                   WHEN r.created_by_type = 'faculty' THEN f.full_name
                   ELSE 'Unknown'
               END as author_name
        FROM forum_replies r
        LEFT JOIN students s ON r.created_by_id = s.id AND r.created_by_type = 'student'
        LEFT JOIN faculty f ON r.created_by_id = f.id AND r.created_by_type = 'faculty'
        WHERE r.topic_id = ?
        ORDER BY r.created_at ASC
    ");
    $stmt->bind_param("i", $topic_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $replies[] = $row;
    }
    
    $stmt->close();
}

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
    <title><?php echo $topic ? htmlspecialchars($topic['title']) : 'Topic'; ?> - Forum</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .reply-card {
            margin-bottom: 1rem;
            border-left: 3px solid #dee2e6;
        }
        .reply-card.faculty-reply {
            border-left-color: #198754;
        }
        .reply-header {
            background-color: rgba(0,0,0,0.03);
            padding: 0.5rem 1rem;
            border-bottom: 1px solid rgba(0,0,0,0.125);
        }
        .reply-content {
            padding: 1rem;
            white-space: pre-line;
        }
        .topic-content {
            white-space: pre-line;
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

        <?php if ($topic_id > 0 && $has_access && $topic): ?>
            <!-- Breadcrumb Navigation -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="course_forums.php?user_type=<?php echo $user_type; ?>">Forums</a></li>
                    <li class="breadcrumb-item"><a href="course_forums.php?course_id=<?php echo $topic['course_id']; ?>&user_type=<?php echo $user_type; ?>"><?php echo htmlspecialchars($topic['course_code']); ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($topic['title']); ?></li>
                </ol>
            </nav>

            <!-- Topic Details -->
            <div class="card mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <?php if ($topic['is_pinned']): ?>
                            <i class="fas fa-thumbtack text-success me-2" title="Pinned Topic"></i>
                        <?php endif; ?>
                        
                        <?php if ($topic['is_locked']): ?>
                            <i class="fas fa-lock text-secondary me-2" title="Locked Topic"></i>
                        <?php endif; ?>
                        
                        <?php echo htmlspecialchars($topic['title']); ?>
                    </h5>
                    <div>
                        <span class="badge bg-secondary">
                            <i class="fas fa-eye me-1"></i><?php echo $topic['views']; ?> views
                        </span>
                        <span class="badge bg-primary ms-1">
                            <i class="fas fa-comments me-1"></i><?php echo count($replies); ?> replies
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <div>
                            <strong>Posted by:</strong> <?php echo htmlspecialchars($topic['author_name']); ?> 
                            (<?php echo ucfirst($topic['created_by_type']); ?>)
                        </div>
                        <div>
                            <small class="text-muted">
                                <?php echo date('F j, Y g:i A', strtotime($topic['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                    <div class="topic-content">
                        <?php echo nl2br(htmlspecialchars($topic['content'])); ?>
                    </div>
                </div>
            </div>

            <!-- Replies -->
            <?php if (count($replies) > 0): ?>
                <h5 class="mb-3"><i class="fas fa-reply me-2"></i>Replies</h5>
                
                <?php foreach ($replies as $reply): ?>
                    <div class="card reply-card <?php echo ($reply['created_by_type'] == 'faculty') ? 'faculty-reply' : ''; ?>">
                        <div class="reply-header d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo htmlspecialchars($reply['author_name']); ?></strong>
                                <span class="badge <?php echo ($reply['created_by_type'] == 'faculty') ? 'bg-success' : 'bg-primary'; ?> ms-2">
                                    <?php echo ucfirst($reply['created_by_type']); ?>
                                </span>
                            </div>
                            <small class="text-muted">
                                <?php echo date('F j, Y g:i A', strtotime($reply['created_at'])); ?>
                            </small>
                        </div>
                        <div class="reply-content">
                            <?php echo nl2br(htmlspecialchars($reply['content'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info mb-4">
                    <i class="fas fa-info-circle me-2"></i>No replies yet. Be the first to reply!
                </div>
            <?php endif; ?>

            <!-- Reply Form -->
            <?php if (!$topic['is_locked']): ?>
                <div class="card mt-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-reply me-2"></i>Post a Reply</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="forum_topic.php?topic_id=<?php echo $topic_id; ?>&user_type=<?php echo $user_type; ?>">
                            <div class="mb-3">
                                <textarea class="form-control" id="content" name="content" rows="4" required></textarea>
                            </div>
                            <div class="text-end">
                                <button type="submit" name="post_reply" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>Post Reply
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mt-4">
                    <i class="fas fa-lock me-2"></i>This topic is locked. No new replies can be posted.
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>Topic not found or you don't have access to view it.
            </div>
            <a href="course_forums.php?user_type=<?php echo $user_type; ?>" class="btn btn-primary">
                <i class="fas fa-arrow-left me-2"></i>Back to Forums
            </a>
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
