<?php
// Start session
session_start();

// For testing purposes, we're not enforcing authentication
// In a real application, you would check if the user is logged in
$faculty_id = isset($_SESSION['faculty_id']) ? $_SESSION['faculty_id'] : 1;
$faculty_name = isset($_SESSION['faculty_name']) ? $_SESSION['faculty_name'] : "Test Faculty";

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle Delete action
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM students WHERE id = $delete_id");
    header("Location: faculty_students.php");
    exit();
}

// Handle Add Student form submission
$add_success = false;
$add_error = "";

if (isset($_POST['add_student'])) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $course = $_POST['course'];
    $year = $_POST['year'];
    $password = "password123"; // Default password for testing
    
    // Simple validation
    if (empty($full_name) || empty($email) || empty($course) || empty($year)) {
        $add_error = "All fields are required";
    } else {
        // Check if email already exists
        $check_email = $conn->prepare("SELECT id FROM students WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $result = $check_email->get_result();
        
        if ($result->num_rows > 0) {
            $add_error = "Email already exists. Please use a different email.";
        } else {
            // Insert new student
            $stmt = $conn->prepare("INSERT INTO students (full_name, email, password, course, year) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $full_name, $email, $password, $course, $year);
            
            if ($stmt->execute()) {
                $add_success = true;
            } else {
                $add_error = "Error: " . $stmt->error;
            }
            
            $stmt->close();
        }
        
        $check_email->close();
    }
}

// Handle Search query
$search = "";
if (isset($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $sql = "SELECT * FROM students WHERE full_name LIKE '%$search%' OR email LIKE '%$search%' OR course LIKE '%$search%'";
} else {
    $sql = "SELECT * FROM students";
}

$result = $conn->query($sql);

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
    <title>Manage Students - Student Management System</title>
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
                        <a class="nav-link active" href="faculty_students.php">Manage Students</a>
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
    <div class="container dashboard-container py-4">
        <div class="row">
            <div class="col-md-12">
                <div class="dashboard-header">
                    <h2>Manage Students</h2>
                    <p class="text-muted">Add, edit, and delete student records</p>
                </div>
            </div>
        </div>

        <!-- Add Student Form -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-user-plus me-2"></i>Add New Student</h5>
                        <hr>
                        
                        <?php if ($add_success): ?>
                            <div class="alert alert-success" role="alert">
                                Student added successfully!
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($add_error): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo $add_error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" class="row g-3">
                            <div class="col-md-6">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="course" class="form-label">Course</label>
                                <input type="text" class="form-control" id="course" name="course" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="year" class="form-label">Year</label>
                                <input type="number" class="form-control" id="year" name="year" min="1" max="10" required>
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" name="add_student" class="btn btn-success">Add Student</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Search and Student List -->
        <div class="row">
            <div class="col-md-12">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-list me-2"></i>Student List</h5>
                        <hr>
                        
                        <!-- Search form -->
                        <form method="get" action="" class="row g-3 mb-4">
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="search" placeholder="Search by name, email, or course" value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary me-2">Search</button>
                                <a href="faculty_students.php" class="btn btn-secondary">Clear</a>
                            </div>
                        </form>
                        
                        <!-- Export links -->
                        <div class="mb-3">
                            <a href="export_excel.php" class="btn btn-outline-success me-2"><i class="fas fa-file-excel me-1"></i>Export to Excel</a>
                        </div>
                        
                        <!-- Student Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Full Name</th>
                                        <th>Email</th>
                                        <th>Course</th>
                                        <th>Year</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $row['id']; ?></td>
                                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                                <td><?php echo htmlspecialchars($row['course']); ?></td>
                                                <td><?php echo $row['year']; ?></td>
                                                <td class="action-buttons">
                                                    <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Edit</a>
                                                    <a href="faculty_students.php?delete_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this student?');"><i class="fas fa-trash"></i> Delete</a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No students found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
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
