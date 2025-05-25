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

// Handle add faculty
if ($action == 'add' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $email = $_POST['email'];
    $full_name = $_POST['full_name'];
    $faculty_id = $_POST['faculty_id'];
    $department = $_POST['department'];
    $position = $_POST['position'];
    $phone = $_POST['phone'];
    $qualification = $_POST['qualification'];
    $experience = $_POST['experience'];
    $specialization = $_POST['specialization'];

    $stmt = $conn->prepare("INSERT INTO faculty (username, password, email, full_name, faculty_id, department, position, phone, qualification, experience, specialization) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssss", $username, $password, $email, $full_name, $faculty_id, $department, $position, $phone, $qualification, $experience, $specialization);

    if ($stmt->execute()) {
        $message = "Faculty member added successfully!";
        $action = 'list'; // Return to list view
    } else {
        $message = "Error: " . $stmt->error;
        $message_type = 'danger';
    }
}

// Handle delete faculty
if ($action == 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $conn->prepare("DELETE FROM faculty WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $message = "Faculty member deleted successfully!";
    } else {
        $message = "Error: " . $stmt->error;
        $message_type = 'danger';
    }
    $action = 'list'; // Return to list view
}

// Handle subject assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_subject'])) {
    $faculty_id = (int)$_POST['faculty_id'];
    $subject_id = (int)$_POST['subject_id'];

    $stmt = $conn->prepare("UPDATE subjects SET faculty_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $faculty_id, $subject_id);

    if ($stmt->execute()) {
        $message = "Subject assigned successfully!";
    } else {
        $message = "Error assigning subject: " . $stmt->error;
        $message_type = 'danger';
    }
}

// Get faculty for listing with subject count
$faculty_members = [];
if ($action == 'list') {
    $result = $conn->query("
        SELECT f.*,
               COUNT(s.id) as subject_count,
               GROUP_CONCAT(s.subject_name SEPARATOR ', ') as subjects
        FROM faculty f
        LEFT JOIN subjects s ON f.id = s.faculty_id
        GROUP BY f.id
        ORDER BY f.department, f.full_name
    ");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $faculty_members[] = $row;
        }
    }
}

// Get all subjects for assignment
$available_subjects = [];
$result = $conn->query("SELECT * FROM subjects ORDER BY subject_name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $available_subjects[] = $row;
    }
}

// Get single faculty for editing
$faculty = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM faculty WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $faculty = $result->fetch_assoc();
    } else {
        // Faculty not found, redirect to list with error message
        $message = "Faculty member not found.";
        $message_type = 'danger';
        $action = 'list';
    }
}

