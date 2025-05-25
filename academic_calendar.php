<?php
// Start session
session_start();

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user type and ID from session
$user_type = '';
$user_id = 0;

if (isset($_SESSION['student_id'])) {
    $user_type = 'student';
    $user_id = $_SESSION['student_id'];
} elseif (isset($_SESSION['faculty_id'])) {
    $user_type = 'faculty';
    $user_id = $_SESSION['faculty_id'];
} elseif (isset($_SESSION['admin_id'])) {
    $user_type = 'admin';
    $user_id = $_SESSION['admin_id'];
}

// Get user information
$user = null;
if ($user_type == 'student') {
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
    }
} elseif ($user_type == 'faculty') {
    $stmt = $conn->prepare("SELECT * FROM faculty WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
    }
} elseif ($user_type == 'admin') {
    $stmt = $conn->prepare("SELECT * FROM admin WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
    }
}

// Check if calendar_events table exists, if not create it
$result = $conn->query("SHOW TABLES LIKE 'calendar_events'");
if ($result->num_rows == 0) {
    $sql = "CREATE TABLE calendar_events (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        description TEXT,
        start_date DATE NOT NULL,
        end_date DATE,
        event_type ENUM('academic', 'exam', 'holiday', 'other') NOT NULL DEFAULT 'academic',
        created_by VARCHAR(20) NOT NULL,
        created_by_id INT(11) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql) === TRUE) {
        // Insert sample calendar events
        $sample_events = [
            ['Fall Semester Begins', 'First day of classes for the Fall semester', '2023-09-01', '2023-09-01', 'academic', 'admin', 1],
            ['Midterm Exams', 'Fall semester midterm examination period', '2023-10-15', '2023-10-20', 'exam', 'admin', 1],
            ['Thanksgiving Break', 'No classes during Thanksgiving break', '2023-11-23', '2023-11-26', 'holiday', 'admin', 1],
            ['Final Exams', 'Fall semester final examination period', '2023-12-10', '2023-12-15', 'exam', 'admin', 1],
            ['Winter Break', 'Winter holiday break, no classes', '2023-12-16', '2024-01-14', 'holiday', 'admin', 1],
            ['Spring Semester Begins', 'First day of classes for the Spring semester', '2024-01-15', '2024-01-15', 'academic', 'admin', 1],
            ['Spring Break', 'No classes during Spring break', '2024-03-10', '2024-03-17', 'holiday', 'admin', 1],
            ['Spring Midterm Exams', 'Spring semester midterm examination period', '2024-03-01', '2024-03-05', 'exam', 'admin', 1],
            ['Spring Final Exams', 'Spring semester final examination period', '2024-05-01', '2024-05-07', 'exam', 'admin', 1],
            ['Commencement', 'Graduation ceremony', '2024-05-15', '2024-05-15', 'academic', 'admin', 1],
            ['Summer Session Begins', 'First day of classes for the Summer session', '2024-06-01', '2024-06-01', 'academic', 'admin', 1],
            ['Independence Day', 'Independence Day holiday, no classes', '2024-07-04', '2024-07-04', 'holiday', 'admin', 1],
            ['Summer Session Ends', 'Last day of classes for the Summer session', '2024-08-15', '2024-08-15', 'academic', 'admin', 1]
        ];
        
        foreach ($sample_events as $event) {
            $stmt = $conn->prepare("INSERT INTO calendar_events (title, description, start_date, end_date, event_type, created_by, created_by_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssi", $event[0], $event[1], $event[2], $event[3], $event[4], $event[5], $event[6]);
            $stmt->execute();
        }
    }
}

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'view';
$message = '';
$current_month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$current_year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Add new event (admin and faculty only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event']) && ($user_type == 'admin' || $user_type == 'faculty')) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $start_date = $_POST['start_date'];
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : $start_date;
    $event_type = $_POST['event_type'];
    
    $stmt = $conn->prepare("INSERT INTO calendar_events (title, description, start_date, end_date, event_type, created_by, created_by_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssi", $title, $description, $start_date, $end_date, $event_type, $user_type, $user_id);
    
    if ($stmt->execute()) {
        $message = "Event added successfully!";
        $action = 'view'; // Return to view
    } else {
        $message = "Error: " . $stmt->error;
    }
}

// Delete event (admin only)
if (isset($_GET['delete']) && is_numeric($_GET['delete']) && $user_type == 'admin') {
    $event_id = $_GET['delete'];
    
    $stmt = $conn->prepare("DELETE FROM calendar_events WHERE id = ?");
    $stmt->bind_param("i", $event_id);
    
    if ($stmt->execute()) {
        $message = "Event deleted successfully.";
    } else {
        $message = "Error: " . $stmt->error;
    }
}

// Get calendar events
$events = [];
$stmt = $conn->prepare("SELECT * FROM calendar_events ORDER BY start_date");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
}

