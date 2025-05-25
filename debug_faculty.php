<?php
// Debug script to check faculty table status
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Faculty Table Debug Information</h2>";

// Check if faculty table exists
$result = $conn->query("SHOW TABLES LIKE 'faculty'");
if ($result->num_rows == 0) {
    echo "<p style='color: red;'>❌ Faculty table does not exist!</p>";
    echo "<p><a href='setup_database.php'>Run Database Setup</a></p>";
    exit;
} else {
    echo "<p style='color: green;'>✅ Faculty table exists.</p>";
}

// Show table structure
echo "<h3>Table Structure:</h3>";
$result = $conn->query("DESCRIBE faculty");
if ($result) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Count total faculty
$result = $conn->query("SELECT COUNT(*) as count FROM faculty");
$row = $result->fetch_assoc();
$total_faculty = $row['count'];

echo "<h3>Faculty Count: " . $total_faculty . "</h3>";

if ($total_faculty > 0) {
    // Show all faculty
    echo "<h3>All Faculty Records:</h3>";
    $result = $conn->query("SELECT * FROM faculty ORDER BY id");
    if ($result->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Faculty ID</th><th>Department</th><th>Position</th><th>Phone</th><th>Created At</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . ($row['username'] ?? 'NULL') . "</td>";
            echo "<td>" . $row['full_name'] . "</td>";
            echo "<td>" . $row['email'] . "</td>";
            echo "<td>" . ($row['faculty_id'] ?? 'NULL') . "</td>";
            echo "<td>" . $row['department'] . "</td>";
            echo "<td>" . ($row['position'] ?? 'NULL') . "</td>";
            echo "<td>" . ($row['phone'] ?? 'NULL') . "</td>";
            echo "<td>" . ($row['created_at'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check specific IDs
    echo "<h3>Checking Specific Faculty IDs:</h3>";
    $test_ids = [27, 29];
    foreach ($test_ids as $test_id) {
        $stmt = $conn->prepare("SELECT * FROM faculty WHERE id = ?");
        $stmt->bind_param("i", $test_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $faculty = $result->fetch_assoc();
            echo "<p style='color: green;'>✅ Faculty ID $test_id exists: " . $faculty['full_name'] . "</p>";
        } else {
            echo "<p style='color: red;'>❌ Faculty ID $test_id does not exist</p>";
        }
    }
} else {
    echo "<p style='color: orange;'>⚠️ No faculty records found in database.</p>";
    echo "<p><a href='setup_database.php'>Run Database Setup to populate faculty data</a></p>";
}

$conn->close();
?>
