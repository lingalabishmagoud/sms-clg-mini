<?php
// Start session
session_start();

// For testing purposes, we're not enforcing authentication
// In a real application, you would check if the user is logged in
$student_id = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : 1;

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get student information
$student = null;
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
        'username' => 'student1',
        'full_name' => 'Test Student',
        'email' => 'student@example.com',
        'student_id' => 'S12345',
        'program' => 'Computer Science',
        'batch' => '2023'
    ];
}

// Get enrolled courses - students are automatically enrolled based on department
$enrolled_courses = [];
$stmt = $conn->prepare("SELECT c.*, f.full_name as faculty_name, e.enrollment_date, e.status
                       FROM enrollments e
                       JOIN courses c ON e.course_id = c.id
                       LEFT JOIN faculty f ON c.faculty_id = f.id
                       WHERE e.student_id = ?
                       ORDER BY c.semester, c.course_code");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $enrolled_courses[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $action == 'enrolled' ? 'My Courses' : 'Course Catalog'; ?> - Student Portal</title>
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
                        <a class="nav-link" href="student_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_profile.php">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="student_courses.php">My Courses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_grades.php">My Grades</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_files.php">Files</a>
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
            <div class="col-md-12">
                <h2><i class="fas fa-book-reader me-2"></i>My Courses</h2>
                <p class="text-muted">Courses automatically assigned based on your department</p>
            </div>
        </div>

        <!-- My Courses -->
        <div class="card">
            <div class="card-body">
                <?php if (count($enrolled_courses) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Course Name</th>
                                <th>Credits</th>
                                <th>Instructor</th>
                                <th>Semester</th>
                                <th>Enrollment Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enrolled_courses as $course): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                <td><?php echo $course['credits']; ?></td>
                                <td><?php echo htmlspecialchars($course['faculty_name']); ?></td>
                                <td><?php echo htmlspecialchars($course['semester']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($course['enrollment_date'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $course['status'] == 'active' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($course['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="course_details.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-info" title="View Details">
                                        <i class="fas fa-info-circle"></i>
                                    </a>
                                    <a href="student_grades.php" class="btn btn-sm btn-success" title="View Grades">
                                        <i class="fas fa-chart-line"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>You are not enrolled in any courses yet. Courses will be automatically assigned based on your department.
                </div>
                <?php endif; ?>
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
