<?php
// Start session
session_start();

// Check if admin is logged in (for testing, allow access)
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['admin_id'] = 1;
    $_SESSION['user_type'] = 'admin';
    $_SESSION['admin_name'] = 'Test Admin';
}

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';

// Add event
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_event'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $event_date = $_POST['event_date'];
    $event_time = !empty($_POST['event_time']) ? $_POST['event_time'] : null;
    $event_type = $_POST['event_type'];
    $target_audience = $_POST['target_audience'];
    $department = !empty($_POST['department']) ? $_POST['department'] : null;
    
    $stmt = $conn->prepare("INSERT INTO academic_calendar (title, description, event_date, event_time, event_type, target_audience, department, created_by_id, created_by_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'admin')");
    $stmt->bind_param("sssssssi", $title, $description, $event_date, $event_time, $event_type, $target_audience, $department, $_SESSION['admin_id']);
    
    if ($stmt->execute()) {
        $event_id = $stmt->insert_id;
        
        // Send notifications for this event
        sendEventNotifications($conn, $event_id);
        
        $message = "Event added successfully and notifications sent!";
    } else {
        $message = "Error: " . $stmt->error;
    }
    $stmt->close();
}

// Delete event
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $event_id = $_GET['delete'];
    
    $stmt = $conn->prepare("DELETE FROM academic_calendar WHERE id = ?");
    $stmt->bind_param("i", $event_id);
    
    if ($stmt->execute()) {
        $message = "Event deleted successfully.";
    } else {
        $message = "Error: " . $stmt->error;
    }
    $stmt->close();
}

