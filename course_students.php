<?php
// Start session
session_start();

// Check if faculty is logged in
if (!isset($_SESSION['faculty_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'faculty') {
    header("Location: faculty_login.php");
    exit();
}

$faculty_id = $_SESSION['faculty_id'];
$faculty_name = $_SESSION['faculty_name'];

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

// Check if course ID is provided
if (!isset($_GET['course_id']) || empty($_GET['course_id'])) {
    header("Location: faculty_courses.php");
    exit();
}

$course_id = (int)$_GET['course_id'];
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

// Get students enrolled in this course
$enrolled_students = [];
$stmt = $conn->prepare("
    SELECT s.*, e.status, e.enrollment_date
    FROM students s
    JOIN enrollments e ON s.id = e.student_id
    WHERE e.course_id = ?
    ORDER BY s.full_name
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $enrolled_students[] = $row;
    }
}

// Get all students not enrolled in this course for the add student dropdown
$available_students = [];
$stmt = $conn->prepare("
    SELECT s.* FROM students s
    WHERE s.id NOT IN (
        SELECT student_id FROM enrollments WHERE course_id = ?
    )
    ORDER BY s.full_name
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $available_students[] = $row;
    }
}

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'enroll' && isset($_POST['student_id'])) {
        $student_id = (int)$_POST['student_id'];

        // Check if student is already enrolled
        $stmt = $conn->prepare("SELECT * FROM enrollments WHERE student_id = ? AND course_id = ?");
        $stmt->bind_param("ii", $student_id, $course_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            // Get student and course details for notification
            $stmt = $conn->prepare("SELECT s.full_name as student_name, c.course_name, c.course_code
                                   FROM students s, courses c
                                   WHERE s.id = ? AND c.id = ?");
            $stmt->bind_param("ii", $student_id, $course_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $details = $result->fetch_assoc();
            $stmt->close();

            // Enroll student
            $stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $student_id, $course_id);

            if ($stmt->execute()) {
                // Create notification for the enrolled student
                $notification_title = "Enrolled in Course: " . $details['course_code'];
                $notification_message = "You have been enrolled in the course '{$details['course_code']} - {$details['course_name']}' by {$faculty['full_name']}.\n\nWelcome to the course! Please check your course materials and schedule.";

                // Insert notification
                $stmt = $conn->prepare("INSERT INTO notifications (title, message, created_by, target_type, target_id, created_at) VALUES (?, ?, ?, 'student', ?, NOW())");
                $stmt->bind_param("ssii", $notification_title, $notification_message, $faculty_id, $student_id);
                $stmt->execute();
                $stmt->close();

                $message = "Student '{$details['student_name']}' enrolled successfully. Notification sent to student.";
                $message_type = "success";

                // Refresh page to update lists
                header("Location: course_students.php?course_id=$course_id&success=enrolled");
                exit();
            } else {
                $message = "Error enrolling student: " . $conn->error;
                $message_type = "danger";
            }
        } else {
            $message = "Student is already enrolled in this course";
            $message_type = "warning";
        }
    } elseif ($_POST['action'] === 'remove' && isset($_POST['student_id'])) {
        $student_id = (int)$_POST['student_id'];

        // Get student and course details for notification
        $stmt = $conn->prepare("SELECT s.full_name as student_name, s.email as student_email, c.course_name, c.course_code
                               FROM students s, courses c
                               WHERE s.id = ? AND c.id = ?");
        $stmt->bind_param("ii", $student_id, $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $details = $result->fetch_assoc();
        $stmt->close();

        // Remove student from course
        $stmt = $conn->prepare("DELETE FROM enrollments WHERE student_id = ? AND course_id = ?");
        $stmt->bind_param("ii", $student_id, $course_id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            // Create notification for the removed student
            $notification_title = "Removed from Course: " . $details['course_code'];
            $notification_message = "You have been removed from the course '{$details['course_code']} - {$details['course_name']}' by {$faculty['full_name']}.\n\nIf you believe this was done in error, please contact your instructor or the academic office.";

            // Insert notification with target_type = 'student' and target_id = student_id
            $stmt = $conn->prepare("INSERT INTO notifications (title, message, created_by, target_type, target_id, created_at) VALUES (?, ?, ?, 'student', ?, NOW())");
            $stmt->bind_param("ssii", $notification_title, $notification_message, $faculty_id, $student_id);
            $stmt->execute();
            $stmt->close();

            $message = "Student '{$details['student_name']}' removed from course successfully. Notification sent to student.";
            $message_type = "success";

            // Refresh page to update lists
            header("Location: course_students.php?course_id=$course_id&success=removed");
            exit();
        } else {
            $message = "Error removing student from course";
            $message_type = "danger";
        }
    } elseif ($_POST['action'] === 'update_status' && isset($_POST['student_id']) && isset($_POST['status'])) {
        $student_id = (int)$_POST['student_id'];
        $status = $_POST['status'];

        // Update enrollment status
        $stmt = $conn->prepare("UPDATE enrollments SET status = ? WHERE student_id = ? AND course_id = ?");
        $stmt->bind_param("sii", $status, $student_id, $course_id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $message = "Enrollment status updated successfully";
            $message_type = "success";

            // Refresh page to update lists
            header("Location: course_students.php?course_id=$course_id&success=updated");
            exit();
        } else {
            $message = "Error updating enrollment status or no changes made";
            $message_type = "warning";
        }
    }
}

// Check for success messages from redirects
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'enrolled') {
        $message = "Student enrolled successfully";
        $message_type = "success";
    } elseif ($_GET['success'] === 'removed') {
        $message = "Student removed from course successfully";
        $message_type = "success";
    } elseif ($_GET['success'] === 'updated') {
        $message = "Enrollment status updated successfully";
        $message_type = "success";
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
    <title>Course Students - Student Management System</title>
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
                <h2><i class="fas fa-users me-2"></i>Students in <?php echo htmlspecialchars($course['course_name']); ?></h2>
                <p class="text-muted">
                    Course Code: <?php echo htmlspecialchars($course['course_code']); ?> |
                    Credits: <?php echo htmlspecialchars($course['credits']); ?> |
                    Department: <?php echo htmlspecialchars($course['department']); ?>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <a href="faculty_courses.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Courses
                </a>
                <?php if (count($available_students) > 0): ?>
                    <button type="button" class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                        <i class="fas fa-user-plus me-2"></i>Add Student
                    </button>
                <?php endif; ?>
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
                <?php if (count($enrolled_students) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Year</th>
                                    <th>Status</th>
                                    <th>Enrollment Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enrolled_students as $student): ?>
                                    <tr>
                                        <td><?php echo $student['id']; ?></td>
                                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td><?php echo $student['year']; ?></td>
                                        <td>
                                            <form method="post" action="" class="d-inline status-form">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                <select name="status" class="form-select form-select-sm status-select" onchange="this.form.submit()">
                                                    <option value="active" <?php echo ($student['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                                    <option value="completed" <?php echo ($student['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                                                    <option value="dropped" <?php echo ($student['status'] === 'dropped') ? 'selected' : ''; ?>>Dropped</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($student['enrollment_date'])); ?></td>
                                        <td>
                                            <a href="course_grades.php?course_id=<?php echo $course_id; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-graduation-cap"></i> Grades
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger"
                                                    onclick="confirmRemove(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['full_name']); ?>')">
                                                <i class="fas fa-user-minus"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No students are currently enrolled in this course.
                        <?php if (count($available_students) > 0): ?>
                            Click the "Add Student" button to enroll students.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Student Modal -->
    <?php if (count($available_students) > 0): ?>
    <div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addStudentModalLabel">Add Student to Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="enroll">

                        <div class="mb-3">
                            <label for="student_id" class="form-label">Select Student</label>
                            <select class="form-select" id="student_id" name="student_id" required>
                                <?php foreach ($available_students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>">
                                        <?php echo htmlspecialchars($student['full_name']); ?> (<?php echo htmlspecialchars($student['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Enroll Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Remove Student Form -->
    <form id="removeForm" method="post" action="" style="display: none;">
        <input type="hidden" name="action" value="remove">
        <input type="hidden" id="remove_student_id" name="student_id" value="">
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
        function confirmRemove(studentId, studentName) {
            if (confirm(`Are you sure you want to remove ${studentName} from this course?`)) {
                document.getElementById('remove_student_id').value = studentId;
                document.getElementById('removeForm').submit();
            }
        }
    </script>
</body>
</html>
