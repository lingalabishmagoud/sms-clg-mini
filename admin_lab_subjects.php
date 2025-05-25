<?php
// Start session
session_start();

// Check if admin is logged in (for testing, allow access)
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['admin_id'] = 1;
    $_SESSION['user_type'] = 'admin';
    $_SESSION['admin_name'] = 'Test Admin';
}

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';

// Add lab subject
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_lab_subject'])) {
    $subject_name = $_POST['subject_name'];
    $abbreviation = $_POST['abbreviation'];
    $credits = $_POST['credits'];
    $faculty_id = $_POST['faculty_id'] ?: null;
    $lab_type = $_POST['lab_type'];
    $lab_room = $_POST['lab_room'];
    $max_students = $_POST['max_students'];

    // Generate subject code if not provided
    $subject_code = strtoupper($abbreviation);

    // Check if columns exist, if not, use basic insert
    $columns_check = $conn->query("SHOW COLUMNS FROM subjects LIKE 'subject_type'");
    if ($columns_check->num_rows > 0) {
        $stmt = $conn->prepare("INSERT INTO subjects (subject_code, subject_name, abbreviation, credits, faculty_id, department, subject_type, lab_room, max_students_per_lab) VALUES (?, ?, ?, ?, ?, 'Cyber Security', ?, ?, ?)");
        $stmt->bind_param("sssiissi", $subject_code, $subject_name, $abbreviation, $credits, $faculty_id, $lab_type, $lab_room, $max_students);
    } else {
        $stmt = $conn->prepare("INSERT INTO subjects (subject_code, subject_name, abbreviation, credits, faculty_id, department) VALUES (?, ?, ?, ?, ?, 'Cyber Security')");
        $stmt->bind_param("sssii", $subject_code, $subject_name, $abbreviation, $credits, $faculty_id);
    }

    if ($stmt->execute()) {
        $message = "Lab subject added successfully!";
    } else {
        $message = "Error: " . $stmt->error;
    }
    $stmt->close();
}

// Update lab subject assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_assignment'])) {
    $subject_id = $_POST['subject_id'];
    $faculty_id = $_POST['faculty_id'] ?: null;
    $lab_room = $_POST['lab_room'];
    $max_students = $_POST['max_students'];

    // Check if columns exist
    $columns_check = $conn->query("SHOW COLUMNS FROM subjects LIKE 'lab_room'");
    if ($columns_check->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE subjects SET faculty_id = ?, lab_room = ?, max_students_per_lab = ? WHERE id = ?");
        $stmt->bind_param("isii", $faculty_id, $lab_room, $max_students, $subject_id);
    } else {
        $stmt = $conn->prepare("UPDATE subjects SET faculty_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $faculty_id, $subject_id);
    }

    if ($stmt->execute()) {
        $message = "Lab subject assignment updated successfully!";
    } else {
        $message = "Error: " . $stmt->error;
    }
    $stmt->close();
}

// Get all faculty
$faculty = [];
$faculty_result = $conn->query("SELECT id, full_name, department FROM faculty ORDER BY full_name");
while ($row = $faculty_result->fetch_assoc()) {
    $faculty[] = $row;
}

// Get lab subjects
$lab_subjects = [];

// Check if subject_type column exists
$columns_check = $conn->query("SHOW COLUMNS FROM subjects LIKE 'subject_type'");
if ($columns_check->num_rows > 0) {
    $lab_query = "
        SELECT s.*, f.full_name as faculty_name, f.department as faculty_department,
               COUNT(sse.student_id) as enrolled_students
        FROM subjects s
        LEFT JOIN faculty f ON s.faculty_id = f.id
        LEFT JOIN student_subject_enrollment sse ON s.id = sse.subject_id AND sse.status = 'active'
        WHERE s.subject_type = 'lab'
        GROUP BY s.id
        ORDER BY s.abbreviation
    ";
} else {
    // Fallback query for when subject_type column doesn't exist
    // Show subjects that contain 'lab' in their name or abbreviation
    $lab_query = "
        SELECT s.*, f.full_name as faculty_name, f.department as faculty_department,
               COUNT(sse.student_id) as enrolled_students
        FROM subjects s
        LEFT JOIN faculty f ON s.faculty_id = f.id
        LEFT JOIN student_subject_enrollment sse ON s.id = sse.subject_id AND sse.status = 'active'
        WHERE (LOWER(s.subject_name) LIKE '%lab%' OR LOWER(s.abbreviation) LIKE '%lab%')
        GROUP BY s.id
        ORDER BY s.abbreviation
    ";
}

