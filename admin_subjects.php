<?php
// Start session
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
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

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';
$message_type = 'success';

// Handle add subject
if ($action == 'add' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject_code = $_POST['subject_code'];
    $subject_name = $_POST['subject_name'];
    $abbreviation = $_POST['abbreviation'];
    $faculty_id = $_POST['faculty_id'];
    $department = $_POST['department'];
    $credits = $_POST['credits'];

    $stmt = $conn->prepare("INSERT INTO subjects (subject_code, subject_name, abbreviation, faculty_id, department, credits) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssisi", $subject_code, $subject_name, $abbreviation, $faculty_id, $department, $credits);

    if ($stmt->execute()) {
        $message = "Subject added successfully!";
        $action = 'list'; // Return to list view
    } else {
        $message = "Error: " . $stmt->error;
        $message_type = 'danger';
    }
}

// Handle delete subject
if ($action == 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $conn->prepare("DELETE FROM subjects WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $message = "Subject deleted successfully!";
    } else {
        $message = "Error: " . $stmt->error;
        $message_type = 'danger';
    }
    $action = 'list'; // Return to list view
}

// Get subjects for listing
$subjects = [];
if ($action == 'list') {
    $result = $conn->query("SELECT s.*, f.full_name as faculty_name FROM subjects s LEFT JOIN faculty f ON s.faculty_id = f.id ORDER BY s.id DESC");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
    }
}

// Get single subject for editing
$subject = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM subjects WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $subject = $result->fetch_assoc();
    } else {
        // Subject not found, redirect to list with error message
        $message = "Subject not found.";
        $message_type = 'danger';
        $action = 'list';
    }
}

// Handle update subject
if ($action == 'edit' && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = $_POST['id'];
    $subject_code = $_POST['subject_code'];
    $subject_name = $_POST['subject_name'];
    $abbreviation = $_POST['abbreviation'];
    $faculty_id = $_POST['faculty_id'];
    $department = $_POST['department'];
    $credits = $_POST['credits'];

    $stmt = $conn->prepare("UPDATE subjects SET subject_code=?, subject_name=?, abbreviation=?, faculty_id=?, department=?, credits=? WHERE id=?");
    $stmt->bind_param("sssisii", $subject_code, $subject_name, $abbreviation, $faculty_id, $department, $credits, $id);

    if ($stmt->execute()) {
        $message = "Subject updated successfully!";
        $action = 'list'; // Return to list view
    } else {
        $message = "Error: " . $stmt->error;
        $message_type = 'danger';
    }
}

// Get all faculty for dropdown
$faculty_list = [];
$faculty_result = $conn->query("SELECT id, full_name FROM faculty ORDER BY full_name");
if ($faculty_result && $faculty_result->num_rows > 0) {
    while ($row = $faculty_result->fetch_assoc()) {
        $faculty_list[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subjects - Admin Dashboard</title>
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
                        <a class="nav-link active" href="admin_subjects.php">Subjects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_grades.php">Grades</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_departments.php">Departments</a>
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
                <h2><?php echo $action == 'list' ? 'Manage Subjects' : ($action == 'add' ? 'Add New Subject' : 'Edit Subject'); ?></h2>
            </div>
            <div class="col-md-4 text-end">
                <?php if ($action == 'list'): ?>
                <a href="?action=add" class="btn btn-info">
                    <i class="fas fa-plus me-2"></i>Add New Subject
                </a>
                <?php else: ?>
                <a href="?action=list" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to List
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if ($action == 'list'): ?>
        <!-- Subject List -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Code</th>
                                <th>Subject Name</th>
                                <th>Abbreviation</th>
                                <th>Credits</th>
                                <th>Faculty</th>
                                <th>Department</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($subjects) > 0): ?>
                                <?php foreach ($subjects as $subj): ?>
                                <tr>
                                    <td><?php echo $subj['id']; ?></td>
                                    <td><?php echo htmlspecialchars($subj['subject_code']); ?></td>
                                    <td><?php echo htmlspecialchars($subj['subject_name']); ?></td>
                                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($subj['abbreviation']); ?></span></td>
                                    <td><?php echo $subj['credits']; ?></td>
                                    <td><?php echo htmlspecialchars($subj['faculty_name'] ?? 'Not Assigned'); ?></td>
                                    <td><?php echo htmlspecialchars($subj['department']); ?></td>
                                    <td>
                                        <a href="?action=edit&id=<?php echo $subj['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?action=delete&id=<?php echo $subj['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this subject?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">No subjects found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($action == 'add' || ($action == 'edit' && $subject !== null)): ?>
        <!-- Add/Edit Subject Form -->
        <div class="card">
            <div class="card-body">
                <form method="post" action="?action=<?php echo $action; ?><?php echo $action == 'edit' ? '&id=' . $subject['id'] : ''; ?>">
                    <?php if ($action == 'edit'): ?>
                    <input type="hidden" name="id" value="<?php echo $subject['id']; ?>">
                    <?php endif; ?>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="subject_code" class="form-label">Subject Code</label>
                            <input type="text" class="form-control" id="subject_code" name="subject_code" required
                                value="<?php echo $action == 'edit' ? htmlspecialchars($subject['subject_code']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="abbreviation" class="form-label">Abbreviation</label>
                            <input type="text" class="form-control" id="abbreviation" name="abbreviation" required
                                value="<?php echo $action == 'edit' ? htmlspecialchars($subject['abbreviation']) : ''; ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="subject_name" class="form-label">Subject Name</label>
                            <input type="text" class="form-control" id="subject_name" name="subject_name" required
                                value="<?php echo $action == 'edit' ? htmlspecialchars($subject['subject_name']) : ''; ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="faculty_id" class="form-label">Faculty</label>
                            <select class="form-control" id="faculty_id" name="faculty_id">
                                <option value="">Select Faculty</option>
                                <?php foreach ($faculty_list as $faculty): ?>
                                <option value="<?php echo $faculty['id']; ?>"
                                    <?php echo ($action == 'edit' && $subject['faculty_id'] == $faculty['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($faculty['full_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="department" class="form-label">Department</label>
                            <select class="form-select" id="department" name="department" required>
                                <option value="">Select Department</option>
                                <option value="Cyber Security" <?php echo ($action == 'edit' && $subject['department'] == 'Cyber Security') ? 'selected' : ''; ?>>Cyber Security</option>
                                <option value="Computer Science" <?php echo ($action == 'edit' && $subject['department'] == 'Computer Science') ? 'selected' : ''; ?>>Computer Science</option>
                                <option value="Data Science" <?php echo ($action == 'edit' && $subject['department'] == 'Data Science') ? 'selected' : ''; ?>>Data Science</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="credits" class="form-label">Credits</label>
                            <input type="number" class="form-control" id="credits" name="credits" required min="1" max="6"
                                value="<?php echo $action == 'edit' ? $subject['credits'] : '3'; ?>">
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i><?php echo $action == 'add' ? 'Add Subject' : 'Update Subject'; ?>
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