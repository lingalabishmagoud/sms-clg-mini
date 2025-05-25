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
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : (isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0);
$date = isset($_GET['date']) ? $_GET['date'] : (isset($_POST['date']) ? $_POST['date'] : date('Y-m-d'));
$message = '';
$message_type = '';

// Get course details if course_id is set
$course = null;
if ($course_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM courses WHERE id = ? AND faculty_id = ?");
    $stmt->bind_param("ii", $course_id, $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $course = $result->fetch_assoc();
    $stmt->close();

    if (!$course) {
        $message = "Course not found or you don't have permission to access it.";
        $message_type = "danger";
        $course_id = 0;
    }
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

// Handle form submission for marking attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance']) && $course_id > 0) {
    // Begin transaction
    $conn->begin_transaction();

    try {
        // Delete existing attendance records for this date and course
        $stmt = $conn->prepare("DELETE FROM attendance WHERE course_id = ? AND date = ?");
        $stmt->bind_param("is", $course_id, $date);
        $stmt->execute();
        $stmt->close();

        // Insert new attendance records
        $stmt = $conn->prepare("INSERT INTO attendance (course_id, student_id, date, status, remarks, marked_by) VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($_POST['attendance'] as $student_id => $status) {
            $remarks = isset($_POST['remarks'][$student_id]) ? $_POST['remarks'][$student_id] : '';
            $stmt->bind_param("iisssi", $course_id, $student_id, $date, $status, $remarks, $faculty_id);
            $stmt->execute();
        }

        $stmt->close();

        // Commit transaction
        $conn->commit();

        $message = "Attendance marked successfully for " . date('F j, Y', strtotime($date));
        $message_type = "success";
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $message = "Error marking attendance: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Get existing attendance records for this date and course
$attendance_records = [];
if ($course_id > 0) {
    $stmt = $conn->prepare("
        SELECT a.*
        FROM attendance a
        WHERE a.course_id = ? AND a.date = ?
    ");
    $stmt->bind_param("is", $course_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $attendance_records[$row['student_id']] = $row;
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
    <title>Attendance Tracking - Student Management System</title>
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
                <h2><i class="fas fa-clipboard-check me-2"></i>Attendance Tracking</h2>
                <p class="text-muted">Mark and manage student attendance for your courses</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="faculty_attendance_reports.php" class="btn btn-outline-primary">
                    <i class="fas fa-chart-bar me-2"></i>View Attendance Reports
                </a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Course and Date Selection Form -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" action="attendance_tracking.php" class="row g-3">
                    <div class="col-md-5">
                        <label for="course_id" class="form-label">Select Course</label>
                        <select name="course_id" id="course_id" class="form-select" required>
                            <option value="">-- Select Course --</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ($course_id == $c['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label for="date" class="form-label">Select Date</label>
                        <input type="date" name="date" id="date" class="form-control" value="<?php echo $date; ?>" required>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>View
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($course_id > 0 && $course): ?>
            <!-- Attendance Marking Form -->
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>
                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?> |
                        <?php echo date('F j, Y', strtotime($date)); ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (count($students) > 0): ?>
                        <form method="post" action="attendance_tracking.php">
                            <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                            <input type="hidden" name="date" value="<?php echo $date; ?>">

                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Student Name</th>
                                            <th>Status</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $count = 1; ?>
                                        <?php foreach ($students as $student): ?>
                                            <?php
                                                $current_status = isset($attendance_records[$student['id']])
                                                    ? $attendance_records[$student['id']]['status']
                                                    : 'present';
                                                $current_remarks = isset($attendance_records[$student['id']])
                                                    ? $attendance_records[$student['id']]['remarks']
                                                    : '';
                                            ?>
                                            <tr>
                                                <td><?php echo $count++; ?></td>
                                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                                <td>
                                                    <select name="attendance[<?php echo $student['id']; ?>]" class="form-select form-select-sm">
                                                        <option value="present" <?php echo ($current_status == 'present') ? 'selected' : ''; ?>>Present</option>
                                                        <option value="absent" <?php echo ($current_status == 'absent') ? 'selected' : ''; ?>>Absent</option>
                                                        <option value="late" <?php echo ($current_status == 'late') ? 'selected' : ''; ?>>Late</option>
                                                        <option value="excused" <?php echo ($current_status == 'excused') ? 'selected' : ''; ?>>Excused</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="text" name="remarks[<?php echo $student['id']; ?>]"
                                                           class="form-control form-control-sm"
                                                           placeholder="Optional remarks"
                                                           value="<?php echo htmlspecialchars($current_remarks); ?>">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="text-end mt-3">
                                <button type="submit" name="mark_attendance" class="btn btn-success">
                                    <i class="fas fa-save me-2"></i>Save Attendance
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>No students are enrolled in this course.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($course_id > 0): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>Course not found or you don't have permission to access it.
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Please select a course and date to mark attendance.
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
