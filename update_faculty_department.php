<?php
// Script to update all faculty to Cyber Security department
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Updating Faculty Departments</h2>";

// Update all faculty to Cyber Security department
$sql = "UPDATE faculty SET department = 'Cyber Security'";
if ($conn->query($sql) === TRUE) {
    $affected_rows = $conn->affected_rows;
    echo "<p style='color: green;'>✅ Successfully updated $affected_rows faculty members to Cyber Security department.</p>";
} else {
    echo "<p style='color: red;'>❌ Error updating faculty departments: " . $conn->error . "</p>";
}

// Update all subjects to Cyber Security department
$sql = "UPDATE subjects SET department = 'Cyber Security'";
if ($conn->query($sql) === TRUE) {
    $affected_rows = $conn->affected_rows;
    echo "<p style='color: green;'>✅ Successfully updated $affected_rows subjects to Cyber Security department.</p>";
} else {
    echo "<p style='color: red;'>❌ Error updating subject departments: " . $conn->error . "</p>";
}

// Show updated faculty list
echo "<h3>Updated Faculty List:</h3>";
$result = $conn->query("SELECT id, full_name, email, department, position FROM faculty ORDER BY id");
if ($result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Department</th><th>Position</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['full_name'] . "</td>";
        echo "<td>" . $row['email'] . "</td>";
        echo "<td><strong>" . $row['department'] . "</strong></td>";
        echo "<td>" . ($row['position'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No faculty found.</p>";
}

// Show updated subjects list
echo "<h3>Updated Subjects List:</h3>";
$result = $conn->query("SELECT id, subject_code, subject_name, department, credits FROM subjects ORDER BY id");
if ($result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>ID</th><th>Code</th><th>Subject Name</th><th>Department</th><th>Credits</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['subject_code'] . "</td>";
        echo "<td>" . $row['subject_name'] . "</td>";
        echo "<td><strong>" . $row['department'] . "</strong></td>";
        echo "<td>" . $row['credits'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No subjects found.</p>";
}

echo "<div style='margin-top: 20px;'>";
echo "<a href='admin_faculty.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Go to Faculty Management</a>";
echo "<a href='admin_subjects.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Subject Management</a>";
echo "</div>";

$conn->close();
?>