$lab_result = $conn->query($lab_query);
if ($lab_result) {
    while ($row = $lab_result->fetch_assoc()) {
        $lab_subjects[] = $row;
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
    <title>Lab Subjects Management - Admin Panel</title>
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
                        <a class="nav-link active" href="admin_lab_subjects.php">Lab Subjects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_calendar.php">Academic Calendar</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        Welcome, Admin
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
                <h2><i class="fas fa-flask me-2"></i>Lab Subjects Management</h2>
                <p class="text-muted">Manage laboratory subjects and assign faculty</p>
            </div>
            <div class="col-md-4 text-md-end">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addLabSubjectModal">
                    <i class="fas fa-plus me-2"></i>Add Lab Subject
                </button>
            </div>
        </div>

        <?php if (!empty($message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Lab Subjects Overview -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h4><?php echo count($lab_subjects); ?></h4>
                        <p class="mb-0">Total Lab Subjects</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h4><?php echo count(array_filter($lab_subjects, function($s) { return !empty($s['faculty_id']); })); ?></h4>
                        <p class="mb-0">Assigned to Faculty</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <h4><?php echo count(array_filter($lab_subjects, function($s) { return empty($s['faculty_id']); })); ?></h4>
                        <p class="mb-0">Unassigned</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h4><?php echo array_sum(array_column($lab_subjects, 'enrolled_students')); ?></h4>
                        <p class="mb-0">Total Enrollments</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lab Subjects List -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Lab Subjects</h5>
            </div>
            <div class="card-body">
                <?php if (count($lab_subjects) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Abbreviation</th>
                                <th>Credits</th>
                                <th>Assigned Faculty</th>
                                <th>Lab Room</th>
                                <th>Max Students</th>
                                <th>Enrolled</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lab_subjects as $subject): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong></td>
                                <td><span class="badge bg-primary"><?php echo htmlspecialchars($subject['abbreviation']); ?></span></td>
                                <td><?php echo $subject['credits']; ?></td>
                                <td>
                                    <?php if ($subject['faculty_name']): ?>
                                        <span class="text-success">
                                            <i class="fas fa-user-check me-1"></i>
                                            <?php echo htmlspecialchars($subject['faculty_name']); ?>
                                        </span>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($subject['faculty_department']); ?></small>
                                    <?php else: ?>
                                        <span class="text-warning">
                                            <i class="fas fa-user-times me-1"></i>Not Assigned
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($subject['lab_room'] ?? 'Not Set'); ?></td>
                                <td><?php echo $subject['max_students_per_lab'] ?? 'Not Set'; ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $subject['enrolled_students']; ?></span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary"
                                            onclick="editLabSubject(<?php echo $subject['id']; ?>, '<?php echo htmlspecialchars($subject['subject_name'], ENT_QUOTES); ?>', '<?php echo $subject['faculty_id'] ?? ''; ?>', '<?php echo htmlspecialchars($subject['lab_room'] ?? '', ENT_QUOTES); ?>', '<?php echo $subject['max_students_per_lab'] ?? ''; ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-flask fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Lab Subjects Found</h5>
                    <p class="text-muted">Add your first lab subject to get started.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Lab Subject Modal -->
    <div class="modal fade" id="addLabSubjectModal" tabindex="-1" aria-labelledby="addLabSubjectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addLabSubjectModalLabel">Add Lab Subject</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="admin_lab_subjects.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="subject_name" class="form-label">Subject Name *</label>
                            <input type="text" class="form-control" id="subject_name" name="subject_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="abbreviation" class="form-label">Abbreviation *</label>
                            <input type="text" class="form-control" id="abbreviation" name="abbreviation" maxlength="10" required>
                        </div>
                        <div class="mb-3">
                            <label for="credits" class="form-label">Credits *</label>
                            <input type="number" class="form-control" id="credits" name="credits" min="1" max="10" required>
                        </div>
                        <div class="mb-3">
                            <label for="faculty_id" class="form-label">Assign Faculty</label>
                            <select class="form-select" id="faculty_id" name="faculty_id">
                                <option value="">Select Faculty (Optional)</option>
                                <?php foreach ($faculty as $f): ?>
                                    <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['full_name'] . ' - ' . $f['department']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="lab_type" class="form-label">Lab Type *</label>
                            <select class="form-select" id="lab_type" name="lab_type" required>
                                <option value="lab">Laboratory</option>
                                <option value="practical">Practical</option>
                                <option value="workshop">Workshop</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="lab_room" class="form-label">Lab Room</label>
                            <input type="text" class="form-control" id="lab_room" name="lab_room" placeholder="e.g., Lab-101, Computer Lab-A">
                        </div>
                        <div class="mb-3">
                            <label for="max_students" class="form-label">Max Students per Lab Session</label>
                            <input type="number" class="form-control" id="max_students" name="max_students" min="10" max="50" value="30">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_lab_subject" class="btn btn-success">Add Lab Subject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Lab Subject Assignment Modal -->
    <div class="modal fade" id="editLabSubjectModal" tabindex="-1" aria-labelledby="editLabSubjectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editLabSubjectModalLabel">Edit Lab Subject Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="admin_lab_subjects.php">
                    <div class="modal-body">
                        <input type="hidden" id="edit_subject_id" name="subject_id">
                        <div class="mb-3">
                            <label class="form-label">Subject Name</label>
                            <input type="text" class="form-control" id="edit_subject_name" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="edit_faculty_id" class="form-label">Assign Faculty</label>
                            <select class="form-select" id="edit_faculty_id" name="faculty_id">
                                <option value="">Select Faculty</option>
                                <?php foreach ($faculty as $f): ?>
                                    <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['full_name'] . ' - ' . $f['department']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_lab_room" class="form-label">Lab Room</label>
                            <input type="text" class="form-control" id="edit_lab_room" name="lab_room" placeholder="e.g., Lab-101, Computer Lab-A">
                        </div>
                        <div class="mb-3">
                            <label for="edit_max_students" class="form-label">Max Students per Lab Session</label>
                            <input type="number" class="form-control" id="edit_max_students" name="max_students" min="10" max="50">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_assignment" class="btn btn-primary">Update Assignment</button>
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
                    <h5>Admin Panel</h5>
                    <p>Comprehensive lab subjects management system.</p>
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
        function editLabSubject(subjectId, subjectName, facultyId, labRoom, maxStudents) {
            document.getElementById('edit_subject_id').value = subjectId;
            document.getElementById('edit_subject_name').value = subjectName;
            document.getElementById('edit_faculty_id').value = facultyId || '';
            document.getElementById('edit_lab_room').value = labRoom || '';
            document.getElementById('edit_max_students').value = maxStudents || '';

            var editModal = new bootstrap.Modal(document.getElementById('editLabSubjectModal'));
            editModal.show();
        }
    </script>
</body>
</html>
