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

// Get subjects the student is enrolled in
$subjects_query = "
    SELECT
        s.*,
        f.full_name as faculty_name,
        f.phone as faculty_phone,
        COUNT(DISTINCT sch.id) as classes_per_week,
        sse.status as enrollment_status
    FROM subjects s
    INNER JOIN student_subject_enrollment sse ON s.id = sse.subject_id
    LEFT JOIN faculty f ON s.faculty_id = f.id
    LEFT JOIN schedules sch ON s.id = sch.subject_id AND sch.section = ?
    WHERE sse.student_id = ? AND sse.status = 'active'
    GROUP BY s.id
    ORDER BY s.subject_name
";

$stmt = $conn->prepare($subjects_query);
$stmt->bind_param("si", $student['section'], $student_id);
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
    <title>My Subjects - Student Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .subject-card {
            transition: transform 0.2s;
            border-left: 4px solid #007bff;
        }
        .subject-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .subject-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .faculty-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
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
                        <a class="nav-link active" href="student_subjects.php">My Subjects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="schedule.php">Schedule</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_materials.php">Study Materials</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_attendance.php">My Attendance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="subject_forums.php?user_type=student">Forums</a>
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
                <h2><i class="fas fa-book me-2"></i>My Subjects</h2>
                <p class="text-muted">Subjects for your current semester</p>
            </div>
        </div>

        <!-- Student Information -->
        <div class="card mb-4 subject-header">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <h5><i class="fas fa-user-graduate me-2"></i>Student Information</h5>
                        <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($student['full_name']); ?></p>
                        <p class="mb-1"><strong>Roll Number:</strong> <?php echo htmlspecialchars($student['roll_number']); ?></p>
                        <p class="mb-1"><strong>Department:</strong> <?php echo htmlspecialchars($student['department']); ?></p>
                        <p class="mb-0"><strong>Year:</strong> <?php echo htmlspecialchars($student['year']); ?> | <strong>Semester:</strong> <?php echo htmlspecialchars($student['semester']); ?></p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="bg-white text-dark p-3 rounded">
                            <h3 class="mb-1 text-primary"><?php echo count($subjects); ?></h3>
                            <small>Total Subjects</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Subjects List -->
        <?php if (!empty($subjects)): ?>
            <div class="row">
                <?php foreach ($subjects as $subject): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card subject-card h-100">
                            <div class="card-header bg-primary text-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="fas fa-book me-2"></i>
                                        <?php echo htmlspecialchars($subject['abbreviation']); ?>
                                    </h5>
                                    <span class="badge bg-light text-dark"><?php echo $subject['credits']; ?> Credits</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <h6 class="card-title"><?php echo htmlspecialchars($subject['subject_name']); ?></h6>

                                <?php if ($subject['faculty_name']): ?>
                                    <div class="faculty-info mb-3">
                                        <h6 class="mb-2"><i class="fas fa-user-tie me-1"></i>Faculty</h6>
                                        <p class="mb-1"><strong><?php echo htmlspecialchars($subject['faculty_name']); ?></strong></p>
                                        <?php if ($subject['faculty_phone']): ?>
                                            <p class="mb-0"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($subject['faculty_phone']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="row text-center mb-3">
                                    <div class="col-6">
                                        <div class="border-end">
                                            <h5 class="text-success"><?php echo $subject['classes_per_week']; ?></h5>
                                            <small class="text-muted">Classes/Week</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <h5 class="text-info"><?php echo $subject['credits']; ?></h5>
                                        <small class="text-muted">Credits</small>
                                    </div>
                                </div>

                                <div class="d-grid gap-2">
                                    <a href="student_materials.php" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-book-open me-1"></i>Study Materials
                                    </a>
                                    <a href="subject_forums.php?subject_id=<?php echo $subject['id']; ?>&user_type=student" class="btn btn-outline-info btn-sm">
                                        <i class="fas fa-comments me-1"></i>Subject Forum
                                    </a>
                                    <a href="subject_attendance.php?subject_id=<?php echo $subject['id']; ?>" class="btn btn-outline-warning btn-sm">
                                        <i class="fas fa-clipboard-check me-1"></i>Attendance
                                    </a>
                                </div>
                            </div>
                            <div class="card-footer text-muted">
                                <small>
                                    <i class="fas fa-building me-1"></i>Department: <?php echo htmlspecialchars($subject['department']); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Subject Summary -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Subject Summary</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 text-center">
                            <h4 class="text-primary"><?php echo count($subjects); ?></h4>
                            <p class="text-muted">Total Subjects</p>
                        </div>
                        <div class="col-md-3 text-center">
                            <h4 class="text-success"><?php echo array_sum(array_column($subjects, 'credits')); ?></h4>
                            <p class="text-muted">Total Credits</p>
                        </div>
                        <div class="col-md-3 text-center">
                            <h4 class="text-info"><?php echo array_sum(array_column($subjects, 'classes_per_week')); ?></h4>
                            <p class="text-muted">Classes per Week</p>
                        </div>
                        <div class="col-md-3 text-center">
                            <h4 class="text-warning"><?php echo count(array_filter($subjects, function($s) { return !empty($s['faculty_name']); })); ?></h4>
                            <p class="text-muted">Assigned Faculty</p>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="alert alert-info">
                <h5><i class="fas fa-info-circle me-2"></i>No Subjects Found</h5>
                <p class="mb-0">No subjects are currently assigned to your department. Please contact the administrator.</p>
            </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-tasks me-2"></i>Quick Actions</h5>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="schedule.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-calendar-week me-2"></i>View Schedule
                                </a>
                            </div>

                            <div class="col-md-3 mb-3">
                                <a href="student_attendance.php" class="btn btn-outline-warning w-100">
                                    <i class="fas fa-clipboard-check me-2"></i>My Attendance
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="student_materials.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-book-open me-2"></i>Study Materials
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
