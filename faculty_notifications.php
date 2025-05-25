<?php
// Start session
session_start();

// For testing purposes, we're not enforcing authentication
// In a real application, you would check if the user is logged in
$faculty_id = isset($_SESSION['faculty_id']) ? $_SESSION['faculty_id'] : 1;

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get faculty information
$faculty = null;
$stmt = $conn->prepare("SELECT * FROM faculty WHERE id = ?");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $faculty = $result->fetch_assoc();
} else {
    // For testing, create a dummy faculty if not found
    $faculty = [
        'id' => 1,
        'full_name' => 'Test Faculty',
        'email' => 'faculty@example.com',
        'department' => 'Computer Science'
    ];
}

// Get faculty's courses for dropdown
$faculty_courses = [];
$stmt = $conn->prepare("SELECT * FROM courses WHERE faculty_id = ? ORDER BY course_name");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $faculty_courses[] = $row;
    }
}

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';

// Create notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_notification'])) {
    $title = $_POST['title'];
    $notification_message = $_POST['message'];
    $target_type = $_POST['target_type'];
    $target_id = ($target_type == 'course') ? $_POST['course_id'] : null;

    $stmt = $conn->prepare("INSERT INTO notifications (title, message, created_by, target_type, target_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiss", $title, $notification_message, $faculty_id, $target_type, $target_id);

    if ($stmt->execute()) {
        $message = "Notification created successfully!";
        $action = 'list'; // Return to list view
    } else {
        $message = "Error: " . $stmt->error;
    }
}

// Mark notification as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification_id = $_GET['mark_read'];

    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $stmt->bind_param("i", $notification_id);

    if ($stmt->execute()) {
        $message = "Notification marked as read.";
    } else {
        $message = "Error: " . $stmt->error;
    }
}

// Delete notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notification_id = $_GET['delete'];

    // Check if this notification was created by this faculty
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE id = ? AND created_by = ?");
    $stmt->bind_param("ii", $notification_id, $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->bind_param("i", $notification_id);

        if ($stmt->execute()) {
            $message = "Notification deleted successfully.";
        } else {
            $message = "Error: " . $stmt->error;
        }
    } else {
        $message = "You don't have permission to delete this notification.";
    }
}

// Clear all notifications created by this faculty
if (isset($_GET['clear_all_sent'])) {
    $stmt = $conn->prepare("DELETE FROM notifications WHERE created_by = ?");
    $stmt->bind_param("i", $faculty_id);

    if ($stmt->execute()) {
        $affected_rows = $stmt->affected_rows;
        $message = "Successfully deleted $affected_rows notification(s) that you created.";
    } else {
        $message = "Error: " . $stmt->error;
    }
}

// Mark all received notifications as read
if (isset($_GET['mark_all_read'])) {
    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1, read_at = NOW()
        WHERE target_type IN ('all', 'faculty')
        OR (target_type = 'subject' AND target_id IN (
            SELECT id FROM subjects WHERE faculty_id = ?
        ))
    ");
    $stmt->bind_param("i", $faculty_id);

    if ($stmt->execute()) {
        $affected_rows = $stmt->affected_rows;
        $message = "Marked $affected_rows notification(s) as read.";
    } else {
        $message = "Error: " . $stmt->error;
    }
}

