<?php
session_start();

// Destroy all session data
session_unset();
session_destroy();

// Clear any cookies if they exist
if (isset($_COOKIE['faculty_remember'])) {
    setcookie('faculty_remember', '', time() - 3600, '/');
}

// Redirect to login page with logout message
header("Location: faculty_login.php?logout=1");
exit();
?>
