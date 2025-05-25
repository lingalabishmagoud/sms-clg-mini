<?php
// Start session
session_start();

// Initialize variables
$email = $password = "";
$error = "";

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Simple validation
    if (empty($email) || empty($password)) {
        $error = "Email and password are required";
    } else {
        // Connect to database
        $conn = new mysqli("localhost", "root", "", "student_db");

        // Check connection
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        // Check if email exists and verify password
        $stmt = $conn->prepare("SELECT id, full_name, password FROM students WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $student = $result->fetch_assoc();

            // Verify password (handles both hashed and plain text passwords)
            if (password_verify($password, $student['password']) || $password === $student['password']) {
                // Login successful
                // Set session variables
                $_SESSION['student_id'] = $student['id'];
                $_SESSION['student_name'] = $student['full_name'];
                $_SESSION['user_type'] = 'student';

                // Redirect to dashboard
                header("Location: student_dashboard.php");
                exit();
            } else {
                $error = "Invalid email or password";
            }
        } else {
            $error = "Invalid email or password";
        }

        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login - Student Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="form-container">
                    <h2 class="form-title">Student Login</h2>

                    <?php if (isset($_GET['logout']) && $_GET['logout'] == '1'): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle"></i> You have been successfully logged out.
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <div class="form-text text-end">
                                <a href="forgot_password.php?user_type=student" class="text-decoration-none">Forgot Password?</a>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </button>
                        </div>
                    </form>

                    <div class="mt-3 text-center">
                        <p>Don't have an account? <a href="student_signup.php">Register here</a></p>
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
