<?php
// Start session
session_start();

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get course ID from URL
$course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Check if course exists
$course = null;
$stmt = $conn->prepare("SELECT c.*, f.full_name as faculty_name, f.email as faculty_email 
                        FROM courses c 
                        LEFT JOIN faculty f ON c.faculty_id = f.id 
                        WHERE c.id = ?");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $course = $result->fetch_assoc();
} else {
    // Course not found
    header("Location: index.html");
    exit();
}

// Get user type and ID from session
$user_type = '';
$user_id = 0;

if (isset($_SESSION['student_id'])) {
    $user_type = 'student';
    $user_id = $_SESSION['student_id'];
} elseif (isset($_SESSION['faculty_id'])) {
    $user_type = 'faculty';
    $user_id = $_SESSION['faculty_id'];
} elseif (isset($_SESSION['admin_id'])) {
    $user_type = 'admin';
    $user_id = $_SESSION['admin_id'];
}

// Check if student is enrolled in this course
$is_enrolled = false;
if ($user_type == 'student') {
    $stmt = $conn->prepare("SELECT * FROM course_enrollment WHERE student_id = ? AND course_id = ?");
    $stmt->bind_param("ii", $user_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $is_enrolled = ($result->num_rows > 0);
}

// Check if faculty is teaching this course
$is_teaching = false;
if ($user_type == 'faculty') {
    $is_teaching = ($course['faculty_id'] == $user_id);
}

// Get enrolled students
$enrolled_students = [];
$stmt = $conn->prepare("SELECT s.*, ce.enrollment_date, ce.status 
                        FROM students s 
                        JOIN course_enrollment ce ON s.id = ce.student_id 
                        WHERE ce.course_id = ? 
                        ORDER BY s.full_name");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $enrolled_students[] = $row;
    }
}

// Get course materials/files
$course_files = [];
$stmt = $conn->prepare("SELECT * FROM files WHERE course_id = ? ORDER BY upload_date DESC");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $course_files[] = $row;
    }
}

// Get grade distribution
$grade_distribution = [];
$stmt = $conn->prepare("SELECT 
                        CASE 
                            WHEN (grade_value/max_grade*100) >= 90 THEN 'A' 
                            WHEN (grade_value/max_grade*100) >= 80 THEN 'B' 
                            WHEN (grade_value/max_grade*100) >= 70 THEN 'C' 
                            WHEN (grade_value/max_grade*100) >= 60 THEN 'D' 
                            ELSE 'F' 
                        END as letter_grade, 
                        COUNT(*) as count 
                        FROM grades 
                        WHERE course_id = ? 
                        GROUP BY letter_grade 
                        ORDER BY letter_grade");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $grade_distribution[$row['letter_grade']] = $row['count'];
    }
}

// Get assignments
$assignments = [];
$stmt = $conn->prepare("SELECT assignment_name, COUNT(*) as graded_count, AVG(grade_value/max_grade*100) as avg_grade 
                        FROM grades 
                        WHERE course_id = ? 
                        GROUP BY assignment_name 
                        ORDER BY assignment_name");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $assignments[] = $row;
    }
}

