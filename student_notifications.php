<?php
// Start session
session_start();

// For testing purposes, we're not enforcing authentication
// In a real application, you would check if the user is logged in
$student_id = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : 1;

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get student information
$student = null;
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $student = $result->fetch_assoc();
} else {
    // For testing, create a dummy student if not found
    $student = [
        'id' => 1,
        'full_name' => 'Test Student',
        'email' => 'student@example.com',
        'course' => 'Computer Science',
        'year' => 2
    ];
}

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';

// Mark notification as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification_id = $_GET['mark_read'];

    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $notification_id);

    if ($stmt->execute()) {
        $message = "Notification marked as read.";
    } else {
        $message = "Error: " . $stmt->error;
    }
}

// Mark all notifications as read
if (isset($_GET['mark_all_read'])) {
    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1, read_at = NOW()
        WHERE (target_type = 'all' OR
              (target_type = 'student' AND target_id = ?) OR
              (target_type = 'subject' AND target_id IN (
                  SELECT subject_id FROM student_subject_enrollment WHERE student_id = ? AND status = 'active'
              )))
    ");
    $stmt->bind_param("ii", $student_id, $student_id);

    if ($stmt->execute()) {
        $message = "All notifications marked as read.";
    } else {
        $message = "Error: " . $stmt->error;
    }
}

