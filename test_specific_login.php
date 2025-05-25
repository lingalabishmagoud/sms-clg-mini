<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Testing Specific Login: lingala.bishma@student.college.edu</h2>";

$test_email = "lingala.bishma@student.college.edu";
$test_password = "Student@254";

// Check if this student exists
$stmt = $conn->prepare("SELECT id, roll_number, full_name, email, password, section FROM students WHERE email = ?");
$stmt->bind_param("s", $test_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $student = $result->fetch_assoc();
    
    echo "<h3>✅ Student Found in Database</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>ID</td><td>{$student['id']}</td></tr>";
    echo "<tr><td>Roll Number</td><td>{$student['roll_number']}</td></tr>";
    echo "<tr><td>Full Name</td><td>{$student['full_name']}</td></tr>";
    echo "<tr><td>Email</td><td>{$student['email']}</td></tr>";
    echo "<tr><td>Section</td><td>{$student['section']}</td></tr>";
    echo "<tr><td>Password (first 20 chars)</td><td>" . substr($student['password'], 0, 20) . "...</td></tr>";
    echo "<tr><td>Password Length</td><td>" . strlen($student['password']) . " characters</td></tr>";
    echo "</table>";
    
    echo "<h3>🔍 Password Testing</h3>";
    echo "<p><strong>Expected Password:</strong> {$test_password}</p>";
    echo "<p><strong>Stored Password:</strong> {$student['password']}</p>";
    
    // Test different password scenarios
    echo "<h4>Test Results:</h4>";
    echo "<ul>";
    
    // Test 1: Direct comparison
    if ($test_password === $student['password']) {
        echo "<li style='color: green;'>✅ Direct password match: SUCCESS</li>";
    } else {
        echo "<li style='color: red;'>❌ Direct password match: FAILED</li>";
    }
    
    // Test 2: Password verify (for hashed passwords)
    if (password_verify($test_password, $student['password'])) {
        echo "<li style='color: green;'>✅ Password verify (hashed): SUCCESS</li>";
    } else {
        echo "<li style='color: red;'>❌ Password verify (hashed): FAILED</li>";
    }
    
    // Test 3: Check if password looks hashed
    if (strlen($student['password']) > 20 && strpos($student['password'], '$') !== false) {
        echo "<li style='color: orange;'>⚠️ Password appears to be hashed (length: " . strlen($student['password']) . ")</li>";
    } else {
        echo "<li style='color: blue;'>ℹ️ Password appears to be plain text</li>";
    }
    
    echo "</ul>";
    
    // Try to fix this specific student's password
    echo "<h3>🔧 Fixing Password</h3>";
    $update_stmt = $conn->prepare("UPDATE students SET password = ? WHERE email = ?");
    $update_stmt->bind_param("ss", $test_password, $test_email);
    
    if ($update_stmt->execute()) {
        echo "<p style='color: green;'>✅ Password updated successfully to: <strong>{$test_password}</strong></p>";
        
        // Verify the update
        $verify_stmt = $conn->prepare("SELECT password FROM students WHERE email = ?");
        $verify_stmt->bind_param("s", $test_email);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        $updated_student = $verify_result->fetch_assoc();
        
        echo "<p><strong>New stored password:</strong> {$updated_student['password']}</p>";
        
        if ($test_password === $updated_student['password']) {
            echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
            echo "<h4 style='color: #155724; margin-top: 0;'>🎉 SUCCESS!</h4>";
            echo "<p style='color: #155724; margin-bottom: 0;'>You can now login with:</p>";
            echo "<p style='color: #155724; margin-bottom: 0;'><strong>Email:</strong> {$test_email}</p>";
            echo "<p style='color: #155724; margin-bottom: 0;'><strong>Password:</strong> {$test_password}</p>";
            echo "</div>";
        }
        
        $verify_stmt->close();
    } else {
        echo "<p style='color: red;'>❌ Failed to update password: " . $update_stmt->error . "</p>";
    }
    
    $update_stmt->close();
    
} else {
    echo "<h3>❌ Student NOT Found</h3>";
    echo "<p style='color: red;'>No student found with email: {$test_email}</p>";
    
    // Check if student exists with different email format
    echo "<h4>Searching for similar students...</h4>";
    $search_stmt = $conn->prepare("SELECT roll_number, full_name, email FROM students WHERE full_name LIKE '%LINGALA%' OR full_name LIKE '%BISHMA%'");
    $search_stmt->execute();
    $search_result = $search_stmt->get_result();
    
    if ($search_result->num_rows > 0) {
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
        echo "<p>No similar students found.</p>";
    }
    $search_stmt->close();
}

$stmt->close();
$conn->close();
?>

<style>
table {
    margin: 10px 0;
}
th, td {
    padding: 8px;
    text-align: left;
}
th {
    background-color: #f2f2f2;
}
li {
    margin: 5px 0;
}
</style>
