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

$topic_id = isset($_GET['topic_id']) ? (int)$_GET['topic_id'] : 0;

$message = '';
$message_type = '';

// Get topic details
$topic = null;
$subject = null;
if ($topic_id > 0) {
    $stmt = $conn->prepare("
        SELECT t.*, 
               CASE 
                   WHEN t.created_by_type = 'student' THEN s.full_name
                   WHEN t.created_by_type = 'faculty' THEN f.full_name
                   ELSE 'Unknown'
               END as author_name,
               sub.id as subject_id, sub.abbreviation, sub.subject_name, sub.faculty_id
        FROM forum_topics t
        LEFT JOIN students s ON t.created_by_id = s.id AND t.created_by_type = 'student'
        LEFT JOIN faculty f ON t.created_by_id = f.id AND t.created_by_type = 'faculty'
        LEFT JOIN subjects sub ON t.subject_id = sub.id
        WHERE t.id = ?
    ");
    $stmt->bind_param("i", $topic_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $topic = $result->fetch_assoc();
    $stmt->close();
    
    if ($topic) {
        $subject = [
            'id' => $topic['subject_id'],
            'abbreviation' => $topic['abbreviation'],
            'subject_name' => $topic['subject_name'],
            'faculty_id' => $topic['faculty_id']
        ];
        
        // Increment view count
        $stmt = $conn->prepare("UPDATE forum_topics SET views = views + 1 WHERE id = ?");
        $stmt->bind_param("i", $topic_id);
        $stmt->execute();
        $stmt->close();
    }
}

if (!$topic) {
    header("Location: subject_forums.php?user_type=" . $user_type);
    exit();
}

// Check if user has access to this subject
$has_access = false;
if ($user_type == 'faculty' && $subject['faculty_id'] == $user_id) {
    $has_access = true;
} elseif ($user_type == 'student') {
    // All CS students have access to all CS subjects
    $has_access = true; // Since we're showing CS subjects only
}

if (!$has_access) {
    header("Location: subject_forums.php?user_type=" . $user_type);
    exit();
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
if ($topic_id > 0) {
    $sql = "
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
    ";
    
    $stmt = $conn->prepare($sql);
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $topic ? htmlspecialchars($topic['title']) : 'Topic'; ?> - Subject Forum</title>
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
        .reply-card.student-reply {
            border-left-color: #0d6efd;
        }
        .topic-content {
            line-height: 1.6;
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
                        Welcome, <?php echo ucfirst($user_type); ?>
                    </span>
                    <a href="?logout=1" class="btn btn-light btn-sm">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="subject_forums.php?user_type=<?php echo $user_type; ?>">Forums</a></li>
                <li class="breadcrumb-item"><a href="subject_forums.php?subject_id=<?php echo $subject['id']; ?>&user_type=<?php echo $user_type; ?>"><?php echo htmlspecialchars($subject['abbreviation']); ?></a></li>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($topic['title']); ?></li>
            </ol>
        </nav>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Topic -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <?php if ($topic['is_pinned']): ?>
                        <i class="fas fa-thumbtack text-warning me-2"></i>
                    <?php endif; ?>
                    <?php if ($topic['is_locked']): ?>
                        <i class="fas fa-lock text-danger me-2"></i>
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
        <?php if (!empty($replies)): ?>
            <h5 class="mb-3"><i class="fas fa-comments me-2"></i>Replies (<?php echo count($replies); ?>)</h5>
            
            <?php foreach ($replies as $reply): ?>
                <div class="card reply-card <?php echo $reply['created_by_type']; ?>-reply">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <div>
                                <strong><?php echo htmlspecialchars($reply['author_name']); ?></strong>
                                <span class="badge bg-<?php echo $reply['created_by_type'] == 'faculty' ? 'success' : 'primary'; ?> ms-2">
                                    <?php echo ucfirst($reply['created_by_type']); ?>
                                </span>
                            </div>
                            <div>
                                <small class="text-muted">
                                    <?php echo date('F j, Y g:i A', strtotime($reply['created_at'])); ?>
                                </small>
                            </div>
                        </div>
                        <div class="reply-content">
                            <?php echo nl2br(htmlspecialchars($reply['content'])); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Reply Form -->
        <?php if (!$topic['is_locked']): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-reply me-2"></i>Post a Reply</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="content" class="form-label">Your Reply</label>
                            <textarea class="form-control" id="content" name="content" rows="4" required placeholder="Write your reply here..."></textarea>
                        </div>
                        <div class="d-flex justify-content-between">
                            <a href="subject_forums.php?subject_id=<?php echo $subject['id']; ?>&user_type=<?php echo $user_type; ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Forum
                            </a>
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
            <div class="text-center mt-3">
                <a href="subject_forums.php?subject_id=<?php echo $subject['id']; ?>&user_type=<?php echo $user_type; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Forum
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>
