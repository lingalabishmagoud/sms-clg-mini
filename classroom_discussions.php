<?php
// Start session
session_start();

// Check authentication and get user details
$user_type = isset($_GET['user_type']) ? $_GET['user_type'] : (isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'student');
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

if (!isset($_SESSION['user_type'])) {
    $_SESSION['user_type'] = $user_type;
    if ($user_type == 'student') {
        $_SESSION['student_id'] = $_SESSION['student_id'] ?? 1;
    } elseif ($user_type == 'faculty') {
        $_SESSION['faculty_id'] = $_SESSION['faculty_id'] ?? 1;
    }
}

$user_type = $_SESSION['user_type'];
$user_id = ($user_type == 'student') ? $_SESSION['student_id'] : $_SESSION['faculty_id'];

if ($user_type == 'student') {
    $user_name = isset($_SESSION['student_name']) ? $_SESSION['student_name'] : 'Student';
} elseif ($user_type == 'faculty') {
    $user_name = isset($_SESSION['faculty_name']) ? $_SESSION['faculty_name'] : 'Faculty';
}

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$classroom_id = isset($_GET['classroom_id']) ? (int)$_GET['classroom_id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';
$message_type = 'success';

// Auto-detect classroom based on user
if ($classroom_id == 0) {
    if ($user_type == 'student') {
        // Get student's section and find corresponding classroom
        $stmt = $conn->prepare("SELECT section FROM students WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();
        $stmt->close();

        if ($student && $student['section']) {
            $stmt = $conn->prepare("SELECT id FROM classrooms WHERE classroom_name = ?");
            $stmt->bind_param("s", $student['section']);
            $stmt->execute();
            $result = $stmt->get_result();
            $classroom_data = $result->fetch_assoc();
            if ($classroom_data) {
                $classroom_id = $classroom_data['id'];
            }
            $stmt->close();
        }
    }
}

// Get classroom details
$classroom = null;
if ($classroom_id > 0) {
    $stmt = $conn->prepare("
        SELECT c.*, f.full_name as incharge_name
        FROM classrooms c
        LEFT JOIN faculty f ON c.class_incharge_id = f.id
        WHERE c.id = ?
    ");
    $stmt->bind_param("i", $classroom_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $classroom = $result->fetch_assoc();
    $stmt->close();
}

// Check if user has access to this classroom
$has_access = false;
if ($classroom_id > 0 && $classroom) {
    if ($user_type == 'admin') {
        $has_access = true; // Admin can access all classrooms
    } elseif ($user_type == 'faculty') {
        // Faculty can access if they teach in this classroom or are class incharge
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count FROM (
                SELECT 1 FROM schedules s
                JOIN subjects sub ON s.subject_id = sub.id
                WHERE s.section = ? AND sub.faculty_id = ?
                UNION
                SELECT 1 FROM classrooms WHERE id = ? AND class_incharge_id = ?
            ) as access_check
        ");
        $stmt->bind_param("siii", $classroom['classroom_name'], $user_id, $classroom_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];
        $has_access = $count > 0;
        $stmt->close();
    } elseif ($user_type == 'student') {
        // Students can access if they belong to this classroom
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE id = ? AND section = ?");
        $stmt->bind_param("is", $user_id, $classroom['classroom_name']);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];
        $has_access = $count > 0;
        $stmt->close();
    }

    if (!$has_access) {
        $message = "You don't have access to this classroom discussion.";
        $message_type = "danger";
        $classroom_id = 0;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $classroom_id > 0 && $has_access) {
    if (isset($_POST['create_topic'])) {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);

        if (empty($title) || empty($content)) {
            $message = "Title and content are required.";
            $message_type = "danger";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO classroom_discussions (classroom_id, title, content, created_by_id, created_by_type)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("issis", $classroom_id, $title, $content, $user_id, $user_type);

            if ($stmt->execute()) {
                $message = "Discussion topic created successfully.";
                $message_type = "success";
                $action = 'list';
            } else {
                $message = "Error creating topic: " . $conn->error;
                $message_type = "danger";
            }

            $stmt->close();
        }
    } elseif (isset($_POST['post_reply'])) {
        $topic_id = (int)$_POST['topic_id'];
        $content = trim($_POST['content']);

        if (empty($content)) {
            $message = "Reply content is required.";
            $message_type = "danger";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO classroom_discussion_replies (topic_id, content, created_by_id, created_by_type)
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

// Get discussion topics for this classroom
$topics = [];
if ($classroom_id > 0 && $action == 'list') {
    $sql = "
        SELECT t.*,
               CASE
                   WHEN t.created_by_type = 'student' THEN s.full_name
                   WHEN t.created_by_type = 'faculty' THEN f.full_name
                   WHEN t.created_by_type = 'admin' THEN 'Administrator'
                   ELSE 'Unknown'
               END as author_name,
               (SELECT COUNT(*) FROM classroom_discussion_replies WHERE topic_id = t.id) as reply_count
        FROM classroom_discussions t
        LEFT JOIN students s ON t.created_by_id = s.id AND t.created_by_type = 'student'
        LEFT JOIN faculty f ON t.created_by_id = f.id AND t.created_by_type = 'faculty'
        WHERE t.classroom_id = ?
        ORDER BY t.is_pinned DESC, t.created_at DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $classroom_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $topics[] = $row;
    }
    $stmt->close();
}

// Get topic details and replies for topic view
$topic = null;
$replies = [];
if ($action == 'topic' && isset($_GET['topic_id'])) {
    $topic_id = (int)$_GET['topic_id'];

    // Get topic details
    $stmt = $conn->prepare("
        SELECT t.*,
               CASE
                   WHEN t.created_by_type = 'student' THEN s.full_name
                   WHEN t.created_by_type = 'faculty' THEN f.full_name
                   WHEN t.created_by_type = 'admin' THEN 'Administrator'
                   ELSE 'Unknown'
               END as author_name
        FROM classroom_discussions t
        LEFT JOIN students s ON t.created_by_id = s.id AND t.created_by_type = 'student'
        LEFT JOIN faculty f ON t.created_by_id = f.id AND t.created_by_type = 'faculty'
        WHERE t.id = ? AND t.classroom_id = ?
    ");
    $stmt->bind_param("ii", $topic_id, $classroom_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $topic = $result->fetch_assoc();
    $stmt->close();

    if ($topic) {
        // Update view count
        $stmt = $conn->prepare("UPDATE classroom_discussions SET views = views + 1 WHERE id = ?");
        $stmt->bind_param("i", $topic_id);
        $stmt->execute();
        $stmt->close();

        // Get replies
        $stmt = $conn->prepare("
            SELECT r.*,
                   CASE
                       WHEN r.created_by_type = 'student' THEN s.full_name
                       WHEN r.created_by_type = 'faculty' THEN f.full_name
                       WHEN r.created_by_type = 'admin' THEN 'Administrator'
                       ELSE 'Unknown'
                   END as author_name
            FROM classroom_discussion_replies r
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
}

// Get all classrooms for navigation (if admin)
$all_classrooms = [];
if ($user_type == 'admin') {
    $result = $conn->query("SELECT id, classroom_name, year, semester FROM classrooms ORDER BY year DESC, semester DESC, classroom_name");
    while ($row = $result->fetch_assoc()) {
        $all_classrooms[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classroom Discussions - Student Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .discussion-card {
            transition: transform 0.2s;
        }
        .discussion-card:hover {
            transform: translateY(-2px);
        }
        .topic-card {
            border-left: 4px solid #dee2e6;
        }
        .topic-card.pinned {
            border-left-color: #ffc107;
            background-color: #fffbf0;
        }
        .reply-card {
            border-left: 3px solid #e9ecef;
            margin-left: 20px;
        }
        .user-badge {
            font-size: 0.75em;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark <?php echo $user_type == 'admin' ? 'bg-dark' : ($user_type == 'faculty' ? 'bg-success' : 'bg-primary'); ?>">
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
                    <?php if ($user_type == 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_classrooms.php">Manage Classrooms</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="admin_discussions.php">Monitor Discussions</a>
                        </li>
                    <?php elseif ($user_type == 'faculty'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="faculty_subjects.php">My Subjects</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="faculty_schedule.php">My Schedule</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="#">Classroom Discussions</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="student_subjects.php">My Subjects</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="schedule.php">Schedule</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="#">Classroom Discussions</a>
                        </li>
                    <?php endif; ?>
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
        <?php if ($user_type == 'admin' && $classroom_id == 0): ?>
        <!-- Admin Classroom Selection -->
        <div class="row mb-4">
            <div class="col-md-12">
                <h2><i class="fas fa-comments me-2"></i>Monitor Classroom Discussions</h2>
                <p class="text-muted">Select a classroom to monitor discussions between students and faculty</p>
            </div>
        </div>

        <div class="row">
            <?php foreach ($all_classrooms as $class): ?>
                <div class="col-md-4 mb-3">
                    <div class="card discussion-card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($class['classroom_name']); ?></h5>
                            <p class="card-text">
                                <span class="badge bg-info"><?php echo $class['year']; ?> Year</span>
                                <span class="badge bg-secondary"><?php echo $class['semester']; ?> Semester</span>
                            </p>
                        </div>
                        <div class="card-footer bg-white">
                            <a href="classroom_discussions.php?classroom_id=<?php echo $class['id']; ?>&user_type=admin" class="btn btn-primary w-100">
                                <i class="fas fa-eye me-2"></i>Monitor Discussions
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($classroom_id > 0 && $classroom): ?>
        <!-- Classroom Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h2>
                    <i class="fas fa-comments me-2"></i>
                    <?php echo htmlspecialchars($classroom['classroom_name']); ?> Discussions
                </h2>
                <p class="text-muted">
                    <?php echo $classroom['year']; ?> Year - <?php echo $classroom['semester']; ?> Semester |
                    <?php echo htmlspecialchars($classroom['department']); ?> |
                    Room: <?php echo htmlspecialchars($classroom['room_number']); ?>
                    <?php if ($classroom['incharge_name']): ?>
                        | Incharge: <?php echo htmlspecialchars($classroom['incharge_name']); ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <?php if ($user_type != 'admin'): ?>
                <a href="classroom_discussions.php?classroom_id=<?php echo $classroom_id; ?>&action=new_topic&user_type=<?php echo $user_type; ?>" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-2"></i>New Topic
                </a>
                <?php endif; ?>
                <?php if ($user_type == 'admin'): ?>
                <a href="classroom_discussions.php?user_type=admin" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Classrooms
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if ($action == 'new_topic' && $has_access && $user_type != 'admin'): ?>
        <!-- Create New Topic Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Create New Discussion Topic</h5>
            </div>
            <div class="card-body">
                <form method="post" action="classroom_discussions.php?classroom_id=<?php echo $classroom_id; ?>&user_type=<?php echo $user_type; ?>">
                    <div class="mb-3">
                        <label for="title" class="form-label">Topic Title *</label>
                        <input type="text" class="form-control" id="title" name="title" required
                            placeholder="Enter discussion topic title">
                    </div>
                    <div class="mb-3">
                        <label for="content" class="form-label">Content *</label>
                        <textarea class="form-control" id="content" name="content" rows="6" required
                            placeholder="Describe your topic or question in detail..."></textarea>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="classroom_discussions.php?classroom_id=<?php echo $classroom_id; ?>&user_type=<?php echo $user_type; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Cancel
                        </a>
                        <button type="submit" name="create_topic" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Post Topic
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php elseif ($action == 'list' && $has_access): ?>
        <!-- Discussion Topics List -->
        <div class="row">
            <?php if (count($topics) > 0): ?>
                <?php foreach ($topics as $topic): ?>
                    <div class="col-12 mb-3">
                        <div class="card topic-card <?php echo $topic['is_pinned'] ? 'pinned' : ''; ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title mb-0">
                                        <?php if ($topic['is_pinned']): ?>
                                            <i class="fas fa-thumbtack text-warning me-2"></i>
                                        <?php endif; ?>
                                        <a href="classroom_discussions.php?classroom_id=<?php echo $classroom_id; ?>&action=topic&topic_id=<?php echo $topic['id']; ?>&user_type=<?php echo $user_type; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($topic['title']); ?>
                                        </a>
                                    </h5>
                                    <div>
                                        <span class="badge bg-secondary">
                                            <i class="fas fa-eye me-1"></i><?php echo $topic['views']; ?> views
                                        </span>
                                        <span class="badge bg-primary ms-1">
                                            <i class="fas fa-comments me-1"></i><?php echo $topic['reply_count']; ?> replies
                                        </span>
                                    </div>
                                </div>
                                <p class="card-text"><?php echo nl2br(htmlspecialchars(substr($topic['content'], 0, 200))); ?><?php echo strlen($topic['content']) > 200 ? '...' : ''; ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="text-muted">
                                            Posted by: <strong><?php echo htmlspecialchars($topic['author_name']); ?></strong>
                                            <span class="badge user-badge bg-<?php echo $topic['created_by_type'] == 'faculty' ? 'success' : ($topic['created_by_type'] == 'admin' ? 'dark' : 'info'); ?>">
                                                <?php echo ucfirst($topic['created_by_type']); ?>
                                            </span>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted d-block">
                                            <?php echo date('M j, Y g:i A', strtotime($topic['created_at'])); ?>
                                        </small>
                                        <a href="classroom_discussions.php?classroom_id=<?php echo $classroom_id; ?>&action=topic&topic_id=<?php echo $topic['id']; ?>&user_type=<?php echo $user_type; ?>" class="btn btn-sm btn-outline-primary mt-1">
                                            <i class="fas fa-reply me-1"></i>View & Reply
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                        <h5>No discussions yet</h5>
                        <p class="text-muted">Be the first to start a discussion in this classroom!</p>
                        <?php if ($user_type != 'admin'): ?>
                        <a href="classroom_discussions.php?classroom_id=<?php echo $classroom_id; ?>&action=new_topic&user_type=<?php echo $user_type; ?>" class="btn btn-primary">
                            <i class="fas fa-plus-circle me-2"></i>Start Discussion
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php elseif ($action == 'topic' && $topic && $has_access): ?>
        <!-- Topic View with Replies -->
        <div class="card topic-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <?php if ($topic['is_pinned']): ?>
                        <i class="fas fa-thumbtack text-warning me-2"></i>
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
                        <span class="badge user-badge bg-<?php echo $topic['created_by_type'] == 'faculty' ? 'success' : ($topic['created_by_type'] == 'admin' ? 'dark' : 'info'); ?>">
                            <?php echo ucfirst($topic['created_by_type']); ?>
                        </span>
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
            <h6 class="mb-3"><i class="fas fa-comments me-2"></i>Replies (<?php echo count($replies); ?>)</h6>
            <?php foreach ($replies as $reply): ?>
                <div class="card reply-card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <div>
                                <strong><?php echo htmlspecialchars($reply['author_name']); ?></strong>
                                <span class="badge user-badge bg-<?php echo $reply['created_by_type'] == 'faculty' ? 'success' : ($reply['created_by_type'] == 'admin' ? 'dark' : 'info'); ?>">
                                    <?php echo ucfirst($reply['created_by_type']); ?>
                                </span>
                            </div>
                            <small class="text-muted">
                                <?php echo date('M j, Y g:i A', strtotime($reply['created_at'])); ?>
                            </small>
                        </div>
                        <div class="reply-content">
                            <?php echo nl2br(htmlspecialchars($reply['content'])); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Reply Form -->
        <?php if ($user_type != 'admin'): ?>
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-reply me-2"></i>Post a Reply</h6>
            </div>
            <div class="card-body">
                <form method="post" action="classroom_discussions.php?classroom_id=<?php echo $classroom_id; ?>&action=topic&topic_id=<?php echo $topic['id']; ?>&user_type=<?php echo $user_type; ?>">
                    <input type="hidden" name="topic_id" value="<?php echo $topic['id']; ?>">
                    <div class="mb-3">
                        <label for="content" class="form-label">Your Reply *</label>
                        <textarea class="form-control" id="content" name="content" rows="4" required
                            placeholder="Write your reply here..."></textarea>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="classroom_discussions.php?classroom_id=<?php echo $classroom_id; ?>&user_type=<?php echo $user_type; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Topics
                        </a>
                        <button type="submit" name="post_reply" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Post Reply
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="text-center mt-4">
            <a href="classroom_discussions.php?classroom_id=<?php echo $classroom_id; ?>&user_type=admin" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Topics
            </a>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
