
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

if ($result->num_rows == 1) {
    $student = $result->fetch_assoc();
} else {
    // For testing, create a dummy student if not found
    $student = [
        'id' => 1,
        'full_name' => $student_name,
        'email' => 'test@example.com',
        'course' => 'Computer Science',
        'year' => 2
    ];
}

// Get subjects the student is enrolled in
$enrolled_subjects = [];
$stmt = $conn->prepare("
    SELECT s.*, sse.status, sse.enrollment_date, f.full_name as faculty_name
    FROM subjects s
    JOIN student_subject_enrollment sse ON s.id = sse.subject_id
    LEFT JOIN faculty f ON s.faculty_id = f.id
    WHERE sse.student_id = ? AND sse.status = 'active'
    ORDER BY s.subject_code
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $enrolled_subjects[] = $row;
    }
}

// Recent grades functionality removed - students will see grades in dedicated grades page

// Get notifications
$notifications = [];
$stmt = $conn->prepare("
    SELECT n.*, f.full_name as faculty_name, s.subject_name, s.abbreviation as subject_code
    FROM notifications n
    LEFT JOIN faculty f ON n.created_by = f.id
    LEFT JOIN subjects s ON n.target_id = s.id AND n.target_type = 'subject'
    WHERE (n.target_type = 'all' OR
          (n.target_type = 'student' AND n.target_id = ?) OR
          (n.target_type = 'subject' AND n.target_id IN (
              SELECT subject_id FROM student_subject_enrollment WHERE student_id = ? AND status = 'active'
          )))
    ORDER BY n.created_at DESC
    LIMIT 5
");
$stmt->bind_param("ii", $student_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
}

// Quiz statistics removed - quiz system has been removed from the project

// Quiz stats variables removed

// Quiz statistics variables removed

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
    <title>Student Dashboard - Student Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
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
                        <a class="nav-link active" href="student_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_profile.php">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_subjects.php">My Subjects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_schedule.php">Schedule</a>
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
    <div class="container dashboard-container py-4">
        <div class="row">
            <div class="col-md-12">
                <div class="dashboard-header">
                    <h2>Student Dashboard</h2>
                    <p class="text-muted">Welcome to your student portal</p>
                </div>
            </div>
        </div>

        <!-- Student Info Card -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-user me-2"></i>Student Information</h5>
                        <hr>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($student['full_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
                        <p><strong>Department:</strong> <?php echo htmlspecialchars($student['department'] ?? $student['course']); ?></p>
                        <p><strong>Year & Semester:</strong> <?php echo htmlspecialchars($student['year']); ?> Year - <?php echo htmlspecialchars($student['semester'] ?? '2nd'); ?> Semester</p>
                        <p><strong>Section:</strong> <?php echo htmlspecialchars($student['section'] ?? 'Not Assigned'); ?></p>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
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
                                        <th>Instructor</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($enrolled_subjects) > 0): ?>
                                        <?php foreach ($enrolled_subjects as $subject): ?>
                                        <tr>
                                            <td><span class="badge bg-primary"><?php echo htmlspecialchars($subject['abbreviation']); ?></span></td>
                                            <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                            <td><?php echo htmlspecialchars($subject['credits']); ?></td>
                                            <td><?php echo htmlspecialchars($subject['faculty_name'] ?: 'Not Assigned'); ?></td>
                                            <td>
                                                <a href="subject_forums.php?subject_id=<?php echo $subject['id']; ?>&user_type=student" class="btn btn-sm btn-outline-info" title="Subject Forum">
                                                    <i class="fas fa-comments"></i>
                                                </a>
                                                <a href="student_materials.php" class="btn btn-sm btn-outline-success" title="Study Materials">
                                                    <i class="fas fa-book-open"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">You are not enrolled in any subjects yet.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (count($enrolled_subjects) > 0): ?>
                        <div class="text-end mt-3">
                            <a href="student_subjects.php" class="btn btn-outline-primary">View All Subjects</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notifications -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-bell me-2"></i>Notifications</h5>
                        <hr>
                        <?php if (count($notifications) > 0): ?>
                            <div class="list-group">
                                <?php foreach ($notifications as $notification): ?>
                                    <?php
                                    $redirect_url = $notification['redirect_url'] ?? 'student_notifications.php';
                                    $is_clickable = !empty($notification['redirect_url']);
                                    ?>
                                    <div class="list-group-item list-group-item-action <?php echo $is_clickable ? 'notification-clickable' : ''; ?>"
                                         <?php if ($is_clickable): ?>onclick="window.location.href='<?php echo htmlspecialchars($redirect_url); ?>'" style="cursor: pointer;"<?php endif; ?>>
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">
                                                <?php if (!$notification['is_read']): ?>
                                                    <span class="badge bg-danger me-2">New</span>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($notification['title']); ?>
                                                <?php if ($is_clickable): ?>
                                                    <i class="fas fa-external-link-alt ms-2 text-muted" style="font-size: 0.8em;"></i>
                                                <?php endif; ?>
                                            </h6>
                                            <small class="text-muted"><?php echo date('M d, Y', strtotime($notification['created_at'])); ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <?php if ($is_clickable): ?>
                                            <small class="text-primary"><i class="fas fa-mouse-pointer me-1"></i>Click to view details</small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-end mt-3">
                                <a href="student_notifications.php" class="btn btn-outline-primary">View All Notifications</a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>No notifications at this time.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="row">
            <div class="col-md-12">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-link me-2"></i>Quick Links</h5>
                        <hr>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="student_profile.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-user-edit me-2"></i>Edit Profile
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="student_subjects.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-book me-2"></i>My Subjects
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="student_schedule.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-calendar-week me-2"></i>Class Schedule
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="student_materials.php" class="btn btn-outline-warning w-100">
                                    <i class="fas fa-book-open me-2"></i>Study Materials
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="student_calendar.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-calendar-alt me-2"></i>Academic Calendar
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="student_attendance.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-clipboard-check me-2"></i>My Attendance
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="subject_forums.php?user_type=student" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-comments me-2"></i>Subject Forums
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="classroom_discussions.php?user_type=student" class="btn btn-outline-success w-100">
                                    <i class="fas fa-users me-2"></i>Classroom Discussions
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