// Get notifications
$notifications = [];
if ($action == 'list') {
    // Get notifications created by this faculty
    $stmt = $conn->prepare("SELECT n.*,
                           CASE
                               WHEN n.target_type = 'course' THEN c.course_name
                               ELSE NULL
                           END as target_name
                           FROM notifications n
                           LEFT JOIN courses c ON n.target_id = c.id AND n.target_type = 'course'
                           WHERE n.created_by = ?
                           ORDER BY n.created_at DESC");
    $stmt->bind_param("i", $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
    }

    // Get notifications for this faculty
    $stmt = $conn->prepare("SELECT n.*,
                           CASE
                               WHEN n.target_type = 'course' THEN c.course_name
                               ELSE NULL
                           END as target_name,
                           f.full_name as creator_name
                           FROM notifications n
                           LEFT JOIN courses c ON n.target_id = c.id AND n.target_type = 'course'
                           LEFT JOIN faculty f ON n.created_by = f.id
                           WHERE n.target_type = 'all'
                              OR n.target_type = 'faculty'
                              OR (n.target_type = 'course' AND n.target_id IN (
                                  SELECT id FROM courses WHERE faculty_id = ?
                              ))
                           ORDER BY n.created_at DESC");
    $stmt->bind_param("i", $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Only add if not already in the array (to avoid duplicates)
            $found = false;
            foreach ($notifications as $notification) {
                if ($notification['id'] == $row['id']) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $notifications[] = $row;
            }
        }
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
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="#">Student Management System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_courses.php">My Courses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_students.php">Students</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_grades.php">Grades</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_files.php">Files</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="faculty_notifications.php">Notifications</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        Welcome, <?php echo htmlspecialchars($faculty['full_name']); ?>
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
                        <a href="?mark_all_read=1" class="btn btn-outline-primary me-2" onclick="return confirm('Mark all received notifications as read?')">
                            <i class="fas fa-check-double me-2"></i>Mark All Read
                        </a>
                        <a href="?clear_all_sent=1" class="btn btn-outline-danger me-2" onclick="return confirm('Are you sure you want to delete all notifications you created? This action cannot be undone.')">
                            <i class="fas fa-trash me-2"></i>Clear All Sent
                        </a>
                        <a href="?action=create" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>Create Notification
                        </a>
                    </div>
                </div>
                <p class="text-muted">Manage and send notifications to students</p>
            </div>
        </div>

        <?php if (!empty($message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if ($action == 'create'): ?>
        <!-- Create Notification Form -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Create New Notification</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label for="message" class="form-label">Message</label>
                                <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="target_type" class="form-label">Send To</label>
                                <select class="form-select" id="target_type" name="target_type" required>
                                    <option value="all">All Users</option>
                                    <option value="student">All Students</option>
                                    <option value="faculty">All Faculty</option>
                                    <option value="course">Specific Course</option>
                                </select>
                            </div>
                            <div class="mb-3" id="course_select_div" style="display: none;">
                                <label for="course_id" class="form-label">Select Course</label>
                                <select class="form-select" id="course_id" name="course_id">
                                    <?php foreach ($faculty_courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="d-flex justify-content-between">
                                <a href="faculty_notifications.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" name="create_notification" class="btn btn-primary">Send Notification</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Notifications List -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="notificationTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab" aria-controls="all" aria-selected="true">All Notifications</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="sent-tab" data-bs-toggle="tab" data-bs-target="#sent" type="button" role="tab" aria-controls="sent" aria-selected="false">Sent by Me</button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="notificationTabsContent">
                            <div class="tab-pane fade show active" id="all" role="tabpanel" aria-labelledby="all-tab">
                                <?php if (count($notifications) > 0): ?>
                                <div class="list-group">
                                    <?php foreach ($notifications as $notification): ?>
                                    <div class="list-group-item list-group-item-action notification-item <?php echo $notification['is_read'] ? '' : 'bg-light'; ?>">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h5 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h5>
                                            <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <small class="text-muted">
                                                <?php if (isset($notification['creator_name']) && $notification['created_by'] != $faculty_id): ?>
                                                From: <?php echo htmlspecialchars($notification['creator_name']); ?> |
                                                <?php endif; ?>

                                                To:
                                                <?php
                                                if ($notification['target_type'] == 'all') {
                                                    echo 'All Users';
                                                } elseif ($notification['target_type'] == 'student') {
                                                    echo 'All Students';
                                                } elseif ($notification['target_type'] == 'faculty') {
                                                    echo 'All Faculty';
                                                } elseif ($notification['target_type'] == 'course') {
                                                    echo 'Course: ' . htmlspecialchars($notification['target_name'] ?? 'Unknown');
                                                }
                                                ?>
                                            </small>
                                            <div>
                                                <?php if (!$notification['is_read'] && $notification['created_by'] != $faculty_id): ?>
                                                <a href="?mark_read=<?php echo $notification['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-check me-1"></i>Mark as Read
                                                </a>
                                                <?php endif; ?>

                                                <?php if ($notification['created_by'] == $faculty_id): ?>
                                                <a href="?delete=<?php echo $notification['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this notification?');">
                                                    <i class="fas fa-trash me-1"></i>Delete
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
                            <div class="tab-pane fade" id="sent" role="tabpanel" aria-labelledby="sent-tab">
                                <?php
                                $sent_notifications = array_filter($notifications, function($notification) use ($faculty_id) {
                                    return $notification['created_by'] == $faculty_id;
                                });

                                if (count($sent_notifications) > 0):
                                ?>
                                <div class="list-group">
                                    <?php foreach ($sent_notifications as $notification): ?>
                                    <div class="list-group-item list-group-item-action notification-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h5 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h5>
                                            <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <small class="text-muted">
                                                To:
                                                <?php
                                                if ($notification['target_type'] == 'all') {
                                                    echo 'All Users';
                                                } elseif ($notification['target_type'] == 'student') {
                                                    echo 'All Students';
                                                } elseif ($notification['target_type'] == 'faculty') {
                                                    echo 'All Faculty';
                                                } elseif ($notification['target_type'] == 'course') {
                                                    echo 'Course: ' . htmlspecialchars($notification['target_name'] ?? 'Unknown');
                                                }
                                                ?>
                                            </small>
                                            <a href="?delete=<?php echo $notification['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this notification?');">
                                                <i class="fas fa-trash me-1"></i>Delete
                                            </a>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>You haven't sent any notifications yet.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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

    <script>
        // Show/hide course selection based on target type
        document.addEventListener('DOMContentLoaded', function() {
            const targetTypeSelect = document.getElementById('target_type');
            const courseSelectDiv = document.getElementById('course_select_div');

            if (targetTypeSelect && courseSelectDiv) {
                targetTypeSelect.addEventListener('change', function() {
                    if (this.value === 'course') {
                        courseSelectDiv.style.display = 'block';
                    } else {
                        courseSelectDiv.style.display = 'none';
                    }
                });
            }
        });
    </script>
</body>
</html>
