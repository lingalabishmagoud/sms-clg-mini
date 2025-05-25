<?php
// Start session
session_start();

// Check if faculty is logged in
if (!isset($_SESSION['faculty_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'faculty') {
    header("Location: faculty_login.php");
    exit();
}

$faculty_id = $_SESSION['faculty_id'];
$faculty_name = $_SESSION['faculty_name'];

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get faculty information
$stmt = $conn->prepare("SELECT * FROM faculty WHERE id = ?");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();
$faculty = $result->fetch_assoc();
$stmt->close();

// Get faculty's schedule
$schedule_query = "
    SELECT
        s.*,
        p.period_name,
        p.start_time,
        p.end_time,
        p.period_number,
        sub.subject_name,
        sub.abbreviation
    FROM schedules s
    LEFT JOIN periods p ON s.period_id = p.id
    LEFT JOIN subjects sub ON s.subject_id = sub.id
    WHERE s.faculty_id = ?
    ORDER BY
        FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
        p.period_number
";

$stmt = $conn->prepare($schedule_query);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();

$schedule = [];
while ($row = $result->fetch_assoc()) {
    $schedule[$row['day_of_week']][] = $row;
}
$stmt->close();

// Get all periods for display
$periods_query = "SELECT * FROM periods WHERE is_break = FALSE ORDER BY period_number";
$periods_result = $conn->query($periods_query);
$periods = [];
while ($row = $periods_result->fetch_assoc()) {
    $periods[] = $row;
}

// Get faculty's subjects
$subjects_query = "SELECT * FROM subjects WHERE faculty_id = ?";
$stmt = $conn->prepare($subjects_query);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$subjects_result = $stmt->get_result();
$subjects = [];
while ($row = $subjects_result->fetch_assoc()) {
    $subjects[] = $row;
}
$stmt->close();

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
    <title>My Schedule - Faculty Portal</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .schedule-table {
            font-size: 0.9rem;
        }
        .schedule-cell {
            min-height: 60px;
            vertical-align: middle;
            padding: 8px;
        }
        .class-block {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px;
            border-radius: 6px;
            margin: 2px 0;
            text-align: center;
            font-weight: 500;
        }
        .lab-block {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .period-time {
            font-size: 0.8rem;
            color: #666;
        }
        .section-info {
            font-size: 0.75rem;
            opacity: 0.9;
        }
        .faculty-info {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
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
                        <a class="nav-link" href="faculty_students.php">Manage Students</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_courses.php">Manage Courses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="faculty_schedule.php">My Schedule</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="attendance_tracking.php">Attendance</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        Welcome, <?php echo htmlspecialchars($faculty['full_name']); ?>
                    </span>
                    <div class="dropdown">
                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="userMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-cog"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuButton">
                            <li><a class="dropdown-item" href="change_password.php"><i class="fas fa-key me-2"></i>Change Password</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="?logout=1"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <h2><i class="fas fa-calendar-week me-2"></i>My Teaching Schedule</h2>
                <p class="text-muted">Your weekly class timetable</p>
            </div>
        </div>

        <!-- Faculty Information -->
        <div class="faculty-info">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-user-tie me-2"></i>Faculty Information</h5>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($faculty['full_name']); ?></p>
                    <p><strong>Department:</strong> <?php echo htmlspecialchars($faculty['department']); ?></p>
                    <p><strong>Position:</strong> <?php echo htmlspecialchars($faculty['position']); ?></p>
                    <?php if (!empty($faculty['phone'])): ?>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($faculty['phone']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <h5><i class="fas fa-book me-2"></i>My Subjects</h5>
                    <?php if (!empty($subjects)): ?>
                        <ul class="list-unstyled">
                            <?php foreach ($subjects as $subject): ?>
                                <li><i class="fas fa-chevron-right me-2"></i><?php echo htmlspecialchars($subject['subject_name']) . ' (' . htmlspecialchars($subject['abbreviation']) . ')'; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">No subjects assigned yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Weekly Schedule -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-table me-2"></i>Weekly Teaching Schedule</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($schedule)): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered schedule-table">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width: 100px;">Time</th>
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
                                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

                                foreach ($periods as $period):
                                ?>
                                    <tr>
                                        <td class="schedule-cell text-center">
                                            <strong><?php echo $period['period_name']; ?></strong><br>
                                            <small class="period-time">
                                                <?php echo date('g:i A', strtotime($period['start_time'])) . '<br>' . date('g:i A', strtotime($period['end_time'])); ?>
                                            </small>
                                        </td>
                                        <?php foreach ($days as $day): ?>
                                            <td class="schedule-cell">
                                                <?php
                                                $day_schedule = $schedule[$day] ?? [];
                                                $period_class = null;

                                                foreach ($day_schedule as $class) {
                                                    if ($class['period_number'] == $period['period_number']) {
                                                        $period_class = $class;
                                                        break;
                                                    }
                                                }

                                                if ($period_class):
                                                    $block_class = $period_class['is_lab'] ? 'lab-block' : 'class-block';
                                                ?>
                                                    <div class="<?php echo $block_class; ?>">
                                                        <div><strong><?php echo htmlspecialchars($period_class['abbreviation']); ?></strong></div>
                                                        <div class="section-info">Section: <?php echo htmlspecialchars($period_class['section']); ?></div>
                                                        <div class="section-info">Room: <?php echo htmlspecialchars($period_class['room_number']); ?></div>
                                                        <?php if ($period_class['is_lab'] && $period_class['lab_group']): ?>
                                                            <div class="section-info">Group: <?php echo htmlspecialchars($period_class['lab_group']); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-muted text-center">Free</div>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No classes scheduled for you yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-tasks me-2"></i>Quick Actions</h5>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="attendance_tracking.php" class="btn btn-outline-success w-100">
                                    <i class="fas fa-clipboard-check me-2"></i>Mark Attendance
                                </a>
                            </div>

                            <div class="col-md-3 mb-3">
                                <a href="faculty_courses.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-book me-2"></i>My Courses
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="academic_calendar.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-calendar-alt me-2"></i>Academic Calendar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