// Get events for the current month
$month_events = [];
foreach ($events as $event) {
    $start_month = date('m', strtotime($event['start_date']));
    $start_year = date('Y', strtotime($event['start_date']));
    $end_month = date('m', strtotime($event['end_date']));
    $end_year = date('Y', strtotime($event['end_date']));
    
    // Check if event falls in current month/year or spans across it
    if (($start_month == $current_month && $start_year == $current_year) || 
        ($end_month == $current_month && $end_year == $current_year) ||
        (strtotime($event['start_date']) <= strtotime("$current_year-$current_month-01") && 
         strtotime($event['end_date']) >= strtotime("$current_year-$current_month-" . date('t', strtotime("$current_year-$current_month-01"))))) {
        $month_events[] = $event;
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
    <title>Academic Calendar - Student Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .calendar-day {
            height: 120px;
            border: 1px solid #dee2e6;
            padding: 5px;
        }
        .calendar-day:hover {
            background-color: #f8f9fa;
        }
        .calendar-day.inactive {
            background-color: #f8f9fa;
            color: #adb5bd;
        }
        .calendar-day .day-number {
            font-weight: bold;
            font-size: 1.2rem;
        }
        .event-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .event-academic {
            background-color: #007bff;
        }
        .event-exam {
            background-color: #dc3545;
        }
        .event-holiday {
            background-color: #28a745;
        }
        .event-other {
            background-color: #6c757d;
        }
        .event-item {
            font-size: 0.8rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 2px;
            padding: 2px 4px;
            border-radius: 3px;
        }
        .event-item.academic {
            background-color: rgba(0, 123, 255, 0.1);
            border-left: 3px solid #007bff;
        }
        .event-item.exam {
            background-color: rgba(220, 53, 69, 0.1);
            border-left: 3px solid #dc3545;
        }
        .event-item.holiday {
            background-color: rgba(40, 167, 69, 0.1);
            border-left: 3px solid #28a745;
        }
        .event-item.other {
            background-color: rgba(108, 117, 125, 0.1);
            border-left: 3px solid #6c757d;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
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
                        <a class="nav-link" href="student_courses.php">My Courses</a>
                    </li>
                    <?php elseif ($user_type == 'faculty'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_courses.php">My Courses</a>
                    </li>
                    <?php elseif ($user_type == 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_courses.php">Courses</a>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="index.html">Home</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link active" href="academic_calendar.php">Academic Calendar</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <?php if ($user_type): ?>
                    <span class="navbar-text me-3">
                        Welcome, <?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?>
                    </span>
                    <a href="?logout=1" class="btn btn-light btn-sm">Logout</a>
                    <?php else: ?>
                    <a href="student_login.php" class="btn btn-outline-light me-2">Student Login</a>
                    <a href="faculty_login.php" class="btn btn-light">Faculty Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2><i class="fas fa-calendar-alt me-2"></i>Academic Calendar</h2>
                    <?php if ($user_type == 'admin' || $user_type == 'faculty'): ?>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add Event
                    </a>
                    <?php endif; ?>
                </div>
                <p class="text-muted">View important academic dates and events</p>
            </div>
        </div>

        <?php if (!empty($message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if ($action == 'add' && ($user_type == 'admin' || $user_type == 'faculty')): ?>
        <!-- Add Event Form -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Add New Calendar Event</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="title" class="form-label">Event Title</label>
                                    <input type="text" class="form-control" id="title" name="title" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="event_type" class="form-label">Event Type</label>
                                    <select class="form-select" id="event_type" name="event_type" required>
                                        <option value="academic">Academic</option>
                                        <option value="exam">Exam</option>
                                        <option value="holiday">Holiday</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="end_date" class="form-label">End Date (optional)</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            <div class="d-flex justify-content-between">
                                <a href="academic_calendar.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" name="add_event" class="btn btn-primary">Add Event</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Calendar View -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0">
                                    <?php echo date('F Y', strtotime("$current_year-$current_month-01")); ?>
                                </h5>
                            </div>
                            <div>
                                <?php
                                // Previous month
                                $prev_month = $current_month - 1;
                                $prev_year = $current_year;
                                if ($prev_month < 1) {
                                    $prev_month = 12;
                                    $prev_year--;
                                }
                                
                                // Next month
                                $next_month = $current_month + 1;
                                $next_year = $current_year;
                                if ($next_month > 12) {
                                    $next_month = 1;
                                    $next_year++;
                                }
                                ?>
                                <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                                <a href="?month=<?php echo date('m'); ?>&year=<?php echo date('Y'); ?>" class="btn btn-sm btn-outline-primary mx-2">
                                    Today
                                </a>
                                <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="btn btn-sm btn-outline-secondary">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Calendar Legend -->
                        <div class="mb-3">
                            <span class="me-3"><span class="event-dot event-academic"></span> Academic</span>
                            <span class="me-3"><span class="event-dot event-exam"></span> Exam</span>
                            <span class="me-3"><span class="event-dot event-holiday"></span> Holiday</span>
                            <span class="me-3"><span class="event-dot event-other"></span> Other</span>
                        </div>
                        
                        <!-- Calendar Grid -->
                        <div class="row">
                            <div class="col">
                                <div class="row text-center fw-bold mb-2">
                                    <div class="col">Sun</div>
                                    <div class="col">Mon</div>
                                    <div class="col">Tue</div>
                                    <div class="col">Wed</div>
                                    <div class="col">Thu</div>
                                    <div class="col">Fri</div>
                                    <div class="col">Sat</div>
                                </div>
                                
                                <?php
                                // Get first day of the month
                                $first_day_timestamp = strtotime("$current_year-$current_month-01");
                                $first_day_of_week = date('w', $first_day_timestamp); // 0 (Sun) to 6 (Sat)
                                
                                // Get number of days in the month
                                $days_in_month = date('t', $first_day_timestamp);
                                
                                // Get current day
                                $current_day = date('j');
                                $is_current_month = ($current_month == date('m') && $current_year == date('Y'));
                                
                                // Start calendar grid
                                echo '<div class="row">';
                                
                                // Add empty cells for days before the first day of the month
                                for ($i = 0; $i < $first_day_of_week; $i++) {
                                    echo '<div class="col calendar-day inactive"></div>';
                                }
                                
                                // Add cells for each day of the month
                                for ($day = 1; $day <= $days_in_month; $day++) {
                                    $date_string = "$current_year-$current_month-$day";
                                    $is_today = ($is_current_month && $day == $current_day);
                                    
                                    // Get events for this day
                                    $day_events = [];
                                    foreach ($month_events as $event) {
                                        $event_start = strtotime($event['start_date']);
                                        $event_end = strtotime($event['end_date']);
                                        $current_date = strtotime($date_string);
                                        
                                        if ($current_date >= $event_start && $current_date <= $event_end) {
                                            $day_events[] = $event;
                                        }
                                    }
                                    
                                    echo '<div class="col calendar-day' . ($is_today ? ' bg-light' : '') . '">';
                                    echo '<div class="day-number' . ($is_today ? ' text-primary' : '') . '">' . $day . '</div>';
                                    
                                    // Display events for this day (limit to 3 for space)
                                    $event_count = count($day_events);
                                    $display_count = min($event_count, 3);
                                    
                                    for ($e = 0; $e < $display_count; $e++) {
                                        $event = $day_events[$e];
                                        echo '<div class="event-item ' . $event['event_type'] . '" data-bs-toggle="tooltip" title="' . htmlspecialchars($event['title']) . '">';
                                        echo htmlspecialchars($event['title']);
                                        echo '</div>';
                                    }
                                    
                                    if ($event_count > 3) {
                                        echo '<div class="text-muted small">+' . ($event_count - 3) . ' more</div>';
                                    }
                                    
                                    echo '</div>';
                                    
                                    // Start a new row after Saturday
                                    if (($first_day_of_week + $day) % 7 == 0) {
                                        echo '</div><div class="row">';
                                    }
                                }
                                
                                // Add empty cells for days after the last day of the month
                                $last_day_of_week = ($first_day_of_week + $days_in_month) % 7;
                                if ($last_day_of_week > 0) {
                                    for ($i = $last_day_of_week; $i < 7; $i++) {
                                        echo '<div class="col calendar-day inactive"></div>';
                                    }
                                }
                                
                                echo '</div>'; // Close the last row
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Upcoming Events List -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Upcoming Events</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>Type</th>
                                        <th>Dates</th>
                                        <th>Description</th>
                                        <?php if ($user_type == 'admin'): ?>
                                        <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Sort events by start date
                                    usort($events, function($a, $b) {
                                        return strtotime($a['start_date']) - strtotime($b['start_date']);
                                    });
                                    
                                    // Filter to show only upcoming events
                                    $upcoming_events = array_filter($events, function($event) {
                                        return strtotime($event['end_date'] ?? $event['start_date']) >= strtotime('today');
                                    });
                                    
                                    // Limit to 10 upcoming events
                                    $upcoming_events = array_slice($upcoming_events, 0, 10);
                                    
                                    foreach ($upcoming_events as $event): 
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($event['title']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $event['event_type'] == 'academic' ? 'primary' : 
                                                    ($event['event_type'] == 'exam' ? 'danger' : 
                                                    ($event['event_type'] == 'holiday' ? 'success' : 'secondary')); 
                                            ?>">
                                                <?php echo ucfirst($event['event_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            echo date('M d, Y', strtotime($event['start_date']));
                                            if ($event['end_date'] && $event['end_date'] != $event['start_date']) {
                                                echo ' - ' . date('M d, Y', strtotime($event['end_date']));
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($event['description'] ?? 'No description'); ?></td>
                                        <?php if ($user_type == 'admin'): ?>
                                        <td>
                                            <a href="?delete=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this event?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (count($upcoming_events) == 0): ?>
                                    <tr>
                                        <td colspan="<?php echo $user_type == 'admin' ? '5' : '4'; ?>" class="text-center">No upcoming events found.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
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
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    </script>
</body>
</html>
