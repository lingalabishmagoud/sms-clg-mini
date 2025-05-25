<?php
// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Fix Subject Assignments</h2>";

// Get faculty IDs
$faculty_map = [];
$faculty_result = $conn->query("SELECT id, full_name FROM faculty");
while ($row = $faculty_result->fetch_assoc()) {
    $faculty_map[$row['full_name']] = $row['id'];
    echo "<p>Faculty: " . htmlspecialchars($row['full_name']) . " (ID: " . $row['id'] . ")</p>";
}

echo "<hr>";

// Subject assignments based on the schedule
$subject_assignments = [
    'CSE' => 'Dr. K. Subba Rao',
    'CCDF' => 'Mr. Mukesh Gilda', 
    'ADA' => 'Mrs. P. Sandhya Rani',
    'DEVOPS' => 'Mr. J. Naresh Kumar',
    'FIOT' => 'Mr. R. Anbarasu',
    'ES' => 'Mr. R. Anbarasu',
    'IOMP' => 'Mrs. P. Sandhya Rani'
];

echo "<h3>Updating Subject Assignments...</h3>";

foreach ($subject_assignments as $subject_code => $faculty_name) {
    if (isset($faculty_map[$faculty_name])) {
        $faculty_id = $faculty_map[$faculty_name];
        
        // Check if subject exists
        $check_stmt = $conn->prepare("SELECT id FROM subjects WHERE abbreviation = ?");
        $check_stmt->bind_param("s", $subject_code);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE subjects SET faculty_id = ? WHERE abbreviation = ?");
            $stmt->bind_param("is", $faculty_id, $subject_code);
            
            if ($stmt->execute()) {
                echo "<p style='color: green;'>✅ Assigned $subject_code to $faculty_name (ID: $faculty_id)</p>";
            } else {
                echo "<p style='color: red;'>❌ Failed to assign $subject_code to $faculty_name: " . $stmt->error . "</p>";
            }
            $stmt->close();
        } else {
            echo "<p style='color: orange;'>⚠️ Subject not found: $subject_code</p>";
        }
        $check_stmt->close();
    } else {
        echo "<p style='color: red;'>❌ Faculty not found: $faculty_name for subject $subject_code</p>";
    }
}

echo "<hr>";

// Verify assignments
echo "<h3>Verification - Subject Count per Faculty:</h3>";
$verification_result = $conn->query("
    SELECT f.id, f.full_name, COUNT(s.id) as subject_count
    FROM faculty f
    LEFT JOIN subjects s ON f.id = s.faculty_id
    GROUP BY f.id, f.full_name
    ORDER BY f.id
");

if ($verification_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Faculty ID</th><th>Faculty Name</th><th>Subject Count</th></tr>";
    while ($count = $verification_result->fetch_assoc()) {
        $color = $count['subject_count'] > 0 ? 'green' : 'red';
        echo "<tr>";
        echo "<td>" . $count['id'] . "</td>";
        echo "<td>" . htmlspecialchars($count['full_name']) . "</td>";
        echo "<td style='color: $color; font-weight: bold;'>" . $count['subject_count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";

// Show all subject assignments
echo "<h3>All Subject Assignments:</h3>";
$all_subjects = $conn->query("
    SELECT s.abbreviation, s.subject_name, s.faculty_id, f.full_name as faculty_name
    FROM subjects s
    LEFT JOIN faculty f ON s.faculty_id = f.id
    WHERE s.faculty_id IS NOT NULL
    ORDER BY s.abbreviation
");

if ($all_subjects->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Subject Code</th><th>Subject Name</th><th>Faculty</th></tr>";
    while ($subject = $all_subjects->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($subject['abbreviation']) . "</td>";
        echo "<td>" . htmlspecialchars($subject['subject_name']) . "</td>";
        echo "<td>" . htmlspecialchars($subject['faculty_name']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>No subjects with faculty assignments found!</p>";
}

echo "<hr>";
echo "<h3>Quick Actions:</h3>";
echo "<p><a href='faculty_dashboard.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Faculty Dashboard</a></p>";
echo "<p><a href='test_faculty_subjects.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Faculty Subjects</a></p>";
echo "<p><a href='faculty_login.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Faculty Login</a></p>";

$conn->close();
?>
