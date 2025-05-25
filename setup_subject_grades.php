<?php
// Setup script for subject-based grades system
echo "<h2>Setting up Subject-Based Grades System</h2>";

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<p>Connected to database successfully.</p>";

// Read and execute SQL file
$sql_content = file_get_contents('create_subject_grades_tables.sql');
$sql_statements = explode(';', $sql_content);

$success_count = 0;
$error_count = 0;

foreach ($sql_statements as $statement) {
    $statement = trim($statement);
    if (!empty($statement)) {
        if ($conn->query($statement)) {
            $success_count++;
            echo "<p style='color: green;'>✓ Executed: " . substr($statement, 0, 50) . "...</p>";
        } else {
            $error_count++;
            echo "<p style='color: red;'>✗ Error: " . $conn->error . "</p>";
            echo "<p style='color: red;'>Statement: " . substr($statement, 0, 100) . "...</p>";
        }
    }
}

echo "<h3>Setup Summary:</h3>";
echo "<p>Successful operations: <strong>$success_count</strong></p>";
echo "<p>Errors: <strong>$error_count</strong></p>";

// Verify tables were created
echo "<h3>Verifying Tables:</h3>";
$tables_to_check = ['subject_grades', 'student_subject_enrollment', 'semester_results'];

foreach ($tables_to_check as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "<p style='color: green;'>✓ Table '$table' exists</p>";
        
        // Show table structure
        $structure = $conn->query("DESCRIBE $table");
        echo "<details><summary>View structure</summary>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $structure->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table></details>";
    } else {
        echo "<p style='color: red;'>✗ Table '$table' does not exist</p>";
    }
}

// Check student-subject enrollments
echo "<h3>Checking Student-Subject Enrollments:</h3>";
$result = $conn->query("
    SELECT s.full_name, sub.subject_name, sub.subject_code 
    FROM student_subject_enrollment sse
    JOIN students s ON sse.student_id = s.id
    JOIN subjects sub ON sse.subject_id = sub.id
    LIMIT 10
");

if ($result->num_rows > 0) {
    echo "<p style='color: green;'>✓ Student-subject enrollments created:</p>";
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>" . $row['full_name'] . " enrolled in " . $row['subject_code'] . " - " . $row['subject_name'] . "</li>";
    }
    echo "</ul>";
    
    $total_result = $conn->query("SELECT COUNT(*) as count FROM student_subject_enrollment");
    $total_enrollments = $total_result->fetch_assoc()['count'];
    echo "<p><strong>Total enrollments: $total_enrollments</strong></p>";
} else {
    echo "<p style='color: orange;'>⚠ No student-subject enrollments found. Students may need to be enrolled manually.</p>";
}

// Check semester results
echo "<h3>Checking Semester Results:</h3>";
$result = $conn->query("
    SELECT s.full_name, sr.year, sr.semester, sr.total_credits, sr.sgpa, sr.cgpa
    FROM semester_results sr
    JOIN students s ON sr.student_id = s.id
    LIMIT 5
");

if ($result->num_rows > 0) {
    echo "<p style='color: green;'>✓ Semester results initialized:</p>";
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>" . $row['full_name'] . " - " . $row['year'] . " Year, " . $row['semester'] . " Semester (Credits: " . $row['total_credits'] . ")</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: orange;'>⚠ No semester results found.</p>";
}

// Add some sample grades for demonstration
echo "<h3>Adding Sample Grades:</h3>";
$sample_grades = [
    [1, 1, 'Internal Assessment 1', 85.00, 100.00, 'A', 9.00, 1],
    [1, 1, 'Internal Assessment 2', 78.00, 100.00, 'B', 8.00, 1],
    [1, 2, 'Internal Assessment 1', 92.00, 100.00, 'A', 9.00, 2],
    [2, 1, 'Internal Assessment 1', 76.00, 100.00, 'B', 8.00, 1],
    [2, 2, 'Internal Assessment 1', 88.00, 100.00, 'A', 9.00, 2]
];

foreach ($sample_grades as $grade) {
    $stmt = $conn->prepare("
        INSERT IGNORE INTO subject_grades 
        (student_id, subject_id, assessment_type, marks_obtained, max_marks, grade_letter, grade_points, graded_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iisddidi", $grade[0], $grade[1], $grade[2], $grade[3], $grade[4], $grade[5], $grade[6], $grade[7]);
    
    if ($stmt->execute()) {
        echo "<p style='color: green;'>✓ Added sample grade: Student {$grade[0]}, Subject {$grade[1]}, {$grade[2]}</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Sample grade already exists or error: " . $conn->error . "</p>";
    }
}

$conn->close();

echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>Visit <a href='admin_grades.php'>Admin Grades</a> to view the new subject-based grade system</li>";
echo "<li>Visit <a href='subject_grade_management.php'>Subject Grade Management</a> to add grades for students</li>";
echo "<li>Visit <a href='student_grades.php'>Student Grades</a> to test student grade viewing</li>";
echo "<li>Faculty can now assign grades through their subject management interface</li>";
echo "</ol>";

echo "<p><strong>Subject-based grades system setup completed!</strong></p>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
    line-height: 1.6;
}
details {
    margin: 10px 0;
}
summary {
    cursor: pointer;
    font-weight: bold;
    color: #007bff;
}
table {
    width: 100%;
    font-size: 12px;
}
th, td {
    padding: 5px;
    text-align: left;
}
th {
    background-color: #f8f9fa;
}
</style>
