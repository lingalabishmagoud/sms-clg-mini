<?php
// Start session
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_schedule'])) {
        $section = $_POST['section'];
        $room_number = $_POST['room_number'];
        $day_of_week = $_POST['day_of_week'];
        $period_id = $_POST['period_id'];
        $subject_id = $_POST['subject_id'];
        $faculty_id = $_POST['faculty_id'];
        $lab_group = $_POST['lab_group'];
        $lab_location = $_POST['lab_location'];
        $is_lab = isset($_POST['is_lab']) ? 1 : 0;
        $effective_from = $_POST['effective_from'];

        $stmt = $conn->prepare("INSERT INTO schedules (section, room_number, day_of_week, period_id, subject_id, faculty_id, lab_group, lab_location, is_lab, effective_from) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssiiiisss", $section, $room_number, $day_of_week, $period_id, $subject_id, $faculty_id, $lab_group, $lab_location, $is_lab, $effective_from);

        if ($stmt->execute()) {
            $message = "Schedule entry added successfully!";
            $message_type = "success";
        } else {
            $message = "Error adding schedule entry: " . $stmt->error;
            $message_type = "danger";
        }
        $stmt->close();
    }

    if (isset($_POST['delete_schedule'])) {
        $schedule_id = $_POST['schedule_id'];
        $stmt = $conn->prepare("DELETE FROM schedules WHERE id = ?");
        $stmt->bind_param("i", $schedule_id);

        if ($stmt->execute()) {
            $message = "Schedule entry deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error deleting schedule entry: " . $stmt->error;
            $message_type = "danger";
        }
        $stmt->close();
    }
}

// Get all schedules
$schedules_query = "
    SELECT
        s.*,
        p.period_name,
        p.start_time,
        p.end_time,
        sub.subject_name,
        sub.abbreviation,
        f.full_name as faculty_name
    FROM schedules s
    LEFT JOIN periods p ON s.period_id = p.id
    LEFT JOIN subjects sub ON s.subject_id = sub.id
    LEFT JOIN faculty f ON s.faculty_id = f.id
    ORDER BY s.section,
        FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
        p.period_number
";
$schedules_result = $conn->query($schedules_query);

// Get periods for dropdown
$periods_result = $conn->query("SELECT * FROM periods WHERE is_break = FALSE ORDER BY period_number");

// Get subjects for dropdown
$subjects_result = $conn->query("SELECT * FROM subjects ORDER BY subject_name");

// Get faculty for dropdown
$faculty_result = $conn->query("SELECT * FROM faculty ORDER BY full_name");

// Handle logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: admin_login.php");
    exit();
}

