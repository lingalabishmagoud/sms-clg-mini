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
    header("Location: faculty_login.php");
    exit();
}

// Handle form submissions for grade updates
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_grade' && isset($_POST['grade_id']) && isset($_POST['grade_value']) && isset($_POST['max_grade'])) {
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
            $stmt = $conn->prepare("UPDATE grades SET grade_value = ?, max_grade = ?, comments = ? WHERE id = ?");
            $stmt->bind_param("ddsi", $grade_value, $max_grade, $comments, $grade_id);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = "Grade updated successfully";
                $message_type = "success";
            } else {
                $message = "Error updating grade or no changes made";
                $message_type = "warning";
            }
        }
    } elseif ($_POST['action'] === 'add_grade' && isset($_POST['student_id']) && isset($_POST['course_id'])) {
        $student_id = (int)$_POST['student_id'];
        $course_id = (int)$_POST['course_id'];
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
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$grade_filter = isset($_GET['grade_filter']) ? $_GET['grade_filter'] : '';
$course_filter = isset($_GET['course_filter']) ? (int)$_GET['course_filter'] : 0;

// Get all courses taught by this faculty for filter dropdown
$faculty_courses = [];
$stmt = $conn->prepare("SELECT * FROM courses WHERE faculty_id = ? ORDER BY course_code");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $faculty_courses[] = $row;
}

// Build the main query with search and filters
$where_conditions = [];
$params = [];
$param_types = "";

// Base query to get all students with their grades and courses
$base_query = "
    SELECT DISTINCT
        s.id as student_id,
        s.full_name as student_name,
        s.email as student_email,
        s.student_id as student_number,
        c.id as course_id,
        c.course_name,
        c.course_code,
        g.id as grade_id,
        g.assignment_name,
        g.grade_value,
        g.max_grade,
        g.comments,
        g.graded_date
    FROM students s
    JOIN enrollments e ON s.id = e.student_id
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN grades g ON s.id = g.student_id AND c.id = g.course_id
    WHERE c.faculty_id = ? AND e.status != 'dropped'
";

$where_conditions[] = "c.faculty_id = ?";
$params[] = $faculty_id;
$param_types .= "i";

// Add search condition
if (!empty($search)) {
    $where_conditions[] = "(s.full_name LIKE ? OR s.email LIKE ? OR s.student_id LIKE ? OR c.course_name LIKE ? OR c.course_code LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    $param_types .= "sssss";
}

// Add course filter
if ($course_filter > 0) {
    $where_conditions[] = "c.id = ?";
    $params[] = $course_filter;
    $param_types .= "i";
}

// Construct final query
$query = "
    SELECT DISTINCT
        s.id as student_id,
        s.full_name as student_name,
        s.email as student_email,
        s.student_id as student_number,
        c.id as course_id,
        c.course_name,
        c.course_code
    FROM students s
    JOIN enrollments e ON s.id = e.student_id
    JOIN courses c ON e.course_id = c.id
    WHERE " . implode(" AND ", $where_conditions) . "
    ORDER BY s.full_name, c.course_code
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$students_data = [];
while ($row = $result->fetch_assoc()) {
    $students_data[] = $row;
}

