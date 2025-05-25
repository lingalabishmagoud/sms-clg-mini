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

// Handle add classroom
if ($action == 'add' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $classroom_name = $_POST['classroom_name'];
    $year = $_POST['year'];
    $semester = $_POST['semester'];
    $department = $_POST['department'];
    $class_incharge_id = $_POST['class_incharge_id'];
    $room_number = $_POST['room_number'];
    $capacity = $_POST['capacity'];

    $stmt = $conn->prepare("INSERT INTO classrooms (classroom_name, year, semester, department, class_incharge_id, room_number, capacity) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sissssi", $classroom_name, $year, $semester, $department, $class_incharge_id, $room_number, $capacity);

    if ($stmt->execute()) {
        $message = "Classroom added successfully!";
        $action = 'list';
    } else {
        $message = "Error: " . $stmt->error;
        $message_type = 'danger';
    }
    $stmt->close();
}

// Handle delete classroom
if ($action == 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];

    // Check if classroom has students
    $stmt = $conn->prepare("SELECT COUNT(*) as student_count FROM students WHERE section = (SELECT classroom_name FROM classrooms WHERE id = ?)");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student_count = $result->fetch_assoc()['student_count'];
    $stmt->close();

    if ($student_count > 0) {
        $message = "Cannot delete classroom. It has $student_count student(s) assigned.";
        $message_type = "warning";
    } else {
        $stmt = $conn->prepare("DELETE FROM classrooms WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $message = "Classroom deleted successfully!";
        } else {
            $message = "Error: " . $stmt->error;
            $message_type = 'danger';
        }
        $stmt->close();
    }
    $action = 'list';
}

// Get classrooms for listing
$classrooms = [];
if ($action == 'list') {
    $result = $conn->query("
        SELECT c.*,
               f.full_name as incharge_name,
               (SELECT COUNT(*) FROM students WHERE section = c.classroom_name) as student_count
        FROM classrooms c
        LEFT JOIN faculty f ON c.class_incharge_id = f.id
        ORDER BY c.year DESC, c.semester DESC, c.classroom_name
    ");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $classrooms[] = $row;
        }
    }
}

// Get faculty list for dropdown
$faculty_list = [];
$faculty_result = $conn->query("SELECT id, full_name FROM faculty ORDER BY full_name");
if ($faculty_result && $faculty_result->num_rows > 0) {
    while ($row = $faculty_result->fetch_assoc()) {
        $faculty_list[] = $row;
    }
}

// Get single classroom for editing
$classroom = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM classrooms WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $classroom = $result->fetch_assoc();
    } else {
        $message = "Classroom not found.";
        $message_type = 'danger';
        $action = 'list';
    }
    $stmt->close();
}

