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

// Handle search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_section = isset($_GET['filter_section']) ? $_GET['filter_section'] : '';
$filter_department = isset($_GET['filter_department']) ? $_GET['filter_department'] : '';
$filter_year = isset($_GET['filter_year']) ? $_GET['filter_year'] : '';

// Handle bulk section transfer
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_transfer'])) {
    $selected_students = isset($_POST['selected_students']) ? $_POST['selected_students'] : [];
    $new_section = $_POST['bulk_new_section'];

    if (!empty($selected_students) && !empty($new_section)) {
        $success_count = 0;
        foreach ($selected_students as $student_id) {
            $stmt = $conn->prepare("UPDATE students SET section = ? WHERE id = ?");
            $stmt->bind_param("si", $new_section, $student_id);
            if ($stmt->execute()) {
                $success_count++;
            }
            $stmt->close();
        }
        $message = "Successfully transferred $success_count students to section $new_section!";
    } else {
        $message = "Please select students and a target section for bulk transfer.";
    }
}

// Handle add student
if ($action == 'add' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $email = $_POST['email'];
    $full_name = $_POST['full_name'];
    $roll_number = $_POST['roll_number'];
    $father_name = $_POST['father_name'];
    $dob = $_POST['dob'];
    $blood_group = $_POST['blood_group'];
    $aadhaar_number = $_POST['aadhaar_number'];
    $phone_number = $_POST['phone_number'];
    $address = $_POST['address'];
    $section = $_POST['section'];
    $department = $_POST['department'];
    $year = $_POST['year'];
    $semester = $_POST['semester'];
    $course = $_POST['course'];
    $program = $_POST['program'];
    $batch = $_POST['batch'];
    $student_id = 'STU' . substr($roll_number, -6); // Generate student ID from roll number

    $stmt = $conn->prepare("INSERT INTO students (username, password, email, full_name, roll_number, father_name, dob, blood_group, aadhaar_number, phone_number, address, section, department, year, semester, course, program, batch, student_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssssssssissss", $username, $password, $email, $full_name, $roll_number, $father_name, $dob, $blood_group, $aadhaar_number, $phone_number, $address, $section, $department, $year, $semester, $course, $program, $batch, $student_id);

    if ($stmt->execute()) {
        $message = "Student added successfully!";
        $action = 'list'; // Return to list view
    } else {
        $message = "Error: " . $stmt->error;
    }
}

// Handle section transfer
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['transfer_section'])) {
    $student_id = (int)$_POST['student_id'];
    $new_section = $_POST['new_section'];

    $stmt = $conn->prepare("UPDATE students SET section = ? WHERE id = ?");
    $stmt->bind_param("si", $new_section, $student_id);

    if ($stmt->execute()) {
        $message = "Student transferred to section $new_section successfully!";
    } else {
        $message = "Error transferring student: " . $stmt->error;
    }
}

// Handle delete student
if ($action == 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $message = "Student deleted successfully!";
    } else {
        $message = "Error: " . $stmt->error;
    }
    $action = 'list'; // Return to list view
}

// Get students for listing with search and filter
$students = [];
$students_by_section = [];
if ($action == 'list') {
    // Build query with search and filter conditions
    $query = "SELECT * FROM students WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($search)) {
        $query .= " AND (full_name LIKE ? OR roll_number LIKE ? OR email LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "sss";
    }

    if (!empty($filter_section)) {
        $query .= " AND section = ?";
        $params[] = $filter_section;
        $types .= "s";
    }

    if (!empty($filter_department)) {
        $query .= " AND department = ?";
        $params[] = $filter_department;
        $types .= "s";
    }

    if (!empty($filter_year)) {
        $query .= " AND year = ?";
        $params[] = $filter_year;
        $types .= "i";
    }

    $query .= " ORDER BY section, roll_number";

    if (!empty($params)) {
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($query);
    }

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
            $section = $row['section'] ?: 'Unassigned';
            if (!isset($students_by_section[$section])) {
                $students_by_section[$section] = [];
            }
            $students_by_section[$section][] = $row;
        }
    }

    if (!empty($params)) {
        $stmt->close();
    }
}

// Get single student for editing
$student = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $student = $result->fetch_assoc();
    }
}

