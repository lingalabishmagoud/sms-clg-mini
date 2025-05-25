<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Login Verification Test</h2>";

$test_email = "lingala.bishma@student.college.edu";
$test_password = "Student@254";

echo "<h3>Testing Login for:</h3>";
echo "<p><strong>Email:</strong> {$test_email}</p>";
echo "<p><strong>Password:</strong> {$test_password}</p>";

// Check if student exists
$stmt = $conn->prepare("SELECT id, roll_number, full_name, email, password, section FROM students WHERE email = ?");
$stmt->bind_param("s", $test_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $student = $result->fetch_assoc();
    
    echo "<h3>✅ Student Found!</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>ID</td><td>{$student['id']}</td></tr>";
    echo "<tr><td>Roll Number</td><td>{$student['roll_number']}</td></tr>";
    echo "<tr><td>Full Name</td><td>{$student['full_name']}</td></tr>";
    echo "<tr><td>Email</td><td>{$student['email']}</td></tr>";
    echo "<tr><td>Section</td><td>{$student['section']}</td></tr>";
    echo "<tr><td>Stored Password</td><td>{$student['password']}</td></tr>";
    echo "</table>";
    
    // Test password verification
    echo "<h3>🔐 Password Verification</h3>";
    if ($test_password === $student['password']) {
        echo "<p style='color: green; font-size: 18px;'><strong>✅ PASSWORD MATCH - LOGIN SHOULD WORK!</strong></p>";
        
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
        echo "<h4 style='color: #155724; margin-top: 0;'>🎉 Ready to Login!</h4>";
        echo "<p style='color: #155724;'><strong>Your credentials are correct:</strong></p>";
        echo "<p style='color: #155724;'><strong>Email:</strong> {$test_email}</p>";
        echo "<p style='color: #155724;'><strong>Password:</strong> {$test_password}</p>";
        echo "<p style='color: #155724; margin-bottom: 0;'><a href='student_login.php' style='color: #155724; text-decoration: none; font-weight: bold;'>→ Click here to login now!</a></p>";
        echo "</div>";
        
    } elseif (password_verify($test_password, $student['password'])) {
        echo "<p style='color: green; font-size: 18px;'><strong>✅ HASHED PASSWORD MATCH - LOGIN SHOULD WORK!</strong></p>";
    } else {
        echo "<p style='color: red; font-size: 18px;'><strong>❌ PASSWORD MISMATCH</strong></p>";
        echo "<p>Expected: {$test_password}</p>";
        echo "<p>Stored: {$student['password']}</p>";
        
        // Try to fix the password
        echo "<h4>🔧 Fixing Password...</h4>";
        $fix_stmt = $conn->prepare("UPDATE students SET password = ? WHERE email = ?");
        $fix_stmt->bind_param("ss", $test_password, $test_email);
        if ($fix_stmt->execute()) {
            echo "<p style='color: green;'>✅ Password fixed! You can now login.</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to fix password: " . $fix_stmt->error . "</p>";
        }
        $fix_stmt->close();
    }
    
} else {
    echo "<h3>❌ Student Not Found</h3>";
    echo "<p style='color: red;'>No student found with email: {$test_email}</p>";
    
    // Search for similar students
    echo "<h4>Searching for similar students...</h4>";
    $search_result = $conn->query("SELECT roll_number, full_name, email FROM students WHERE full_name LIKE '%LINGALA%' OR full_name LIKE '%BISHMA%' OR roll_number = '22N81A6254'");
    
    if ($search_result && $search_result->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Roll Number</th><th>Name</th><th>Email</th></tr>";
        while ($row = $search_result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['roll_number']}</td>";
            echo "<td>{$row['full_name']}</td>";
            echo "<td>{$row['email']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No similar students found. Please run <a href='clean_and_add_students.php'>clean_and_add_students.php</a> first.</p>";
    }
}

// Show all students with roll numbers 22N81A62xxx
echo "<h3>📋 All Students with Roll Numbers 22N81A62xxx</h3>";
$all_students = $conn->query("SELECT roll_number, full_name, email, section FROM students WHERE roll_number LIKE '22N81A62%' ORDER BY roll_number");

if ($all_students && $all_students->num_rows > 0) {
    echo "<p>Found {$all_students->num_rows} students:</p>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Roll Number</th><th>Name</th><th>Email</th><th>Section</th></tr>";
    while ($row = $all_students->fetch_assoc()) {
        $highlight = ($row['roll_number'] == '22N81A6254') ? 'background: #fff3cd;' : '';
        echo "<tr style='{$highlight}'>";
        echo "<td>{$row['roll_number']}</td>";
        echo "<td>{$row['full_name']}</td>";
        echo "<td>{$row['email']}</td>";
        echo "<td>{$row['section']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>No students found with roll numbers 22N81A62xxx</p>";
}

$stmt->close();
$conn->close();
?>

<style>
table { margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
</style>
