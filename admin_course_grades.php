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

// Check if course ID is provided
if (!isset($_GET['course_id']) || empty($_GET['course_id'])) {
    header("Location: admin_grades.php");
    exit();
}

$course_id = (int)$_GET['course_id'];
$course = null;

// Get course information
$stmt = $conn->prepare("
    SELECT c.*, f.full_name as faculty_name, f.email as faculty_email
    FROM courses c
    LEFT JOIN faculty f ON c.faculty_id = f.id
    WHERE c.id = ?
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $course = $result->fetch_assoc();
} else {
    // Course not found
    header("Location: admin_grades.php");
    exit();
}

// Get students enrolled in this course with their grades
$enrolled_students = [];
$stmt = $conn->prepare("
    SELECT s.*, e.status, e.enrollment_date 
    FROM students s
    JOIN enrollments e ON s.id = e.student_id
    WHERE e.course_id = ? AND e.status != 'dropped'
    ORDER BY s.full_name
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $enrolled_students[] = $row;
    }
}

// Get grades for each student
$student_grades = [];
foreach ($enrolled_students as $student) {
    $stmt = $conn->prepare("
        SELECT * FROM grades 
        WHERE student_id = ? AND course_id = ?
        ORDER BY graded_date DESC
    ");
    $stmt->bind_param("ii", $student['id'], $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $grades = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $grades[] = $row;
        }
    }
    
    $student_grades[$student['id']] = $grades;
}

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
    <title>Course Grades - Admin Panel</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-danger">
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
                        <a class="nav-link" href="admin_courses.php">Courses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="admin_grades.php">Grades</a>
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
                <h2><i class="fas fa-graduation-cap me-2"></i>Grades for <?php echo htmlspecialchars($course['course_name']); ?></h2>
                <p class="text-muted">
                    Course Code: <?php echo htmlspecialchars($course['course_code']); ?> | 
                    Credits: <?php echo htmlspecialchars($course['credits']); ?> |
                    Department: <?php echo htmlspecialchars($course['department']); ?><br>
                    Faculty: <?php echo htmlspecialchars($course['faculty_name'] ?? 'Not Assigned'); ?>
                    <?php if ($course['faculty_email']): ?>
                        (<?php echo htmlspecialchars($course['faculty_email']); ?>)
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <a href="admin_grades.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Grades
                </a>
            </div>
        </div>

        <?php if (count($enrolled_students) > 0): ?>
            <div class="accordion" id="studentsAccordion">
                <?php foreach ($enrolled_students as $index => $student): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading<?php echo $student['id']; ?>">
                            <button class="accordion-button <?php echo ($index !== 0) ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" 
                                    data-bs-target="#collapse<?php echo $student['id']; ?>" 
                                    aria-expanded="<?php echo ($index === 0) ? 'true' : 'false'; ?>" 
                                    aria-controls="collapse<?php echo $student['id']; ?>">
                                <div class="d-flex justify-content-between w-100 me-3">
                                    <span>
                                        <?php echo htmlspecialchars($student['full_name']); ?>
                                        <small class="text-muted">(<?php echo htmlspecialchars($student['email']); ?>)</small>
                                    </span>
                                    <span class="text-muted">
                                        <?php 
                                        $grades = $student_grades[$student['id']];
                                        $total_points = 0;
                                        $max_points = 0;
                                        
                                        foreach ($grades as $grade) {
                                            $total_points += $grade['grade_value'];
                                            $max_points += $grade['max_grade'];
                                        }
                                        
                                        if ($max_points > 0) {
                                            $percentage = round(($total_points / $max_points) * 100, 1);
                                            echo "Overall: $percentage%";
                                        } else {
                                            echo "No grades yet";
                                        }
                                        ?>
                                    </span>
                                </div>
                            </button>
                        </h2>
                        <div id="collapse<?php echo $student['id']; ?>" class="accordion-collapse collapse <?php echo ($index === 0) ? 'show' : ''; ?>" 
                             aria-labelledby="heading<?php echo $student['id']; ?>" data-bs-parent="#studentsAccordion">
                            <div class="accordion-body">
                                <?php if (count($student_grades[$student['id']]) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Assignment</th>
                                                    <th>Grade</th>
                                                    <th>Percentage</th>
                                                    <th>Date</th>
                                                    <th>Comments</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($student_grades[$student['id']] as $grade): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($grade['assignment_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($grade['grade_value']) . ' / ' . htmlspecialchars($grade['max_grade']); ?></td>
                                                        <td>
                                                            <?php 
                                                            $percentage = round(($grade['grade_value'] / $grade['max_grade']) * 100, 1);
                                                            $badge_class = 'bg-secondary';
                                                            if ($percentage >= 90) $badge_class = 'bg-success';
                                                            elseif ($percentage >= 80) $badge_class = 'bg-primary';
                                                            elseif ($percentage >= 70) $badge_class = 'bg-warning';
                                                            elseif ($percentage >= 60) $badge_class = 'bg-danger';
                                                            ?>
                                                            <span class="badge <?php echo $badge_class; ?>"><?php echo $percentage; ?>%</span>
                                                        </td>
                                                        <td><?php echo date('M d, Y', strtotime($grade['graded_date'])); ?></td>
                                                        <td><?php echo htmlspecialchars($grade['comments'] ?? ''); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>No grades recorded for this student yet.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>No students are currently enrolled in this course.
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
