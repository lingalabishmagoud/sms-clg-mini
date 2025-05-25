<?php
// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Subject and Faculty Assignment Check</h2>";

// Check faculty table
echo "<h3>Faculty in Database:</h3>";
$faculty_result = $conn->query("SELECT id, full_name, email FROM faculty ORDER BY id");
if ($faculty_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th></tr>";
    while ($faculty = $faculty_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $faculty['id'] . "</td>";
        echo "<td>" . htmlspecialchars($faculty['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars($faculty['email']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>No faculty found!</p>";
}

echo "<hr>";

// Check subjects table
echo "<h3>Subjects in Database:</h3>";
$subjects_result = $conn->query("
    SELECT s.*, f.full_name as faculty_name 
    FROM subjects s 
    LEFT JOIN faculty f ON s.faculty_id = f.id 
    ORDER BY s.id
");

if ($subjects_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Code</th><th>Subject Name</th><th>Faculty ID</th><th>Faculty Name</th><th>Credits</th></tr>";
    while ($subject = $subjects_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $subject['id'] . "</td>";
        echo "<td>" . htmlspecialchars($subject['abbreviation']) . "</td>";
        echo "<td>" . htmlspecialchars($subject['subject_name']) . "</td>";
        echo "<td>" . ($subject['faculty_id'] ?: 'NULL') . "</td>";
        echo "<td>" . ($subject['faculty_name'] ?: 'Not Assigned') . "</td>";
        echo "<td>" . $subject['credits'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>No subjects found!</p>";
}

echo "<hr>";

// Check subject counts per faculty
echo "<h3>Subject Count per Faculty:</h3>";
$count_result = $conn->query("
    SELECT f.id, f.full_name, COUNT(s.id) as subject_count
    FROM faculty f
    LEFT JOIN subjects s ON f.id = s.faculty_id
    GROUP BY f.id, f.full_name
    ORDER BY f.id
");

if ($count_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Faculty ID</th><th>Faculty Name</th><th>Subject Count</th></tr>";
    while ($count = $count_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $count['id'] . "</td>";
        echo "<td>" . htmlspecialchars($count['full_name']) . "</td>";
        echo "<td>" . $count['subject_count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";

// Fix subject assignments
echo "<h3>Fix Subject Assignments</h3>";
echo "<p>Click the button below to properly assign subjects to faculty members:</p>";

if (isset($_POST['fix_assignments'])) {
    echo "<h4>Fixing Subject Assignments...</h4>";
    
    // Get faculty IDs
    $faculty_ids = [];
    $faculty_result = $conn->query("SELECT id, full_name FROM faculty ORDER BY id");
    while ($faculty = $faculty_result->fetch_assoc()) {
        $faculty_ids[$faculty['full_name']] = $faculty['id'];
    }
    
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
    
    foreach ($subject_assignments as $subject_code => $faculty_name) {
        if (isset($faculty_ids[$faculty_name])) {
            $faculty_id = $faculty_ids[$faculty_name];
            $stmt = $conn->prepare("UPDATE subjects SET faculty_id = ? WHERE abbreviation = ?");
            $stmt->bind_param("is", $faculty_id, $subject_code);
            
            if ($stmt->execute()) {
                echo "<p style='color: green;'>✅ Assigned $subject_code to $faculty_name (ID: $faculty_id)</p>";
            } else {
                echo "<p style='color: red;'>❌ Failed to assign $subject_code to $faculty_name: " . $stmt->error . "</p>";
            }
            $stmt->close();
        } else {
            echo "<p style='color: orange;'>⚠️ Faculty not found: $faculty_name for subject $subject_code</p>";
        }
    }
    
    echo "<p style='color: blue;'><strong>Assignment complete! Refresh the page to see updated counts.</strong></p>";
}

echo "<form method='POST'>";
echo "<button type='submit' name='fix_assignments' style='background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Fix Subject Assignments</button>";
echo "</form>";

echo "<hr>";
echo "<p><a href='faculty_dashboard.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Faculty Dashboard</a></p>";
echo "<p><a href='setup_database.php' style='background: #ffc107; color: black; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Run Database Setup</a></p>";

$conn->close();
?>
