<?php
// Start session
session_start();

// For testing purposes, we're not enforcing authentication
// In a real application, you would check if the user is logged in
$faculty_id = isset($_SESSION['faculty_id']) ? $_SESSION['faculty_id'] : 1;
$faculty_name = isset($_SESSION['faculty_name']) ? $_SESSION['faculty_name'] : "Test Faculty";

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
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

// Get courses taught by this faculty
$courses = [];
$stmt = $conn->prepare("SELECT * FROM courses WHERE faculty_id = ? ORDER BY course_code");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
}

// Get all departments for dropdown
$departments = [];
$result = $conn->query("SELECT DISTINCT department FROM faculty ORDER BY department");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row['department'];
    }
}

// Handle course creation
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        // Validate inputs
        $course_code = trim($_POST['course_code']);
        $course_name = trim($_POST['course_name']);
        $description = trim($_POST['description']);
        $credits = (int)$_POST['credits'];
        $department = trim($_POST['department']);
        
        if (empty($course_code) || empty($course_name) || empty($department) || $credits <= 0) {
            $message = "Please fill all required fields";
            $message_type = "danger";
        } else {
            // Insert new course
            $stmt = $conn->prepare("INSERT INTO courses (course_code, course_name, description, credits, faculty_id, department) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssiss", $course_code, $course_name, $description, $credits, $faculty_id, $department);
            
            if ($stmt->execute()) {
                $message = "Course created successfully";
                $message_type = "success";
                
                // Refresh courses list
                $stmt = $conn->prepare("SELECT * FROM courses WHERE faculty_id = ? ORDER BY course_code");
                $stmt->bind_param("i", $faculty_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $courses = [];
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $courses[] = $row;
                    }
                }
            } else {
                $message = "Error creating course: " . $conn->error;
                $message_type = "danger";
            }
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['course_id'])) {
        $course_id = (int)$_POST['course_id'];
        
        // Delete course
        $stmt = $conn->prepare("DELETE FROM courses WHERE id = ? AND faculty_id = ?");
        $stmt->bind_param("ii", $course_id, $faculty_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $message = "Course deleted successfully";
            $message_type = "success";
            
            // Refresh courses list
            $stmt = $conn->prepare("SELECT * FROM courses WHERE faculty_id = ? ORDER BY course_code");
            $stmt->bind_param("i", $faculty_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $courses = [];
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $courses[] = $row;
                }
            }
        } else {
            $message = "Error deleting course";
            $message_type = "danger";
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

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses - Student Management System</title>
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
                        <a class="nav-link active" href="faculty_courses.php">Manage Courses</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        Welcome, <?php echo htmlspecialchars($faculty['full_name']); ?>
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
                <h2><i class="fas fa-book me-2"></i>Manage Courses</h2>
                <p class="text-muted">Create and manage your courses</p>
            </div>
            <div class="col-md-4 text-end">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                    <i class="fas fa-plus me-2"></i>Add New Course
                </button>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <?php if (count($courses) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Course Name</th>
                                    <th>Department</th>
                                    <th>Credits</th>
                                    <th>Students</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courses as $course): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                        <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                        <td><?php echo htmlspecialchars($course['department']); ?></td>
                                        <td><?php echo htmlspecialchars($course['credits']); ?></td>
                                        <td>
                                            <a href="course_students.php?course_id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                View Students
                                            </a>
                                        </td>
                                        <td>
                                            <a href="edit_course.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    onclick="confirmDelete(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['course_name']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>You haven't created any courses yet. Click the "Add New Course" button to get started.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Course Modal -->
    <div class="modal fade" id="addCourseModal" tabindex="-1" aria-labelledby="addCourseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCourseModalLabel">Add New Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="mb-3">
                            <label for="course_code" class="form-label">Course Code*</label>
                            <input type="text" class="form-control" id="course_code" name="course_code" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="course_name" class="form-label">Course Name*</label>
                            <input type="text" class="form-control" id="course_name" name="course_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="credits" class="form-label">Credits*</label>
                            <input type="number" class="form-control" id="credits" name="credits" min="1" max="6" value="3" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="department" class="form-label">Department*</label>
                            <select class="form-select" id="department" name="department" required>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo ($dept === $faculty['department']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Form -->
    <form id="deleteForm" method="post" action="" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" id="delete_course_id" name="course_id" value="">
    </form>

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
    
    <script>
        function confirmDelete(courseId, courseName) {
            if (confirm(`Are you sure you want to delete the course "${courseName}"? This action cannot be undone.`)) {
                document.getElementById('delete_course_id').value = courseId;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>
