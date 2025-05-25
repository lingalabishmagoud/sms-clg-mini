<?php
// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Faculty Database Check</h2>";

// Check if faculty table exists
$result = $conn->query("SHOW TABLES LIKE 'faculty'");
if ($result->num_rows == 0) {
    echo "<p style='color: red;'>Faculty table does not exist!</p>";
    echo "<p><a href='setup_database.php'>Run Database Setup</a></p>";
} else {
    echo "<p style='color: green;'>Faculty table exists.</p>";
    
    // Get all faculty
    $faculty_result = $conn->query("SELECT * FROM faculty");
    
    if ($faculty_result->num_rows > 0) {
        echo "<h3>Current Faculty in Database:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Full Name</th><th>Email</th><th>Password (first 20 chars)</th><th>Phone</th><th>Department</th></tr>";
        
        while ($faculty = $faculty_result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $faculty['id'] . "</td>";
            echo "<td>" . htmlspecialchars($faculty['full_name']) . "</td>";
            echo "<td>" . htmlspecialchars($faculty['email']) . "</td>";
            echo "<td>" . substr($faculty['password'], 0, 20) . "...</td>";
            echo "<td>" . htmlspecialchars($faculty['phone'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($faculty['department']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<h3>Reset Passwords to Plain Text for Testing</h3>";
        echo "<p>Click the button below to reset all faculty passwords to 'password123' (plain text for easy testing):</p>";
        
        if (isset($_POST['reset_passwords'])) {
            // Reset all passwords to plain text
            $update_result = $conn->query("UPDATE faculty SET password = 'password123'");
            if ($update_result) {
                echo "<p style='color: green;'>✅ All faculty passwords have been reset to 'password123'</p>";
            } else {
                echo "<p style='color: red;'>❌ Error resetting passwords: " . $conn->error . "</p>";
            }
        }
        
        echo "<form method='POST'>";
        echo "<button type='submit' name='reset_passwords' style='background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Reset All Passwords to 'password123'</button>";
        echo "</form>";
        
    } else {
        echo "<p style='color: orange;'>No faculty found in database.</p>";
        echo "<p><a href='setup_database.php'>Run Database Setup to Add Faculty</a></p>";
    }
}

echo "<hr>";
echo "<h3>Test Login Credentials</h3>";
echo "<p>After running the password reset, you can use these credentials:</p>";
echo "<ul>";
echo "<li><strong>Dr. K. Subba Rao:</strong> subbarao@college.edu / password123</li>";
echo "<li><strong>Mr. Mukesh Gilda:</strong> mukesh@college.edu / password123</li>";
echo "<li><strong>Mrs. P. Sandhya Rani:</strong> sandhya@college.edu / password123</li>";
echo "<li><strong>Mr. J. Naresh Kumar:</strong> naresh@college.edu / password123</li>";
echo "<li><strong>Mr. R. Anbarasu:</strong> anbarasu@college.edu / password123</li>";
echo "</ul>";

echo "<p><a href='faculty_login.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Faculty Login</a></p>";

$conn->close();
?>
