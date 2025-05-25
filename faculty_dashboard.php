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

// Check if faculty table exists
$result = $conn->query("SHOW TABLES LIKE 'faculty'");
if ($result->num_rows == 0) {
    // Create faculty table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS faculty (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        department VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Insert a test faculty member
    $conn->query("INSERT INTO faculty (full_name, email, password, department)
                 VALUES ('Test Faculty', 'faculty@example.com', 'password', 'Computer Science')");

    $faculty_id = $conn->insert_id;
}

// Get faculty information
$faculty = null;
$stmt = $conn->prepare("SELECT * FROM faculty WHERE id = ?");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $faculty = $result->fetch_assoc();
} else {
    // For testing, create a dummy faculty if not found
    $faculty = [
        'id' => 1,
        'full_name' => $faculty_name,
        'email' => 'faculty@example.com',
        'department' => 'Computer Science'
    ];
}

// Get student count
$result = $conn->query("SELECT COUNT(*) as count FROM students");
$student_count = ($result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;

// Get subject count for this faculty
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM subjects WHERE faculty_id = ?");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();
$subject_count = ($result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;

// Debug: Add temporary debug info (remove this later)
$debug_info = "Faculty ID: $faculty_id, Subject Count: $subject_count";

// Get recent students
$recent_students = [];
$result = $conn->query("SELECT * FROM students ORDER BY id DESC LIMIT 5");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_students[] = $row;
    }
}

// Get recent subjects
$recent_subjects = [];
$stmt = $conn->prepare("SELECT * FROM subjects WHERE faculty_id = ? ORDER BY id DESC LIMIT 3");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_subjects[] = $row;
    }
}

// Get notifications count (unread)
$notification_count = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE target_type IN ('all', 'faculty') AND is_read = 0");
if ($result->num_rows > 0) {
    $notification_count = $result->fetch_assoc()['count'];
}

// Handle logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.html");
    exit();
}

$stmt->close();
// Don't close connection yet - we need it for the HTML section
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard - Student Management System</title>
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
                        <a class="nav-link active" href="faculty_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_students.php">Manage Students</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_subjects.php">My Subjects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_schedule.php">My Schedule</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_materials.php">Study Materials</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="attendance_tracking.php">Attendance</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="subject_forums.php?user_type=faculty">Subject Forums</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="classroom_discussions.php?user_type=faculty">Classroom Discussions</a>
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
    <div class="container dashboard-container py-4">
        <div class="row">
            <div class="col-md-12">
                <div class="dashboard-header">
                    <h2>Faculty Dashboard</h2>
                    <p class="text-muted">Welcome to your faculty portal</p>
                    <!-- Temporary debug info -->
                    <div class="alert alert-info">
                        <small>Debug: <?php echo $debug_info; ?></small>
                    </div>
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
                                <h6 class="card-title">Total Students</h6>
                                <h2 class="mb-0"><?php echo $student_count; ?></h2>
                            </div>
                            <i class="fas fa-users fa-3x opacity-50"></i>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0">
                        <a href="faculty_students.php" class="text-white">View all students <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card dashboard-card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">My Subjects</h6>
                                <h2 class="mb-0"><?php echo $subject_count; ?></h2>
                            </div>
                            <i class="fas fa-book fa-3x opacity-50"></i>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0">
                        <a href="faculty_subjects.php" class="text-white">Manage subjects <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card dashboard-card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Notifications</h6>
                                <h2 class="mb-0"><?php echo $notification_count; ?></h2>
                            </div>
                            <i class="fas fa-bell fa-3x opacity-50"></i>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0">
                        <a href="faculty_notifications.php" class="text-white">View notifications <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card dashboard-card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Department</h6>
                                <h2 class="mb-0"><?php echo htmlspecialchars($faculty['department']); ?></h2>
                            </div>
                            <i class="fas fa-university fa-3x opacity-50"></i>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0">
                        <a href="#" class="text-white">Department details <i class="fas fa-arrow-right ms-1"></i></a>
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
                                <a href="faculty_subjects.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-book me-2"></i>My Subjects
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="faculty_schedule.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-calendar-week me-2"></i>My Schedule
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="faculty_materials.php" class="btn btn-outline-warning w-100">
                                    <i class="fas fa-upload me-2"></i>Study Materials
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="attendance_tracking.php" class="btn btn-outline-success w-100">
                                    <i class="fas fa-clipboard-check me-2"></i>Mark Attendance
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="faculty_attendance_reports.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-chart-bar me-2"></i>Attendance Reports
                                </a>
                            </div>

                            <div class="col-md-3 mb-3">
                                <a href="subject_forums.php?user_type=faculty" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-comments me-2"></i>Subject Forums
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="faculty_notifications.php" class="btn btn-outline-warning w-100">
                                    <i class="fas fa-bullhorn me-2"></i>Create Announcement
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



        <!-- Recent Content -->
        <div class="row">
            <!-- Recent Students -->
            <div class="col-md-6 mb-4">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-user-graduate me-2"></i>Recent Students</h5>
                        <hr>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Course</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($recent_students) > 0): ?>
                                        <?php foreach ($recent_students as $student): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                                <td><?php echo htmlspecialchars($student['course']); ?></td>
                                                <td>
                                                    <a href="edit.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No students found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end mt-3">
                            <a href="faculty_students.php" class="btn btn-outline-primary">View All Students</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Subjects -->
            <div class="col-md-6 mb-4">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-book me-2"></i>My Subjects</h5>
                        <hr>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Subject Name</th>
                                        <th>Credits</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($recent_subjects) > 0): ?>
                                        <?php foreach ($recent_subjects as $subject): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($subject['abbreviation']); ?></td>
                                                <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                                <td><?php echo htmlspecialchars($subject['credits']); ?></td>
                                                <td>
                                                    <a href="subject_students.php?subject_id=<?php echo $subject['id']; ?>" class="btn btn-sm btn-primary">Students</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No subjects assigned</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end mt-3">
                            <a href="faculty_subjects.php" class="btn btn-outline-primary">View All Subjects</a>
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

<?php $conn->close(); ?>
