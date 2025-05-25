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
    // Faculty not found, redirect to login
    header("Location: faculty_login.php");
    exit();
}

// Check if course ID is provided
if (!isset($_GET['course_id']) || empty($_GET['course_id'])) {
    header("Location: faculty_grades.php");
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
    header("Location: faculty_grades.php");
    exit();
}

// Get students enrolled in this course
$enrolled_students = [];
$stmt = $conn->prepare("
    SELECT s.*, e.status, e.enrollment_date
    FROM students s
    JOIN enrollments e ON s.id = e.student_id
    WHERE e.course_id = ? AND e.status != 'dropped'
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

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_grade' && isset($_POST['student_id']) && isset($_POST['assignment_name']) && isset($_POST['grade_value']) && isset($_POST['max_grade'])) {
        $student_id = (int)$_POST['student_id'];
        $assignment_name = trim($_POST['assignment_name']);
        $assignment_type = trim($_POST['assignment_type'] ?? 'Assignment');
        $grade_value = (float)$_POST['grade_value'];
        $max_grade = (float)$_POST['max_grade'];
        $comments = trim($_POST['comments'] ?? '');

        // Validate inputs
        if (empty($assignment_name) || $grade_value < 0 || $max_grade <= 0 || $grade_value > $max_grade) {
            $message = "Please provide valid grade information";
            $message_type = "danger";
        } else {
            // Insert grade with assignment type
            $stmt = $conn->prepare("INSERT INTO grades (student_id, course_id, assignment_name, grade_value, max_grade, comments) VALUES (?, ?, ?, ?, ?, ?)");
            $assignment_with_type = "[$assignment_type] $assignment_name";
            $stmt->bind_param("iisdds", $student_id, $course_id, $assignment_with_type, $grade_value, $max_grade, $comments);

            if ($stmt->execute()) {
                $message = "Grade added successfully for $assignment_type: $assignment_name";
                $message_type = "success";
            } else {
                $message = "Error adding grade: " . $conn->error;
                $message_type = "danger";
            }
        }
    } elseif ($_POST['action'] === 'update_grade' && isset($_POST['grade_id']) && isset($_POST['grade_value']) && isset($_POST['max_grade'])) {
        $grade_id = (int)$_POST['grade_id'];
        $grade_value = (float)$_POST['grade_value'];
        $max_grade = (float)$_POST['max_grade'];
        $comments = trim($_POST['comments'] ?? '');

        // Validate inputs
        if ($grade_value < 0 || $max_grade <= 0 || $grade_value > $max_grade) {
            $message = "Please provide valid grade information";
            $message_type = "danger";
        } else {
            // Update grade
            $stmt = $conn->prepare("UPDATE grades SET grade_value = ?, max_grade = ?, comments = ? WHERE id = ? AND course_id = ?");
            $stmt->bind_param("ddsii", $grade_value, $max_grade, $comments, $grade_id, $course_id);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = "Grade updated successfully";
                $message_type = "success";
            } else {
                $message = "Error updating grade or no changes made";
                $message_type = "warning";
            }
        }
    } elseif ($_POST['action'] === 'delete_grade' && isset($_POST['grade_id'])) {
        $grade_id = (int)$_POST['grade_id'];

        // Delete grade
        $stmt = $conn->prepare("DELETE FROM grades WHERE id = ? AND course_id = ?");
        $stmt->bind_param("ii", $grade_id, $course_id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $message = "Grade deleted successfully";
            $message_type = "success";
        } else {
            $message = "Error deleting grade";
            $message_type = "danger";
        }
    }
}

// Get grades for each student
$student_grades = [];
foreach ($enrolled_students as $student) {
    $stmt = $conn->prepare("
        SELECT * FROM grades
        WHERE student_id = ? AND course_id = ?
        ORDER BY graded_date DESC
    ");
    $stmt->bind_param("ii", $student['id'], $course_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $grades = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $grades[] = $row;
        }
    }

    $student_grades[$student['id']] = $grades;
}