// Handle update faculty
if ($action == 'edit' && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = $_POST['id'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $full_name = $_POST['full_name'];
    $faculty_id = $_POST['faculty_id'];
    $department = $_POST['department'];
    $position = $_POST['position'];
    $phone = $_POST['phone'];
    $qualification = $_POST['qualification'];
    $experience = $_POST['experience'];
    $specialization = $_POST['specialization'];

    // Check if password is being updated
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE faculty SET username=?, password=?, email=?, full_name=?, faculty_id=?, department=?, position=?, phone=?, qualification=?, experience=?, specialization=? WHERE id=?");
        $stmt->bind_param("sssssssssssi", $username, $password, $email, $full_name, $faculty_id, $department, $position, $phone, $qualification, $experience, $specialization, $id);
    } else {
        $stmt = $conn->prepare("UPDATE faculty SET username=?, email=?, full_name=?, faculty_id=?, department=?, position=?, phone=?, qualification=?, experience=?, specialization=? WHERE id=?");
        $stmt->bind_param("ssssssssssi", $username, $email, $full_name, $faculty_id, $department, $position, $phone, $qualification, $experience, $specialization, $id);
    }

    if ($stmt->execute()) {
        $message = "Faculty member updated successfully!";
        $action = 'list'; // Return to list view
    } else {
        $message = "Error: " . $stmt->error;
        $message_type = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Faculty - Admin Dashboard</title>
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
                        <a class="nav-link active" href="admin_faculty.php">Faculty</a>
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
                <h2><?php echo $action == 'list' ? 'Manage Faculty' : ($action == 'add' ? 'Add New Faculty' : 'Edit Faculty'); ?></h2>
            </div>
            <div class="col-md-4 text-end">
                <?php if ($action == 'list'): ?>
                <a href="?action=add" class="btn btn-success">
                    <i class="fas fa-user-plus me-2"></i>Add New Faculty
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
        <!-- Faculty List -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Faculty ID</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Position</th>
                                <th>Phone</th>
                                <th>Subjects</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($faculty_members) > 0): ?>
                                <?php foreach ($faculty_members as $faculty): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($faculty['faculty_id']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($faculty['full_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($faculty['email']); ?></small>
                                    </td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($faculty['department']); ?></span></td>
                                    <td><?php echo htmlspecialchars($faculty['position']); ?></td>
                                    <td><?php echo htmlspecialchars($faculty['phone'] ?: 'N/A'); ?></td>
                                    <td>
                                        <?php if ($faculty['subject_count'] > 0): ?>
                                            <span class="badge bg-info"><?php echo $faculty['subject_count']; ?> subjects</span>
                                            <br><small class="text-muted"><?php echo htmlspecialchars(substr($faculty['subjects'], 0, 50)) . (strlen($faculty['subjects']) > 50 ? '...' : ''); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">No subjects assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?action=edit&id=<?php echo $faculty['id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-success" title="Assign Subject"
                                                onclick="assignSubject(<?php echo $faculty['id']; ?>, '<?php echo htmlspecialchars($faculty['full_name'], ENT_QUOTES); ?>')">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                        <a href="?action=delete&id=<?php echo $faculty['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this faculty member?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No faculty members found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($action == 'add' || ($action == 'edit' && $faculty !== null)): ?>
        <!-- Add/Edit Faculty Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?php echo $action == 'add' ? 'Add New Faculty' : 'Edit Faculty'; ?></h5>
            </div>
            <div class="card-body">
                <form method="post" action="?action=<?php echo $action; ?><?php echo $action == 'edit' ? '&id=' . $faculty['id'] : ''; ?>">
                    <?php if ($action == 'edit'): ?>
                    <input type="hidden" name="id" value="<?php echo $faculty['id']; ?>">
                    <?php endif; ?>

                    <!-- Basic Information -->
                    <h6 class="text-primary mb-3">Basic Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="full_name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required
                                value="<?php echo $action == 'edit' ? htmlspecialchars($faculty['full_name']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required
                                value="<?php echo $action == 'edit' ? htmlspecialchars($faculty['email']) : ''; ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone Number *</label>
                            <input type="tel" class="form-control" id="phone" name="phone" required
                                value="<?php echo $action == 'edit' ? htmlspecialchars($faculty['phone']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="faculty_id" class="form-label">Faculty ID *</label>
                            <input type="text" class="form-control" id="faculty_id" name="faculty_id" required
                                placeholder="e.g., FAC001" value="<?php echo $action == 'edit' ? htmlspecialchars($faculty['faculty_id']) : ''; ?>">
                        </div>
                    </div>

                    <!-- Academic Information -->
                    <h6 class="text-primary mb-3 mt-4">Academic Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="department" class="form-label">Department *</label>
                            <select class="form-select" id="department" name="department" required>
                                <option value="">Select Department</option>
                                <option value="Cyber Security" <?php echo ($action == 'edit' && $faculty['department'] == 'Cyber Security') ? 'selected' : ''; ?>>Cyber Security</option>
                                <option value="Computer Science" <?php echo ($action == 'edit' && $faculty['department'] == 'Computer Science') ? 'selected' : ''; ?>>Computer Science</option>
                                <option value="Data Science" <?php echo ($action == 'edit' && $faculty['department'] == 'Data Science') ? 'selected' : ''; ?>>Data Science</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="position" class="form-label">Position *</label>
                            <select class="form-select" id="position" name="position" required>
                                <option value="">Select Position</option>
                                <option value="Professor" <?php echo ($action == 'edit' && $faculty['position'] == 'Professor') ? 'selected' : ''; ?>>Professor</option>
                                <option value="Associate Professor" <?php echo ($action == 'edit' && $faculty['position'] == 'Associate Professor') ? 'selected' : ''; ?>>Associate Professor</option>
                                <option value="Assistant Professor" <?php echo ($action == 'edit' && $faculty['position'] == 'Assistant Professor') ? 'selected' : ''; ?>>Assistant Professor</option>
                                <option value="Lecturer" <?php echo ($action == 'edit' && $faculty['position'] == 'Lecturer') ? 'selected' : ''; ?>>Lecturer</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="qualification" class="form-label">Qualification</label>
                            <input type="text" class="form-control" id="qualification" name="qualification"
                                placeholder="e.g., Ph.D, M.Tech" value="<?php echo $action == 'edit' ? htmlspecialchars($faculty['qualification']) : ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="experience" class="form-label">Experience (Years)</label>
                            <input type="number" class="form-control" id="experience" name="experience" min="0"
                                value="<?php echo $action == 'edit' ? $faculty['experience'] : ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="specialization" class="form-label">Specialization</label>
                            <input type="text" class="form-control" id="specialization" name="specialization"
                                placeholder="e.g., Machine Learning, Cybersecurity" value="<?php echo $action == 'edit' ? htmlspecialchars($faculty['specialization']) : ''; ?>">
                        </div>
                    </div>

                    <!-- Login Information -->
                    <h6 class="text-primary mb-3 mt-4">Login Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="username" name="username" required
                                value="<?php echo $action == 'edit' ? htmlspecialchars($faculty['username']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password <?php echo $action == 'edit' ? '(Leave blank to keep current)' : '*'; ?></label>
                            <input type="password" class="form-control" id="password" name="password" <?php echo $action == 'add' ? 'required' : ''; ?>>
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i><?php echo $action == 'add' ? 'Add Faculty' : 'Update Faculty'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Assign Subject Modal -->
    <div class="modal fade" id="assignSubjectModal" tabindex="-1" aria-labelledby="assignSubjectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignSubjectModalLabel">Assign Subject to Faculty</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="admin_faculty.php">
                    <div class="modal-body">
                        <input type="hidden" id="assign_faculty_id" name="faculty_id">
                        <div class="mb-3">
                            <label class="form-label">Faculty Member</label>
                            <input type="text" class="form-control" id="assign_faculty_name" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="subject_id" class="form-label">Select Subject *</label>
                            <select class="form-select" id="subject_id" name="subject_id" required>
                                <option value="">Select Subject</option>
                                <?php foreach ($available_subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>">
                                        <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?>
                                        <?php if ($subject['faculty_id']): ?>
                                            (Currently assigned)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="assign_subject" class="btn btn-success">Assign Subject</button>
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

    <script>
        function assignSubject(facultyId, facultyName) {
            document.getElementById('assign_faculty_id').value = facultyId;
            document.getElementById('assign_faculty_name').value = facultyName;

            // Reset the subject selection
            document.getElementById('subject_id').value = '';

            var assignModal = new bootstrap.Modal(document.getElementById('assignSubjectModal'));
            assignModal.show();
        }
    </script>
</body>
</html>
