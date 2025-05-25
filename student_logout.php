<?php
session_start();

// Destroy all session data
session_unset();
session_destroy();

// Clear any cookies if they exist
if (isset($_COOKIE['student_remember'])) {
    setcookie('student_remember', '', time() - 3600, '/');
}

// Redirect to login page with logout message
header("Location: student_login.php?logout=1");
exit();
?>
