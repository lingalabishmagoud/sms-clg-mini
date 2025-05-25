<?php
// Start session
session_start();

// Check if faculty is logged in (for testing, allow access)
if (!isset($_SESSION['faculty_id'])) {
    $_SESSION['faculty_id'] = 1;
    $_SESSION['user_type'] = 'faculty';
    $_SESSION['faculty_name'] = 'Test Faculty';
}

$faculty_id = $_SESSION['faculty_id'];
$faculty_name = $_SESSION['faculty_name'] ?? 'Test Faculty';

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get faculty information
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
        'department' => 'Cyber Security',
        'position' => 'Assistant Professor',
        'phone' => '+91-9876543210'
    ];
}
$stmt->close();

// Get faculty's subjects
$subjects_query = "
    SELECT
        s.*,
        COUNT(DISTINCT sch.id) as schedule_count
    FROM subjects s
    LEFT JOIN schedules sch ON s.id = sch.subject_id
    WHERE s.faculty_id = ?
    GROUP BY s.id
    ORDER BY s.subject_name
";

$stmt = $conn->prepare($subjects_query);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();

$subjects = [];
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}
$stmt->close();

// Get students enrolled in faculty's subjects (both CS-A and CS-B sections)
$students_by_section = [];
$total_students = 0;

if (!empty($subjects)) {
    foreach ($subjects as $subject) {
        // Get students for each section
        foreach (['CS-A', 'CS-B'] as $section) {
            $students_query = "
                SELECT DISTINCT s.id, s.full_name, s.email, s.roll_number, s.department, s.year, s.semester, s.section
                FROM students s
                JOIN student_subject_enrollment sse ON s.id = sse.student_id
                WHERE sse.subject_id = ? AND s.section = ? AND sse.status = 'active'
                ORDER BY s.roll_number
            ";

            $stmt = $conn->prepare($students_query);
            $stmt->bind_param("is", $subject['id'], $section);
            $stmt->execute();
            $result = $stmt->get_result();

            if (!isset($students_by_section[$section])) {
                $students_by_section[$section] = [];
            }

            while ($row = $result->fetch_assoc()) {
                // Avoid duplicates
                $found = false;
                foreach ($students_by_section[$section] as $existing_student) {
                    if ($existing_student['id'] == $row['id']) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $students_by_section[$section][] = $row;
                    $total_students++;
                }
            }
            $stmt->close();
        }
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.html");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Subjects - Faculty Portal</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .subject-card {
            transition: transform 0.2s;
        }
        .subject-card:hover {
            transform: translateY(-5px);
        }
        .subject-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .stats-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
    </style>
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
                        <a class="nav-link" href="faculty_materials.php">Study Materials</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_grades.php">Grades</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_attendance.php">Attendance</a>
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
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <h2><i class="fas fa-book me-2"></i>My Subjects</h2>
                <p class="text-muted">Subjects assigned to you for teaching</p>
            </div>
        </div>

        <!-- Faculty Information -->
        <div class="card mb-4 subject-header">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <h5><i class="fas fa-user-tie me-2"></i>Faculty Information</h5>
                        <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($faculty['full_name']); ?></p>
                        <p class="mb-1"><strong>Department:</strong> <?php echo htmlspecialchars($faculty['department']); ?></p>
                        <p class="mb-1"><strong>Position:</strong> <?php echo htmlspecialchars($faculty['position']); ?></p>
                        <?php if (!empty($faculty['phone'])): ?>
                            <p class="mb-0"><strong>Phone:</strong> <?php echo htmlspecialchars($faculty['phone']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="stats-card p-3 rounded">
                            <h3 class="mb-1"><?php echo count($subjects); ?></h3>
                            <small>Subjects Assigned</small>
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
                                <h5 class="mb-0">
                                    <i class="fas fa-book me-2"></i>
                                    <?php echo htmlspecialchars($subject['abbreviation']); ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <h6 class="card-title"><?php echo htmlspecialchars($subject['subject_name']); ?></h6>
                                <div class="row text-center mb-3">
                                    <div class="col-4">
                                        <div class="border-end">
                                            <h5 class="text-primary"><?php echo $subject['credits']; ?></h5>
                                            <small class="text-muted">Credits</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="border-end">
                                            <h5 class="text-success"><?php echo $subject['schedule_count']; ?></h5>
                                            <small class="text-muted">Classes/Week</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <h5 class="text-info"><?php echo $total_students; ?></h5>
                                        <small class="text-muted">Students</small>
                                    </div>
                                </div>

                                <div class="d-grid gap-2">
                                    <a href="subject_students.php?subject_id=<?php echo $subject['id']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-users me-1"></i>View Students
                                    </a>
                                    <a href="subject_forums.php?subject_id=<?php echo $subject['id']; ?>&user_type=faculty" class="btn btn-outline-info btn-sm">
                                        <i class="fas fa-comments me-1"></i>Subject Forum
                                    </a>
                                    <a href="subject_attendance.php?subject_id=<?php echo $subject['id']; ?>" class="btn btn-outline-warning btn-sm">
                                        <i class="fas fa-clipboard-check me-1"></i>Mark Attendance
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
        <?php else: ?>
            <div class="alert alert-info">
                <h5><i class="fas fa-info-circle me-2"></i>No Subjects Assigned</h5>
                <p class="mb-0">You don't have any subjects assigned yet. Please contact the administrator to assign subjects to your account.</p>
            </div>
        <?php endif; ?>

        <!-- Students Overview by Section -->
        <?php foreach (['CS-A', 'CS-B'] as $section): ?>
            <?php if (isset($students_by_section[$section]) && !empty($students_by_section[$section])): ?>
                <div class="card mt-4">
                    <div class="card-header bg-<?php echo ($section == 'CS-A') ? 'primary' : 'success'; ?> text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-users me-2"></i>Students in Your Classes (<?php echo $section; ?> Section)
                            <span class="badge bg-light text-dark ms-2"><?php echo count($students_by_section[$section]); ?> Students</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Roll Number</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Year</th>
                                        <th>Semester</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students_by_section[$section] as $student): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($student['roll_number']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                                            <td><?php echo htmlspecialchars($student['year']); ?></td>
                                            <td><?php echo htmlspecialchars($student['semester']); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="student_profile.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                    <a href="faculty_attendance.php?subject_id=<?php echo $subjects[0]['id'] ?? ''; ?>&section=<?php echo $section; ?>" class="btn btn-sm btn-outline-success">
                                                        <i class="fas fa-clipboard-check"></i> Attendance
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if (empty($students_by_section) || (empty($students_by_section['CS-A']) && empty($students_by_section['CS-B']))): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-users me-2"></i>Students in Your Classes</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No students found enrolled in your subjects. Students may not be enrolled yet or there might be no active enrollments.
                    </div>
                </div>
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
                                <a href="faculty_schedule.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-calendar-week me-2"></i>View Schedule
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="faculty_materials.php" class="btn btn-outline-warning w-100">
                                    <i class="fas fa-upload me-2"></i>Study Materials
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="faculty_attendance.php" class="btn btn-outline-success w-100">
                                    <i class="fas fa-clipboard-check me-2"></i>Mark Attendance
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="faculty_students.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-users me-2"></i>All Students
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

<?php $conn->close(); ?>
