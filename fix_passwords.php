<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Fixing Student Passwords for Testing</h2>";

// Get all students with roll numbers starting with 22N81A62
$result = $conn->query("SELECT id, roll_number, full_name FROM students WHERE roll_number LIKE '22N81A62%' ORDER BY roll_number");

if ($result && $result->num_rows > 0) {
    echo "<p>Found {$result->num_rows} students to update...</p>";
    echo "<ul>";
    
    $updated_count = 0;
    while ($student = $result->fetch_assoc()) {
        // Generate plain text password
        $plain_password = 'Student@' . substr($student['roll_number'], -3);
        
        // Update password to plain text
        $stmt = $conn->prepare("UPDATE students SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $plain_password, $student['id']);
        
        if ($stmt->execute()) {
            echo "<li>Updated {$student['roll_number']} - {$student['full_name']} → Password: {$plain_password}</li>";
            $updated_count++;
        } else {
            echo "<li style='color: red;'>Failed to update {$student['roll_number']}: " . $stmt->error . "</li>";
        }
        $stmt->close();
    }
    
    echo "</ul>";
    echo "<h3>Summary</h3>";
    echo "<p><strong>Successfully updated {$updated_count} student passwords to plain text for testing.</strong></p>";
    
    // Show some sample credentials
    echo "<h3>Sample Login Credentials (Now Working)</h3>";
    $sample_result = $conn->query("SELECT roll_number, full_name, email FROM students WHERE roll_number LIKE '22N81A62%' ORDER BY roll_number LIMIT 5");
    
    if ($sample_result && $sample_result->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f2f2f2;'><th>Roll Number</th><th>Name</th><th>Email</th><th>Password</th></tr>";
        
        while ($sample = $sample_result->fetch_assoc()) {
            $password = 'Student@' . substr($sample['roll_number'], -3);
            echo "<tr>";
            echo "<td>{$sample['roll_number']}</td>";
            echo "<td>{$sample['full_name']}</td>";
            echo "<td>{$sample['email']}</td>";
            echo "<td><strong>{$password}</strong></td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
    echo "<h4 style='color: #155724; margin-top: 0;'>✅ Passwords Fixed!</h4>";
    echo "<p style='color: #155724; margin-bottom: 0;'>You can now login with any student using their email and the password pattern: <strong>Student@XXX</strong> (where XXX is the last 3 characters of their roll number)</p>";
    echo "</div>";
    
} else {
    echo "<p style='color: red;'>No students found with roll numbers starting with 22N81A62</p>";
    echo "<p>Please run <a href='add_new_students.php'>add_new_students.php</a> first to add the students to the database.</p>";
}

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
