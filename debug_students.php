<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Debug: Student Database Check</h2>";

// Check if students table exists and has the new columns
echo "<h3>1. Table Structure Check</h3>";
$result = $conn->query("DESCRIBE students");
if ($result) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>Error: " . $conn->error . "</p>";
}

// Check total number of students
echo "<h3>2. Student Count</h3>";
$result = $conn->query("SELECT COUNT(*) as total FROM students");
if ($result) {
    $row = $result->fetch_assoc();
    echo "<p>Total students in database: <strong>{$row['total']}</strong></p>";
} else {
    echo "<p style='color: red;'>Error: " . $conn->error . "</p>";
}

// Check students with roll numbers starting with 22N81A62
echo "<h3>3. New Students Check</h3>";
$result = $conn->query("SELECT COUNT(*) as count FROM students WHERE roll_number LIKE '22N81A62%'");
if ($result) {
    $row = $result->fetch_assoc();
    echo "<p>Students with roll numbers 22N81A62xxx: <strong>{$row['count']}</strong></p>";
} else {
    echo "<p style='color: red;'>Error: " . $conn->error . "</p>";
}

// Show first 5 students with their details
echo "<h3>4. Sample Student Data</h3>";
$result = $conn->query("SELECT roll_number, full_name, email, section, LENGTH(password) as pwd_length FROM students WHERE roll_number LIKE '22N81A62%' ORDER BY roll_number LIMIT 5");
if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Roll Number</th><th>Name</th><th>Email</th><th>Section</th><th>Password Length</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['roll_number']}</td>";
        echo "<td>{$row['full_name']}</td>";
        echo "<td>{$row['email']}</td>";
        echo "<td>{$row['section']}</td>";
        echo "<td>{$row['pwd_length']} chars</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>No students found with roll numbers 22N81A62xxx</p>";
}

// Test password verification for first student
echo "<h3>5. Password Test</h3>";
$result = $conn->query("SELECT roll_number, email, password FROM students WHERE roll_number LIKE '22N81A62%' ORDER BY roll_number LIMIT 1");
if ($result && $result->num_rows > 0) {
    $student = $result->fetch_assoc();
    $test_password = 'Student@' . substr($student['roll_number'], -3);
    
    echo "<p><strong>Testing student:</strong> {$student['roll_number']}</p>";
    echo "<p><strong>Email:</strong> {$student['email']}</p>";
    echo "<p><strong>Expected password:</strong> {$test_password}</p>";
    echo "<p><strong>Stored password (first 20 chars):</strong> " . substr($student['password'], 0, 20) . "...</p>";
    
    // Test password verification
    if (password_verify($test_password, $student['password'])) {
        echo "<p style='color: green;'><strong>✓ Password verification: SUCCESS</strong></p>";
    } elseif ($test_password === $student['password']) {
        echo "<p style='color: green;'><strong>✓ Plain text password match: SUCCESS</strong></p>";
    } else {
        echo "<p style='color: red;'><strong>✗ Password verification: FAILED</strong></p>";
        echo "<p>This means the password is not matching. Let's check if it's hashed correctly.</p>";
    }
} else {
    echo "<p style='color: red;'>No students found for password testing</p>";
}

// Check sections
echo "<h3>6. Section Distribution</h3>";
$result = $conn->query("SELECT section, COUNT(*) as count FROM students WHERE roll_number LIKE '22N81A62%' GROUP BY section ORDER BY section");
if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Section</th><th>Student Count</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['section']}</td>";
        echo "<td>{$row['count']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No section data found</p>";
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
</style>