// Get grades for each student-course combination
$students_with_grades = [];
foreach ($students_data as $student_course) {
    $student_id = $student_course['student_id'];
    $course_id = $student_course['course_id'];

    // Get grades for this student in this course
    $stmt = $conn->prepare("
        SELECT * FROM grades
        WHERE student_id = ? AND course_id = ?
        ORDER BY graded_date DESC
    ");
    $stmt->bind_param("ii", $student_id, $course_id);
    $stmt->execute();
    $grades_result = $stmt->get_result();

    $grades = [];
    $total_points = 0;
    $max_points = 0;

    while ($grade = $grades_result->fetch_assoc()) {
        $grades[] = $grade;
        $total_points += $grade['grade_value'];
        $max_points += $grade['max_grade'];
    }

    // Calculate overall percentage and letter grade
    $percentage = ($max_points > 0) ? round(($total_points / $max_points) * 100, 1) : 0;
    $letter_grade = '';
    if ($max_points > 0) {
        if ($percentage >= 90) $letter_grade = 'A';
        elseif ($percentage >= 80) $letter_grade = 'B';
        elseif ($percentage >= 70) $letter_grade = 'C';
        elseif ($percentage >= 60) $letter_grade = 'D';
        else $letter_grade = 'F';
    }

    // Apply grade filter
    if (!empty($grade_filter) && $grade_filter !== $letter_grade && $max_points > 0) {
        continue;
    }

    $student_course['grades'] = $grades;
    $student_course['total_points'] = $total_points;
    $student_course['max_points'] = $max_points;
    $student_course['percentage'] = $percentage;
    $student_course['letter_grade'] = $letter_grade;
    $student_course['has_grades'] = count($grades) > 0;

    $students_with_grades[] = $student_course;
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
    <title>All Students Grades - Student Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .grade-badge {
            font-size: 0.9em;
            padding: 0.3em 0.6em;
        }
        .search-filters {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .student-row {
            border-left: 4px solid #dee2e6;
        }
        .student-row.grade-a { border-left-color: #28a745; }
        .student-row.grade-b { border-left-color: #17a2b8; }
        .student-row.grade-c { border-left-color: #ffc107; }
        .student-row.grade-d { border-left-color: #fd7e14; }
        .student-row.grade-f { border-left-color: #dc3545; }
        .student-row.no-grade { border-left-color: #6c757d; }
    </style>
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
                        <a class="nav-link" href="all_students_grades.php">All Students Grades</a>
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
                <h2><i class="fas fa-chart-line me-2"></i>All Students Grades Management</h2>
                <p class="text-muted">Search, filter, and manage grades for all students in your courses</p>
            </div>
            <div class="col-md-4 text-end">
                <a href="faculty_grades.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Course Grades
                </a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Search and Filter Section -->
        <div class="search-filters">
            <form method="GET" action="">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="search" class="form-label">Search Students/Courses</label>
                        <input type="text" class="form-control" id="search" name="search"
                               value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Name, email, student ID, course...">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="course_filter" class="form-label">Filter by Course</label>
                        <select class="form-select" id="course_filter" name="course_filter">
                            <option value="">All Courses</option>
                            <?php foreach ($faculty_courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>"
                                        <?php echo ($course_filter == $course['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="grade_filter" class="form-label">Filter by Grade</label>
                        <select class="form-select" id="grade_filter" name="grade_filter">
                            <option value="">All Grades</option>
                            <option value="A" <?php echo ($grade_filter === 'A') ? 'selected' : ''; ?>>A (90-100%)</option>
                            <option value="B" <?php echo ($grade_filter === 'B') ? 'selected' : ''; ?>>B (80-89%)</option>
                            <option value="C" <?php echo ($grade_filter === 'C') ? 'selected' : ''; ?>>C (70-79%)</option>
                            <option value="D" <?php echo ($grade_filter === 'D') ? 'selected' : ''; ?>>D (60-69%)</option>
                            <option value="F" <?php echo ($grade_filter === 'F') ? 'selected' : ''; ?>>F (Below 60%)</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <a href="all_students_grades.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-times me-2"></i>Clear Filters
                        </a>
                        <span class="text-muted ms-3">
                            Showing <?php echo count($students_with_grades); ?> results
                        </span>
                    </div>
                </div>
            </form>
        </div>

        <!-- Students Grades Table -->
        <?php if (count($students_with_grades) > 0): ?>
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-table me-2"></i>Students Grades Overview</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th>Course</th>
                                <th>Overall Grade</th>
                                <th>Assignments</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students_with_grades as $student):
                                $grade_class = '';
                                if ($student['has_grades']) {
                                    $grade_class = 'grade-' . strtolower($student['letter_grade']);
                                } else {
                                    $grade_class = 'no-grade';
                                }
                            ?>
                                <tr class="student-row <?php echo $grade_class; ?>">
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($student['student_name']); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                ID: <?php echo htmlspecialchars($student['student_number']); ?> |
                                                <?php echo htmlspecialchars($student['student_email']); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($student['course_code']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($student['course_name']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($student['has_grades']): ?>
                                            <div class="text-center">
                                                <h5 class="mb-1">
                                                    <span class="badge grade-badge
                                                        <?php
                                                        switch($student['letter_grade']) {
                                                            case 'A': echo 'bg-success'; break;
                                                            case 'B': echo 'bg-info'; break;
                                                            case 'C': echo 'bg-warning'; break;
                                                            case 'D': echo 'bg-orange'; break;
                                                            case 'F': echo 'bg-danger'; break;
                                                            default: echo 'bg-secondary';
                                                        }
                                                        ?>">
                                                        <?php echo $student['letter_grade']; ?>
                                                    </span>
                                                </h5>
                                                <small class="text-muted"><?php echo $student['percentage']; ?>%</small>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo $student['total_points']; ?>/<?php echo $student['max_points']; ?> pts
                                                </small>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center">
                                                <span class="badge bg-secondary grade-badge">No Grades</span>
                                                <br>
                                                <small class="text-muted">Not graded yet</small>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo count($student['grades']); ?></strong> assignments
                                            <?php if (count($student['grades']) > 0): ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?php
                                                    $types = [];
                                                    foreach ($student['grades'] as $grade) {
                                                        if (preg_match('/\[(.*?)\]/', $grade['assignment_name'], $matches)) {
                                                            $types[] = $matches[1];
                                                        }
                                                    }
                                                    $unique_types = array_unique($types);
                                                    echo implode(', ', array_slice($unique_types, 0, 3));
                                                    if (count($unique_types) > 3) echo '...';
                                                    ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (count($student['grades']) > 0): ?>
                                            <?php
                                            $latest_grade = $student['grades'][0]; // Already ordered by graded_date DESC
                                            echo date('M d, Y', strtotime($latest_grade['graded_date']));
                                            ?>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($latest_grade['assignment_name']); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">No grades yet</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group-vertical btn-group-sm" role="group">
                                            <button type="button" class="btn btn-primary btn-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#viewGradesModal<?php echo $student['student_id']; ?>_<?php echo $student['course_id']; ?>">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button type="button" class="btn btn-success btn-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#addGradeModal<?php echo $student['student_id']; ?>_<?php echo $student['course_id']; ?>">
                                                <i class="fas fa-plus"></i> Add
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            No students found matching your search criteria.
            <a href="all_students_grades.php" class="alert-link">Clear filters</a> to see all students.
        </div>
        <?php endif; ?>

        <!-- Modals for each student-course combination -->
        <?php foreach ($students_with_grades as $student): ?>
            <!-- View Grades Modal -->
            <div class="modal fade" id="viewGradesModal<?php echo $student['student_id']; ?>_<?php echo $student['course_id']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                Grades for <?php echo htmlspecialchars($student['student_name']); ?> -
                                <?php echo htmlspecialchars($student['course_code']); ?>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <?php if (count($student['grades']) > 0): ?>
                                <div class="mb-3">
                                    <div class="row">
                                        <div class="col-md-4 text-center">
                                            <h4 class="text-primary"><?php echo $student['percentage']; ?>%</h4>
                                            <small class="text-muted">Overall Grade</small>
                                        </div>
                                        <div class="col-md-4 text-center">
                                            <h4 class="text-info"><?php echo $student['letter_grade']; ?></h4>
                                            <small class="text-muted">Letter Grade</small>
                                        </div>
                                        <div class="col-md-4 text-center">
                                            <h4 class="text-success"><?php echo count($student['grades']); ?></h4>
                                            <small class="text-muted">Assignments</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Type</th>
                                                <th>Assignment</th>
                                                <th>Grade</th>
                                                <th>Percentage</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($student['grades'] as $grade):
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
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-primary"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#editGradeModal<?php echo $grade['id']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>No grades recorded for this student in this course yet.
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add Grade Modal -->
            <div class="modal fade" id="addGradeModal<?php echo $student['student_id']; ?>_<?php echo $student['course_id']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                Add Grade for <?php echo htmlspecialchars($student['student_name']); ?> -
                                <?php echo htmlspecialchars($student['course_code']); ?>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="post" action="">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="add_grade">
                                <input type="hidden" name="student_id" value="<?php echo $student['student_id']; ?>">
                                <input type="hidden" name="course_id" value="<?php echo $student['course_id']; ?>">

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="assignment_type" class="form-label">Assignment Type*</label>
                                        <select class="form-select" name="assignment_type" required>
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
                                        <input type="text" class="form-control" name="assignment_name"
                                               placeholder="e.g., Midterm, Chapter 5 Quiz" required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="grade_value" class="form-label">Grade Value*</label>
                                        <input type="number" class="form-control" name="grade_value" step="0.01" min="0" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="max_grade" class="form-label">Max Grade*</label>
                                        <input type="number" class="form-control" name="max_grade" step="0.01" min="0.01" value="100" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="comments" class="form-label">Comments</label>
                                    <textarea class="form-control" name="comments" rows="3"></textarea>
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

        <!-- Edit Grade Modals -->
        <?php foreach ($students_with_grades as $student): ?>
            <?php foreach ($student['grades'] as $grade): ?>
                <div class="modal fade" id="editGradeModal<?php echo $grade['id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    Edit Grade: <?php echo htmlspecialchars($grade['assignment_name']); ?>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="post" action="">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="update_grade">
                                    <input type="hidden" name="grade_id" value="<?php echo $grade['id']; ?>">

                                    <div class="mb-3">
                                        <label class="form-label">Student</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($student['student_name']); ?>" readonly>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Assignment</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($grade['assignment_name']); ?>" readonly>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="grade_value" class="form-label">Grade Value*</label>
                                            <input type="number" class="form-control" name="grade_value"
                                                   value="<?php echo $grade['grade_value']; ?>" step="0.01" min="0" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="max_grade" class="form-label">Max Grade*</label>
                                            <input type="number" class="form-control" name="max_grade"
                                                   value="<?php echo $grade['max_grade']; ?>" step="0.01" min="0.01" required>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="comments" class="form-label">Comments</label>
                                        <textarea class="form-control" name="comments" rows="3"><?php echo htmlspecialchars($grade['comments'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="alert alert-info">
                                        <small>
                                            <i class="fas fa-info-circle me-2"></i>
                                            Current: <?php echo $grade['grade_value']; ?>/<?php echo $grade['max_grade']; ?>
                                            (<?php echo round(($grade['grade_value'] / $grade['max_grade']) * 100, 1); ?>%)
                                        </small>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Update Grade</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Auto-refresh page after form submission to show updated data
        <?php if (!empty($message) && $message_type === 'success'): ?>
            setTimeout(function() {
                window.location.href = window.location.pathname + window.location.search;
            }, 2000);
        <?php endif; ?>

        // Add search functionality
        document.getElementById('search').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });
    </script>
</body>
</html>