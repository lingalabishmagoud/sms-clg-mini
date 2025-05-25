<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Complete Student Login Credentials</h2>";
echo "<p>All students have been properly organized into sections as requested:</p>";

// Get section counts
$cs_a_count = $conn->query("SELECT COUNT(*) as count FROM students WHERE section = 'CS-A' AND roll_number LIKE '22N81A62%'")->fetch_assoc()['count'];
$cs_b_count = $conn->query("SELECT COUNT(*) as count FROM students WHERE section = 'CS-B' AND roll_number LIKE '22N81A62%'")->fetch_assoc()['count'];

echo "<div style='background: #e7f3ff; border: 1px solid #b3d9ff; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
echo "<h3 style='color: #004085; margin-top: 0;'>📊 Section Summary</h3>";
echo "<ul style='color: #004085;'>";
echo "<li><strong>CS-A Section:</strong> {$cs_a_count} students (Roll Numbers: 22N81A6201-22N81A6267)</li>";
echo "<li><strong>CS-B Section:</strong> {$cs_b_count} students (Roll Numbers: 22N81A6268-22N81A62C8)</li>";
echo "<li><strong>Total Students:</strong> " . ($cs_a_count + $cs_b_count) . "</li>";
echo "</ul>";
echo "</div>";

// Display CS-A Section
echo "<h3 style='color: #007bff;'>🔵 CS-A Section Students</h3>";
echo "<p><strong>Roll Number Range:</strong> 22N81A6201-22N81A6267</p>";

$cs_a_result = $conn->query("SELECT roll_number, full_name, email FROM students WHERE section = 'CS-A' AND roll_number LIKE '22N81A62%' ORDER BY roll_number");

if ($cs_a_result && $cs_a_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-bottom: 30px;'>";
    echo "<tr style='background: #007bff; color: white;'><th>Roll Number</th><th>Name</th><th>Email</th><th>Password</th></tr>";

    while ($row = $cs_a_result->fetch_assoc()) {
        $password = 'Student@' . substr($row['roll_number'], -3);
        $highlight = ($row['roll_number'] == '22N81A6254') ? 'background: #fff3cd; font-weight: bold;' : '';
        echo "<tr style='{$highlight}'>";
        echo "<td>{$row['roll_number']}</td>";
        echo "<td>{$row['full_name']}</td>";
        echo "<td>{$row['email']}</td>";
        echo "<td><strong>{$password}</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No CS-A students found.</p>";
}

// Display CS-B Section
echo "<h3 style='color: #28a745;'>🟢 CS-B Section Students</h3>";
echo "<p><strong>Roll Number Range:</strong> 22N81A6268-22N81A62C8</p>";

$cs_b_result = $conn->query("SELECT roll_number, full_name, email FROM students WHERE section = 'CS-B' AND roll_number LIKE '22N81A62%' ORDER BY roll_number");

if ($cs_b_result && $cs_b_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-bottom: 30px;'>";
    echo "<tr style='background: #28a745; color: white;'><th>Roll Number</th><th>Name</th><th>Email</th><th>Password</th></tr>";

    while ($row = $cs_b_result->fetch_assoc()) {
        $password = 'Student@' . substr($row['roll_number'], -3);
        $highlight = ($row['roll_number'] == '22N81A6208') ? 'background: #fff3cd; font-weight: bold;' : '';
        echo "<tr style='{$highlight}'>";
        echo "<td>{$row['roll_number']}</td>";
        echo "<td>{$row['full_name']}</td>";
        echo "<td>{$row['email']}</td>";
        echo "<td><strong>{$password}</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No CS-B students found.</p>";
}

// Password pattern explanation
echo "<div style='background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
echo "<h3 style='margin-top: 0;'>🔑 Password Pattern</h3>";
echo "<p><strong>All passwords follow the pattern:</strong> <code>Student@XXX</code></p>";
echo "<p>Where <strong>XXX</strong> = last 3 characters of the roll number</p>";
echo "<h4>Examples:</h4>";
echo "<ul>";
echo "<li>Roll Number <strong>22N81A6254</strong> → Password: <strong>Student@254</strong></li>";
echo "<li>Roll Number <strong>22N81A6208</strong> → Password: <strong>Student@208</strong></li>";
echo "<li>Roll Number <strong>22N81A62A5</strong> → Password: <strong>Student@2A5</strong></li>";
echo "<li>Roll Number <strong>22N81A62C8</strong> → Password: <strong>Student@2C8</strong></li>";
echo "</ul>";
echo "</div>";

// Quick test section
echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
echo "<h3 style='color: #155724; margin-top: 0;'>🧪 Quick Test Credentials</h3>";
echo "<p style='color: #155724;'>Here are some credentials you can test immediately:</p>";

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #155724; color: white;'><th>Section</th><th>Roll Number</th><th>Email</th><th>Password</th></tr>";

// Your account
echo "<tr style='background: #fff3cd;'>";
echo "<td><strong>CS-A</strong></td>";
echo "<td><strong>22N81A6254</strong></td>";
echo "<td><strong>lingala.bishma@student.college.edu</strong></td>";
echo "<td><strong>Student@254</strong></td>";
echo "</tr>";

// Sample from CS-A
$sample_cs_a = $conn->query("SELECT roll_number, full_name, email FROM students WHERE section = 'CS-A' AND roll_number LIKE '22N81A62%' AND roll_number != '22N81A6254' ORDER BY roll_number LIMIT 2");
while ($row = $sample_cs_a->fetch_assoc()) {
    $password = 'Student@' . substr($row['roll_number'], -3);
    echo "<tr>";
    echo "<td>CS-A</td>";
    echo "<td>{$row['roll_number']}</td>";
    echo "<td>{$row['email']}</td>";
    echo "<td>{$password}</td>";
    echo "</tr>";
}

// Sample from CS-B
$sample_cs_b = $conn->query("SELECT roll_number, full_name, email FROM students WHERE section = 'CS-B' AND roll_number LIKE '22N81A62%' ORDER BY roll_number LIMIT 2");
while ($row = $sample_cs_b->fetch_assoc()) {
    $password = 'Student@' . substr($row['roll_number'], -3);
    echo "<tr>";
    echo "<td>CS-B</td>";
    echo "<td>{$row['roll_number']}</td>";
    echo "<td>{$row['email']}</td>";
    echo "<td>{$password}</td>";
    echo "</tr>";
}

echo "</table>";
echo "<p style='color: #155724; margin-bottom: 0;'><a href='student_login.php' style='color: #155724; text-decoration: none; font-weight: bold; font-size: 18px;'>→ CLICK HERE TO TEST LOGIN!</a></p>";
echo "</div>";

$conn->close();
?>

<style>
table { margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
code { background: #f8f9fa; padding: 2px 4px; border-radius: 3px; }
</style>
