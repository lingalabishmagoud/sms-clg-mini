<?php
// Start session
session_start();

// For testing purposes, we're not enforcing authentication
// In a real application, you would check if the user is logged in as admin
$admin_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 1;
$admin_name = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : "Test Admin";

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_department'])) {
        // Add new department
        $dept_name = trim($_POST['dept_name']);
        $dept_code = trim($_POST['dept_code']);
        $description = trim($_POST['description']);

        if (!empty($dept_name) && !empty($dept_code)) {
            // Check if department code already exists
            $stmt = $conn->prepare("SELECT id FROM departments WHERE dept_code = ?");
            $stmt->bind_param("s", $dept_code);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 0) {
                // Insert new department
                $stmt = $conn->prepare("INSERT INTO departments (dept_name, dept_code, description) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $dept_name, $dept_code, $description);

                if ($stmt->execute()) {
                    $message = "Department added successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error adding department: " . $conn->error;
                    $message_type = "danger";
                }
            } else {
                $message = "Department code already exists!";
                $message_type = "warning";
            }
            $stmt->close();
        } else {
            $message = "Please fill in all required fields!";
            $message_type = "warning";
        }
    }

    if (isset($_POST['edit_department'])) {
        // Edit department
        $dept_id = (int)$_POST['dept_id'];
        $dept_name = trim($_POST['dept_name']);
        $dept_code = trim($_POST['dept_code']);
        $description = trim($_POST['description']);

        if (!empty($dept_name) && !empty($dept_code)) {
            // Check if department code already exists for other departments
            $stmt = $conn->prepare("SELECT id FROM departments WHERE dept_code = ? AND id != ?");
            $stmt->bind_param("si", $dept_code, $dept_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 0) {
                // Update department
                $stmt = $conn->prepare("UPDATE departments SET dept_name = ?, dept_code = ?, description = ? WHERE id = ?");
                $stmt->bind_param("sssi", $dept_name, $dept_code, $description, $dept_id);

                if ($stmt->execute()) {
                    $message = "Department updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating department: " . $conn->error;
                    $message_type = "danger";
                }
            } else {
                $message = "Department code already exists!";
                $message_type = "warning";
            }
            $stmt->close();
        } else {
            $message = "Please fill in all required fields!";
            $message_type = "warning";
        }
    }

    if (isset($_POST['transfer_faculty'])) {
        // Transfer faculty to different department
        $faculty_id = (int)$_POST['faculty_id'];
        $new_department = trim($_POST['new_department']);

        if (!empty($new_department)) {
            $stmt = $conn->prepare("UPDATE faculty SET department = ? WHERE id = ?");
            $stmt->bind_param("si", $new_department, $faculty_id);

            if ($stmt->execute()) {
                $message = "Faculty transferred successfully!";
                $message_type = "success";
            } else {
                $message = "Error transferring faculty: " . $conn->error;
                $message_type = "danger";
            }
            $stmt->close();
        } else {
            $message = "Please select a department!";
            $message_type = "warning";
        }
    }

    if (isset($_POST['delete_department'])) {
        // Delete department
        $dept_id = (int)$_POST['dept_id'];

        // Check if department is being used by faculty or courses
        $stmt = $conn->prepare("SELECT COUNT(*) as faculty_count FROM faculty WHERE department = (SELECT dept_name FROM departments WHERE id = ?)");
        $stmt->bind_param("i", $dept_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $faculty_count = $result->fetch_assoc()['faculty_count'];
        $stmt->close();

        $stmt = $conn->prepare("SELECT COUNT(*) as subject_count FROM subjects WHERE department = (SELECT dept_name FROM departments WHERE id = ?)");
        $stmt->bind_param("i", $dept_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $subject_count = $result->fetch_assoc()['subject_count'];
        $stmt->close();

        if ($faculty_count > 0 || $subject_count > 0) {
            $message = "Cannot delete department. It is being used by $faculty_count faculty member(s) and $subject_count subject(s).";
            $message_type = "warning";
        } else {
            // Delete department
            $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
            $stmt->bind_param("i", $dept_id);

            if ($stmt->execute()) {
                $message = "Department deleted successfully!";
                $message_type = "success";
            } else {
                $message = "Error deleting department: " . $conn->error;
                $message_type = "danger";
            }
            $stmt->close();
        }
    }
}

// Get all departments
$departments = [];
$result = $conn->query("SELECT * FROM departments ORDER BY dept_name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }
}

// Get faculty by department for transfer functionality
$faculty_by_dept = [];
$result = $conn->query("SELECT f.*, d.dept_name FROM faculty f LEFT JOIN departments d ON f.department = d.dept_name ORDER BY f.department, f.full_name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $dept = $row['department'] ?: 'Unassigned';
        if (!isset($faculty_by_dept[$dept])) {
            $faculty_by_dept[$dept] = [];
        }
        $faculty_by_dept[$dept][] = $row;
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
    <title>Manage Departments - Admin Panel</title>
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
            <a class="navbar-brand" href="#">Admin Panel</a>
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
                        <a class="nav-link active" href="admin_departments.php">Departments</a>
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
                <h2><i class="fas fa-building me-2"></i>Manage Departments</h2>
                <p class="text-muted">Add, edit, and manage academic departments</p>
            </div>
            <div class="col-md-4 text-md-end">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                    <i class="fas fa-plus me-2"></i>Add Department
                </button>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Departments Table -->
        <div class="card">
            <div class="card-body">
                <?php if (count($departments) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Department Code</th>
                                    <th>Department Name</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departments as $dept): ?>
                                    <tr>
                                        <td><?php echo $dept['id']; ?></td>
                                        <td><span class="badge bg-primary"><?php echo htmlspecialchars($dept['dept_code']); ?></span></td>
                                        <td><?php echo htmlspecialchars($dept['dept_name']); ?></td>
                                        <td><?php echo htmlspecialchars($dept['description'] ?? 'No description'); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                    onclick="editDepartment(<?php echo $dept['id']; ?>, '<?php echo htmlspecialchars($dept['dept_code'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($dept['dept_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($dept['description'] ?? '', ENT_QUOTES); ?>')">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                    onclick="deleteDepartment(<?php echo $dept['id']; ?>, '<?php echo htmlspecialchars($dept['dept_name'], ENT_QUOTES); ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-building fa-3x text-muted mb-3"></i>
                        <h5>No departments found</h5>
                        <p class="text-muted">Click "Add Department" to create your first department.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Faculty Transfer Section -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>Faculty by Department</h5>
                <small class="text-muted">Transfer faculty between departments</small>
            </div>
            <div class="card-body">
                <?php if (count($faculty_by_dept) > 0): ?>
                    <div class="row">
                        <?php foreach ($faculty_by_dept as $dept_name => $faculty_list): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card border-secondary">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($dept_name); ?> (<?php echo count($faculty_list); ?> faculty)</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php if (count($faculty_list) > 0): ?>
                                            <div class="list-group list-group-flush">
                                                <?php foreach ($faculty_list as $faculty): ?>
                                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($faculty['full_name']); ?></strong><br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($faculty['email']); ?></small>
                                                        </div>
                                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                                                onclick="transferFaculty(<?php echo $faculty['id']; ?>, '<?php echo htmlspecialchars($faculty['full_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($faculty['department'], ENT_QUOTES); ?>')">
                                                            <i class="fas fa-exchange-alt"></i> Transfer
                                                        </button>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted mb-0">No faculty in this department</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5>No faculty found</h5>
                        <p class="text-muted">Add faculty members to see them organized by department.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Department Modal -->
    <div class="modal fade" id="addDepartmentModal" tabindex="-1" aria-labelledby="addDepartmentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addDepartmentModalLabel">Add New Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="admin_departments.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="dept_code" class="form-label">Department Code *</label>
                            <input type="text" class="form-control" id="dept_code" name="dept_code" required maxlength="10" placeholder="e.g., CS, ENG, BUS">
                        </div>
                        <div class="mb-3">
                            <label for="dept_name" class="form-label">Department Name *</label>
                            <input type="text" class="form-control" id="dept_name" name="dept_name" required maxlength="100" placeholder="e.g., Computer Science">
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" placeholder="Optional description of the department"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_department" class="btn btn-primary">Add Department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Department Modal -->
    <div class="modal fade" id="editDepartmentModal" tabindex="-1" aria-labelledby="editDepartmentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editDepartmentModalLabel">Edit Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="admin_departments.php">
                    <div class="modal-body">
                        <input type="hidden" id="edit_dept_id" name="dept_id">
                        <div class="mb-3">
                            <label for="edit_dept_code" class="form-label">Department Code *</label>
                            <input type="text" class="form-control" id="edit_dept_code" name="dept_code" required maxlength="10">
                        </div>
                        <div class="mb-3">
                            <label for="edit_dept_name" class="form-label">Department Name *</label>
                            <input type="text" class="form-control" id="edit_dept_name" name="dept_name" required maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_department" class="btn btn-primary">Update Department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Department Modal -->
    <div class="modal fade" id="deleteDepartmentModal" tabindex="-1" aria-labelledby="deleteDepartmentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteDepartmentModalLabel">Delete Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="admin_departments.php">
                    <div class="modal-body">
                        <input type="hidden" id="delete_dept_id" name="dept_id">
                        <p>Are you sure you want to delete the department "<span id="delete_dept_name"></span>"?</p>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            This action cannot be undone. The department can only be deleted if it's not being used by any faculty or courses.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_department" class="btn btn-danger">Delete Department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Transfer Faculty Modal -->
    <div class="modal fade" id="transferFacultyModal" tabindex="-1" aria-labelledby="transferFacultyModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="transferFacultyModalLabel">Transfer Faculty</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="admin_departments.php">
                    <div class="modal-body">
                        <input type="hidden" id="transfer_faculty_id" name="faculty_id">
                        <div class="mb-3">
                            <label class="form-label">Faculty Member</label>
                            <input type="text" class="form-control" id="transfer_faculty_name" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Current Department</label>
                            <input type="text" class="form-control" id="transfer_current_dept" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="new_department" class="form-label">Transfer to Department *</label>
                            <select class="form-select" id="new_department" name="new_department" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['dept_name']); ?>">
                                        <?php echo htmlspecialchars($dept['dept_name']); ?> (<?php echo htmlspecialchars($dept['dept_code']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="transfer_faculty" class="btn btn-primary">Transfer Faculty</button>
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
        function editDepartment(id, code, name, description) {
            document.getElementById('edit_dept_id').value = id;
            document.getElementById('edit_dept_code').value = code;
            document.getElementById('edit_dept_name').value = name;
            document.getElementById('edit_description').value = description;

            var editModal = new bootstrap.Modal(document.getElementById('editDepartmentModal'));
            editModal.show();
        }

        function deleteDepartment(id, name) {
            document.getElementById('delete_dept_id').value = id;
            document.getElementById('delete_dept_name').textContent = name;

            var deleteModal = new bootstrap.Modal(document.getElementById('deleteDepartmentModal'));
            deleteModal.show();
        }

        function transferFaculty(id, name, currentDept) {
            document.getElementById('transfer_faculty_id').value = id;
            document.getElementById('transfer_faculty_name').value = name;
            document.getElementById('transfer_current_dept').value = currentDept;

            // Reset the department selection
            document.getElementById('new_department').value = '';

            var transferModal = new bootstrap.Modal(document.getElementById('transferFacultyModal'));
            transferModal.show();
        }
    </script>
</body>
</html>