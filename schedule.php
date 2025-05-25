<?php
// Start session
session_start();

// Check if student is logged in
if (!isset($_SESSION['student_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header("Location: student_login.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get student information
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

// For now, we'll show the CS-A schedule for all students
// In a real system, you'd determine the student's section from their data
$section = 'CS-A';

// Get schedule data
$schedule_query = "
    SELECT
        s.*,
        p.period_name,
        p.start_time,
        p.end_time,
        p.period_number,
        sub.subject_name,
        sub.abbreviation,
        f.full_name as faculty_name,
        f.phone as faculty_phone
    FROM schedules s
    LEFT JOIN periods p ON s.period_id = p.id
    LEFT JOIN subjects sub ON s.subject_id = sub.id
    LEFT JOIN faculty f ON s.faculty_id = f.id
    WHERE s.section = ?
    ORDER BY
        FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
        p.period_number
";

$stmt = $conn->prepare($schedule_query);
$stmt->bind_param("s", $section);
$stmt->execute();
$result = $stmt->get_result();

$schedule = [];
while ($row = $result->fetch_assoc()) {
    $schedule[$row['day_of_week']][] = $row;
}
$stmt->close();

// Get period timings
$periods_query = "SELECT * FROM periods ORDER BY period_number";
$periods_result = $conn->query($periods_query);
$periods = [];
while ($row = $periods_result->fetch_assoc()) {
    $periods[] = $row;
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
    <title>Class Schedule - Student Management System</title>
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
        .subject-block {
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
        .break-block {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .period-time {
            font-size: 0.8rem;
            color: #666;
        }
        .faculty-info {
            font-size: 0.75rem;
            opacity: 0.9;
        }
        .section-info {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
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
                        <a class="nav-link" href="student_courses.php">My Courses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="schedule.php">Schedule</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="student_attendance.php">My Attendance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_files.php">Files</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        Welcome, <?php echo htmlspecialchars($student['full_name']); ?>
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
                <h2><i class="fas fa-calendar-week me-2"></i>Class Schedule</h2>
                <p class="text-muted">Your weekly class timetable</p>
            </div>
        </div>

        <!-- Section Information -->
        <div class="section-info">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-info-circle me-2"></i>Section Information</h5>
                    <p><strong>Section:</strong> <?php echo $section; ?></p>
                    <p><strong>Room No:</strong> 307</p>
                    <p><strong>Effective From:</strong> 27/01/2025</p>
                </div>
                <div class="col-md-6">
                    <h5><i class="fas fa-user-tie me-2"></i>Class In-charge</h5>
                    <p><strong>Mrs. P. Sandhya Rani</strong></p>
                    <p><i class="fas fa-phone me-1"></i> 9502060155</p>
                </div>
            </div>
        </div>

        <!-- Period Timings -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Period Timings</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($periods as $period): ?>
                        <div class="col-md-3 mb-2">
                            <div class="<?php echo $period['is_break'] ? 'alert alert-info' : 'alert alert-light'; ?> py-2 mb-2">
                                <strong><?php echo $period['period_name']; ?></strong><br>
                                <small><?php echo date('g:i A', strtotime($period['start_time'])) . ' - ' . date('g:i A', strtotime($period['end_time'])); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Subjects and Faculty Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-book me-2"></i>Subjects & Faculty Details</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Subject Name</th>
                                <th>Abbreviation</th>
                                <th>Faculty Name</th>
                                <th>Contact</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Cyber Security Essentials</td>
                                <td>CSE</td>
                                <td>Dr. K. Subba Rao</td>
                                <td>9986991545</td>
                            </tr>
                            <tr>
                                <td>Cyber Crime Investigation & Digital Forensics</td>
                                <td>CCDF</td>
                                <td>Mr. Mukesh Gilda</td>
                                <td>9177508064</td>
                            </tr>
                            <tr>
                                <td>Algorithms Design and Analysis</td>
                                <td>ADA</td>
                                <td>Mrs. P. Sandhya Rani</td>
                                <td>9502060155</td>
                            </tr>
                            <tr>
                                <td>DevOps (Professional Elective III)</td>
                                <td>DEVOPS</td>
                                <td>Mr. J. Naresh Kumar</td>
                                <td>9704768449</td>
                            </tr>
                            <tr>
                                <td>FIOT (Open Elective I)</td>
                                <td>FIOT</td>
                                <td>Mr. R. Anbarasu</td>
                                <td>9042932195</td>
                            </tr>
                            <tr>
                                <td>Environmental Science</td>
                                <td>ES</td>
                                <td>Mr. R. Anbarasu</td>
                                <td>9042932195</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Lab Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-flask me-2"></i>Practical Labs</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Lab Name</th>
                                <th>Faculty</th>
                                <th>Lab No</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Cyber Security Essentials Lab</td>
                                <td>Mr. R. Anbarasu</td>
                                <td>B1</td>
                            </tr>
                            <tr>
                                <td>Cyber Crime & Digital Forensics Lab</td>
                                <td>Mr. Mukesh Gilda</td>
                                <td>B2</td>
                            </tr>
                            <tr>
                                <td>DevOps Lab (Professional Elective III Lab)</td>
                                <td>Mr. J. Naresh Kumar</td>
                                <td>306</td>
                            </tr>
                            <tr>
                                <td>Industrial Oriented Mini Project</td>
                                <td>Mrs. P. Sandhya Rani</td>
                                <td>306</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Weekly Schedule -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-table me-2"></i>Weekly Schedule</h5>
            </div>
            <div class="card-body">
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
                            $non_break_periods = array_filter($periods, function($p) { return !$p['is_break']; });

                            foreach ($non_break_periods as $period):
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
                                                $block_class = $period_class['is_lab'] ? 'lab-block' : 'subject-block';
                                            ?>
                                                <div class="<?php echo $block_class; ?>">
                                                    <div><strong><?php echo htmlspecialchars($period_class['abbreviation']); ?></strong></div>
                                                    <?php if ($period_class['faculty_name']): ?>
                                                        <div class="faculty-info"><?php echo htmlspecialchars($period_class['faculty_name']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($period_class['is_lab'] && $period_class['lab_group']): ?>
                                                        <div class="faculty-info">Group: <?php echo htmlspecialchars($period_class['lab_group']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-muted text-center">-</div>
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
