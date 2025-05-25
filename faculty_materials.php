<?php
// Start session
session_start();

// For testing purposes, we're not enforcing authentication
$faculty_id = isset($_SESSION['faculty_id']) ? $_SESSION['faculty_id'] : 1;
$faculty_name = isset($_SESSION['faculty_name']) ? $_SESSION['faculty_name'] : 'Dr. K. Subba Rao';

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = '';
$message_type = 'success';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_material'])) {
    $subject_id = $_POST['subject_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $visibility = $_POST['visibility'];

    // File upload directory
    $upload_dir = "uploads/study_materials/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Check if file was uploaded without errors
    if (isset($_FILES["material_file"]) && $_FILES["material_file"]["error"] == 0) {
        $file_name = basename($_FILES["material_file"]["name"]);
        $file_type = $_FILES["material_file"]["type"];
        $file_size = $_FILES["material_file"]["size"];
        $file_tmp = $_FILES["material_file"]["tmp_name"];

        // Generate unique filename
        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
        $new_file_name = "material_" . $subject_id . "_" . time() . "." . $file_extension;
        $destination = $upload_dir . $new_file_name;

        // Check file size (limit to 10MB)
        if ($file_size <= 10000000) {
            // Check file type
            $allowed_types = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png'];
            if (in_array(strtolower($file_extension), $allowed_types)) {
                if (move_uploaded_file($file_tmp, $destination)) {
                    // Save file info to database
                    $stmt = $conn->prepare("INSERT INTO files (title, file_name, file_path, file_type, file_size, uploaded_by_type, uploaded_by, subject_id, description, visibility) VALUES (?, ?, ?, ?, ?, 'faculty', ?, ?, ?, ?)");
                    $stmt->bind_param("ssssiisss", $title, $file_name, $destination, $file_type, $file_size, $faculty_id, $subject_id, $description, $visibility);

                    if ($stmt->execute()) {
                        $message = "Study material uploaded successfully!";
                    } else {
                        $message = "Error saving file info: " . $stmt->error;
                        $message_type = 'danger';
                    }
                    $stmt->close();
                } else {
                    $message = "Error moving uploaded file.";
                    $message_type = 'danger';
                }
            } else {
                $message = "Invalid file type. Allowed types: PDF, DOC, DOCX, PPT, PPTX, TXT, JPG, JPEG, PNG";
                $message_type = 'danger';
            }
        } else {
            $message = "File size too large. Maximum size is 10MB.";
            $message_type = 'danger';
        }
    } else {
        $message = "Please select a file to upload.";
        $message_type = 'danger';
    }
}

// Handle file deletion
if (isset($_GET['delete_file'])) {
    $file_id = $_GET['delete_file'];

    // Get file info first
    $stmt = $conn->prepare("SELECT file_path FROM files WHERE id = ? AND uploaded_by = ? AND uploaded_by_type = 'faculty'");
    $stmt->bind_param("ii", $file_id, $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $file = $result->fetch_assoc();

        // Delete file from filesystem
        if (file_exists($file['file_path'])) {
            unlink($file['file_path']);
        }

        // Delete from database
        $delete_stmt = $conn->prepare("DELETE FROM files WHERE id = ? AND uploaded_by = ? AND uploaded_by_type = 'faculty'");
        $delete_stmt->bind_param("ii", $file_id, $faculty_id);

        if ($delete_stmt->execute()) {
            $message = "File deleted successfully!";
        } else {
            $message = "Error deleting file.";
            $message_type = 'danger';
        }
        $delete_stmt->close();
    }
    $stmt->close();
}

// Get faculty's subjects
$subjects = [];
$stmt = $conn->prepare("SELECT id, subject_name, abbreviation FROM subjects WHERE faculty_id = ? ORDER BY subject_name");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}
$stmt->close();

