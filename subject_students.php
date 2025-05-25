<?php
// Start session
session_start();

// Check if faculty is logged in
if (!isset($_SESSION['faculty_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'faculty') {
    header("Location: faculty_login.php");
    exit();
}

$faculty_id = $_SESSION['faculty_id'];
$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get subject information
$stmt = $conn->prepare("SELECT * FROM subjects WHERE id = ? AND faculty_id = ?");
$stmt->bind_param("ii", $subject_id, $faculty_id);
$stmt->execute();
$result = $stmt->get_result();
$subject = $result->fetch_assoc();
$stmt->close();

if (!$subject) {
    header("Location: faculty_subjects.php");
    exit();
}

// Get all students enrolled in this subject
$students_query = "
    SELECT s.*
    FROM students s
    INNER JOIN student_subject_enrollment sse ON s.id = sse.student_id
    WHERE sse.subject_id = ? AND sse.status = 'active'
    ORDER BY s.roll_number
";

$stmt = $conn->prepare($students_query);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
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
    <title>Students in <?php echo htmlspecialchars($subject['abbreviation']); ?> - Faculty Portal</title>
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
                        <a class="nav-link active" href="faculty_subjects.php">My Subjects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_schedule.php">My Schedule</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_grades.php">Grades</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="attendance_tracking.php">Attendance</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        Welcome, Faculty
                    </span>
                    <a href="?logout=1" class="btn btn-light btn-sm">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="faculty_dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="faculty_subjects.php">My Subjects</a></li>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($subject['abbreviation']); ?> Students</li>
            </ol>
        </nav>

        <div class="row mb-4">
            <div class="col-md-12">
                <h2><i class="fas fa-users me-2"></i>Students in <?php echo htmlspecialchars($subject['abbreviation']); ?></h2>
                <p class="text-muted"><?php echo htmlspecialchars($subject['subject_name']); ?></p>
            </div>
        </div>

        <!-- Subject Information -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-book me-2"></i>Subject Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Subject Code:</strong> <?php echo htmlspecialchars($subject['abbreviation']); ?></p>
                        <p><strong>Subject Name:</strong> <?php echo htmlspecialchars($subject['subject_name']); ?></p>
                        <p><strong>Credits:</strong> <?php echo htmlspecialchars($subject['credits']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Department:</strong> <?php echo htmlspecialchars($subject['department']); ?></p>
                        <p><strong>Total Students:</strong> <?php echo count($students); ?></p>
                        <p><strong>Section:</strong> CS-A</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Students List -->
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Students List</h5>
                    <div>
                        <a href="subject_attendance.php?subject_id=<?php echo $subject_id; ?>" class="btn btn-warning btn-sm">
                            <i class="fas fa-clipboard-check me-1"></i>Mark Attendance
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($students)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Roll Number</th>
                                    <th>Student Name</th>
                                    <th>Email</th>
                                    <th>Year</th>
                                    <th>Semester</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['roll_number']); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                    <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                                                </div>
                                                <?php echo htmlspecialchars($student['full_name']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td><?php echo htmlspecialchars($student['year']); ?></td>
                                        <td><?php echo htmlspecialchars($student['semester']); ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="student_profile.php?id=<?php echo $student['id']; ?>"
                                                   class="btn btn-outline-primary btn-sm" title="View Profile">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="student_attendance.php?student_id=<?php echo $student['id']; ?>&subject_id=<?php echo $subject_id; ?>"
                                                   class="btn btn-outline-warning btn-sm" title="View Attendance">
                                                    <i class="fas fa-clipboard-check"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Statistics -->
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">Class Statistics</h6>
                                    <div class="row text-center">
                                        <div class="col-md-4">
                                            <h4 class="text-primary"><?php echo count($students); ?></h4>
                                            <small class="text-muted">Total Students</small>
                                        </div>
                                        <div class="col-md-4">
                                            <h4 class="text-success">CS-A</h4>
                                            <small class="text-muted">Section</small>
                                        </div>
                                        <div class="col-md-4">
                                            <h4 class="text-info"><?php echo htmlspecialchars($subject['credits']); ?></h4>
                                            <small class="text-muted">Credits</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="alert alert-info">
                        <h5><i class="fas fa-info-circle me-2"></i>No Students Found</h5>
                        <p class="mb-0">No students are currently enrolled in this subject.</p>
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
                                <a href="faculty_subjects.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Subjects
                                </a>
                            </div>

                            <div class="col-md-3 mb-3">
                                <a href="subject_attendance.php?subject_id=<?php echo $subject_id; ?>" class="btn btn-outline-warning w-100">
                                    <i class="fas fa-clipboard-check me-2"></i>Mark Attendance
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="faculty_schedule.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-calendar-week me-2"></i>View Schedule
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

    <style>
        .avatar-sm {
            width: 32px;
            height: 32px;
            font-size: 14px;
        }
    </style>
</body>
</html>
