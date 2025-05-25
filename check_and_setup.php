<?php
// Quick check and setup script
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Database Status Check</h2>";

// Check if faculty table exists and has data
$result = $conn->query("SELECT COUNT(*) as count FROM faculty");
if ($result) {
    $row = $result->fetch_assoc();
    $faculty_count = $row['count'];
    
    echo "<p><strong>Faculty count:</strong> $faculty_count</p>";
    
    if ($faculty_count == 0) {
        echo "<p style='color: red;'>❌ No faculty members found in database!</p>";
        echo "<p><a href='setup_database.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Run Database Setup</a></p>";
    } else {
        echo "<p style='color: green;'>✅ Faculty table has $faculty_count members</p>";
        
        // Show first few faculty members
        $result = $conn->query("SELECT id, full_name, email, faculty_id FROM faculty ORDER BY id LIMIT 5");
        if ($result->num_rows > 0) {
            echo "<h3>Faculty Members:</h3>";
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Faculty ID</th></tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row['id'] . "</td>";
                echo "<td>" . $row['full_name'] . "</td>";
                echo "<td>" . $row['email'] . "</td>";
                echo "<td>" . ($row['faculty_id'] ?? 'NULL') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        echo "<p><a href='admin_faculty.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Faculty Management</a></p>";
    }
} else {
    echo "<p style='color: red;'>❌ Error checking faculty table: " . $conn->error . "</p>";
    echo "<p><a href='setup_database.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Run Database Setup</a></p>";
}

$conn->close();
?>
