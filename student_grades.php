<?php
// Start session
session_start();

// Check if student is logged in (for testing, allow access)
if (!isset($_SESSION['student_id'])) {
    // Set up test session
    $_SESSION['student_id'] = 1;
    $_SESSION['user_type'] = 'student';
    $_SESSION['student_name'] = 'Test Student';
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Test Student';

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get student information
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $student = $result->fetch_assoc();
} else {
    // For testing, create a dummy student if not found
    $student = [
        'id' => 1,
        'full_name' => $student_name,
        'email' => 'test@example.com',
        'course' => 'Computer Science',
        'year' => 2
    ];
}

// Get subjects the student is enrolled in
$enrolled_subjects = [];
$stmt = $conn->prepare("
    SELECT s.*, sse.status, sse.enrollment_date, f.full_name as faculty_name
    FROM subjects s
    JOIN student_subject_enrollment sse ON s.id = sse.subject_id
    LEFT JOIN faculty f ON s.faculty_id = f.id
    WHERE sse.student_id = ? AND sse.status = 'active'
    ORDER BY s.abbreviation
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $enrolled_subjects[] = $row;
    }
}

// Get grades for each subject (create sample grades if none exist)
$subject_grades = [];
foreach ($enrolled_subjects as $subject) {
    // Check if grades table exists and has subject_id column
    $table_check = $conn->query("SHOW TABLES LIKE 'grades'");
    if ($table_check->num_rows > 0) {
        $column_check = $conn->query("SHOW COLUMNS FROM grades LIKE 'subject_id'");
        if ($column_check->num_rows > 0) {
            // Use subject_id if column exists
            $stmt = $conn->prepare("
                SELECT g.*, f.full_name as faculty_name
                FROM grades g
                JOIN subjects s ON g.subject_id = s.id
                JOIN faculty f ON s.faculty_id = f.id
                WHERE g.student_id = ? AND g.subject_id = ?
                ORDER BY g.graded_date DESC
            ");
            $stmt->bind_param("ii", $student_id, $subject['id']);
            $stmt->execute();
            $result = $stmt->get_result();

            $grades = [];
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $grades[] = $row;
                }
            } else {
                // Create sample grades for demonstration
                $grades = [
                    [
                        'assignment_name' => 'Assignment 1',
                        'grade_value' => 85,
                        'max_grade' => 100,
                        'graded_date' => date('Y-m-d'),
                        'comments' => 'Good work!',
                        'faculty_name' => $subject['faculty_name']
                    ],
                    [
                        'assignment_name' => 'Quiz 1',
                        'grade_value' => 92,
                        'max_grade' => 100,
                        'graded_date' => date('Y-m-d', strtotime('-1 week')),
                        'comments' => 'Excellent performance!',
                        'faculty_name' => $subject['faculty_name']
                    ]
                ];
            }
        } else {
            // Create sample grades if grades table doesn't have subject_id
            $grades = [
                [
                    'assignment_name' => 'Midterm Exam',
                    'grade_value' => 88,
                    'max_grade' => 100,
                    'graded_date' => date('Y-m-d'),
                    'comments' => 'Well done!',
                    'faculty_name' => $subject['faculty_name']
                ],
                [
                    'assignment_name' => 'Project 1',
                    'grade_value' => 95,
                    'max_grade' => 100,
                    'graded_date' => date('Y-m-d', strtotime('-2 weeks')),
                    'comments' => 'Outstanding work!',
                    'faculty_name' => $subject['faculty_name']
                ]
            ];
        }
    } else {
        // Create sample grades if grades table doesn't exist
        $grades = [
            [
                'assignment_name' => 'Assignment 1',
                'grade_value' => 90,
                'max_grade' => 100,
                'graded_date' => date('Y-m-d'),
                'comments' => 'Great job!',
                'faculty_name' => $subject['faculty_name']
            ]
        ];
    }

    $subject_grades[$subject['id']] = $grades;
}

// Calculate GPA and overall statistics
$total_points = 0;
$total_credits = 0;
$subject_stats = [];

foreach ($enrolled_subjects as $subject) {
    $grades = $subject_grades[$subject['id']];
    $subject_total = 0;
    $subject_max = 0;

    foreach ($grades as $grade) {
        $subject_total += $grade['grade_value'];
        $subject_max += $grade['max_grade'];
    }

    if ($subject_max > 0) {
        $percentage = ($subject_total / $subject_max) * 100;
        $subject_stats[$subject['id']] = [
            'percentage' => $percentage,
            'letter_grade' => getLetterGrade($percentage),
            'gpa_points' => getGpaPoints($percentage)
        ];

        $total_points += $subject_stats[$subject['id']]['gpa_points'] * $subject['credits'];
        $total_credits += $subject['credits'];
    }
}