// Handle update classroom
if ($action == 'edit' && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = $_POST['id'];
    $classroom_name = $_POST['classroom_name'];
    $year = $_POST['year'];
    $semester = $_POST['semester'];
    $department = $_POST['department'];
    $class_incharge_id = $_POST['class_incharge_id'];
    $room_number = $_POST['room_number'];
    $capacity = $_POST['capacity'];

    $stmt = $conn->prepare("UPDATE classrooms SET classroom_name=?, year=?, semester=?, department=?, class_incharge_id=?, room_number=?, capacity=? WHERE id=?");
    $stmt->bind_param("sissssis", $classroom_name, $year, $semester, $department, $class_incharge_id, $room_number, $capacity, $id);

    if ($stmt->execute()) {
        $message = "Classroom updated successfully!";
        $action = 'list';
    } else {
        $message = "Error: " . $stmt->error;
        $message_type = 'danger';
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classrooms - Admin Dashboard</title>
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
                        <a class="nav-link" href="admin_subjects.php">Subjects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_grades.php">Grades</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_departments.php">Departments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="admin_classrooms.php">Classrooms</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_schedule.php">Schedules</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_discussions.php">Discussions</a>
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
                <h2><?php echo $action == 'list' ? 'Manage Classrooms' : ($action == 'add' ? 'Add New Classroom' : 'Edit Classroom'); ?></h2>
            </div>
            <div class="col-md-4 text-end">
                <?php if ($action == 'list'): ?>
                <a href="?action=add" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Add New Classroom
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
        <!-- Classroom List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-school me-2"></i>Classrooms</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Classroom</th>
                                <th>Year & Semester</th>
                                <th>Department</th>
                                <th>Class Incharge</th>
                                <th>Room</th>
                                <th>Students</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($classrooms) > 0): ?>
                                <?php foreach ($classrooms as $class): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($class['classroom_name']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $class['year']; ?> Year</span>
                                        <span class="badge bg-secondary"><?php echo $class['semester']; ?> Sem</span>
                                    </td>
                                    <td><?php echo htmlspecialchars($class['department']); ?></td>
                                    <td><?php echo htmlspecialchars($class['incharge_name'] ?: 'Not Assigned'); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($class['room_number']); ?>
                                        <br><small class="text-muted">Capacity: <?php echo $class['capacity']; ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $class['student_count']; ?> students</span>
                                    </td>
                                    <td>
                                        <a href="?action=edit&id=<?php echo $class['id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="classroom_discussions.php?classroom_id=<?php echo $class['id']; ?>" class="btn btn-sm btn-success" title="View Discussions">
                                            <i class="fas fa-comments"></i>
                                        </a>
                                        <a href="?action=delete&id=<?php echo $class['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this classroom?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No classrooms found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($action == 'add' || ($action == 'edit' && $classroom !== null)): ?>
        <!-- Add/Edit Classroom Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?php echo $action == 'add' ? 'Add New Classroom' : 'Edit Classroom'; ?></h5>
            </div>
            <div class="card-body">
                <form method="post" action="?action=<?php echo $action; ?><?php echo $action == 'edit' ? '&id=' . $classroom['id'] : ''; ?>">
                    <?php if ($action == 'edit'): ?>
                    <input type="hidden" name="id" value="<?php echo $classroom['id']; ?>">
                    <?php endif; ?>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="classroom_name" class="form-label">Classroom Name *</label>
                            <input type="text" class="form-control" id="classroom_name" name="classroom_name" required
                                placeholder="e.g., CS-A, CS-B, IT-A" value="<?php echo $action == 'edit' ? htmlspecialchars($classroom['classroom_name']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="department" class="form-label">Department *</label>
                            <select class="form-select" id="department" name="department" required>
                                <option value="">Select Department</option>
                                <option value="Cyber Security" <?php echo ($action == 'edit' && $classroom['department'] == 'Cyber Security') ? 'selected' : ''; ?>>Cyber Security</option>
                                <option value="Computer Science" <?php echo ($action == 'edit' && $classroom['department'] == 'Computer Science') ? 'selected' : ''; ?>>Computer Science</option>
                                <option value="Data Science" <?php echo ($action == 'edit' && $classroom['department'] == 'Data Science') ? 'selected' : ''; ?>>Data Science</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="year" class="form-label">Year *</label>
                            <select class="form-select" id="year" name="year" required>
                                <option value="">Select Year</option>
                                <option value="1" <?php echo ($action == 'edit' && $classroom['year'] == 1) ? 'selected' : ''; ?>>1st Year</option>
                                <option value="2" <?php echo ($action == 'edit' && $classroom['year'] == 2) ? 'selected' : ''; ?>>2nd Year</option>
                                <option value="3" <?php echo ($action == 'edit' && $classroom['year'] == 3) ? 'selected' : ''; ?>>3rd Year</option>
                                <option value="4" <?php echo ($action == 'edit' && $classroom['year'] == 4) ? 'selected' : ''; ?>>4th Year</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="semester" class="form-label">Semester *</label>
                            <select class="form-select" id="semester" name="semester" required>
                                <option value="">Select Semester</option>
                                <option value="1st" <?php echo ($action == 'edit' && $classroom['semester'] == '1st') ? 'selected' : ''; ?>>1st Semester</option>
                                <option value="2nd" <?php echo ($action == 'edit' && $classroom['semester'] == '2nd') ? 'selected' : ''; ?>>2nd Semester</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="class_incharge_id" class="form-label">Class Incharge</label>
                            <select class="form-select" id="class_incharge_id" name="class_incharge_id">
                                <option value="">Select Faculty</option>
                                <?php foreach ($faculty_list as $faculty): ?>
                                <option value="<?php echo $faculty['id']; ?>"
                                    <?php echo ($action == 'edit' && $classroom['class_incharge_id'] == $faculty['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($faculty['full_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="room_number" class="form-label">Room Number *</label>
                            <input type="text" class="form-control" id="room_number" name="room_number" required
                                placeholder="e.g., 303, 307" value="<?php echo $action == 'edit' ? htmlspecialchars($classroom['room_number']) : ''; ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="capacity" class="form-label">Capacity *</label>
                            <input type="number" class="form-control" id="capacity" name="capacity" required min="1" max="100"
                                placeholder="e.g., 60" value="<?php echo $action == 'edit' ? $classroom['capacity'] : ''; ?>">
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i><?php echo $action == 'add' ? 'Add Classroom' : 'Update Classroom'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
