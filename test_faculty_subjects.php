<?php
// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Faculty Subject Assignment Test</h2>";

// Test with each faculty ID
$faculty_result = $conn->query("SELECT id, full_name, email FROM faculty ORDER BY id");

if ($faculty_result->num_rows > 0) {
    while ($faculty = $faculty_result->fetch_assoc()) {
        echo "<h3>Testing Faculty: " . htmlspecialchars($faculty['full_name']) . " (ID: " . $faculty['id'] . ")</h3>";
        
        // Test the exact query from faculty dashboard
        $faculty_id = $faculty['id'];
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM subjects WHERE faculty_id = ?");
        $stmt->bind_param("i", $faculty_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $subject_count = ($result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;
        $stmt->close();
        
        echo "<p><strong>Subject Count:</strong> " . $subject_count . "</p>";
        
        // Get actual subjects assigned
        $stmt = $conn->prepare("SELECT * FROM subjects WHERE faculty_id = ?");
        $stmt->bind_param("i", $faculty_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo "<p><strong>Assigned Subjects:</strong></p>";
            echo "<ul>";
            while ($subject = $result->fetch_assoc()) {
                echo "<li>" . htmlspecialchars($subject['abbreviation']) . " - " . htmlspecialchars($subject['subject_name']) . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color: red;'>No subjects assigned to this faculty member.</p>";
        }
        $stmt->close();
        
        echo "<hr>";
    }
} else {
    echo "<p style='color: red;'>No faculty found in database!</p>";
}

echo "<h3>All Subjects in Database:</h3>";
$all_subjects = $conn->query("SELECT s.*, f.full_name as faculty_name FROM subjects s LEFT JOIN faculty f ON s.faculty_id = f.id ORDER BY s.id");

if ($all_subjects->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Code</th><th>Subject Name</th><th>Faculty ID</th><th>Faculty Name</th></tr>";
    while ($subject = $all_subjects->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $subject['id'] . "</td>";
        echo "<td>" . htmlspecialchars($subject['abbreviation']) . "</td>";
        echo "<td>" . htmlspecialchars($subject['subject_name']) . "</td>";
        echo "<td>" . ($subject['faculty_id'] ?: 'NULL') . "</td>";
        echo "<td>" . ($subject['faculty_name'] ?: 'Not Assigned') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>No subjects found in database!</p>";
}

echo "<hr>";
echo "<h3>Quick Actions:</h3>";
echo "<p><a href='check_subjects.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Check & Fix Subject Assignments</a></p>";
echo "<p><a href='faculty_dashboard.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Faculty Dashboard</a></p>";
echo "<p><a href='setup_database.php' style='background: #ffc107; color: black; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Run Database Setup</a></p>";

$conn->close();
?>
