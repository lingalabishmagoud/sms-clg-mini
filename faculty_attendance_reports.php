<?php
// Start session
session_start();

// For testing purposes, we're not enforcing authentication
// In a real application, you would check if the user is logged in
$faculty_id = isset($_SESSION['faculty_id']) ? $_SESSION['faculty_id'] : 1;
$faculty_name = isset($_SESSION['faculty_name']) ? $_SESSION['faculty_name'] : "Test Faculty";

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get faculty info
$stmt = $conn->prepare("SELECT * FROM faculty WHERE id = ?");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();
$faculty = $result->fetch_assoc();
$stmt->close();

// Get courses taught by this faculty
$courses = [];
$stmt = $conn->prepare("SELECT * FROM courses WHERE faculty_id = ? ORDER BY course_name");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $courses[] = $row;
}
$stmt->close();

// Initialize variables
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'course';

// Get course details if course_id is set
$course = null;
if ($course_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM courses WHERE id = ? AND faculty_id = ?");
    $stmt->bind_param("ii", $course_id, $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $course = $result->fetch_assoc();
    $stmt->close();
}

// Get student details if student_id is set
$student = null;
if ($student_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
}

// Get enrolled students for this course
$students = [];
if ($course_id > 0) {
    $stmt = $conn->prepare("
        SELECT s.*, e.status as enrollment_status
        FROM students s
        JOIN enrollments e ON s.id = e.student_id
        WHERE e.course_id = ?
        ORDER BY s.full_name
    ");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();
}

// Get attendance data based on report type
$attendance_data = [];
$attendance_summary = [];

if ($report_type === 'course' && $course_id > 0) {
    // Get all attendance records for this course in the date range
    $stmt = $conn->prepare("
        SELECT a.*, s.full_name as student_name
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE a.course_id = ? AND a.date BETWEEN ? AND ?
        ORDER BY a.date DESC, s.full_name
    ");
    $stmt->bind_param("iss", $course_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $date = $row['date'];
        $student_id = $row['student_id'];

        if (!isset($attendance_data[$date])) {
            $attendance_data[$date] = [];
        }

        $attendance_data[$date][$student_id] = $row;

        // Build summary data
        if (!isset($attendance_summary[$student_id])) {
            $attendance_summary[$student_id] = [
                'student_name' => $row['student_name'],
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'excused' => 0,
                'total' => 0
            ];
        }

        $attendance_summary[$student_id][$row['status']]++;
        $attendance_summary[$student_id]['total']++;
    }

    $stmt->close();

    // Get all dates in the range for complete report
    $all_dates = [];
    $current_date = new DateTime($start_date);
    $end = new DateTime($end_date);

    while ($current_date <= $end) {
        $date_str = $current_date->format('Y-m-d');
        $all_dates[] = $date_str;
        if (!isset($attendance_data[$date_str])) {
            $attendance_data[$date_str] = [];
        }
        $current_date->modify('+1 day');
    }

    // Sort dates in descending order
    rsort($all_dates);

} elseif ($report_type === 'student' && $student_id > 0) {
    // Get all attendance records for this student across all faculty's courses
    $stmt = $conn->prepare("
        SELECT a.*, c.course_code, c.course_name
        FROM attendance a
        JOIN courses c ON a.course_id = c.id
        WHERE a.student_id = ? AND c.faculty_id = ? AND a.date BETWEEN ? AND ?
        ORDER BY a.date DESC, c.course_name
    ");
    $stmt->bind_param("iiss", $student_id, $faculty_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $attendance_data[] = $row;

        // Build summary data by course
        $course_id = $row['course_id'];

        if (!isset($attendance_summary[$course_id])) {
            $attendance_summary[$course_id] = [
                'course_name' => $row['course_name'],
                'course_code' => $row['course_code'],
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'excused' => 0,
                'total' => 0
            ];
        }

        $attendance_summary[$course_id][$row['status']]++;
        $attendance_summary[$course_id]['total']++;
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

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports - Student Management System</title>
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
                        <a class="nav-link" href="faculty_students.php">Manage Students</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_courses.php">Manage Courses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_grades.php">Grades</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="attendance_tracking.php">Attendance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_files.php">Files</a>
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
            <div class="col-md-8">
                <h2><i class="fas fa-chart-bar me-2"></i>Attendance Reports</h2>
                <p class="text-muted">View and analyze attendance data for your courses</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="attendance_tracking.php" class="btn btn-outline-primary">
                    <i class="fas fa-clipboard-check me-2"></i>Mark Attendance
                </a>
            </div>
        </div>

        <!-- Report Filters Form -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" action="faculty_attendance_reports.php" class="row g-3">
                    <div class="col-md-3">
                        <label for="report_type" class="form-label">Report Type</label>
                        <select name="report_type" id="report_type" class="form-select" onchange="toggleReportFields()">
                            <option value="course" <?php echo ($report_type == 'course') ? 'selected' : ''; ?>>Course Report</option>
                            <option value="student" <?php echo ($report_type == 'student') ? 'selected' : ''; ?>>Student Report</option>
                        </select>
                    </div>

                    <div class="col-md-3 course-field">
                        <label for="course_id" class="form-label">Select Course</label>
                        <select name="course_id" id="course_id" class="form-select" <?php echo ($report_type == 'course') ? 'required' : ''; ?>>
                            <option value="">-- Select Course --</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ($course_id == $c['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3 student-field" style="<?php echo ($report_type == 'student') ? '' : 'display: none;'; ?>">
                        <label for="student_id" class="form-label">Select Student</label>
                        <select name="student_id" id="student_id" class="form-select" <?php echo ($report_type == 'student') ? 'required' : ''; ?>>
                            <option value="">-- Select Student --</option>
                            <?php if ($course_id > 0 && count($students) > 0): ?>
                                <?php foreach ($students as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo ($student_id == $s['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo $start_date; ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo $end_date; ?>" required>
                    </div>

                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Generate Report
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($report_type === 'course' && $course_id > 0 && $course): ?>
            <!-- Course Attendance Report -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>
                        Attendance Report: <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                    </h5>
                    <p class="text-muted mb-0">
                        <?php echo date('F j, Y', strtotime($start_date)); ?> to
                        <?php echo date('F j, Y', strtotime($end_date)); ?>
                    </p>
                </div>
                <div class="card-body">
                    <?php if (count($attendance_summary) > 0): ?>
                        <!-- Attendance Summary -->
                        <h6 class="mb-3">Attendance Summary</h6>
                        <div class="table-responsive mb-4">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Student</th>
                                        <th class="text-center text-success">Present</th>
                                        <th class="text-center text-danger">Absent</th>
                                        <th class="text-center text-warning">Late</th>
                                        <th class="text-center text-info">Excused</th>
                                        <th class="text-center">Attendance Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance_summary as $student_id => $summary): ?>
                                        <tr>
                                            <td>
                                                <a href="faculty_attendance_reports.php?report_type=student&student_id=<?php echo $student_id; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">
                                                    <?php echo htmlspecialchars($summary['student_name']); ?>
                                                </a>
                                            </td>
                                            <td class="text-center"><?php echo $summary['present']; ?></td>
                                            <td class="text-center"><?php echo $summary['absent']; ?></td>
                                            <td class="text-center"><?php echo $summary['late']; ?></td>
                                            <td class="text-center"><?php echo $summary['excused']; ?></td>
                                            <td class="text-center">
                                                <?php
                                                    $attendance_rate = $summary['total'] > 0
                                                        ? round((($summary['present'] + $summary['late']) / $summary['total']) * 100, 1)
                                                        : 0;

                                                    $rate_class = 'bg-danger';
                                                    if ($attendance_rate >= 90) {
                                                        $rate_class = 'bg-success';
                                                    } elseif ($attendance_rate >= 75) {
                                                        $rate_class = 'bg-warning';
                                                    } elseif ($attendance_rate >= 60) {
                                                        $rate_class = 'bg-info';
                                                    }
                                                ?>
                                                <div class="progress">
                                                    <div class="progress-bar <?php echo $rate_class; ?>"
                                                         role="progressbar"
                                                         style="width: <?php echo $attendance_rate; ?>%"
                                                         aria-valuenow="<?php echo $attendance_rate; ?>"
                                                         aria-valuemin="0"
                                                         aria-valuemax="100">
                                                        <?php echo $attendance_rate; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Detailed Attendance Records -->
                        <h6 class="mb-3">Daily Attendance Records</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <?php foreach ($students as $student): ?>
                                            <th><?php echo htmlspecialchars($student['full_name']); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_dates as $date): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y (D)', strtotime($date)); ?></td>
                                            <?php foreach ($students as $student): ?>
                                                <td class="text-center">
                                                    <?php if (isset($attendance_data[$date][$student['id']])): ?>
                                                        <?php
                                                            $status = $attendance_data[$date][$student['id']]['status'];
                                                            $status_icon = '';
                                                            $status_class = '';

                                                            switch ($status) {
                                                                case 'present':
                                                                    $status_icon = '<i class="fas fa-check-circle"></i>';
                                                                    $status_class = 'text-success';
                                                                    break;
                                                                case 'absent':
                                                                    $status_icon = '<i class="fas fa-times-circle"></i>';
                                                                    $status_class = 'text-danger';
                                                                    break;
                                                                case 'late':
                                                                    $status_icon = '<i class="fas fa-clock"></i>';
                                                                    $status_class = 'text-warning';
                                                                    break;
                                                                case 'excused':
                                                                    $status_icon = '<i class="fas fa-exclamation-circle"></i>';
                                                                    $status_class = 'text-info';
                                                                    break;
                                                            }

                                                            $remarks = $attendance_data[$date][$student['id']]['remarks'];
                                                            $title = ucfirst($status) . ($remarks ? ": $remarks" : '');
                                                        ?>
                                                        <span class="<?php echo $status_class; ?>" title="<?php echo htmlspecialchars($title); ?>">
                                                            <?php echo $status_icon; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
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
                            <i class="fas fa-info-circle me-2"></i>No attendance records found for the selected date range.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($report_type === 'student' && $student_id > 0 && $student): ?>
            <!-- Student Attendance Report -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-user me-2"></i>
                        Student Attendance Report: <?php echo htmlspecialchars($student['full_name']); ?>
                    </h5>
                    <p class="text-muted mb-0">
                        <?php echo date('F j, Y', strtotime($start_date)); ?> to
                        <?php echo date('F j, Y', strtotime($end_date)); ?>
                    </p>
                </div>
                <div class="card-body">
                    <?php if (count($attendance_summary) > 0): ?>
                        <!-- Attendance Summary by Course -->
                        <h6 class="mb-3">Attendance Summary by Course</h6>
                        <div class="table-responsive mb-4">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Course</th>
                                        <th class="text-center text-success">Present</th>
                                        <th class="text-center text-danger">Absent</th>
                                        <th class="text-center text-warning">Late</th>
                                        <th class="text-center text-info">Excused</th>
                                        <th class="text-center">Attendance Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance_summary as $course_id => $summary): ?>
                                        <tr>
                                            <td>
                                                <a href="faculty_attendance_reports.php?report_type=course&course_id=<?php echo $course_id; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">
                                                    <?php echo htmlspecialchars($summary['course_code'] . ' - ' . $summary['course_name']); ?>
                                                </a>
                                            </td>
                                            <td class="text-center"><?php echo $summary['present']; ?></td>
                                            <td class="text-center"><?php echo $summary['absent']; ?></td>
                                            <td class="text-center"><?php echo $summary['late']; ?></td>
                                            <td class="text-center"><?php echo $summary['excused']; ?></td>
                                            <td class="text-center">
                                                <?php
                                                    $attendance_rate = $summary['total'] > 0
                                                        ? round((($summary['present'] + $summary['late']) / $summary['total']) * 100, 1)
                                                        : 0;

                                                    $rate_class = 'bg-danger';
                                                    if ($attendance_rate >= 90) {
                                                        $rate_class = 'bg-success';
                                                    } elseif ($attendance_rate >= 75) {
                                                        $rate_class = 'bg-warning';
                                                    } elseif ($attendance_rate >= 60) {
                                                        $rate_class = 'bg-info';
                                                    }
                                                ?>
                                                <div class="progress">
                                                    <div class="progress-bar <?php echo $rate_class; ?>"
                                                         role="progressbar"
                                                         style="width: <?php echo $attendance_rate; ?>%"
                                                         aria-valuenow="<?php echo $attendance_rate; ?>"
                                                         aria-valuemin="0"
                                                         aria-valuemax="100">
                                                        <?php echo $attendance_rate; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Detailed Attendance Records -->
                        <h6 class="mb-3">Detailed Attendance Records</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Course</th>
                                        <th>Status</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance_data as $record): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y (D)', strtotime($record['date'])); ?></td>
                                            <td><?php echo htmlspecialchars($record['course_code'] . ' - ' . $record['course_name']); ?></td>
                                            <td>
                                                <?php
                                                    $status = $record['status'];
                                                    $status_class = '';

                                                    switch ($status) {
                                                        case 'present':
                                                            $status_class = 'text-success';
                                                            break;
                                                        case 'absent':
                                                            $status_class = 'text-danger';
                                                            break;
                                                        case 'late':
                                                            $status_class = 'text-warning';
                                                            break;
                                                        case 'excused':
                                                            $status_class = 'text-info';
                                                            break;
                                                    }
                                                ?>
                                                <span class="<?php echo $status_class; ?>">
                                                    <?php echo ucfirst($status); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($record['remarks'] ?: '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>No attendance records found for this student in the selected date range.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Please select the report parameters to generate an attendance report.
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
        function toggleReportFields() {
            const reportType = document.getElementById('report_type').value;
            const courseFields = document.querySelectorAll('.course-field');
            const studentFields = document.querySelectorAll('.student-field');

            if (reportType === 'course') {
                courseFields.forEach(field => field.style.display = '');
                studentFields.forEach(field => field.style.display = 'none');
                document.getElementById('course_id').required = true;
                document.getElementById('student_id').required = false;
            } else {
                courseFields.forEach(field => field.style.display = '');
                studentFields.forEach(field => field.style.display = '');
                document.getElementById('course_id').required = false;
                document.getElementById('student_id').required = true;
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', toggleReportFields);
    </script>
</body>
</html>
