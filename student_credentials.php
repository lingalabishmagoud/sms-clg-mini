<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Student Login Credentials</h2>";
echo "<p>Here are the login credentials for all the newly added students:</p>";

// Get all students with their sections
$result = $conn->query("SELECT roll_number, full_name, email, section FROM students WHERE roll_number LIKE '22N81A62%' ORDER BY roll_number");

if ($result->num_rows > 0) {
    echo "<h3>CS-A Section (Roll Numbers: 22N81A6201-22N81A6267)</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>";
    echo "<tr style='background: #007bff; color: white;'><th>Roll Number</th><th>Name</th><th>Email</th><th>Password</th></tr>";

    while ($row = $result->fetch_assoc()) {
        if ($row['section'] == 'CS-A') {
            $password = 'Student@' . substr($row['roll_number'], -3);
            echo "<tr>";
            echo "<td>{$row['roll_number']}</td>";
            echo "<td>{$row['full_name']}</td>";
            echo "<td>{$row['email']}</td>";
            echo "<td>{$password}</td>";
            echo "</tr>";
        }
    }
    echo "</table>";

    // Reset result pointer
    $result->data_seek(0);

    echo "<h3>CS-B Section (Roll Numbers: 22N81A6268-22N81A62C8)</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>";
    echo "<tr style='background: #28a745; color: white;'><th>Roll Number</th><th>Name</th><th>Email</th><th>Password</th></tr>";

    while ($row = $result->fetch_assoc()) {
        if ($row['section'] == 'CS-B') {
            $password = 'Student@' . substr($row['roll_number'], -3);
            echo "<tr>";
            echo "<td>{$row['roll_number']}</td>";
            echo "<td>{$row['full_name']}</td>";
            echo "<td>{$row['email']}</td>";
            echo "<td>{$password}</td>";
            echo "</tr>";
        }
    }
    echo "</table>";
} else {
    echo "<p>No students found. Please run add_new_students.php first.</p>";
}

// Count students by section
$cs_a_count = $conn->query("SELECT COUNT(*) as count FROM students WHERE section = 'CS-A' AND roll_number LIKE '22N81A62%'")->fetch_assoc()['count'];
$cs_b_count = $conn->query("SELECT COUNT(*) as count FROM students WHERE section = 'CS-B' AND roll_number LIKE '22N81A62%'")->fetch_assoc()['count'];

echo "<h3>Summary</h3>";
echo "<ul>";
echo "<li>CS-A Section: {$cs_a_count} students</li>";
echo "<li>CS-B Section: {$cs_b_count} students</li>";
echo "<li>Total: " . ($cs_a_count + $cs_b_count) . " students</li>";
echo "</ul>";

echo "<h3>Password Pattern</h3>";
echo "<p>All passwords follow the pattern: <strong>Student@XXX</strong> where XXX is the last 3 characters of the roll number.</p>";
echo "<p>For example:</p>";
echo "<ul>";
echo "<li>Roll Number 22N81A6201 → Password: Student@201</li>";
echo "<li>Roll Number 22N81A62A5 → Password: Student@2A5</li>";
echo "<li>Roll Number 22N81A62C8 → Password: Student@2C8</li>";
echo "</ul>";

$conn->close();
?>
