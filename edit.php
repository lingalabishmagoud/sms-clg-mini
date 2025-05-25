<?php
// Start session
session_start();

// For testing purposes, we're not enforcing authentication
// In a real application, you would check if the user is logged in
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'faculty';

$conn = new mysqli("localhost", "root", "", "student_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id == 0) {
    header("Location: " . ($user_type == 'faculty' ? 'faculty_students.php' : 'student_dashboard.php'));
    exit();
}

$success = false;
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $course = $conn->real_escape_string($_POST['course']);
    $year = intval($_POST['year']);

    $stmt = $conn->prepare("UPDATE students SET full_name=?, email=?, course=?, year=? WHERE id=?");
    $stmt->bind_param("sssii", $full_name, $email, $course, $year, $id);

    if ($stmt->execute()) {
        $success = true;
    } else {
        $error = "Error updating student: " . $stmt->error;
    }

    $stmt->close();

    if ($success && !isset($_POST['stay'])) {
        header("Location: " . ($user_type == 'faculty' ? 'faculty_students.php' : 'student_dashboard.php'));
        exit();
    }
}

// Fetch current student data
$result = $conn->query("SELECT * FROM students WHERE id=$id");
if ($result->num_rows == 0) {
    header("Location: " . ($user_type == 'faculty' ? 'faculty_students.php' : 'student_dashboard.php'));
    exit();
}
$student = $result->fetch_assoc();

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
    <title>Edit Student - Student Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark <?php echo $user_type == 'faculty' ? 'bg-success' : 'bg-primary'; ?>">
        <div class="container">
            <a class="navbar-brand" href="#">Student Management System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if ($user_type == 'faculty'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="faculty_dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="faculty_students.php">Manage Students</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="student_dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="student_profile.php">Profile</a>
                        </li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex">
                    <a href="?logout=1" class="btn btn-light btn-sm">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container dashboard-container py-4">
        <div class="row">
            <div class="col-md-12">
                <div class="dashboard-header">
                    <h2>Edit Student</h2>
                    <p class="text-muted">Update student information</p>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-user-edit me-2"></i>Edit Student Details</h5>
                        <hr>

                        <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                Student updated successfully!
                            </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($student['full_name']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($student['email']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="course" class="form-label">Course</label>
                                <input type="text" class="form-control" id="course" name="course" value="<?php echo htmlspecialchars($student['course']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="year" class="form-label">Year</label>
                                <input type="number" class="form-control" id="year" name="year" value="<?php echo $student['year']; ?>" required min="1" max="10">
                            </div>

                            <div class="d-flex justify-content-between">
                                <div>
                                    <button type="submit" class="btn btn-primary">Update Student</button>
                                    <button type="submit" name="stay" value="1" class="btn btn-outline-primary">Update & Stay</button>
                                </div>
                                <a href="<?php echo $user_type == 'faculty' ? 'faculty_students.php' : 'student_dashboard.php'; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-1"></i> Back
                                </a>
                            </div>
                        </form>
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

<?php $conn->close(); ?>
