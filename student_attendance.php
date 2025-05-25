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
        'section' => 'CS-A',
        'year' => 2
    ];
}

// Initialize variables
$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get subjects the student is enrolled in
$subjects = [];
$stmt = $conn->prepare("
    SELECT s.*, sse.status as enrollment_status, f.full_name as faculty_name
    FROM subjects s
    JOIN student_subject_enrollment sse ON s.id = sse.subject_id
    LEFT JOIN faculty f ON s.faculty_id = f.id
    WHERE sse.student_id = ? AND sse.status = 'active'
    ORDER BY s.abbreviation
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}
$stmt->close();

// Get attendance data
$attendance_data = [];
$attendance_summary = [];

if ($subject_id > 0) {
    // Get attendance for specific subject
    $stmt = $conn->prepare("
        SELECT a.*, s.abbreviation, s.subject_name
        FROM attendance a
        JOIN subjects s ON a.subject_id = s.id
        WHERE a.student_id = ? AND a.subject_id = ? AND a.date BETWEEN ? AND ?
        ORDER BY a.date DESC
    ");
    $stmt->bind_param("iiss", $student_id, $subject_id, $start_date, $end_date);
} else {
    // Get attendance for all subjects
    $stmt = $conn->prepare("
        SELECT a.*, s.abbreviation, s.subject_name
        FROM attendance a
        JOIN subjects s ON a.subject_id = s.id
        WHERE a.student_id = ? AND a.date BETWEEN ? AND ?
        ORDER BY a.date DESC, s.abbreviation
    ");
    $stmt->bind_param("iss", $student_id, $start_date, $end_date);
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $attendance_data[] = $row;

    // Build summary data by subject
    $subject_id_key = $row['subject_id'];

    if (!isset($attendance_summary[$subject_id_key])) {
        $attendance_summary[$subject_id_key] = [
            'subject_name' => $row['subject_name'],
            'abbreviation' => $row['abbreviation'],
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'excused' => 0,
            'total' => 0
        ];
    }

    $attendance_summary[$subject_id_key][$row['status']]++;
    $attendance_summary[$subject_id_key]['total']++;
}

$stmt->close();

// Calculate overall attendance percentage
$total_present = 0;
$total_classes = 0;
foreach ($attendance_summary as $summary) {
    $total_present += $summary['present'];
    $total_classes += $summary['total'];
}
$overall_percentage = ($total_classes > 0) ? round(($total_present / $total_classes) * 100, 1) : 0;

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
    <title>My Attendance - Student Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .attendance-card {
            border-left: 4px solid;
            transition: all 0.3s ease;
        }
        .attendance-excellent { border-left-color: #28a745; }
        .attendance-good { border-left-color: #17a2b8; }
        .attendance-average { border-left-color: #ffc107; }
        .attendance-poor { border-left-color: #dc3545; }
        
        .status-present { color: #28a745; }
        .status-absent { color: #dc3545; }
        .status-late { color: #ffc107; }
        .status-excused { color: #17a2b8; }
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
                        <a class="nav-link active" href="student_attendance.php">My Attendance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_schedule.php">Schedule</a>
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
            <div class="col-md-8">
                <h2><i class="fas fa-calendar-check me-2"></i>My Attendance</h2>
                <p class="text-muted">Track your attendance across all subjects</p>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h4 class="mb-0"><?php echo $overall_percentage; ?>%</h4>
                        <small>Overall Attendance</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" action="student_attendance.php" class="row g-3">
                    <div class="col-md-4">
                        <label for="subject_id" class="form-label">Filter by Subject</label>
                        <select name="subject_id" id="subject_id" class="form-select">
                            <option value="">All Subjects</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo ($subject_id == $s['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['abbreviation'] . ' - ' . $s['subject_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">From Date</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">To Date</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo $end_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-1"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Attendance Summary by Subject -->
        <?php if (count($attendance_summary) > 0): ?>
            <div class="row mb-4">
                <?php foreach ($attendance_summary as $summary): ?>
                    <?php
                        $percentage = ($summary['total'] > 0) ? round(($summary['present'] / $summary['total']) * 100, 1) : 0;
                        $card_class = '';
                        if ($percentage >= 90) $card_class = 'attendance-excellent';
                        elseif ($percentage >= 80) $card_class = 'attendance-good';
                        elseif ($percentage >= 70) $card_class = 'attendance-average';
                        else $card_class = 'attendance-poor';
                    ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card attendance-card <?php echo $card_class; ?>">
                            <div class="card-body">
                                <h6 class="card-title"><?php echo htmlspecialchars($summary['abbreviation']); ?></h6>
                                <p class="text-muted small mb-2"><?php echo htmlspecialchars($summary['subject_name']); ?></p>
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="fw-bold text-primary" style="font-size: 1.5rem;"><?php echo $percentage; ?>%</div>
                                        <small class="text-muted">Attendance</small>
                                    </div>
                                    <div class="col-6">
                                        <div class="fw-bold" style="font-size: 1.5rem;"><?php echo $summary['present']; ?>/<?php echo $summary['total']; ?></div>
                                        <small class="text-muted">Present/Total</small>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <small class="text-success">Present: <?php echo $summary['present']; ?></small> |
                                    <small class="text-danger">Absent: <?php echo $summary['absent']; ?></small>
                                    <?php if ($summary['late'] > 0): ?>
                                        | <small class="text-warning">Late: <?php echo $summary['late']; ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Detailed Attendance Records -->
        <?php if (count($attendance_data) > 0): ?>
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-list-alt me-2"></i>Detailed Attendance Records
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendance_data as $record): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y (D)', strtotime($record['date'])); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($record['abbreviation']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($record['subject_name']); ?></small>
                                        </td>
                                        <td>
                                            <?php
                                                $status = $record['status'];
                                                $status_class = 'status-' . $status;
                                                $status_icon = '';
                                                switch ($status) {
                                                    case 'present':
                                                        $status_icon = 'fa-check-circle';
                                                        break;
                                                    case 'absent':
                                                        $status_icon = 'fa-times-circle';
                                                        break;
                                                    case 'late':
                                                        $status_icon = 'fa-clock';
                                                        break;
                                                    case 'excused':
                                                        $status_icon = 'fa-info-circle';
                                                        break;
                                                }
                                            ?>
                                            <span class="<?php echo $status_class; ?>">
                                                <i class="fas <?php echo $status_icon; ?> me-1"></i>
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($record['remarks'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>No attendance records found for the selected criteria.
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>Student Management System</h5>
                    <p>Track your academic progress effectively.</p>
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
