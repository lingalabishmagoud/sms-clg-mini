<?php
// Initialize variables
$email = "";
$user_type = isset($_GET['user_type']) ? $_GET['user_type'] : 'student';
$error = "";
$success = false;

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $email = trim($_POST['email']);
    $user_type = $_POST['user_type'];
    
    // Simple validation
    if (empty($email)) {
        $error = "Email is required";
    } else {
        // Connect to database
        $conn = new mysqli("localhost", "root", "", "student_db");
        
        // Check connection
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        
        // Check if email exists in the appropriate table
        if ($user_type == 'student') {
            $table = 'students';
        } elseif ($user_type == 'faculty') {
            $table = 'faculty';
        } else {
            $table = 'admin';
        }
        
        $stmt = $conn->prepare("SELECT id, full_name FROM $table WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $error = "Email not found in our records";
        } else {
            $user = $result->fetch_assoc();
            
            // Generate a unique token
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Delete any existing tokens for this email
            $delete_stmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE email = ? AND user_type = ?");
            $delete_stmt->bind_param("ss", $email, $user_type);
            $delete_stmt->execute();
            $delete_stmt->close();
            
            // Insert new token
            $insert_stmt = $conn->prepare("INSERT INTO password_reset_tokens (email, token, user_type, expires_at) VALUES (?, ?, ?, ?)");
            $insert_stmt->bind_param("ssss", $email, $token, $user_type, $expires_at);
            
            if ($insert_stmt->execute()) {
                $success = true;
                
                // In a real application, you would send an email with the reset link
                // For this demo, we'll just display the link
                $reset_link = "reset_password.php?token=$token&email=$email";
                
                // Clear email
                $email = "";
            } else {
                $error = "Error generating reset token: " . $insert_stmt->error;
            }
            
            $insert_stmt->close();
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
    <title>Forgot Password - Student Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="form-container">
                    <h2 class="form-title">Forgot Password</h2>
                    <p class="text-center text-muted mb-4">Enter your email address and we'll send you a link to reset your password.</p>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success" role="alert">
                            <h5><i class="fas fa-check-circle me-2"></i>Password Reset Link Generated</h5>
                            <p>A password reset link has been generated. In a real application, this would be sent to your email.</p>
                            <p>For demonstration purposes, you can use this link:</p>
                            <div class="d-grid">
                                <a href="<?php echo $reset_link; ?>" class="btn btn-outline-success">Reset Password</a>
                            </div>
                            <hr>
                            <p class="mb-0">Return to <a href="<?php echo $user_type; ?>_login.php">login page</a>.</p>
                        </div>
                    <?php else: ?>
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="user_type" class="form-label">Account Type</label>
                                <select class="form-select" id="user_type" name="user_type" required>
                                    <option value="student" <?php echo ($user_type == 'student') ? 'selected' : ''; ?>>Student</option>
                                    <option value="faculty" <?php echo ($user_type == 'faculty') ? 'selected' : ''; ?>>Faculty</option>
                                    <option value="admin" <?php echo ($user_type == 'admin') ? 'selected' : ''; ?>>Administrator</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                                </button>
                            </div>
                        </form>
                        
                        <div class="mt-3 text-center">
                            <p>Remember your password? 
                                <a href="student_login.php" id="login-link">Login here</a>
                            </p>
                            <p><a href="index.html">Back to Home</a></p>
                        </div>
                        
                        <script>
                            // Update login link based on selected user type
                            document.getElementById('user_type').addEventListener('change', function() {
                                const userType = this.value;
                                const loginLink = document.getElementById('login-link');
                                loginLink.href = userType + '_login.php';
                            });
                        </script>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
