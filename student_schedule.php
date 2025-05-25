<?php
// Start session
session_start();

// Check if student is logged in
if (!isset($_SESSION['student_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header("Location: student_login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

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

// Get student's schedule based on their section
$schedule_query = "
    SELECT 
        sch.day_of_week,
        p.period_number,
        p.start_time,
        p.end_time,
        s.subject_name,
        s.abbreviation,
        f.full_name as faculty_name,
        sch.room_number,
        sch.lab_group,
        sch.is_lab
    FROM schedules sch
    JOIN periods p ON sch.period_id = p.id
    LEFT JOIN subjects s ON sch.subject_id = s.id
    LEFT JOIN faculty f ON sch.faculty_id = f.id
    WHERE sch.section = ?
    ORDER BY 
        FIELD(sch.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
        p.period_number
";

$stmt = $conn->prepare($schedule_query);
$stmt->bind_param("s", $student['section']);
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

// Get student's subjects
$subjects_query = "
    SELECT s.*, f.full_name as faculty_name
    FROM subjects s
    LEFT JOIN faculty f ON s.faculty_id = f.id
    INNER JOIN student_subject_enrollment sse ON s.id = sse.subject_id
    WHERE sse.student_id = ? AND sse.status = 'active'
    ORDER BY s.subject_name
";

$stmt = $conn->prepare($subjects_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

$subjects = [];
while ($row = $result->fetch_assoc()) {
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
    <title>My Schedule - Student Portal</title>
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
        .student-info {
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
                        <a class="nav-link" href="student_subjects.php">My Subjects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="student_schedule.php">My Schedule</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_grades.php">Grades</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_attendance.php">Attendance</a>
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
                <h2><i class="fas fa-calendar-week me-2"></i>My Class Schedule</h2>
                <p class="text-muted">Your weekly class timetable</p>
            </div>
        </div>

        <!-- Student Information -->
        <div class="student-info">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-user-graduate me-2"></i>Student Information</h5>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($student['full_name']); ?></p>
                    <p><strong>Roll Number:</strong> <?php echo htmlspecialchars($student['roll_number']); ?></p>
                    <p><strong>Section:</strong> <?php echo htmlspecialchars($student['section']); ?></p>
                    <p><strong>Department:</strong> <?php echo htmlspecialchars($student['department']); ?></p>
                </div>
                <div class="col-md-6">
                    <h5><i class="fas fa-book me-2"></i>My Subjects</h5>
                    <?php if (!empty($subjects)): ?>
                        <ul class="list-unstyled">
                            <?php foreach ($subjects as $subject): ?>
                                <li><i class="fas fa-chevron-right me-2"></i><?php echo htmlspecialchars($subject['abbreviation']) . ' - ' . htmlspecialchars($subject['subject_name']); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">No subjects enrolled yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Weekly Schedule -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-table me-2"></i>Weekly Class Schedule - Section <?php echo htmlspecialchars($student['section']); ?></h5>
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
                                            <strong>Period <?php echo $period['period_number']; ?></strong><br>
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
                                                        <div class="section-info"><?php echo htmlspecialchars($period_class['faculty_name']); ?></div>
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
                        <i class="fas fa-info-circle me-2"></i>No classes scheduled for your section yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Subject Details -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-book me-2"></i>Subject Details</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($subjects)): ?>
                            <div class="row">
                                <?php foreach ($subjects as $subject): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="card border-info">
                                            <div class="card-body">
                                                <h6 class="card-title"><?php echo htmlspecialchars($subject['abbreviation']); ?></h6>
                                                <p class="card-text"><?php echo htmlspecialchars($subject['subject_name']); ?></p>
                                                <small class="text-muted">
                                                    <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($subject['faculty_name'] ?: 'Not Assigned'); ?><br>
                                                    <i class="fas fa-star me-1"></i><?php echo $subject['credits']; ?> Credits
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <p class="mb-0">No subjects enrolled yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
