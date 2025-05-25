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

// Get admin information
$admin = null;
$stmt = $conn->prepare("SELECT * FROM admin WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $admin = $result->fetch_assoc();
} else {
    // For testing, create a dummy admin if not found
    $admin = [
        'id' => 1,
        'username' => 'admin',
        'full_name' => $admin_name,
        'email' => 'admin@example.com'
    ];
}

// Get statistics
// Student count
$result = $conn->query("SELECT COUNT(*) as count FROM students");
$student_count = ($result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;

// Faculty count
$result = $conn->query("SELECT COUNT(*) as count FROM faculty");
$faculty_count = ($result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;

// Subject count
$result = $conn->query("SELECT COUNT(*) as count FROM subjects");
$subject_count = ($result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;



// Recent students
$recent_students = [];
$result = $conn->query("SELECT * FROM students ORDER BY id DESC LIMIT 5");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_students[] = $row;
    }
}

// Recent faculty
$recent_faculty = [];
$result = $conn->query("SELECT * FROM faculty ORDER BY id DESC LIMIT 5");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_faculty[] = $row;
    }
}

// Recent subjects
$recent_subjects = [];
$result = $conn->query("SELECT s.*, f.full_name as faculty_name FROM subjects s LEFT JOIN faculty f ON s.faculty_id = f.id ORDER BY s.id DESC LIMIT 5");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_subjects[] = $row;
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
    <title>Admin Dashboard - Student Management System</title>
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
                        <a class="nav-link active" href="admin_dashboard.php">Dashboard</a>
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
            <div class="col-md-12">
                <div class="dashboard-header">
                    <h2>Admin Dashboard</h2>
                    <p class="text-muted">System overview and management</p>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card dashboard-card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Students</h6>
                                <h2 class="mb-0"><?php echo $student_count; ?></h2>
                            </div>
                            <i class="fas fa-user-graduate fa-3x opacity-50"></i>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0">
                        <a href="admin_students.php" class="text-white">Manage students <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card dashboard-card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Faculty</h6>
                                <h2 class="mb-0"><?php echo $faculty_count; ?></h2>
                            </div>
                            <i class="fas fa-chalkboard-teacher fa-3x opacity-50"></i>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0">
                        <a href="admin_faculty.php" class="text-white">Manage faculty <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card dashboard-card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Subjects</h6>
                                <h2 class="mb-0"><?php echo $subject_count; ?></h2>
                            </div>
                            <i class="fas fa-book fa-3x opacity-50"></i>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0">
                        <a href="admin_subjects.php" class="text-white">Manage subjects <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>


        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-tasks me-2"></i>Quick Actions</h5>
                        <hr>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="admin_students.php?action=add" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-user-plus me-2"></i>Add New Student
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="admin_faculty.php?action=add" class="btn btn-outline-success w-100">
                                    <i class="fas fa-user-plus me-2"></i>Add New Faculty
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="admin_subjects.php?action=add" class="btn btn-outline-info w-100">
                                    <i class="fas fa-plus me-2"></i>Add New Subject
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="admin_grades.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-graduation-cap me-2"></i>Manage Grades
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="admin_reports.php" class="btn btn-outline-warning w-100">
                                    <i class="fas fa-chart-bar me-2"></i>Generate Reports
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="admin_departments.php" class="btn btn-outline-dark w-100">
                                    <i class="fas fa-building me-2"></i>Manage Departments
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="admin_schedule.php" class="btn btn-outline-purple w-100">
                                    <i class="fas fa-calendar-alt me-2"></i>Manage Schedules
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="admin_classrooms.php" class="btn btn-outline-success w-100">
                                    <i class="fas fa-school me-2"></i>Manage Classrooms
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="admin_discussions.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-comments me-2"></i>Monitor Discussions
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="admin_calendar.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-calendar-alt me-2"></i>Academic Calendar
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="admin_lab_subjects.php" class="btn btn-outline-warning w-100">
                                    <i class="fas fa-flask me-2"></i>Lab Subjects
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="admin_settings.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-cog me-2"></i>System Settings
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Content -->
        <div class="row">
            <!-- Recent Students -->
            <div class="col-md-4 mb-4">
                <div class="card dashboard-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-user-graduate me-2"></i>Recent Students</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($recent_students) > 0): ?>
                            <div class="list-group">
                                <?php foreach ($recent_students as $student): ?>
                                    <a href="admin_students.php?action=edit&id=<?php echo $student['id']; ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($student['full_name']); ?></h6>
                                            <small>ID: <?php echo $student['id']; ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo htmlspecialchars($student['email']); ?></p>
                                        <small><?php echo htmlspecialchars($student['course']); ?>, Year <?php echo $student['year']; ?></small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">No students found</div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <a href="admin_students.php" class="btn btn-sm btn-primary">View All Students</a>
                    </div>
                </div>
            </div>

            <!-- Recent Faculty -->
            <div class="col-md-4 mb-4">
                <div class="card dashboard-card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-chalkboard-teacher me-2"></i>Recent Faculty</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($recent_faculty) > 0): ?>
                            <div class="list-group">
                                <?php foreach ($recent_faculty as $faculty): ?>
                                    <a href="admin_faculty.php?action=edit&id=<?php echo $faculty['id']; ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($faculty['full_name']); ?></h6>
                                            <small>ID: <?php echo $faculty['id']; ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo htmlspecialchars($faculty['email']); ?></p>
                                        <small><?php echo htmlspecialchars($faculty['department']); ?></small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">No faculty found</div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <a href="admin_faculty.php" class="btn btn-sm btn-success">View All Faculty</a>
                    </div>
                </div>
            </div>

            <!-- Recent Subjects -->
            <div class="col-md-4 mb-4">
                <div class="card dashboard-card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-book me-2"></i>Recent Subjects</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($recent_subjects) > 0): ?>
                            <div class="list-group">
                                <?php foreach ($recent_subjects as $subject): ?>
                                    <a href="admin_subjects.php?action=edit&id=<?php echo $subject['id']; ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($subject['subject_code']); ?></h6>
                                            <small><?php echo $subject['credits']; ?> credits</small>
                                        </div>
                                        <p class="mb-1"><?php echo htmlspecialchars($subject['subject_name']); ?></p>
                                        <small>Instructor: <?php echo htmlspecialchars($subject['faculty_name'] ?: 'Not Assigned'); ?></small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">No subjects found</div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <a href="admin_subjects.php" class="btn btn-sm btn-info">View All Subjects</a>
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
