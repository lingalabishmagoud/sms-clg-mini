<?php
// Start session
session_start();

// Check if student is logged in (for testing, allow access)
if (!isset($_SESSION['student_id'])) {
    $_SESSION['student_id'] = 1;
    $_SESSION['user_type'] = 'student';
    $_SESSION['student_name'] = 'Test Student';
}

$student_id = $_SESSION['student_id'];

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get student information
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $student = $result->fetch_assoc();
} else {
    $student = [
        'id' => 1,
        'full_name' => $_SESSION['student_name'] ?? 'Test Student',
        'email' => 'test@example.com',
        'department' => 'Cyber Security',
        'year' => 2
    ];
}

// Get academic calendar events
$calendar_events = [];
$stmt = $conn->prepare("
    SELECT * FROM academic_calendar 
    WHERE is_active = 1 
    AND (target_audience = 'all' OR target_audience = 'students')
    AND event_date >= CURDATE()
    ORDER BY event_date ASC, event_time ASC
");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $calendar_events[] = $row;
}

// Get events by month for calendar view
$events_by_month = [];
foreach ($calendar_events as $event) {
    $month_key = date('Y-m', strtotime($event['event_date']));
    if (!isset($events_by_month[$month_key])) {
        $events_by_month[$month_key] = [];
    }
    $events_by_month[$month_key][] = $event;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.html");
    exit();
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Calendar - Student Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .event-card {
            border-left: 4px solid;
            margin-bottom: 1rem;
        }
        .event-exam { border-left-color: #dc3545; }
        .event-holiday { border-left-color: #28a745; }
        .event-assignment { border-left-color: #ffc107; }
        .event-meeting { border-left-color: #17a2b8; }
        .event-deadline { border-left-color: #fd7e14; }
        .event-event { border-left-color: #6f42c1; }
        .event-announcement { border-left-color: #6c757d; }
        
        .calendar-month {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
    </style>
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
                        <a class="nav-link active" href="student_calendar.php">Calendar</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_notifications.php">Notifications</a>
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
                <h2><i class="fas fa-calendar-alt me-2"></i>Academic Calendar</h2>
                <p class="text-muted">View important academic events, deadlines, and announcements</p>
            </div>
        </div>

        <!-- Upcoming Events Summary -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card bg-light">
                    <div class="card-body">
                        <h5><i class="fas fa-clock me-2"></i>Upcoming Events</h5>
                        <div class="row">
                            <?php 
                            $upcoming_count = 0;
                            $next_7_days = [];
                            foreach ($calendar_events as $event) {
                                if (strtotime($event['event_date']) <= strtotime('+7 days')) {
                                    $next_7_days[] = $event;
                                    $upcoming_count++;
                                }
                            }
                            ?>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="display-6 fw-bold text-primary"><?php echo $upcoming_count; ?></div>
                                    <small class="text-muted">Next 7 Days</small>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <?php if (!empty($next_7_days)): ?>
                                    <?php foreach (array_slice($next_7_days, 0, 3) as $event): ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-circle text-<?php echo $event['event_type'] == 'exam' ? 'danger' : ($event['event_type'] == 'holiday' ? 'success' : 'warning'); ?> me-2" style="font-size: 0.5rem;"></i>
                                            <strong><?php echo htmlspecialchars($event['title']); ?></strong>
                                            <span class="ms-auto text-muted"><?php echo date('M j', strtotime($event['event_date'])); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted mb-0">No upcoming events in the next 7 days.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Calendar Events by Month -->
        <?php if (!empty($events_by_month)): ?>
            <?php foreach ($events_by_month as $month_key => $month_events): ?>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header calendar-month">
                                <h4 class="mb-0">
                                    <i class="fas fa-calendar me-2"></i>
                                    <?php echo date('F Y', strtotime($month_key . '-01')); ?>
                                </h4>
                            </div>
                            <div class="card-body">
                                <?php foreach ($month_events as $event): ?>
                                    <div class="card event-card event-<?php echo $event['event_type']; ?>">
                                        <div class="card-body">
                                            <div class="row align-items-center">
                                                <div class="col-md-2 text-center">
                                                    <div class="fw-bold text-primary" style="font-size: 1.5rem;">
                                                        <?php echo date('j', strtotime($event['event_date'])); ?>
                                                    </div>
                                                    <div class="text-muted small">
                                                        <?php echo date('M', strtotime($event['event_date'])); ?>
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
                                                    <span class="badge bg-<?php echo $event['event_type'] == 'exam' ? 'danger' : ($event['event_type'] == 'holiday' ? 'success' : ($event['event_type'] == 'deadline' ? 'warning' : 'info')); ?>">
                                                        <?php echo ucfirst($event['event_type']); ?>
                                                    </span>
                                                </div>
                                                <div class="col-md-2 text-end">
                                                    <?php 
                                                    $days_until = ceil((strtotime($event['event_date']) - time()) / (60 * 60 * 24));
                                                    if ($days_until == 0): ?>
                                                        <span class="badge bg-danger">Today</span>
                                                    <?php elseif ($days_until == 1): ?>
                                                        <span class="badge bg-warning">Tomorrow</span>
                                                    <?php elseif ($days_until <= 7): ?>
                                                        <span class="badge bg-info"><?php echo $days_until; ?> days</span>
                                                    <?php else: ?>
                                                        <span class="text-muted"><?php echo date('M j', strtotime($event['event_date'])); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="row">
                <div class="col-md-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No upcoming academic events found.
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
</body>
</html>
