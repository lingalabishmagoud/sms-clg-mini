
<?php
// Start session
session_start();

// For testing purposes, we're not enforcing authentication
$student_id = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : 1;
$student_name = isset($_SESSION['student_name']) ? $_SESSION['student_name'] : 'Test Student';

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get student information
$student = null;
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
        'email' => 'student@example.com',
        'department' => 'Cyber Security',
        'section' => 'CS-A',
        'year' => 3,
        'semester' => 1
    ];
}
$stmt->close();

// Get student's enrolled subjects
$subjects = [];
$stmt = $conn->prepare("
    SELECT s.id, s.subject_name, s.abbreviation, s.credits, f.full_name as faculty_name
    FROM subjects s
    LEFT JOIN faculty f ON s.faculty_id = f.id
    JOIN student_subject_enrollment sse ON s.id = sse.subject_id
    WHERE sse.student_id = ? AND sse.status = 'active'
    ORDER BY s.subject_name
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}
$stmt->close();

// Get study materials for enrolled subjects
$materials = [];
if (!empty($subjects)) {
    $subject_ids = array_column($subjects, 'id');
    $placeholders = str_repeat('?,', count($subject_ids) - 1) . '?';
    
    $query = "SELECT f.*, s.subject_name, s.abbreviation, fa.full_name as uploaded_by_name
              FROM files f 
              JOIN subjects s ON f.subject_id = s.id 
              LEFT JOIN faculty fa ON f.uploaded_by = fa.id AND f.uploaded_by_type = 'faculty'
              WHERE f.subject_id IN ($placeholders) 
              AND f.visibility IN ('students', 'public')
              ORDER BY f.uploaded_at DESC";
    
    $stmt = $conn->prepare($query);
    $types = str_repeat('i', count($subject_ids));
    $stmt->bind_param($types, ...$subject_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $materials[] = $row;
    }
    $stmt->close();
}

// Group materials by subject
$materials_by_subject = [];
foreach ($materials as $material) {
    $subject_key = $material['abbreviation'];
    if (!isset($materials_by_subject[$subject_key])) {
        $materials_by_subject[$subject_key] = [
            'subject_name' => $material['subject_name'],
            'materials' => []
        ];
    }
    $materials_by_subject[$subject_key]['materials'][] = $material;
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
    <title>Study Materials - Student Portal</title>
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
            <a class="navbar-brand" href="#">Student Portal</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="student_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_subjects.php">My Subjects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_schedule.php">Schedule</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="student_materials.php">Study Materials</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student_grades.php">Grades</a>
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
                <h2><i class="fas fa-book me-2"></i>Study Materials</h2>
                <p class="text-muted">Access study materials uploaded by your faculty</p>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h4><?php echo count($materials); ?></h4>
                        <p class="mb-0">Total Materials</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Materials Overview -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h4><?php echo count($subjects); ?></h4>
                        <p class="mb-0">Enrolled Subjects</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h4><?php echo count($materials_by_subject); ?></h4>
                        <p class="mb-0">Subjects with Materials</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <h4><?php echo array_sum(array_column($materials, 'file_size')); ?></h4>
                        <p class="mb-0">Total Size (bytes)</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h4><?php echo htmlspecialchars($student['section']); ?></h4>
                        <p class="mb-0">Section</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Materials by Subject -->
        <?php if (!empty($materials_by_subject)): ?>
            <?php foreach ($materials_by_subject as $subject_code => $subject_data): ?>
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-book me-2"></i>
                            <?php echo htmlspecialchars($subject_code . ' - ' . $subject_data['subject_name']); ?>
                            <span class="badge bg-light text-dark ms-2"><?php echo count($subject_data['materials']); ?> Materials</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>File Name</th>
                                        <th>Size</th>
                                        <th>Uploaded By</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subject_data['materials'] as $material): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($material['title']); ?></strong>
                                            <?php if (!empty($material['description'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($material['description']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($material['file_name']); ?></td>
                                        <td><?php echo round($material['file_size'] / 1024, 2); ?> KB</td>
                                        <td><?php echo htmlspecialchars($material['uploaded_by_name'] ?? 'Faculty'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($material['uploaded_at'])); ?></td>
                                        <td>
                                            <a href="<?php echo $material['file_path']; ?>" class="btn btn-sm btn-primary" target="_blank">
                                                <i class="fas fa-download me-1"></i>Download
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-book fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Study Materials Available</h5>
                    <p class="text-muted">Your faculty haven't uploaded any study materials yet, or you may not be enrolled in any subjects.</p>
                    <a href="student_subjects.php" class="btn btn-primary">
                        <i class="fas fa-book me-2"></i>View My Subjects
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>Student Portal</h5>
                    <p>Access your study materials and resources</p>
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

<?php $conn->close(); ?>