// Get action for different views
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Organize schedules by section for timetable view
$schedules_by_section = [];
if ($action == 'timetable') {
    $timetable_query = "
        SELECT
            s.*,
            p.period_name,
            p.start_time,
            p.end_time,
            sub.subject_name,
            sub.abbreviation,
            f.full_name as faculty_name
        FROM schedules s
        LEFT JOIN periods p ON s.period_id = p.id
        LEFT JOIN subjects sub ON s.subject_id = sub.id
        LEFT JOIN faculty f ON s.faculty_id = f.id
        ORDER BY s.section,
            FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
            p.period_number
    ";
    $timetable_result = $conn->query($timetable_query);

    while ($schedule = $timetable_result->fetch_assoc()) {
        $section = $schedule['section'];
        $day = $schedule['day_of_week'];
        $period = $schedule['period_name'];

        if (!isset($schedules_by_section[$section])) {
            $schedules_by_section[$section] = [];
        }
        if (!isset($schedules_by_section[$section][$day])) {
            $schedules_by_section[$section][$day] = [];
        }
        $schedules_by_section[$section][$day][$period] = $schedule;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Management - Admin Portal</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .schedule-cell {
            min-height: 60px;
            border: 1px solid #dee2e6;
            padding: 8px;
            font-size: 0.85em;
            vertical-align: top;
        }
        .schedule-entry {
            background: #e3f2fd;
            border-radius: 4px;
            padding: 4px 6px;
            margin: 2px 0;
            border-left: 3px solid #2196f3;
        }
        .lab-entry {
            background: #fff3e0;
            border-radius: 4px;
            padding: 4px 6px;
            margin: 2px 0;
            border-left: 3px solid #ff9800;
        }
        .break-cell {
            background: #f8f9fa;
            text-align: center;
            font-weight: bold;
            color: #6c757d;
            vertical-align: middle;
        }
        .timetable-legend {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .legend-color {
            width: 20px;
            height: 15px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Admin Portal</a>
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
                        <a class="nav-link" href="admin_grades.php">Grades</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_departments.php">Departments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="admin_schedule.php">Schedules</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_reports.php">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_settings.php">Settings</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        Welcome, <?php echo htmlspecialchars($admin_name); ?>
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
                <h2><i class="fas fa-calendar-week me-2"></i>Schedule Management</h2>
                <p class="text-muted">Manage class schedules and timetables</p>
            </div>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Add Schedule Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Add Schedule Entry</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="section" class="form-label">Section</label>
                            <select class="form-select" id="section" name="section" required>
                                <option value="">Select Section</option>
                                <option value="CS-A">CS-A</option>
                                <option value="CS-B">CS-B</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="room_number" class="form-label">Room Number</label>
                            <input type="text" class="form-control" id="room_number" name="room_number" value="307" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="day_of_week" class="form-label">Day</label>
                            <select class="form-select" id="day_of_week" name="day_of_week" required>
                                <option value="">Select Day</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="period_id" class="form-label">Period</label>
                            <select class="form-select" id="period_id" name="period_id" required>
                                <option value="">Select Period</option>
                                <?php
                                $periods_result->data_seek(0);
                                while ($period = $periods_result->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $period['id']; ?>">
                                        <?php echo $period['period_name'] . ' (' . date('g:i A', strtotime($period['start_time'])) . ' - ' . date('g:i A', strtotime($period['end_time'])) . ')'; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="subject_id" class="form-label">Subject</label>
                            <select class="form-select" id="subject_id" name="subject_id">
                                <option value="">Select Subject</option>
                                <?php
                                $subjects_result->data_seek(0);
                                while ($subject = $subjects_result->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $subject['id']; ?>">
                                        <?php echo $subject['subject_name'] . ' (' . $subject['abbreviation'] . ')'; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="faculty_id" class="form-label">Faculty</label>
                            <select class="form-select" id="faculty_id" name="faculty_id">
                                <option value="">Select Faculty</option>
                                <?php
                                $faculty_result->data_seek(0);
                                while ($faculty = $faculty_result->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $faculty['id']; ?>">
                                        <?php echo $faculty['full_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="lab_group" class="form-label">Lab Group</label>
                            <input type="text" class="form-control" id="lab_group" name="lab_group" placeholder="B1, B2, etc.">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="lab_location" class="form-label">Lab Location</label>
                            <input type="text" class="form-control" id="lab_location" name="lab_location" placeholder="Lab room">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="effective_from" class="form-label">Effective From</label>
                            <input type="date" class="form-control" id="effective_from" name="effective_from" value="2025-01-27" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_lab" name="is_lab">
                                <label class="form-check-label" for="is_lab">
                                    This is a lab session
                                </label>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="add_schedule" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add Schedule Entry
                    </button>
                </form>
            </div>
        </div>

        <?php if ($action == 'list'): ?>
        <!-- Current Schedules -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-table me-2"></i>Current Schedules</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Section</th>
                                <th>Day</th>
                                <th>Period</th>
                                <th>Subject</th>
                                <th>Faculty</th>
                                <th>Room</th>
                                <th>Lab Info</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($schedules_result->num_rows > 0): ?>
                                <?php while ($schedule = $schedules_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($schedule['section']); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['day_of_week']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($schedule['period_name']); ?><br>
                                            <small class="text-muted">
                                                <?php echo date('g:i A', strtotime($schedule['start_time'])) . ' - ' . date('g:i A', strtotime($schedule['end_time'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($schedule['abbreviation']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($schedule['subject_name']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($schedule['faculty_name']); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['room_number']); ?></td>
                                        <td>
                                            <?php if ($schedule['is_lab']): ?>
                                                <span class="badge bg-info">Lab</span><br>
                                                <?php if ($schedule['lab_group']): ?>
                                                    <small>Group: <?php echo htmlspecialchars($schedule['lab_group']); ?></small><br>
                                                <?php endif; ?>
                                                <?php if ($schedule['lab_location']): ?>
                                                    <small>Location: <?php echo htmlspecialchars($schedule['lab_location']); ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Lecture</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this schedule entry?');">
                                                <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                                <button type="submit" name="delete_schedule" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">No schedules found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($action == 'timetable'): ?>
        <!-- Timetable View -->
        <div class="row">
            <?php foreach (['CS-A', 'CS-B'] as $section): ?>
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-calendar-week me-2"></i>Section <?php echo $section; ?> - Weekly Timetable</h5>
                        </div>
                        <div class="card-body">
                            <!-- Legend -->
                            <div class="timetable-legend">
                                <div class="legend-item">
                                    <div class="legend-color" style="background: #e3f2fd; border-left: 3px solid #2196f3;"></div>
                                    <span>Regular Lecture</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color" style="background: #fff3e0; border-left: 3px solid #ff9800;"></div>
                                    <span>Lab Session</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color" style="background: #f8f9fa;"></div>
                                    <span>Break/Lunch</span>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm">
                                    <thead class="table-dark">
                                        <tr>
                                            <th style="width: 100px;">Period</th>
                                            <th>Monday</th>
                                            <th>Tuesday</th>
                                            <th>Wednesday</th>
                                            <th>Thursday</th>
                                            <th>Friday</th>
                                            <th>Saturday</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $periods_for_timetable = ['I', 'II', 'BREAK', 'III', 'IV', 'LUNCH', 'V', 'VI', 'VII'];
                                        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

                                        foreach ($periods_for_timetable as $period):
                                        ?>
                                            <tr>
                                                <td class="fw-bold text-center">
                                                    <?php echo $period; ?>
                                                    <?php if ($period == 'BREAK'): ?>
                                                        <br><small class="text-muted">10:50-11:00</small>
                                                    <?php elseif ($period == 'LUNCH'): ?>
                                                        <br><small class="text-muted">12:40-1:30</small>
                                                    <?php endif; ?>
                                                </td>
                                                <?php foreach ($days as $day): ?>
                                                    <td class="schedule-cell <?php echo ($period == 'BREAK' || $period == 'LUNCH') ? 'break-cell' : ''; ?>">
                                                        <?php if ($period == 'BREAK' || $period == 'LUNCH'): ?>
                                                            <?php echo $period; ?>
                                                        <?php else: ?>
                                                            <?php
                                                            if (isset($schedules_by_section[$section][$day][$period])) {
                                                                $schedule = $schedules_by_section[$section][$day][$period];
                                                                $class = $schedule['is_lab'] ? 'lab-entry' : 'schedule-entry';
                                                                echo '<div class="' . $class . '">';
                                                                echo '<strong>' . htmlspecialchars($schedule['abbreviation']) . '</strong><br>';
                                                                echo '<small>' . htmlspecialchars($schedule['faculty_name']) . '</small><br>';
                                                                echo '<small>Room: ' . htmlspecialchars($schedule['room_number']) . '</small>';
                                                                if ($schedule['is_lab'] && $schedule['lab_group']) {
                                                                    echo '<br><small>Group: ' . htmlspecialchars($schedule['lab_group']) . '</small>';
                                                                }
                                                                echo '</div>';
                                                            } else {
                                                                echo '<span class="text-muted">---</span>';
                                                            }
                                                            ?>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>
