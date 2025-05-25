<?php
// Start session
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
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

$action = isset($_GET['action']) ? $_GET['action'] : 'overview';
$message = '';
$message_type = 'success';

// Get admin information
$admin = null;
$stmt = $conn->prepare("SELECT * FROM admin WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $admin = $result->fetch_assoc();
} else {
    // Admin not found, redirect to login
    header("Location: admin_login.php");
    exit();
}

// Get all courses with faculty information
$courses = [];
$stmt = $conn->prepare("
    SELECT c.*, f.full_name as faculty_name,
           COUNT(e.id) as enrolled_count
    FROM courses c
    LEFT JOIN faculty f ON c.faculty_id = f.id
    LEFT JOIN enrollments e ON c.id = e.course_id AND e.status != 'dropped'
    GROUP BY c.id
    ORDER BY c.course_code
");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
}

// Get subject-based grade statistics
$grade_stats = [];
$stmt = $conn->prepare("
    SELECT
        s.id as subject_id,
        s.subject_code,
        s.subject_name,
        s.credits,
        f.full_name as faculty_name,
        COUNT(sg.id) as total_grades,
        AVG(sg.marks_obtained / sg.max_marks * 100) as avg_percentage,
        MIN(sg.marks_obtained / sg.max_marks * 100) as min_percentage,
        MAX(sg.marks_obtained / sg.max_marks * 100) as max_percentage,
        COUNT(DISTINCT sg.student_id) as students_graded
    FROM subjects s
    LEFT JOIN subject_grades sg ON s.id = sg.subject_id
    LEFT JOIN faculty f ON s.faculty_id = f.id
    GROUP BY s.id
    ORDER BY s.subject_code
");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $grade_stats[] = $row;
    }
}

// Get overall statistics
$total_students = 0;
$total_subjects = 0;
$total_grades = 0;
$avg_sgpa = 0;

$result = $conn->query("SELECT COUNT(*) as count FROM students WHERE year = 3 AND semester = '2nd'");
$total_students = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM subjects");
$total_subjects = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM subject_grades");
$total_grades = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT AVG(sgpa) as avg_sgpa FROM semester_results WHERE sgpa IS NOT NULL");
$avg_sgpa_result = $result->fetch_assoc();
$avg_sgpa = $avg_sgpa_result['avg_sgpa'] ? round($avg_sgpa_result['avg_sgpa'], 2) : 0;

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
    <title>Admin Grades - Student Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
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
                        <a class="nav-link active" href="admin_grades.php">Grades</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_departments.php">Departments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_classrooms.php">Classrooms</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_schedule.php">Schedules</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_discussions.php">Discussions</a>
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
                        Welcome, <?php echo htmlspecialchars($admin['full_name']); ?>
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
                <h2><i class="fas fa-chart-line me-2"></i>Subject-Based Grade Management</h2>
                <p class="text-muted">Monitor and manage student grades across all subjects with SGPA/CGPA calculation</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="subject_grade_management.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Manage Subject Grades
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?php echo $total_students; ?></h4>
                                <p class="mb-0">Total Students</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?php echo $total_subjects; ?></h4>
                                <p class="mb-0">Total Subjects</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-book fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?php echo $total_grades; ?></h4>
                                <p class="mb-0">Total Grades</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-clipboard-list fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?php echo $avg_sgpa; ?></h4>
                                <p class="mb-0">Average SGPA</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-chart-line fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grade Statistics -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Grade Statistics by Subject</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($grade_stats) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Subject Code</th>
                                            <th>Subject Name</th>
                                            <th>Faculty</th>
                                            <th>Credits</th>
                                            <th>Students Graded</th>
                                            <th>Total Assessments</th>
                                            <th>Average %</th>
                                            <th>Min %</th>
                                            <th>Max %</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($grade_stats as $stat): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($stat['subject_code']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($stat['subject_name']); ?></td>
                                                <td><?php echo htmlspecialchars($stat['faculty_name'] ?: 'Not Assigned'); ?></td>
                                                <td><span class="badge bg-info"><?php echo $stat['credits']; ?></span></td>
                                                <td><span class="badge bg-primary"><?php echo $stat['students_graded']; ?></span></td>
                                                <td><?php echo $stat['total_grades']; ?></td>
                                                <td>
                                                    <?php
                                                    if ($stat['avg_percentage'] !== null) {
                                                        echo number_format($stat['avg_percentage'], 1) . '%';
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    if ($stat['min_percentage'] !== null) {
                                                        echo number_format($stat['min_percentage'], 1) . '%';
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    if ($stat['max_percentage'] !== null) {
                                                        echo number_format($stat['max_percentage'], 1) . '%';
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <a href="subject_grade_details.php?subject_id=<?php echo $stat['subject_id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-eye me-1"></i>View Details
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>No grade data available yet. Subjects need to be assigned to faculty and grades need to be entered.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-tools me-2"></i>Grade Management Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="subject_grade_management.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-plus me-2"></i>Add Subject Grades
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="calculate_sgpa_cgpa.php" class="btn btn-outline-success w-100">
                                    <i class="fas fa-calculator me-2"></i>Calculate SGPA/CGPA
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="student_grade_reports.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-file-alt me-2"></i>Grade Reports
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="admin_subjects.php" class="btn btn-outline-warning w-100">
                                    <i class="fas fa-book me-2"></i>Manage Subjects
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
