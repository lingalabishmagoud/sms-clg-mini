<?php
// Start session
session_start();

// Check authentication and get user details
$user_type = isset($_GET['user_type']) ? $_GET['user_type'] : (isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'student');
$user_id = 1; // Default for testing
$user_name = 'User'; // Default name

// Set up session if not exists (for testing)
if (!isset($_SESSION['student_id']) && $user_type == 'student') {
    $conn_temp = new mysqli("localhost", "root", "", "student_db");
    $test_student = $conn_temp->query("SELECT id, full_name FROM students LIMIT 1")->fetch_assoc();
    if ($test_student) {
        $_SESSION['student_id'] = $test_student['id'];
        $_SESSION['student_name'] = $test_student['full_name'];
        $_SESSION['user_type'] = 'student';
    }
    $conn_temp->close();
}

if ($user_type == 'student') {
    $user_id = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : 1;
    $user_name = isset($_SESSION['student_name']) ? $_SESSION['student_name'] : 'Student';
} elseif ($user_type == 'faculty') {
    $user_id = isset($_SESSION['faculty_id']) ? $_SESSION['faculty_id'] : 1;
    $user_name = isset($_SESSION['faculty_name']) ? $_SESSION['faculty_name'] : 'Faculty';
}

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

$message = '';
$message_type = '';

// Get subject details
$subject = null;
$available_subjects = [];

if ($subject_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM subjects WHERE id = ?");
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $subject = $result->fetch_assoc();
    $stmt->close();
}

// Get available subjects based on user type
if ($user_type == 'faculty') {
    // Faculty can see subjects they teach
    $stmt = $conn->prepare("SELECT * FROM subjects WHERE faculty_id = ? ORDER BY subject_name");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $available_subjects[] = $row;
    }
    $stmt->close();
} else {
    // Students can see all subjects for their department
    // Get student's department first
    $student_dept = 'Cyber Security'; // Default department
    if (isset($_SESSION['student_id'])) {
        $stmt = $conn->prepare("SELECT department FROM students WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['student_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $student = $result->fetch_assoc();
            $student_dept = $student['department'];
        }
        $stmt->close();
    }

    // Get subjects for student's department
    $stmt = $conn->prepare("SELECT * FROM subjects WHERE department = ? ORDER BY subject_name");
    $stmt->bind_param("s", $student_dept);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $available_subjects[] = $row;
    }
    $stmt->close();
}

