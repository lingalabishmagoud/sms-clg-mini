<?php
// Initialize variables
$full_name = $email = $password = $confirm_password = $department = "";
$error = "";
$success = false;

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $department = $_POST['department'];
    
    // Simple validation
    if (empty($full_name) || empty($email) || empty($password) || empty($department)) {
        $error = "All fields are required";
    } elseif ($password != $confirm_password) {
        $error = "Passwords do not match";
    } else {
        // Connect to database
        $conn = new mysqli("localhost", "root", "", "student_db");
        
        // Check connection
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        
        // Check if faculty table exists, if not create it
        $conn->query("CREATE TABLE IF NOT EXISTS faculty (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            department VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Check if email already exists
        $check_email = $conn->prepare("SELECT id FROM faculty WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $result = $check_email->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Email already exists. Please use a different email.";
        } else {
            // For testing purposes, we're not hashing the password
            // In a real application, you would use password_hash() here
            
            // Insert new faculty
            $stmt = $conn->prepare("INSERT INTO faculty (full_name, email, password, department) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $full_name, $email, $password, $department);
            
            if ($stmt->execute()) {
                $success = true;
                // Clear form data
                $full_name = $email = $password = $confirm_password = $department = "";
            } else {
                $error = "Error: " . $stmt->error;
            }
            
            $stmt->close();
        }
        
        $check_email->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Signup - Student Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="form-container">
                    <h2 class="form-title">Faculty Registration</h2>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success" role="alert">
                            Registration successful! <a href="faculty_login.php">Login here</a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="department" class="form-label">Department</label>
                            <input type="text" class="form-control" id="department" name="department" value="<?php echo htmlspecialchars($department); ?>" required>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">Register</button>
                        </div>
                    </form>
                    
                    <div class="mt-3 text-center">
                        <p>Already have an account? <a href="faculty_login.php">Login here</a></p>
                        <p><a href="index.html">Back to Home</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