// Function to send event notifications
function sendEventNotifications($conn, $event_id) {
    // Get event details
    $stmt = $conn->prepare("SELECT * FROM academic_calendar WHERE id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$event) return false;
    
    $notification_title = "📅 " . $event['title'];
    $notification_message = $event['description'] . "\n\nDate: " . date('F j, Y', strtotime($event['event_date']));
    if ($event['event_time']) {
        $notification_message .= "\nTime: " . date('g:i A', strtotime($event['event_time']));
    }
    
    $notifications_sent = 0;
    
    // Send to students if target is 'all' or 'students'
    if ($event['target_audience'] == 'all' || $event['target_audience'] == 'students') {
        $students_result = $conn->query("SELECT id FROM students");
        while ($student = $students_result->fetch_assoc()) {
            $stmt = $conn->prepare("INSERT INTO notifications (title, message, created_by_type, target_type, target_id, created_at, is_read) VALUES (?, ?, 'system', 'student', ?, NOW(), 0)");
            $stmt->bind_param("ssi", $notification_title, $notification_message, $student['id']);
            if ($stmt->execute()) {
                $notifications_sent++;
            }
            $stmt->close();
        }
    }
    
    // Send to faculty if target is 'all' or 'faculty'
    if ($event['target_audience'] == 'all' || $event['target_audience'] == 'faculty') {
        $faculty_result = $conn->query("SELECT id FROM faculty");
        while ($faculty = $faculty_result->fetch_assoc()) {
            $stmt = $conn->prepare("INSERT INTO notifications (title, message, created_by_type, target_type, target_id, created_at, is_read) VALUES (?, ?, 'system', 'faculty', ?, NOW(), 0)");
            $stmt->bind_param("ssi", $notification_title, $notification_message, $faculty['id']);
            if ($stmt->execute()) {
                $notifications_sent++;
            }
            $stmt->close();
        }
    }
    
    return $notifications_sent;
}

// Get events
$events = [];
$events_result = $conn->query("
    SELECT * FROM academic_calendar 
    WHERE is_active = 1 
    ORDER BY event_date ASC, event_time ASC
");
while ($row = $events_result->fetch_assoc()) {
    $events[] = $row;
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
    <title>Academic Calendar Management - Admin Panel</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .event-card {
            border-left: 4px solid;
            transition: all 0.3s ease;
        }
        .event-exam { border-left-color: #dc3545; }
        .event-holiday { border-left-color: #28a745; }
        .event-assignment { border-left-color: #ffc107; }
        .event-meeting { border-left-color: #17a2b8; }
        .event-deadline { border-left-color: #fd7e14; }
        .event-event { border-left-color: #6f42c1; }
        .event-announcement { border-left-color: #6c757d; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Admin Panel</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_students.php">Students</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_faculty.php">Faculty</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_subjects.php">Subjects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_lab_subjects.php">Lab Subjects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="admin_calendar.php">Academic Calendar</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        Welcome, Admin
                    </span>
                    <a href="?logout=1" class="btn btn-light btn-sm">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2><i class="fas fa-calendar-alt me-2"></i>Academic Calendar Management</h2>
                <p class="text-muted">Manage academic events and send notifications</p>
            </div>
            <div class="col-md-4 text-md-end">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addEventModal">
                    <i class="fas fa-plus me-2"></i>Add Event
                </button>
            </div>
        </div>

        <?php if (!empty($message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Events Overview -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h4><?php echo count($events); ?></h4>
                        <p class="mb-0">Total Events</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h4><?php echo count(array_filter($events, function($e) { return strtotime($e['event_date']) >= strtotime('today'); })); ?></h4>
                        <p class="mb-0">Upcoming Events</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <h4><?php echo count(array_filter($events, function($e) { return strtotime($e['event_date']) >= strtotime('today') && strtotime($e['event_date']) <= strtotime('+7 days'); })); ?></h4>
                        <p class="mb-0">This Week</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body text-center">
                        <h4><?php echo count(array_filter($events, function($e) { return $e['event_type'] == 'exam'; })); ?></h4>
                        <p class="mb-0">Exams</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Events List -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Academic Events</h5>
            </div>
            <div class="card-body">
                <?php if (count($events) > 0): ?>
                    <?php foreach ($events as $event): ?>
                        <div class="card event-card event-<?php echo $event['event_type']; ?> mb-3">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-2 text-center">
                                        <div class="fw-bold text-primary" style="font-size: 1.5rem;">
                                            <?php echo date('j', strtotime($event['event_date'])); ?>
                                        </div>
                                        <div class="text-muted small">
                                            <?php echo date('M Y', strtotime($event['event_date'])); ?>
                                        </div>
                                        <?php if ($event['event_time']): ?>
                                            <div class="text-muted small">
                                                <?php echo date('g:i A', strtotime($event['event_time'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-8">
                                        <h5 class="mb-1"><?php echo htmlspecialchars($event['title']); ?></h5>
                                        <p class="mb-1 text-muted"><?php echo htmlspecialchars($event['description']); ?></p>
                                        <div class="d-flex gap-2">
                                            <span class="badge bg-<?php echo $event['event_type'] == 'exam' ? 'danger' : ($event['event_type'] == 'holiday' ? 'success' : ($event['event_type'] == 'deadline' ? 'warning' : 'info')); ?>">
                                                <?php echo ucfirst($event['event_type']); ?>
                                            </span>
                                            <span class="badge bg-secondary">
                                                <?php echo ucfirst($event['target_audience']); ?>
                                            </span>
                                            <?php if ($event['department']): ?>
                                                <span class="badge bg-info">
                                                    <?php echo htmlspecialchars($event['department']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-2 text-end">
                                        <?php 
                                        $days_until = ceil((strtotime($event['event_date']) - time()) / (60 * 60 * 24));
                                        if ($days_until == 0): ?>
                                            <span class="badge bg-danger mb-2">Today</span>
                                        <?php elseif ($days_until == 1): ?>
                                            <span class="badge bg-warning mb-2">Tomorrow</span>
                                        <?php elseif ($days_until > 0 && $days_until <= 7): ?>
                                            <span class="badge bg-info mb-2"><?php echo $days_until; ?> days</span>
                                        <?php elseif ($days_until < 0): ?>
                                            <span class="badge bg-secondary mb-2">Past</span>
                                        <?php endif; ?>
                                        <br>
                                        <a href="?delete=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this event?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Events Found</h5>
                        <p class="text-muted">Add your first academic event to get started.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Event Modal -->
    <div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addEventModalLabel">Add Academic Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="admin_calendar.php">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Event Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="event_type" class="form-label">Event Type *</label>
                                    <select class="form-select" id="event_type" name="event_type" required>
                                        <option value="exam">Exam</option>
                                        <option value="holiday">Holiday</option>
                                        <option value="assignment">Assignment</option>
                                        <option value="meeting">Meeting</option>
                                        <option value="deadline">Deadline</option>
                                        <option value="event">Event</option>
                                        <option value="announcement">Announcement</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="event_date" class="form-label">Event Date *</label>
                                    <input type="date" class="form-control" id="event_date" name="event_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="event_time" class="form-label">Event Time (Optional)</label>
                                    <input type="time" class="form-control" id="event_time" name="event_time">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="target_audience" class="form-label">Target Audience *</label>
                                    <select class="form-select" id="target_audience" name="target_audience" required>
                                        <option value="all">All (Students & Faculty)</option>
                                        <option value="students">Students Only</option>
                                        <option value="faculty">Faculty Only</option>
                                        <option value="department">Department Specific</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="department" class="form-label">Department (if specific)</label>
                                    <select class="form-select" id="department" name="department">
                                        <option value="">All Departments</option>
                                        <option value="Cyber Security">Cyber Security</option>
                                        <option value="Computer Science">Computer Science</option>
                                        <option value="Data Science">Data Science</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_event" class="btn btn-success">Add Event & Send Notifications</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>Admin Panel</h5>
                    <p>Comprehensive academic calendar management.</p>
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
        // Set minimum date to today
        document.getElementById('event_date').min = new Date().toISOString().split('T')[0];
        
        // Show/hide department field based on target audience
        document.getElementById('target_audience').addEventListener('change', function() {
            const departmentField = document.getElementById('department');
            if (this.value === 'department') {
                departmentField.required = true;
                departmentField.parentElement.style.display = 'block';
            } else {
                departmentField.required = false;
                departmentField.value = '';
            }
        });
    </script>
</body>
</html>