// Handle syllabus upload
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_syllabus']) && ($user_type == 'faculty' || $user_type == 'admin')) {
    // File upload directory
    $upload_dir = "uploads/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Check if file was uploaded without errors
    if (isset($_FILES["syllabus_file"]) && $_FILES["syllabus_file"]["error"] == 0) {
        $file_name = basename($_FILES["syllabus_file"]["name"]);
        $file_type = $_FILES["syllabus_file"]["type"];
        $file_size = $_FILES["syllabus_file"]["size"];
        $file_tmp = $_FILES["syllabus_file"]["tmp_name"];
        
        // Generate unique filename
        $new_file_name = "syllabus_" . $course_id . "_" . time() . "_" . $file_name;
        $destination = $upload_dir . $new_file_name;
        
        // Check file size (limit to 5MB)
        if ($file_size <= 5000000) {
            if (move_uploaded_file($file_tmp, $destination)) {
                // Save file info to database
                $title = "Course Syllabus - " . $course['course_code'];
                $description = "Syllabus for " . $course['course_name'];
                
                $stmt = $conn->prepare("INSERT INTO files (file_name, file_path, file_type, file_size, uploaded_by_type, uploaded_by_id, course_id, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssissis", $file_name, $destination, $file_type, $file_size, $user_type, $user_id, $course_id, $description);
                
                if ($stmt->execute()) {
                    $message = "Syllabus uploaded successfully!";
                } else {
                    $message = "Error: " . $stmt->error;
                }
            } else {
                $message = "Error moving uploaded file.";
            }
        } else {
            $message = "File is too large. Maximum size is 5MB.";
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['course_code']); ?> - <?php echo htmlspecialchars($course['course_name']); ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <?php if ($user_type == 'student'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="student_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_courses.php">My Courses</a>
                    </li>
                    <?php elseif ($user_type == 'faculty'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_courses.php">My Courses</a>
                    </li>
                    <?php elseif ($user_type == 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_courses.php">Courses</a>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="index.html">Home</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex">
                    <?php if ($user_type): ?>
                    <a href="<?php echo $user_type; ?>_dashboard.php" class="btn btn-outline-light me-2">Back to Dashboard</a>
                    <a href="logout.php" class="btn btn-light">Logout</a>
                    <?php else: ?>
                    <a href="student_login.php" class="btn btn-outline-light me-2">Student Login</a>
                    <a href="faculty_login.php" class="btn btn-light">Faculty Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-4">
        <?php if (!empty($message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Course Header -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2><?php echo htmlspecialchars($course['course_code']); ?> - <?php echo htmlspecialchars($course['course_name']); ?></h2>
                                <p class="text-muted"><?php echo htmlspecialchars($course['department']); ?> Department | <?php echo $course['credits']; ?> Credits</p>
                            </div>
                            <div>
                                <?php if ($user_type == 'student' && !$is_enrolled): ?>
                                <a href="student_courses.php?action=enroll&course_id=<?php echo $course_id; ?>" class="btn btn-primary">
                                    <i class="fas fa-user-plus me-2"></i>Enroll in Course
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($user_type == 'faculty' && $is_teaching): ?>
                                <a href="faculty_grades.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary">
                                    <i class="fas fa-edit me-2"></i>Manage Grades
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($user_type == 'admin'): ?>
                                <a href="admin_courses.php?action=edit&id=<?php echo $course_id; ?>" class="btn btn-primary">
                                    <i class="fas fa-edit me-2"></i>Edit Course
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Course Information -->
            <div class="col-md-8">
                <!-- Course Description -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Course Description</h5>
                    </div>
                    <div class="card-body">
                        <p><?php echo nl2br(htmlspecialchars($course['description'] ?? 'No description available.')); ?></p>
                        
                        <div class="mt-4">
                            <h6>Instructor Information:</h6>
                            <p>
                                <strong>Name:</strong> <?php echo htmlspecialchars($course['faculty_name'] ?? 'Not assigned'); ?><br>
                                <strong>Email:</strong> <?php echo htmlspecialchars($course['faculty_email'] ?? 'N/A'); ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Course Materials -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-book me-2"></i>Course Materials</h5>
                        <?php if ($user_type == 'faculty' && $is_teaching || $user_type == 'admin'): ?>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadSyllabusModal">
                            <i class="fas fa-upload me-2"></i>Upload Syllabus
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (count($course_files) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>File Name</th>
                                        <th>Description</th>
                                        <th>Size</th>
                                        <th>Uploaded</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($course_files as $file): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($file['file_name']); ?></td>
                                        <td><?php echo htmlspecialchars($file['description'] ?? 'N/A'); ?></td>
                                        <td><?php echo round($file['file_size'] / 1024, 2); ?> KB</td>
                                        <td><?php echo date('M d, Y', strtotime($file['upload_date'])); ?></td>
                                        <td>
                                            <a href="<?php echo $file['file_path']; ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                <i class="fas fa-download me-1"></i>Download
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>No course materials available yet.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Assignments -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Assignments</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($assignments) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Assignment Name</th>
                                        <th>Students Graded</th>
                                        <th>Average Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignments as $assignment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($assignment['assignment_name']); ?></td>
                                        <td><?php echo $assignment['graded_count']; ?></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar" role="progressbar" 
                                                    style="width: <?php echo round($assignment['avg_grade'], 2); ?>%;" 
                                                    aria-valuenow="<?php echo round($assignment['avg_grade'], 2); ?>" 
                                                    aria-valuemin="0" 
                                                    aria-valuemax="100">
                                                    <?php echo round($assignment['avg_grade'], 2); ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>No assignments have been graded yet.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-md-4">
                <!-- Course Stats -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Course Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <div>
                                <h6>Enrolled Students</h6>
                                <h3><?php echo count($enrolled_students); ?></h3>
                            </div>
                            <div>
                                <h6>Assignments</h6>
                                <h3><?php echo count($assignments); ?></h3>
                            </div>
                        </div>
                        
                        <?php if (!empty($grade_distribution)): ?>
                        <h6>Grade Distribution</h6>
                        <canvas id="gradeChart" width="100%" height="200"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Enrolled Students -->
                <?php if ($user_type == 'faculty' && $is_teaching || $user_type == 'admin'): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-users me-2"></i>Enrolled Students</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($enrolled_students) > 0): ?>
                        <div class="list-group">
                            <?php foreach ($enrolled_students as $student): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($student['full_name']); ?></h6>
                                    <small class="text-muted">Year <?php echo $student['year']; ?></small>
                                </div>
                                <p class="mb-1"><?php echo htmlspecialchars($student['email']); ?></p>
                                <small class="text-muted">
                                    Enrolled: <?php echo date('M d, Y', strtotime($student['enrollment_date'])); ?> | 
                                    Status: <span class="badge bg-<?php echo $student['status'] == 'active' ? 'success' : ($student['status'] == 'completed' ? 'primary' : 'warning'); ?>">
                                        <?php echo ucfirst($student['status']); ?>
                                    </span>
                                </small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>No students enrolled yet.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Upload Syllabus Modal -->
    <div class="modal fade" id="uploadSyllabusModal" tabindex="-1" aria-labelledby="uploadSyllabusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title" id="uploadSyllabusModalLabel">Upload Course Syllabus</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="syllabus_file" class="form-label">Select File</label>
                            <input class="form-control" type="file" id="syllabus_file" name="syllabus_file" required>
                            <div class="form-text">Max file size: 5MB. Recommended formats: PDF, DOCX</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="upload_syllabus" class="btn btn-primary">Upload</button>
                    </div>
                </form>
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
    
    <!-- Chart Initialization -->
    <script>
        <?php if (!empty($grade_distribution)): ?>
        // Initialize grade distribution chart
        const ctx = document.getElementById('gradeChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($grade_distribution)); ?>,
                datasets: [{
                    label: 'Number of Students',
                    data: <?php echo json_encode(array_values($grade_distribution)); ?>,
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.7)',  // A - Green
                        'rgba(23, 162, 184, 0.7)', // B - Teal
                        'rgba(255, 193, 7, 0.7)',  // C - Yellow
                        'rgba(255, 153, 0, 0.7)',  // D - Orange
                        'rgba(220, 53, 69, 0.7)'   // F - Red
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(23, 162, 184, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(255, 153, 0, 1)',
                        'rgba(220, 53, 69, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
