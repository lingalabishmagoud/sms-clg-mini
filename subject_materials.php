<?php
// Start session
session_start();

// For testing purposes, we're not enforcing authentication
$student_id = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : 1;
$student_name = isset($_SESSION['student_name']) ? $_SESSION['student_name'] : 'Test Student';

// Get subject ID from URL
$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get subject information
$subject = null;
if ($subject_id > 0) {
    $stmt = $conn->prepare("SELECT s.*, f.full_name as faculty_name FROM subjects s LEFT JOIN faculty f ON s.faculty_id = f.id WHERE s.id = ?");
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $subject = $result->fetch_assoc();
    }
    $stmt->close();
}

// If no subject found, redirect to main materials page
if (!$subject) {
    header("Location: student_materials.php");
    exit();
}

// Get study materials for this subject
$materials = [];
$stmt = $conn->prepare("
    SELECT f.*, fa.full_name as uploaded_by_name
    FROM files f 
    LEFT JOIN faculty fa ON f.uploaded_by = fa.id AND f.uploaded_by_type = 'faculty'
    WHERE f.subject_id = ? AND f.visibility IN ('students', 'public')
    ORDER BY f.uploaded_at DESC
");
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $materials[] = $row;
}
$stmt->close();

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
    <title><?php echo htmlspecialchars($subject['abbreviation']); ?> - Study Materials</title>
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
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        Welcome, <?php echo htmlspecialchars($student_name); ?>
                    </span>
                    <a href="?logout=1" class="btn btn-light btn-sm">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="student_dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="student_materials.php">Study Materials</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($subject['abbreviation']); ?></li>
            </ol>
        </nav>

        <!-- Subject Header -->
        <div class="card mb-4 bg-primary text-white">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <h2><i class="fas fa-book me-2"></i><?php echo htmlspecialchars($subject['abbreviation']); ?></h2>
                        <h5><?php echo htmlspecialchars($subject['subject_name']); ?></h5>
                        <p class="mb-1"><strong>Faculty:</strong> <?php echo htmlspecialchars($subject['faculty_name'] ?: 'Not Assigned'); ?></p>
                        <p class="mb-0"><strong>Credits:</strong> <?php echo $subject['credits']; ?></p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="bg-white text-dark p-3 rounded">
                            <h3 class="mb-1"><?php echo count($materials); ?></h3>
                            <small>Study Materials</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Materials List -->
        <?php if (!empty($materials)): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-file-alt me-2"></i>Study Materials
                        <span class="badge bg-primary ms-2"><?php echo count($materials); ?> Files</span>
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
                                <?php foreach ($materials as $material): ?>
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
        <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Study Materials Available</h5>
                    <p class="text-muted">Your faculty haven't uploaded any study materials for this subject yet.</p>
                    <a href="student_materials.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to All Materials
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-tasks me-2"></i>Quick Actions</h5>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="student_materials.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-book-open me-2"></i>All Materials
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="subject_forums.php?subject_id=<?php echo $subject['id']; ?>&user_type=student" class="btn btn-outline-info w-100">
                                    <i class="fas fa-comments me-2"></i>Subject Forum
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="student_subjects.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-book me-2"></i>My Subjects
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="student_dashboard.php" class="btn btn-outline-success w-100">
                                    <i class="fas fa-home me-2"></i>Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
