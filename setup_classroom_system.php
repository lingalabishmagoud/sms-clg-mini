<?php
// Setup script for classroom discussion system
echo "<h2>Setting up Classroom Discussion System</h2>";

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<p>Connected to database successfully.</p>";

// Read and execute SQL file
$sql_content = file_get_contents('create_classroom_tables.sql');
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
$tables_to_check = ['classrooms', 'classroom_discussions', 'classroom_discussion_replies'];

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

// Check if classrooms were inserted
echo "<h3>Checking Default Data:</h3>";
$result = $conn->query("SELECT * FROM classrooms");
if ($result->num_rows > 0) {
    echo "<p style='color: green;'>✓ Default classrooms inserted:</p>";
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>" . $row['classroom_name'] . " - " . $row['year'] . " Year, " . $row['semester'] . " Semester (Room: " . $row['room_number'] . ")</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: orange;'>⚠ No classrooms found. You may need to add them manually.</p>";
}

// Check student semester update
$result = $conn->query("SELECT COUNT(*) as count FROM students WHERE year = 3 AND semester = '2nd'");
$student_count = $result->fetch_assoc()['count'];
echo "<p style='color: green;'>✓ Updated $student_count students to 2nd semester</p>";

$conn->close();

echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>Visit <a href='admin_classrooms.php'>Admin Classrooms</a> to manage classrooms</li>";
echo "<li>Visit <a href='admin_discussions.php'>Admin Discussions</a> to monitor discussions</li>";
echo "<li>Visit <a href='classroom_discussions.php?user_type=student'>Student Discussions</a> to test student access</li>";
echo "<li>Visit <a href='classroom_discussions.php?user_type=faculty'>Faculty Discussions</a> to test faculty access</li>";
echo "</ol>";

echo "<p><strong>Setup completed!</strong></p>";
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