// Get uploaded materials
$materials = [];
if (!empty($subjects)) {
    $subject_ids = array_column($subjects, 'id');
    $placeholders = str_repeat('?,', count($subject_ids) - 1) . '?';

    $query = "SELECT f.*, s.subject_name, s.abbreviation
              FROM files f
              JOIN subjects s ON f.subject_id = s.id
              WHERE f.subject_id IN ($placeholders) AND f.uploaded_by = ? AND f.uploaded_by_type = 'faculty'
              ORDER BY f.uploaded_at DESC";

    $stmt = $conn->prepare($query);
    $types = str_repeat('i', count($subject_ids)) . 'i';
    $params = array_merge($subject_ids, [$faculty_id]);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $materials[] = $row;
    }
    $stmt->close();
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
    <title>Study Materials - Faculty Portal</title>
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
            <a class="navbar-brand" href="#">Faculty Portal</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_subjects.php">My Subjects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_schedule.php">Schedule</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="faculty_materials.php">Study Materials</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_attendance.php">Attendance</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        Welcome, <?php echo htmlspecialchars($faculty_name); ?>
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
                <h2><i class="fas fa-book me-2"></i>Study Materials Management</h2>
                <p class="text-muted">Upload and manage study materials for your subjects</p>
            </div>
            <div class="col-md-4 text-md-end">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadMaterialModal">
                    <i class="fas fa-upload me-2"></i>Upload Material
                </button>
            </div>
        </div>

        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Materials Overview -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h4><?php echo count($subjects); ?></h4>
                        <p class="mb-0">My Subjects</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h4><?php echo count($materials); ?></h4>
                        <p class="mb-0">Total Materials</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h4><?php echo count(array_filter($materials, function($m) { return $m['visibility'] == 'students'; })); ?></h4>
                        <p class="mb-0">Public Materials</p>
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
        </div>

        <!-- Materials List -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Uploaded Materials</h5>
            </div>
            <div class="card-body">
                <?php if (count($materials) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Subject</th>
                                <th>File Name</th>
                                <th>Size</th>
                                <th>Visibility</th>
                                <th>Uploaded</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materials as $material): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($material['title']); ?></strong></td>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($material['abbreviation']); ?></span>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($material['subject_name']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($material['file_name']); ?></td>
                                <td><?php echo round($material['file_size'] / 1024, 2); ?> KB</td>
                                <td>
                                    <?php if ($material['visibility'] == 'students'): ?>
                                        <span class="badge bg-success">Public</span>
                                    <?php elseif ($material['visibility'] == 'faculty'): ?>
                                        <span class="badge bg-warning">Faculty Only</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Private</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($material['uploaded_at'])); ?></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="<?php echo $material['file_path']; ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <a href="?delete_file=<?php echo $material['id']; ?>" class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Are you sure you want to delete this file?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-book fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Study Materials Found</h5>
                    <p class="text-muted">Upload your first study material to get started.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Upload Material Modal -->
    <div class="modal fade" id="uploadMaterialModal" tabindex="-1" aria-labelledby="uploadMaterialModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadMaterialModalLabel">Upload Study Material</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="subject_id" class="form-label">Subject *</label>
                            <select class="form-select" id="subject_id" name="subject_id" required>
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>">
                                        <?php echo htmlspecialchars($subject['abbreviation'] . ' - ' . $subject['subject_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="title" class="form-label">Title *</label>
                            <input type="text" class="form-control" id="title" name="title" required
                                   placeholder="e.g., Chapter 1 Notes, Assignment 1, Lab Manual">
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"
                                      placeholder="Brief description of the material"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="visibility" class="form-label">Visibility *</label>
                            <select class="form-select" id="visibility" name="visibility" required>
                                <option value="students">Public (Visible to Students)</option>
                                <option value="faculty">Faculty Only</option>
                                <option value="private">Private</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="material_file" class="form-label">Select File *</label>
                            <input class="form-control" type="file" id="material_file" name="material_file" required>
                            <div class="form-text">
                                Max file size: 10MB<br>
                                Allowed formats: PDF, DOC, DOCX, PPT, PPTX, TXT, JPG, JPEG, PNG
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="upload_material" class="btn btn-success">
                            <i class="fas fa-upload me-2"></i>Upload Material
                        </button>
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
                    <h5>Faculty Portal</h5>
                    <p>Study Materials Management System</p>
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