// Ensure all enrolled students have an entry in student_grades array
foreach ($enrolled_students as $student) {
    if (!isset($student_grades[$student['id']])) {
        $student_grades[$student['id']] = [];
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
    <title>Course Grades - Student Management System</title>
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
                        <a class="nav-link" href="faculty_courses.php">Manage Courses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="faculty_grades.php">Grades</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_files.php">Files</a>
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
                <h2><i class="fas fa-graduation-cap me-2"></i>Grades for <?php echo htmlspecialchars($course['course_name']); ?></h2>
                <p class="text-muted">
                    Course Code: <?php echo htmlspecialchars($course['course_code']); ?> |
                    Credits: <?php echo htmlspecialchars($course['credits']); ?> |
                    Department: <?php echo htmlspecialchars($course['department']); ?>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <a href="faculty_grades.php" class="btn btn-secondary">
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

        <!-- Course Grade Overview -->
        <?php if (count($enrolled_students) > 0): ?>
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Class Performance Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php
                            $class_total_points = 0;
                            $class_max_points = 0;
                            $students_with_grades = 0;
                            $grade_distribution = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0];

                            foreach ($enrolled_students as $student) {
                                $current_student_grades = isset($student_grades[$student['id']]) ? $student_grades[$student['id']] : [];
                                $student_total = 0;
                                $student_max = 0;

                                if (is_array($current_student_grades)) {
                                    foreach ($current_student_grades as $grade) {
                                        $student_total += $grade['grade_value'];
                                        $student_max += $grade['max_grade'];
                                    }
                                }

                                if ($student_max > 0) {
                                    $students_with_grades++;
                                    $class_total_points += $student_total;
                                    $class_max_points += $student_max;

                                    $percentage = ($student_total / $student_max) * 100;
                                    if ($percentage >= 90) $grade_distribution['A']++;
                                    elseif ($percentage >= 80) $grade_distribution['B']++;
                                    elseif ($percentage >= 70) $grade_distribution['C']++;
                                    elseif ($percentage >= 60) $grade_distribution['D']++;
                                    else $grade_distribution['F']++;
                                }
                            }

                            $class_average = ($class_max_points > 0) ? round(($class_total_points / $class_max_points) * 100, 1) : 0;
                            ?>

                            <div class="col-md-3">
                                <div class="text-center">
                                    <h3 class="text-primary"><?php echo $class_average; ?>%</h3>
                                    <small class="text-muted">Class Average</small>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="text-center">
                                    <h3 class="text-info"><?php echo count($enrolled_students); ?></h3>
                                    <small class="text-muted">Total Students</small>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="text-center">
                                    <h3 class="text-success"><?php echo $students_with_grades; ?></h3>
                                    <small class="text-muted">Students with Grades</small>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="text-center">
                                    <strong>Grade Distribution:</strong><br>
                                    <span class="badge bg-success">A: <?php echo $grade_distribution['A']; ?></span>
                                    <span class="badge bg-info">B: <?php echo $grade_distribution['B']; ?></span>
                                    <span class="badge bg-warning">C: <?php echo $grade_distribution['C']; ?></span>
                                    <span class="badge bg-danger">D: <?php echo $grade_distribution['D']; ?></span>
                                    <span class="badge bg-dark">F: <?php echo $grade_distribution['F']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (count($enrolled_students) > 0): ?>
            <div class="accordion" id="studentsAccordion">
                <?php foreach ($enrolled_students as $index => $student): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading<?php echo $student['id']; ?>">
                            <button class="accordion-button <?php echo ($index !== 0) ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#collapse<?php echo $student['id']; ?>"
                                    aria-expanded="<?php echo ($index === 0) ? 'true' : 'false'; ?>"
                                    aria-controls="collapse<?php echo $student['id']; ?>">
                                <div class="d-flex justify-content-between w-100 me-3">
                                    <span><?php echo htmlspecialchars($student['full_name']); ?></span>
                                    <span class="text-muted">
                                        <?php
                                        $grades = isset($student_grades[$student['id']]) ? $student_grades[$student['id']] : [];
                                        $total_points = 0;
                                        $max_points = 0;
                                        $assignment_count = is_array($grades) ? count($grades) : 0;

                                        // Calculate by assignment type
                                        $type_stats = [];
                                        if (is_array($grades)) {
                                            foreach ($grades as $grade) {
                                                $total_points += $grade['grade_value'];
                                                $max_points += $grade['max_grade'];

                                                // Extract assignment type from assignment name
                                                if (preg_match('/\[(.*?)\]/', $grade['assignment_name'], $matches)) {
                                                    $type = $matches[1];
                                                    if (!isset($type_stats[$type])) {
                                                        $type_stats[$type] = ['points' => 0, 'max' => 0, 'count' => 0];
                                                    }
                                                    $type_stats[$type]['points'] += $grade['grade_value'];
                                                    $type_stats[$type]['max'] += $grade['max_grade'];
                                                    $type_stats[$type]['count']++;
                                                }
                                            }
                                        }

                                        if ($max_points > 0) {
                                            $percentage = round(($total_points / $max_points) * 100, 1);
                                            $letter_grade = '';
                                            if ($percentage >= 90) $letter_grade = 'A';
                                            elseif ($percentage >= 80) $letter_grade = 'B';
                                            elseif ($percentage >= 70) $letter_grade = 'C';
                                            elseif ($percentage >= 60) $letter_grade = 'D';
                                            else $letter_grade = 'F';

                                            echo "<strong>Overall: $percentage% ($letter_grade)</strong> | $assignment_count assignments";
                                        } else {
                                            echo "No grades yet";
                                        }
                                        ?>
                                    </span>
                                </div>
                            </button>
                        </h2>
                        <div id="collapse<?php echo $student['id']; ?>" class="accordion-collapse collapse <?php echo ($index === 0) ? 'show' : ''; ?>"
                             aria-labelledby="heading<?php echo $student['id']; ?>" data-bs-parent="#studentsAccordion">
                            <div class="accordion-body">
                                <!-- Grade Summary Section -->
                                <?php
                                $current_student_grades = isset($student_grades[$student['id']]) ? $student_grades[$student['id']] : [];
                                $has_grades = is_array($current_student_grades) && count($current_student_grades) > 0;
                                ?>
                                <?php if ($has_grades): ?>
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6 class="card-title">Grade Summary for <?php echo htmlspecialchars($student['full_name']); ?></h6>
                                                <div class="row">
                                                    <?php
                                                    $grades = $current_student_grades;
                                                    $total_points = 0;
                                                    $max_points = 0;
                                                    $type_stats = [];

                                                    foreach ($grades as $grade) {
                                                        $total_points += $grade['grade_value'];
                                                        $max_points += $grade['max_grade'];

                                                        if (preg_match('/\[(.*?)\]/', $grade['assignment_name'], $matches)) {
                                                            $type = $matches[1];
                                                            if (!isset($type_stats[$type])) {
                                                                $type_stats[$type] = ['points' => 0, 'max' => 0, 'count' => 0];
                                                            }
                                                            $type_stats[$type]['points'] += $grade['grade_value'];
                                                            $type_stats[$type]['max'] += $grade['max_grade'];
                                                            $type_stats[$type]['count']++;
                                                        }
                                                    }

                                                    $overall_percentage = ($max_points > 0) ? round(($total_points / $max_points) * 100, 1) : 0;
                                                    ?>

                                                    <div class="col-md-3">
                                                        <div class="text-center">
                                                            <h4 class="text-primary"><?php echo $overall_percentage; ?>%</h4>
                                                            <small class="text-muted">Overall Grade</small>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-3">
                                                        <div class="text-center">
                                                            <h4 class="text-info"><?php echo count($grades); ?></h4>
                                                            <small class="text-muted">Total Assignments</small>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <strong>By Category:</strong><br>
                                                        <?php foreach ($type_stats as $type => $stats):
                                                            $type_percentage = ($stats['max'] > 0) ? round(($stats['points'] / $stats['max']) * 100, 1) : 0;
                                                        ?>
                                                            <span class="badge bg-secondary me-1"><?php echo $type; ?>: <?php echo $type_percentage; ?>% (<?php echo $stats['count']; ?>)</span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div class="mb-3">
                                    <button type="button" class="btn btn-primary btn-sm"
                                            data-bs-toggle="modal" data-bs-target="#addGradeModal<?php echo $student['id']; ?>">
                                        <i class="fas fa-plus me-2"></i>Add Grade
                                    </button>
                                </div>

                                <?php if ($has_grades): ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Type</th>
                                                    <th>Assignment</th>
                                                    <th>Grade</th>
                                                    <th>Percentage</th>
                                                    <th>Date</th>
                                                    <th>Comments</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($current_student_grades as $grade):
                                                    // Extract type and name from assignment_name
                                                    $assignment_display = $grade['assignment_name'];
                                                    $assignment_type = 'Assignment';
                                                    if (preg_match('/\[(.*?)\]\s*(.*)/', $grade['assignment_name'], $matches)) {
                                                        $assignment_type = $matches[1];
                                                        $assignment_display = $matches[2];
                                                    }

                                                    $percentage = round(($grade['grade_value'] / $grade['max_grade']) * 100, 1);
                                                    $percentage_class = '';
                                                    if ($percentage >= 90) $percentage_class = 'text-success';
                                                    elseif ($percentage >= 80) $percentage_class = 'text-info';
                                                    elseif ($percentage >= 70) $percentage_class = 'text-warning';
                                                    else $percentage_class = 'text-danger';
                                                ?>
                                                    <tr>
                                                        <td>
                                                            <?php
                                                            $type_badge_class = '';
                                                            switch(strtolower($assignment_type)) {
                                                                case 'exam': $type_badge_class = 'bg-danger'; break;
                                                                case 'quiz': $type_badge_class = 'bg-warning'; break;
                                                                case 'homework': $type_badge_class = 'bg-info'; break;
                                                                case 'project': $type_badge_class = 'bg-success'; break;
                                                                case 'lab': $type_badge_class = 'bg-primary'; break;
                                                                default: $type_badge_class = 'bg-secondary';
                                                            }
                                                            ?>
                                                            <span class="badge <?php echo $type_badge_class; ?>"><?php echo htmlspecialchars($assignment_type); ?></span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($assignment_display); ?></td>
                                                        <td><?php echo htmlspecialchars($grade['grade_value']) . ' / ' . htmlspecialchars($grade['max_grade']); ?></td>
                                                        <td><strong class="<?php echo $percentage_class; ?>"><?php echo $percentage; ?>%</strong></td>
                                                        <td><?php echo date('M d, Y', strtotime($grade['graded_date'])); ?></td>
                                                        <td><?php echo htmlspecialchars($grade['comments'] ?? ''); ?></td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-primary"
                                                                    data-bs-toggle="modal" data-bs-target="#editGradeModal<?php echo $grade['id']; ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-danger"
                                                                    onclick="confirmDeleteGrade(<?php echo $grade['id']; ?>, '<?php echo htmlspecialchars($grade['assignment_name']); ?>')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </td>
                                                    </tr>

                                                    <!-- Edit Grade Modal -->
                                                    <div class="modal fade" id="editGradeModal<?php echo $grade['id']; ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Edit Grade</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <form method="post" action="">
                                                                    <div class="modal-body">
                                                                        <input type="hidden" name="action" value="update_grade">
                                                                        <input type="hidden" name="grade_id" value="<?php echo $grade['id']; ?>">

                                                                        <div class="mb-3">
                                                                            <label class="form-label">Assignment</label>
                                                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($grade['assignment_name']); ?>" readonly>
                                                                        </div>

                                                                        <div class="row">
                                                                            <div class="col-md-6 mb-3">
                                                                                <label for="grade_value" class="form-label">Grade Value*</label>
                                                                                <input type="number" class="form-control" id="grade_value" name="grade_value"
                                                                                       value="<?php echo htmlspecialchars($grade['grade_value']); ?>" step="0.01" min="0" required>
                                                                            </div>

                                                                            <div class="col-md-6 mb-3">
                                                                                <label for="max_grade" class="form-label">Max Grade*</label>
                                                                                <input type="number" class="form-control" id="max_grade" name="max_grade"
                                                                                       value="<?php echo htmlspecialchars($grade['max_grade']); ?>" step="0.01" min="0.01" required>
                                                                            </div>
                                                                        </div>

                                                                        <div class="mb-3">
                                                                            <label for="comments" class="form-label">Comments</label>
                                                                            <textarea class="form-control" id="comments" name="comments" rows="3"><?php echo htmlspecialchars($grade['comments'] ?? ''); ?></textarea>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>No grades recorded for this student yet.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Add Grade Modal -->
                    <div class="modal fade" id="addGradeModal<?php echo $student['id']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Add Grade for <?php echo htmlspecialchars($student['full_name']); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="post" action="">
                                    <div class="modal-body">
                                        <input type="hidden" name="action" value="add_grade">
                                        <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="assignment_type" class="form-label">Assignment Type*</label>
                                                <select class="form-select" id="assignment_type" name="assignment_type" required>
                                                    <option value="Assignment">Assignment</option>
                                                    <option value="Quiz">Quiz</option>
                                                    <option value="Exam">Exam</option>
                                                    <option value="Homework">Homework</option>
                                                    <option value="Project">Project</option>
                                                    <option value="Lab">Lab</option>
                                                    <option value="Presentation">Presentation</option>
                                                    <option value="Participation">Participation</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="assignment_name" class="form-label">Assignment Name*</label>
                                                <input type="text" class="form-control" id="assignment_name" name="assignment_name"
                                                       placeholder="e.g., Midterm, Chapter 5 Quiz" required>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="grade_value" class="form-label">Grade Value*</label>
                                                <input type="number" class="form-control" id="grade_value" name="grade_value" step="0.01" min="0" required>
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <label for="max_grade" class="form-label">Max Grade*</label>
                                                <input type="number" class="form-control" id="max_grade" name="max_grade" step="0.01" min="0.01" value="100" required>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="comments" class="form-label">Comments</label>
                                            <textarea class="form-control" id="comments" name="comments" rows="3"></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Add Grade</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>No students are currently enrolled in this course.
                <a href="course_students.php?course_id=<?php echo $course_id; ?>" class="alert-link">Click here</a> to enroll students.
            </div>
        <?php endif; ?>
    </div>

    <!-- Delete Grade Form -->
    <form id="deleteGradeForm" method="post" action="" style="display: none;">
        <input type="hidden" name="action" value="delete_grade">
        <input type="hidden" id="delete_grade_id" name="grade_id" value="">
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
        function confirmDeleteGrade(gradeId, assignmentName) {
            if (confirm(`Are you sure you want to delete the grade for "${assignmentName}"? This action cannot be undone.`)) {
                document.getElementById('delete_grade_id').value = gradeId;
                document.getElementById('deleteGradeForm').submit();
            }
        }
    </script>
</body>
</html>