// Check if user has access to this subject
$has_access = false;
if ($subject_id > 0) {
    if ($user_type == 'faculty' && $subject['faculty_id'] == $user_id) {
        $has_access = true;
    } elseif ($user_type == 'student') {
        // Students have access to subjects in their department
        $student_dept = 'Cyber Security'; // Default department
        if (isset($_SESSION['student_id'])) {
            $stmt = $conn->prepare("SELECT department FROM students WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['student_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $student = $result->fetch_assoc();
                $student_dept = $student['department'];
            }
            $stmt->close();
        }
        $has_access = ($subject['department'] == $student_dept);
    }

    if (!$has_access) {
        $message = "You don't have access to this subject forum.";
        $message_type = "danger";
        $subject_id = 0;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $subject_id > 0 && $has_access) {
    if (isset($_POST['create_topic'])) {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);

        if (empty($title) || empty($content)) {
            $message = "Title and content are required.";
            $message_type = "danger";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO forum_topics (subject_id, title, content, created_by_id, created_by_type)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("issss", $subject_id, $title, $content, $user_id, $user_type);

            if ($stmt->execute()) {
                $topic_id = $conn->insert_id;

                // Send notifications to all students enrolled in this subject
                $notification_title = "New Discussion: " . $title;
                $notification_message = "A new discussion topic has been started in " . $subject['abbreviation'] . " - " . $subject['subject_name'] . ".\n\nTopic: " . $title . "\n\nClick to view and participate in the discussion.";

                // Get all students enrolled in this subject
                $students_stmt = $conn->prepare("
                    SELECT DISTINCT s.id, s.full_name, s.email
                    FROM students s
                    INNER JOIN student_subject_enrollment sse ON s.id = sse.student_id
                    WHERE sse.subject_id = ? AND sse.status = 'active'
                ");
                $students_stmt->bind_param("i", $subject_id);
                $students_stmt->execute();
                $students_result = $students_stmt->get_result();

                // Create notifications for each student
                while ($student = $students_result->fetch_assoc()) {
                    $notify_stmt = $conn->prepare("
                        INSERT INTO notifications (title, message, created_by, created_by_type, target_type, target_id, created_at)
                        VALUES (?, ?, ?, ?, 'student', ?, NOW())
                    ");
                    $notify_stmt->bind_param("ssisi", $notification_title, $notification_message, $user_id, $user_type, $student['id']);
                    $notify_stmt->execute();
                    $notify_stmt->close();
                }
                $students_stmt->close();

                $message = "Topic created successfully and notifications sent to all enrolled students.";
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

        $stmt = $conn->prepare("UPDATE forum_topics SET is_pinned = ? WHERE id = ? AND subject_id = ?");
        $stmt->bind_param("iii", $is_pinned, $topic_id, $subject_id);

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

        $stmt = $conn->prepare("UPDATE forum_topics SET is_locked = ? WHERE id = ? AND subject_id = ?");
        $stmt->bind_param("iii", $is_locked, $topic_id, $subject_id);

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

// Get forum topics for this subject
$topics = [];
if ($subject_id > 0 && $action == 'list') {
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
        WHERE t.subject_id = ?
        ORDER BY t.is_pinned DESC, t.created_at DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $topics[] = $row;
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Forums - Student Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .forum-card {
            transition: transform 0.2s;
        }
        .forum-card:hover {
            transform: translateY(-2px);
        }
        .topic-card {
            border-left: 4px solid #dee2e6;
        }
        .topic-card.pinned {
            border-left-color: #ffc107;
            background-color: #fffbf0;
        }
        .topic-card.locked {
            border-left-color: #dc3545;
            background-color: #fdf2f2;
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
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $user_type; ?>_dashboard.php">Dashboard</a>
                    </li>
                    <?php if ($user_type == 'faculty'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="faculty_subjects.php">My Subjects</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="faculty_schedule.php">My Schedule</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="student_subjects.php">My Subjects</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="schedule.php">Schedule</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link active" href="subject_forums.php?user_type=<?php echo $user_type; ?>">Forums</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        Welcome, <?php echo htmlspecialchars($user_name); ?>
                    </span>
                    <a href="?logout=1" class="btn btn-light btn-sm">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-4">
        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($subject_id == 0): ?>
            <!-- Subject Selection -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <h2><i class="fas fa-comments me-2"></i>Subject Discussion Forums</h2>
                    <p class="text-muted">Select a subject to join the discussion</p>
                </div>
            </div>

            <?php if (!empty($available_subjects)): ?>
                <div class="row">
                    <?php foreach ($available_subjects as $s): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card forum-card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($s['abbreviation']); ?></h5>
                                    <p class="card-text"><?php echo htmlspecialchars($s['subject_name']); ?></p>
                                    <p class="text-muted mb-0">
                                        <i class="fas fa-graduation-cap me-1"></i><?php echo $s['credits']; ?> Credits
                                    </p>
                                </div>
                                <div class="card-footer bg-white">
                                    <a href="subject_forums.php?subject_id=<?php echo $s['id']; ?>&user_type=<?php echo $user_type; ?>" class="btn btn-primary w-100">
                                        <i class="fas fa-comments me-2"></i>View Forum
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle me-2"></i>No Subjects Available</h5>
                    <p class="mb-0">No subjects are currently available for discussion forums.</p>
                </div>
            <?php endif; ?>

        <?php elseif ($action == 'new_topic' && $has_access): ?>
            <!-- New Topic Form -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <h2><i class="fas fa-plus-circle me-2"></i>Create New Topic</h2>
                    <p class="text-muted">Start a new discussion in <?php echo htmlspecialchars($subject['subject_name']); ?></p>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-body">
                            <form method="POST" action="subject_forums.php?subject_id=<?php echo $subject_id; ?>&user_type=<?php echo $user_type; ?>">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Topic Title</label>
                                    <input type="text" class="form-control" id="title" name="title" required>
                                </div>
                                <div class="mb-3">
                                    <label for="content" class="form-label">Content</label>
                                    <textarea class="form-control" id="content" name="content" rows="6" required></textarea>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <a href="subject_forums.php?subject_id=<?php echo $subject_id; ?>&user_type=<?php echo $user_type; ?>" class="btn btn-secondary">
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
                        <?php echo htmlspecialchars($subject['abbreviation'] . ' - ' . $subject['subject_name']); ?> Forum
                    </h2>
                    <p class="text-muted">Discuss subject topics and ask questions</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="subject_forums.php?subject_id=<?php echo $subject_id; ?>&action=new_topic&user_type=<?php echo $user_type; ?>" class="btn btn-primary">
                        <i class="fas fa-plus-circle me-2"></i>New Topic
                    </a>
                </div>
            </div>

            <!-- Topics List -->
            <?php if (!empty($topics)): ?>
                <div class="row">
                    <div class="col-md-12">
                        <?php foreach ($topics as $topic): ?>
                            <div class="card topic-card mb-3 <?php echo $topic['is_pinned'] ? 'pinned' : ''; ?> <?php echo $topic['is_locked'] ? 'locked' : ''; ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h5 class="card-title mb-1">
                                                <?php if ($topic['is_pinned']): ?>
                                                    <i class="fas fa-thumbtack text-warning me-2"></i>
                                                <?php endif; ?>
                                                <?php if ($topic['is_locked']): ?>
                                                    <i class="fas fa-lock text-danger me-2"></i>
                                                <?php endif; ?>
                                                <a href="subject_topic.php?topic_id=<?php echo $topic['id']; ?>&user_type=<?php echo $user_type; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($topic['title']); ?>
                                                </a>
                                            </h5>
                                            <p class="card-text text-muted mb-2">
                                                <?php echo substr(htmlspecialchars($topic['content']), 0, 150) . (strlen($topic['content']) > 150 ? '...' : ''); ?>
                                            </p>
                                            <small class="text-muted">
                                                By <?php echo htmlspecialchars($topic['author_name']); ?> (<?php echo ucfirst($topic['created_by_type']); ?>) •
                                                <?php echo date('M j, Y g:i A', strtotime($topic['created_at'])); ?>
                                            </small>
                                        </div>
                                        <div class="text-end ms-3">
                                            <span class="badge bg-secondary mb-1">
                                                <i class="fas fa-eye me-1"></i><?php echo $topic['views']; ?> views
                                            </span><br>
                                            <span class="badge bg-primary mb-2">
                                                <i class="fas fa-comments me-1"></i><?php echo $topic['reply_count']; ?> replies
                                            </span><br>
                                            <a href="subject_topic.php?topic_id=<?php echo $topic['id']; ?>&user_type=<?php echo $user_type; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-reply me-1"></i>View & Reply
                                            </a>

                                            <?php if ($user_type == 'faculty'): ?>
                                                <div class="btn-group ms-2">
                                                    <form method="post" action="subject_forums.php?subject_id=<?php echo $subject_id; ?>&user_type=<?php echo $user_type; ?>" class="d-inline">
                                                        <input type="hidden" name="topic_id" value="<?php echo $topic['id']; ?>">
                                                        <input type="hidden" name="is_pinned" value="<?php echo $topic['is_pinned'] ? 0 : 1; ?>">
                                                        <button type="submit" name="pin_topic" class="btn btn-sm btn-outline-secondary" title="<?php echo $topic['is_pinned'] ? 'Unpin Topic' : 'Pin Topic'; ?>">
                                                            <i class="fas <?php echo $topic['is_pinned'] ? 'fa-thumbtack text-success' : 'fa-thumbtack'; ?>"></i>
                                                        </button>
                                                    </form>
                                                    <form method="post" action="subject_forums.php?subject_id=<?php echo $subject_id; ?>&user_type=<?php echo $user_type; ?>" class="d-inline">
                                                        <input type="hidden" name="topic_id" value="<?php echo $topic['id']; ?>">
                                                        <input type="hidden" name="is_locked" value="<?php echo $topic['is_locked'] ? 0 : 1; ?>">
                                                        <button type="submit" name="lock_topic" class="btn btn-sm btn-outline-secondary" title="<?php echo $topic['is_locked'] ? 'Unlock Topic' : 'Lock Topic'; ?>">
                                                            <i class="fas <?php echo $topic['is_locked'] ? 'fa-unlock text-success' : 'fa-lock'; ?>"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle me-2"></i>No Topics Yet</h5>
                    <p class="mb-0">Be the first to start a discussion in this subject forum!</p>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="alert alert-danger">
                <h5><i class="fas fa-exclamation-triangle me-2"></i>Access Denied</h5>
                <p class="mb-0">You don't have permission to access this forum.</p>
            </div>
        <?php endif; ?>

        <!-- Back to Subjects -->
        <?php if ($subject_id > 0): ?>
            <div class="row mt-4">
                <div class="col-md-12">
                    <a href="subject_forums.php?user_type=<?php echo $user_type; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to All Subjects
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>