// Delete all read notifications
if (isset($_GET['delete_all_read'])) {
    $stmt = $conn->prepare("
        DELETE FROM notifications
        WHERE is_read = 1 AND (target_type = 'student' AND target_id = ?)
    ");
    $stmt->bind_param("i", $student_id);

    if ($stmt->execute()) {
        $message = "All read notifications have been deleted.";
    } else {
        $message = "Error: " . $stmt->error;
    }
}

// Get notifications for this student
$notifications = [];
$stmt = $conn->prepare("
    SELECT n.*, f.full_name as faculty_name, s.subject_name, s.abbreviation as subject_code
    FROM notifications n
    LEFT JOIN faculty f ON n.created_by = f.id
    LEFT JOIN subjects s ON n.target_id = s.id AND n.target_type = 'subject'
    WHERE (n.target_type = 'all' OR
          (n.target_type = 'student' AND n.target_id = ?) OR
          (n.target_type = 'subject' AND n.target_id IN (
              SELECT subject_id FROM student_subject_enrollment WHERE student_id = ? AND status = 'active'
          )))
    ORDER BY n.created_at DESC
");
$stmt->bind_param("ii", $student_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
}

// Count unread notifications
$unread_count = 0;
foreach ($notifications as $notification) {
    if (!$notification['is_read']) {
        $unread_count++;
    }
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
    <title>Notifications - Student Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Student Management System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="student_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_profile.php">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_subjects.php">My Subjects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_grades.php">My Grades</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_schedule.php">Schedule</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="student_notifications.php">
                            Notifications
                            <?php if ($unread_count > 0): ?>
                            <span class="badge bg-danger"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        Welcome, <?php echo htmlspecialchars($student['full_name']); ?>
                    </span>
                    <a href="?logout=1" class="btn btn-light btn-sm">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2><i class="fas fa-bell me-2"></i>Notifications</h2>
                    <div>
                        <?php if ($unread_count > 0): ?>
                        <a href="?mark_all_read=1" class="btn btn-outline-primary me-2">
                            <i class="fas fa-check-double me-2"></i>Mark All as Read
                        </a>
                        <?php endif; ?>
                        <?php if (count($notifications) > 0): ?>
                        <a href="?delete_all_read=1" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to delete all read notifications? This action cannot be undone.')">
                            <i class="fas fa-trash me-2"></i>Clear Read Notifications
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="text-muted">View important announcements and updates</p>
            </div>
        </div>

        <?php if (!empty($message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Notifications List -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="notificationTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab" aria-controls="all" aria-selected="true">
                                    All Notifications
                                    <span class="badge bg-secondary ms-1"><?php echo count($notifications); ?></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="unread-tab" data-bs-toggle="tab" data-bs-target="#unread" type="button" role="tab" aria-controls="unread" aria-selected="false">
                                    Unread
                                    <?php if ($unread_count > 0): ?>
                                    <span class="badge bg-danger ms-1"><?php echo $unread_count; ?></span>
                                    <?php endif; ?>
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="notificationTabsContent">
                            <div class="tab-pane fade show active" id="all" role="tabpanel" aria-labelledby="all-tab">
                                <?php if (count($notifications) > 0): ?>
                                <div class="list-group">
                                    <?php foreach ($notifications as $notification): ?>
                                    <?php
                                    $redirect_url = $notification['redirect_url'] ?? '#';
                                    $is_clickable = !empty($notification['redirect_url']);
                                    ?>
                                    <div class="list-group-item list-group-item-action notification-item <?php echo $notification['is_read'] ? '' : 'bg-light'; ?> <?php echo $is_clickable ? 'notification-clickable' : ''; ?>">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h5 class="mb-1">
                                                <?php if (!$notification['is_read']): ?>
                                                <span class="badge bg-danger me-2">New</span>
                                                <?php endif; ?>
                                                <?php if ($is_clickable): ?>
                                                    <a href="<?php echo htmlspecialchars($redirect_url); ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($notification['title']); ?>
                                                        <i class="fas fa-external-link-alt ms-2 text-muted" style="font-size: 0.8em;"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($notification['title']); ?>
                                                <?php endif; ?>
                                            </h5>
                                            <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <small class="text-muted">
                                                From: <?php echo htmlspecialchars($notification['faculty_name'] ?? 'System'); ?>
                                                <?php if ($notification['target_type'] == 'subject' && isset($notification['subject_code'])): ?>
                                                | Subject: <?php echo htmlspecialchars($notification['subject_code'] . ' - ' . $notification['subject_name']); ?>
                                                <?php endif; ?>
                                                <?php if ($is_clickable): ?>
                                                <br><span class="text-primary"><i class="fas fa-mouse-pointer me-1"></i>Click to view details</span>
                                                <?php endif; ?>
                                            </small>
                                            <div>
                                                <?php if ($is_clickable): ?>
                                                <a href="<?php echo htmlspecialchars($redirect_url); ?>" class="btn btn-sm btn-outline-info me-2">
                                                    <i class="fas fa-eye me-1"></i>View
                                                </a>
                                                <?php endif; ?>
                                                <?php if (!$notification['is_read']): ?>
                                                <a href="?mark_read=<?php echo $notification['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-check me-1"></i>Mark as Read
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>No notifications found.
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="tab-pane fade" id="unread" role="tabpanel" aria-labelledby="unread-tab">
                                <?php
                                $unread_notifications = array_filter($notifications, function($notification) {
                                    return !$notification['is_read'];
                                });

                                if (count($unread_notifications) > 0):
                                ?>
                                <div class="list-group">
                                    <?php foreach ($unread_notifications as $notification): ?>
                                    <div class="list-group-item list-group-item-action notification-item bg-light">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h5 class="mb-1">
                                                <span class="badge bg-danger me-2">New</span>
                                                <?php echo htmlspecialchars($notification['title']); ?>
                                            </h5>
                                            <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <small class="text-muted">
                                                From: <?php echo htmlspecialchars($notification['faculty_name'] ?? 'System'); ?>

                                                <?php if ($notification['target_type'] == 'course' && isset($notification['course_code'])): ?>
                                                | Course: <?php echo htmlspecialchars($notification['course_code'] . ' - ' . $notification['course_name']); ?>
                                                <?php endif; ?>
                                            </small>
                                            <a href="?mark_read=<?php echo $notification['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-check me-1"></i>Mark as Read
                                            </a>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>You have no unread notifications.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
