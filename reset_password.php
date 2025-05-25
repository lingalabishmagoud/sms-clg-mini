<?php
// Initialize variables
$token = isset($_GET['token']) ? $_GET['token'] : '';
$email = isset($_GET['email']) ? $_GET['email'] : '';
$error = "";
$success = false;
$token_valid = false;
$user_type = '';

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Validate token
if (!empty($token) && !empty($email)) {
    $stmt = $conn->prepare("
        SELECT * FROM password_reset_tokens 
        WHERE token = ? AND email = ? AND used = 0 AND expires_at > NOW()
    ");
    $stmt->bind_param("ss", $token, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $token_data = $result->fetch_assoc();
        $token_valid = true;
        $user_type = $token_data['user_type'];
    } else {
        $error = "Invalid or expired token. Please request a new password reset link.";
    }
    
    $stmt->close();
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && $token_valid) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Simple validation
    if (empty($password) || empty($confirm_password)) {
        $error = "Both password fields are required";
    } elseif ($password != $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long";
    } else {
        // Determine which table to update based on user_type
        if ($user_type == 'student') {
            $table = 'students';
        } elseif ($user_type == 'faculty') {
            $table = 'faculty';
        } else {
            $table = 'admin';
        }
        
        // Update password
        // In a real application, you would hash the password
        $update_stmt = $conn->prepare("UPDATE $table SET password = ? WHERE email = ?");
        $update_stmt->bind_param("ss", $password, $email);
        
        if ($update_stmt->execute()) {
            // Mark token as used
            $mark_used_stmt = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
            $mark_used_stmt->bind_param("s", $token);
            $mark_used_stmt->execute();
            $mark_used_stmt->close();
            
            $success = true;
        } else {
            $error = "Error updating password: " . $update_stmt->error;
        }
        
        $update_stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Student Management System</title>
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
                    <h2 class="form-title">Reset Password</h2>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success" role="alert">
                            <h5><i class="fas fa-check-circle me-2"></i>Password Reset Successful</h5>
                            <p>Your password has been successfully reset.</p>
                            <div class="d-grid mt-3">
                                <a href="<?php echo $user_type; ?>_login.php" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login with New Password
                                </a>
                            </div>
                        </div>
                    <?php elseif ($token_valid): ?>
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <p class="text-center text-muted mb-4">Please enter your new password below.</p>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Password must be at least 6 characters long.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-key me-2"></i>Reset Password
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo !empty($error) ? $error : "Invalid or missing reset token. Please request a new password reset link."; ?>
                        </div>
                        <div class="d-grid mt-3">
                            <a href="forgot_password.php" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Request New Reset Link
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-3 text-center">
                        <p><a href="index.html">Back to Home</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
        
        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('confirm_password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>
