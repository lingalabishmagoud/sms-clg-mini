<?php
// Test faculty login functionality
$conn = new mysqli("localhost", "root", "", "student_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Faculty Login Test</h2>";

// Test credentials
$test_credentials = [
    ['subbarao@college.edu', 'password123', 'Dr. K. Subba Rao'],
    ['mukesh@college.edu', 'password123', 'Mr. Mukesh Gilda'],
    ['sandhya@college.edu', 'password123', 'Mrs. P. Sandhya Rani'],
    ['naresh@college.edu', 'password123', 'Mr. J. Naresh Kumar'],
    ['anbarasu@college.edu', 'password123', 'Mr. R. Anbarasu']
];

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Faculty Name</th><th>Email</th><th>Password</th><th>Login Test Result</th></tr>";

foreach ($test_credentials as $cred) {
    $email = $cred[0];
    $password = $cred[1];
    $name = $cred[2];
    
    // Test login
    $stmt = $conn->prepare("SELECT id, full_name, password FROM faculty WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $faculty = $result->fetch_assoc();
        
        // Test password verification
        if (password_verify($password, $faculty['password']) || $faculty['password'] === $password) {
            $status = "<span style='color: green;'>✅ LOGIN SUCCESS</span>";
        } else {
            $status = "<span style='color: red;'>❌ PASSWORD MISMATCH</span>";
        }
    } else {
        $status = "<span style='color: red;'>❌ EMAIL NOT FOUND</span>";
    }
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($name) . "</td>";
    echo "<td>" . htmlspecialchars($email) . "</td>";
    echo "<td>" . htmlspecialchars($password) . "</td>";
    echo "<td>" . $status . "</td>";
    echo "</tr>";
    
    $stmt->close();
}

echo "</table>";

echo "<hr>";
echo "<h3>Quick Actions</h3>";
echo "<p><a href='check_faculty.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Check Faculty Database</a></p>";
echo "<p><a href='faculty_login.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Faculty Login</a></p>";
echo "<p><a href='setup_database.php' style='background: #ffc107; color: black; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Run Database Setup</a></p>";

$conn->close();
?>
