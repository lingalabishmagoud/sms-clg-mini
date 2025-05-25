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

// Get all departments for dropdown
$departments = [];
$result = $conn->query("SELECT DISTINCT department FROM faculty ORDER BY department");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row['department'];
    }
}

// Check if course ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: faculty_courses.php");
    exit();
}

$course_id = (int)$_GET['id'];
$course = null;

// Get course information
$stmt = $conn->prepare("SELECT * FROM courses WHERE id = ? AND faculty_id = ?");
$stmt->bind_param("ii", $course_id, $faculty_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $course = $result->fetch_assoc();
} else {
    // Course not found or doesn't belong to this faculty
    header("Location: faculty_courses.php");
    exit();
}

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
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
        // Update course
        $stmt = $conn->prepare("UPDATE courses SET course_code = ?, course_name = ?, description = ?, credits = ?, department = ? WHERE id = ? AND faculty_id = ?");
        $stmt->bind_param("sssisii", $course_code, $course_name, $description, $credits, $department, $course_id, $faculty_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $message = "Course updated successfully";
            $message_type = "success";
            
            // Refresh course data
            $stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $course = $result->fetch_assoc();
            }
        } else {
            $message = "Error updating course or no changes made";
            $message_type = "warning";
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
    <title>Edit Course - Student Management System</title>
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
                <h2><i class="fas fa-edit me-2"></i>Edit Course</h2>
                <p class="text-muted">Update course information</p>
            </div>
            <div class="col-md-4 text-end">
                <a href="faculty_courses.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Courses
                </a>
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
                <form method="post" action="">
                    <input type="hidden" name="action" value="update">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="course_code" class="form-label">Course Code*</label>
                            <input type="text" class="form-control" id="course_code" name="course_code" 
                                   value="<?php echo htmlspecialchars($course['course_code']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="course_name" class="form-label">Course Name*</label>
                            <input type="text" class="form-control" id="course_name" name="course_name" 
                                   value="<?php echo htmlspecialchars($course['course_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($course['description']); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="credits" class="form-label">Credits*</label>
                            <input type="number" class="form-control" id="credits" name="credits" min="1" max="6" 
                                   value="<?php echo htmlspecialchars($course['credits']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="department" class="form-label">Department*</label>
                            <select class="form-select" id="department" name="department" required>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>" 
                                            <?php echo ($dept === $course['department']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                        <a href="faculty_courses.php" class="btn btn-secondary ms-2">Cancel</a>
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
</body>
</html>