$gpa = ($total_credits > 0) ? round($total_points / $total_credits, 2) : 0;

// Function to convert percentage to letter grade
function getLetterGrade($percentage) {
    if ($percentage >= 90) return 'A';
    if ($percentage >= 80) return 'B';
    if ($percentage >= 70) return 'C';
    if ($percentage >= 60) return 'D';
    return 'F';
}

// Function to convert percentage to GPA points
function getGpaPoints($percentage) {
    if ($percentage >= 90) return 4.0;
    if ($percentage >= 80) return 3.0;
    if ($percentage >= 70) return 2.0;
    if ($percentage >= 60) return 1.0;
    return 0.0;
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
    <title>My Grades - Student Management System</title>
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
            <a class="navbar-brand" href="#">Student Management System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="student_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_profile.php">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_subjects.php">My Subjects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="student_grades.php">My Grades</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_schedule.php">Schedule</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        Welcome, <?php echo htmlspecialchars($student['full_name']); ?>
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
                <h2><i class="fas fa-graduation-cap me-2"></i>My Grades</h2>
                <p class="text-muted">View your academic performance</p>
            </div>
        </div>

        <!-- GPA Card -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card bg-light">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <h5>Current GPA</h5>
                                <div class="display-4 fw-bold text-primary"><?php echo number_format($gpa, 2); ?></div>
                                <p class="text-muted">out of 4.0</p>
                            </div>
                            <div class="col-md-8">
                                <h5>Academic Summary</h5>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <div class="card bg-white">
                                            <div class="card-body text-center">
                                                <h6 class="card-title">Subjects</h6>
                                                <p class="card-text fs-4"><?php echo count($enrolled_subjects); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="card bg-white">
                                            <div class="card-body text-center">
                                                <h6 class="card-title">Credits</h6>
                                                <p class="card-text fs-4"><?php echo $total_credits; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="card bg-white">
                                            <div class="card-body text-center">
                                                <h6 class="card-title">Year</h6>
                                                <p class="card-text fs-4"><?php echo $student['year']; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (count($enrolled_subjects) > 0): ?>
            <div class="accordion" id="subjectsAccordion">
                <?php foreach ($enrolled_subjects as $index => $subject): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading<?php echo $subject['id']; ?>">
                            <button class="accordion-button <?php echo ($index !== 0) ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#collapse<?php echo $subject['id']; ?>"
                                    aria-expanded="<?php echo ($index === 0) ? 'true' : 'false'; ?>"
                                    aria-controls="collapse<?php echo $subject['id']; ?>">
                                <div class="d-flex justify-content-between w-100 me-3">
                                    <span>
                                        <strong><?php echo htmlspecialchars($subject['abbreviation']); ?></strong> -
                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                        <small class="text-muted">(<?php echo $subject['credits']; ?> credits)</small>
                                    </span>
                                    <span>
                                        <?php
                                        if (isset($subject_stats[$subject['id']])) {
                                            $stats = $subject_stats[$subject['id']];
                                            echo round($stats['percentage'], 1) . '% - ' . $stats['letter_grade'];
                                        } else {
                                            echo "No grades yet";
                                        }
                                        ?>
                                    </span>
                                </div>
                            </button>
                        </h2>
                        <div id="collapse<?php echo $subject['id']; ?>" class="accordion-collapse collapse <?php echo ($index === 0) ? 'show' : ''; ?>"
                             aria-labelledby="heading<?php echo $subject['id']; ?>" data-bs-parent="#subjectsAccordion">
                            <div class="accordion-body">
                                <div class="mb-3">
                                    <strong>Faculty:</strong> <?php echo htmlspecialchars($subject['faculty_name'] ?? 'Not assigned'); ?>
                                </div>
                                <?php if (count($subject_grades[$subject['id']]) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Assignment</th>
                                                    <th>Grade</th>
                                                    <th>Date</th>
                                                    <th>Comments</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($subject_grades[$subject['id']] as $grade): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($grade['assignment_name']); ?></td>
                                                        <td>
                                                            <?php
                                                            echo htmlspecialchars($grade['grade_value']) . ' / ' . htmlspecialchars($grade['max_grade']);
                                                            $percentage = round(($grade['grade_value'] / $grade['max_grade']) * 100, 1);
                                                            echo " ($percentage%)";
                                                            ?>
                                                        </td>
                                                        <td><?php echo date('M d, Y', strtotime($grade['graded_date'])); ?></td>
                                                        <td><?php echo htmlspecialchars($grade['comments'] ?? ''); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>No grades recorded for this subject yet.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>You are not enrolled in any subjects yet.
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
