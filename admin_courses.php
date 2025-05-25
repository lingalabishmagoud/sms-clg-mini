<?php
// Start session
session_start();

// For testing purposes, we're not enforcing authentication
// In a real application, you would check if the user is logged in
$admin_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 1;
$admin_name = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : "System Administrator";

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';

// Get faculty list for dropdown
$faculty_list = [];
$result = $conn->query("SELECT id, full_name FROM faculty ORDER BY full_name");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $faculty_list[$row['id']] = $row['full_name'];
    }
}

// Handle add course
if ($action == 'add' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $course_code = $_POST['course_code'];
    $course_name = $_POST['course_name'];
    $description = $_POST['description'];
    $credits = $_POST['credits'];
    $faculty_id = $_POST['faculty_id'];
    $semester = $_POST['semester'];
    $max_students = $_POST['max_students'];
    
    $stmt = $conn->prepare("INSERT INTO courses (course_code, course_name, description, credits, faculty_id, semester, max_students) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssiisi", $course_code, $course_name, $description, $credits, $faculty_id, $semester, $max_students);
    
    if ($stmt->execute()) {
        $message = "Course added successfully!";
        $action = 'list'; // Return to list view
    } else {
        $message = "Error: " . $stmt->error;
    }
}

// Handle delete course
if ($action == 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $conn->prepare("DELETE FROM courses WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $message = "Course deleted successfully!";
    } else {
        $message = "Error: " . $stmt->error;
    }
    $action = 'list'; // Return to list view
}

// Get courses for listing
$courses = [];
if ($action == 'list') {
    $result = $conn->query("SELECT c.*, f.full_name as faculty_name FROM courses c LEFT JOIN faculty f ON c.faculty_id = f.id ORDER BY c.id DESC");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $courses[] = $row;
        }
    }
}

// Get single course for editing
$course = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $course = $result->fetch_assoc();
    }
}

// Handle update course
if ($action == 'edit' && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = $_POST['id'];
    $course_code = $_POST['course_code'];
    $course_name = $_POST['course_name'];
    $description = $_POST['description'];
    $credits = $_POST['credits'];
    $faculty_id = $_POST['faculty_id'];
    $semester = $_POST['semester'];
    $max_students = $_POST['max_students'];
    
    $stmt = $conn->prepare("UPDATE courses SET course_code=?, course_name=?, description=?, credits=?, faculty_id=?, semester=?, max_students=? WHERE id=?");
    $stmt->bind_param("sssiisii", $course_code, $course_name, $description, $credits, $faculty_id, $semester, $max_students, $id);
    
    if ($stmt->execute()) {
        $message = "Course updated successfully!";
        $action = 'list'; // Return to list view
    } else {
        $message = "Error: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses - Admin Dashboard</title>
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
                        <a class="nav-link" href="admin_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_students.php">Students</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_faculty.php">Faculty</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="admin_courses.php">Courses</a>
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
                        Welcome, <?php echo htmlspecialchars($admin_name); ?>
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
                <h2><?php echo $action == 'list' ? 'Manage Courses' : ($action == 'add' ? 'Add New Course' : 'Edit Course'); ?></h2>
            </div>
            <div class="col-md-4 text-end">
                <?php if ($action == 'list'): ?>
                <a href="?action=add" class="btn btn-info">
                    <i class="fas fa-plus me-2"></i>Add New Course
                </a>
                <?php else: ?>
                <a href="?action=list" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to List
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if ($action == 'list'): ?>
        <!-- Course List -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Code</th>
                                <th>Course Name</th>
                                <th>Credits</th>
                                <th>Faculty</th>
                                <th>Semester</th>
                                <th>Max Students</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($courses) > 0): ?>
                                <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td><?php echo $course['id']; ?></td>
                                    <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                    <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                    <td><?php echo $course['credits']; ?></td>
                                    <td><?php echo htmlspecialchars($course['faculty_name']); ?></td>
                                    <td><?php echo htmlspecialchars($course['semester']); ?></td>
                                    <td><?php echo $course['max_students']; ?></td>
                                    <td>
                                        <a href="?action=edit&id=<?php echo $course['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?action=delete&id=<?php echo $course['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this course?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">No courses found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($action == 'add' || $action == 'edit'): ?>
        <!-- Add/Edit Course Form -->
        <div class="card">
            <div class="card-body">
                <form method="post" action="?action=<?php echo $action; ?><?php echo $action == 'edit' ? '&id=' . $course['id'] : ''; ?>">
                    <?php if ($action == 'edit'): ?>
                    <input type="hidden" name="id" value="<?php echo $course['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="course_code" class="form-label">Course Code</label>
                            <input type="text" class="form-control" id="course_code" name="course_code" required
                                value="<?php echo $action == 'edit' ? htmlspecialchars($course['course_code']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="course_name" class="form-label">Course Name</label>
                            <input type="text" class="form-control" id="course_name" name="course_name" required
                                value="<?php echo $action == 'edit' ? htmlspecialchars($course['course_name']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo $action == 'edit' ? htmlspecialchars($course['description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label for="credits" class="form-label">Credits</label>
                            <input type="number" class="form-control" id="credits" name="credits" required min="1" max="6"
                                value="<?php echo $action == 'edit' ? $course['credits'] : '3'; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="faculty_id" class="form-label">Faculty</label>
                            <select class="form-select" id="faculty_id" name="faculty_id" required>
                                <option value="">Select Faculty</option>
                                <?php foreach ($faculty_list as $id => $name): ?>
                                <option value="<?php echo $id; ?>" <?php echo ($action == 'edit' && $course['faculty_id'] == $id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="semester" class="form-label">Semester</label>
                            <input type="text" class="form-control" id="semester" name="semester" required
                                value="<?php echo $action == 'edit' ? htmlspecialchars($course['semester']) : ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="max_students" class="form-label">Max Students</label>
                            <input type="number" class="form-control" id="max_students" name="max_students" required min="1"
                                value="<?php echo $action == 'edit' ? $course['max_students'] : '30'; ?>">
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-save me-2"></i><?php echo $action == 'add' ? 'Add Course' : 'Update Course'; ?>
                        </button>
                    </div>
                </form>
            </div>
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