// Handle update student
if ($action == 'edit' && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = $_POST['id'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $full_name = $_POST['full_name'];
    $student_id = $_POST['student_id'];
    $program = $_POST['program'];
    $batch = $_POST['batch'];

    // Check if password is being updated
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE students SET username=?, password=?, email=?, full_name=?, student_id=?, program=?, batch=? WHERE id=?");
        $stmt->bind_param("sssssssi", $username, $password, $email, $full_name, $student_id, $program, $batch, $id);
    } else {
        $stmt = $conn->prepare("UPDATE students SET username=?, email=?, full_name=?, student_id=?, program=?, batch=? WHERE id=?");
        $stmt->bind_param("ssssssi", $username, $email, $full_name, $student_id, $program, $batch, $id);
    }

    if ($stmt->execute()) {
        $message = "Student updated successfully!";
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
    <title>Manage Students - Admin Dashboard</title>
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
                        <a class="nav-link active" href="admin_students.php">Students</a>
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
                <h2><?php echo $action == 'list' ? 'Manage Students' : ($action == 'add' ? 'Add New Student' : 'Edit Student'); ?></h2>
            </div>
            <div class="col-md-4 text-end">
                <?php if ($action == 'list'): ?>
                <a href="?action=add" class="btn btn-primary">
                    <i class="fas fa-user-plus me-2"></i>Add New Student
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
        <!-- Search and Filter Section -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-search me-2"></i>Search & Filter Students</h5>
            </div>
            <div class="card-body">
                <form method="get" action="admin_students.php" class="row g-3">
                    <input type="hidden" name="action" value="list">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search"
                               placeholder="Name, Roll Number, or Email" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="filter_section" class="form-label">Section</label>
                        <select class="form-select" id="filter_section" name="filter_section">
                            <option value="">All Sections</option>
                            <option value="CS-A" <?php echo ($filter_section == 'CS-A') ? 'selected' : ''; ?>>CS-A</option>
                            <option value="CS-B" <?php echo ($filter_section == 'CS-B') ? 'selected' : ''; ?>>CS-B</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="filter_department" class="form-label">Department</label>
                        <select class="form-select" id="filter_department" name="filter_department">
                            <option value="">All Departments</option>
                            <option value="Cyber Security" <?php echo ($filter_department == 'Cyber Security') ? 'selected' : ''; ?>>Cyber Security</option>
                            <option value="Computer Science" <?php echo ($filter_department == 'Computer Science') ? 'selected' : ''; ?>>Computer Science</option>
                            <option value="Data Science" <?php echo ($filter_department == 'Data Science') ? 'selected' : ''; ?>>Data Science</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="filter_year" class="form-label">Year</label>
                        <select class="form-select" id="filter_year" name="filter_year">
                            <option value="">All Years</option>
                            <option value="1" <?php echo ($filter_year == '1') ? 'selected' : ''; ?>>1st Year</option>
                            <option value="2" <?php echo ($filter_year == '2') ? 'selected' : ''; ?>>2nd Year</option>
                            <option value="3" <?php echo ($filter_year == '3') ? 'selected' : ''; ?>>3rd Year</option>
                            <option value="4" <?php echo ($filter_year == '4') ? 'selected' : ''; ?>>4th Year</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>

                <?php if (!empty($search) || !empty($filter_section) || !empty($filter_department) || !empty($filter_year)): ?>
                <div class="mt-3">
                    <a href="admin_students.php?action=list" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-times me-1"></i>Clear Filters
                    </a>
                    <span class="text-muted ms-3">
                        Showing <?php echo count($students); ?> student(s)
                        <?php if (!empty($search)): ?>
                            matching "<?php echo htmlspecialchars($search); ?>"
                        <?php endif; ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Students by Section Overview -->
        <div class="row mb-4">
            <?php foreach ($students_by_section as $section => $section_students): ?>
                <div class="col-md-6 mb-3">
                    <div class="card border-secondary">
                        <div class="card-header bg-<?php echo ($section == 'CS-A') ? 'primary' : (($section == 'CS-B') ? 'success' : 'secondary'); ?> text-white">
                            <h6 class="mb-0"><?php echo htmlspecialchars($section); ?> (<?php echo count($section_students); ?> students)</h6>
                        </div>
                        <div class="card-body">
                            <?php if (count($section_students) > 0): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach (array_slice($section_students, 0, 5) as $student): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($student['full_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($student['roll_number']); ?></small>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-warning"
                                                    onclick="transferSection(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['full_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($student['section'], ENT_QUOTES); ?>')">
                                                <i class="fas fa-exchange-alt"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($section_students) > 5): ?>
                                        <div class="list-group-item text-center">
                                            <small class="text-muted">... and <?php echo count($section_students) - 5; ?> more</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">No students in this section</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Bulk Operations -->
        <?php if (count($students) > 0): ?>
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Bulk Operations</h5>
            </div>
            <div class="card-body">
                <form method="post" action="admin_students.php" id="bulkForm">
                    <div class="row align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Transfer Selected Students to Section:</label>
                            <select class="form-select" name="bulk_new_section" required>
                                <option value="">Select Target Section</option>
                                <option value="CS-A">CS-A</option>
                                <option value="CS-B">CS-B</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" name="bulk_transfer" class="btn btn-warning" onclick="return confirmBulkTransfer()">
                                <i class="fas fa-exchange-alt me-2"></i>Transfer Selected Students
                            </button>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                <label class="form-check-label" for="selectAll">
                                    Select All Students
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <span id="selectedCount">0</span> student(s) selected for bulk operations
                        </small>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Student List -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-users me-2"></i>Student List
                    <span class="badge bg-secondary"><?php echo count($students); ?> students</span>
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th width="50">
                                    <input type="checkbox" class="form-check-input" id="selectAllTable" onchange="toggleSelectAllTable()">
                                </th>
                                <th>Roll Number</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Section</th>
                                <th>Department</th>
                                <th>Year</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($students) > 0): ?>
                                <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input student-checkbox"
                                               name="selected_students[]" value="<?php echo $student['id']; ?>"
                                               form="bulkForm" onchange="updateSelectedCount()">
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($student['roll_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo ($student['section'] == 'CS-A') ? 'primary' : (($student['section'] == 'CS-B') ? 'success' : 'secondary'); ?>">
                                            <?php echo htmlspecialchars($student['section']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($student['department']); ?></td>
                                    <td><?php echo $student['year']; ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="?action=edit&id=<?php echo $student['id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-warning" title="Transfer Section"
                                                    onclick="transferSection(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['full_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($student['section'], ENT_QUOTES); ?>')">
                                                <i class="fas fa-exchange-alt"></i>
                                            </button>
                                            <a href="?action=delete&id=<?php echo $student['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this student?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">
                                        <div class="py-4">
                                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">No students found</h5>
                                            <p class="text-muted">
                                                <?php if (!empty($search) || !empty($filter_section) || !empty($filter_department) || !empty($filter_year)): ?>
                                                    Try adjusting your search criteria or <a href="admin_students.php?action=list">clear filters</a>.
                                                <?php else: ?>
                                                    <a href="?action=add" class="btn btn-primary">Add your first student</a>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($action == 'add' || $action == 'edit'): ?>
        <!-- Add/Edit Student Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?php echo $action == 'add' ? 'Add New Student' : 'Edit Student'; ?></h5>
            </div>
            <div class="card-body">
                <form method="post" action="?action=<?php echo $action; ?><?php echo $action == 'edit' ? '&id=' . $student['id'] : ''; ?>">
                    <?php if ($action == 'edit'): ?>
                    <input type="hidden" name="id" value="<?php echo $student['id']; ?>">
                    <?php endif; ?>

                    <!-- Basic Information -->
                    <h6 class="text-primary mb-3">Basic Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="full_name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required
                                value="<?php echo $action == 'edit' ? htmlspecialchars($student['full_name']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="father_name" class="form-label">Father's Name *</label>
                            <input type="text" class="form-control" id="father_name" name="father_name" required
                                value="<?php echo $action == 'edit' ? htmlspecialchars($student['father_name']) : ''; ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required
                                value="<?php echo $action == 'edit' ? htmlspecialchars($student['email']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="phone_number" class="form-label">Phone Number *</label>
                            <input type="tel" class="form-control" id="phone_number" name="phone_number" required
                                value="<?php echo $action == 'edit' ? htmlspecialchars($student['phone_number']) : ''; ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="dob" class="form-label">Date of Birth *</label>
                            <input type="date" class="form-control" id="dob" name="dob" required
                                value="<?php echo $action == 'edit' ? $student['dob'] : ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="blood_group" class="form-label">Blood Group</label>
                            <select class="form-select" id="blood_group" name="blood_group">
                                <option value="">Select Blood Group</option>
                                <option value="A+" <?php echo ($action == 'edit' && $student['blood_group'] == 'A+') ? 'selected' : ''; ?>>A+</option>
                                <option value="A-" <?php echo ($action == 'edit' && $student['blood_group'] == 'A-') ? 'selected' : ''; ?>>A-</option>
                                <option value="B+" <?php echo ($action == 'edit' && $student['blood_group'] == 'B+') ? 'selected' : ''; ?>>B+</option>
                                <option value="B-" <?php echo ($action == 'edit' && $student['blood_group'] == 'B-') ? 'selected' : ''; ?>>B-</option>
                                <option value="AB+" <?php echo ($action == 'edit' && $student['blood_group'] == 'AB+') ? 'selected' : ''; ?>>AB+</option>
                                <option value="AB-" <?php echo ($action == 'edit' && $student['blood_group'] == 'AB-') ? 'selected' : ''; ?>>AB-</option>
                                <option value="O+" <?php echo ($action == 'edit' && $student['blood_group'] == 'O+') ? 'selected' : ''; ?>>O+</option>
                                <option value="O-" <?php echo ($action == 'edit' && $student['blood_group'] == 'O-') ? 'selected' : ''; ?>>O-</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="aadhaar_number" class="form-label">Aadhaar Number</label>
                            <input type="text" class="form-control" id="aadhaar_number" name="aadhaar_number" maxlength="12"
                                value="<?php echo $action == 'edit' ? htmlspecialchars($student['aadhaar_number']) : ''; ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"><?php echo $action == 'edit' ? htmlspecialchars($student['address']) : ''; ?></textarea>
                    </div>

                    <!-- Academic Information -->
                    <h6 class="text-primary mb-3 mt-4">Academic Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="roll_number" class="form-label">Roll Number *</label>
                            <input type="text" class="form-control" id="roll_number" name="roll_number" required
                                placeholder="e.g., 22N81A6254" value="<?php echo $action == 'edit' ? htmlspecialchars($student['roll_number']) : ''; ?>">
                            <small class="form-text text-muted">Format: YYNCCXXX (YY=year, N8=college, CC=dept code, XXX=number)</small>
                        </div>
                        <div class="col-md-6">
                            <label for="section" class="form-label">Section *</label>
                            <select class="form-select" id="section" name="section" required>
                                <option value="">Select Section</option>
                                <option value="CS-A" <?php echo ($action == 'edit' && $student['section'] == 'CS-A') ? 'selected' : ''; ?>>CS-A</option>
                                <option value="CS-B" <?php echo ($action == 'edit' && $student['section'] == 'CS-B') ? 'selected' : ''; ?>>CS-B</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="department" class="form-label">Department *</label>
                            <select class="form-select" id="department" name="department" required>
                                <option value="">Select Department</option>
                                <option value="Cyber Security" <?php echo ($action == 'edit' && $student['department'] == 'Cyber Security') ? 'selected' : ''; ?>>Cyber Security</option>
                                <option value="Computer Science" <?php echo ($action == 'edit' && $student['department'] == 'Computer Science') ? 'selected' : ''; ?>>Computer Science</option>
                                <option value="Data Science" <?php echo ($action == 'edit' && $student['department'] == 'Data Science') ? 'selected' : ''; ?>>Data Science</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="year" class="form-label">Year *</label>
                            <select class="form-select" id="year" name="year" required>
                                <option value="">Select Year</option>
                                <option value="1" <?php echo ($action == 'edit' && $student['year'] == 1) ? 'selected' : ''; ?>>1st Year</option>
                                <option value="2" <?php echo ($action == 'edit' && $student['year'] == 2) ? 'selected' : ''; ?>>2nd Year</option>
                                <option value="3" <?php echo ($action == 'edit' && $student['year'] == 3) ? 'selected' : ''; ?>>3rd Year</option>
                                <option value="4" <?php echo ($action == 'edit' && $student['year'] == 4) ? 'selected' : ''; ?>>4th Year</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="semester" class="form-label">Semester *</label>
                            <select class="form-select" id="semester" name="semester" required>
                                <option value="">Select Semester</option>
                                <option value="1st" <?php echo ($action == 'edit' && $student['semester'] == '1st') ? 'selected' : ''; ?>>1st Semester</option>
                                <option value="2nd" <?php echo ($action == 'edit' && $student['semester'] == '2nd') ? 'selected' : ''; ?>>2nd Semester</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="course" class="form-label">Course *</label>
                            <input type="text" class="form-control" id="course" name="course" required
                                value="<?php echo $action == 'edit' ? htmlspecialchars($student['course']) : 'Cyber Security'; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="program" class="form-label">Program *</label>
                            <select class="form-select" id="program" name="program" required>
                                <option value="">Select Program</option>
                                <option value="B.Tech" <?php echo ($action == 'edit' && $student['program'] == 'B.Tech') ? 'selected' : ''; ?>>B.Tech</option>
                                <option value="M.Tech" <?php echo ($action == 'edit' && $student['program'] == 'M.Tech') ? 'selected' : ''; ?>>M.Tech</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="batch" class="form-label">Batch *</label>
                            <input type="text" class="form-control" id="batch" name="batch" required
                                placeholder="e.g., 2022" value="<?php echo $action == 'edit' ? htmlspecialchars($student['batch']) : ''; ?>">
                        </div>
                    </div>

                    <!-- Login Information -->
                    <h6 class="text-primary mb-3 mt-4">Login Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="username" name="username" required
                                value="<?php echo $action == 'edit' ? htmlspecialchars($student['username']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password <?php echo $action == 'edit' ? '(Leave blank to keep current)' : '*'; ?></label>
                            <input type="password" class="form-control" id="password" name="password" <?php echo $action == 'add' ? 'required' : ''; ?>>
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i><?php echo $action == 'add' ? 'Add Student' : 'Update Student'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Transfer Section Modal -->
    <div class="modal fade" id="transferSectionModal" tabindex="-1" aria-labelledby="transferSectionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="transferSectionModalLabel">Transfer Student Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="admin_students.php">
                    <div class="modal-body">
                        <input type="hidden" id="transfer_student_id" name="student_id">
                        <div class="mb-3">
                            <label class="form-label">Student Name</label>
                            <input type="text" class="form-control" id="transfer_student_name" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Current Section</label>
                            <input type="text" class="form-control" id="transfer_current_section" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="new_section" class="form-label">Transfer to Section *</label>
                            <select class="form-select" id="new_section" name="new_section" required>
                                <option value="">Select Section</option>
                                <option value="CS-A">CS-A</option>
                                <option value="CS-B">CS-B</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="transfer_section" class="btn btn-primary">Transfer Student</button>
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
        function transferSection(studentId, studentName, currentSection) {
            document.getElementById('transfer_student_id').value = studentId;
            document.getElementById('transfer_student_name').value = studentName;
            document.getElementById('transfer_current_section').value = currentSection;

            // Reset the section selection
            document.getElementById('new_section').value = '';

            var transferModal = new bootstrap.Modal(document.getElementById('transferSectionModal'));
            transferModal.show();
        }

        // Bulk operations functions
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const selectAllTable = document.getElementById('selectAllTable');
            const checkboxes = document.querySelectorAll('.student-checkbox');

            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });

            if (selectAllTable) {
                selectAllTable.checked = selectAll.checked;
            }

            updateSelectedCount();
        }

        function toggleSelectAllTable() {
            const selectAllTable = document.getElementById('selectAllTable');
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.student-checkbox');

            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAllTable.checked;
            });

            if (selectAll) {
                selectAll.checked = selectAllTable.checked;
            }

            updateSelectedCount();
        }

        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.student-checkbox:checked');
            const count = checkboxes.length;
            const countElement = document.getElementById('selectedCount');

            if (countElement) {
                countElement.textContent = count;
            }

            // Update select all checkboxes based on individual selections
            const allCheckboxes = document.querySelectorAll('.student-checkbox');
            const selectAll = document.getElementById('selectAll');
            const selectAllTable = document.getElementById('selectAllTable');

            if (selectAll) {
                selectAll.checked = (count === allCheckboxes.length && count > 0);
            }
            if (selectAllTable) {
                selectAllTable.checked = (count === allCheckboxes.length && count > 0);
            }
        }

        function confirmBulkTransfer() {
            const checkboxes = document.querySelectorAll('.student-checkbox:checked');
            const count = checkboxes.length;
            const targetSection = document.querySelector('select[name="bulk_new_section"]').value;

            if (count === 0) {
                alert('Please select at least one student for bulk transfer.');
                return false;
            }

            if (!targetSection) {
                alert('Please select a target section for bulk transfer.');
                return false;
            }

            return confirm(`Are you sure you want to transfer ${count} selected student(s) to section ${targetSection}?`);
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateSelectedCount();
        });
    </script>
</body>
</html>
