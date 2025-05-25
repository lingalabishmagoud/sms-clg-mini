<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Database Status Check</h2>";

// Check if database exists
echo "<h3>1. Database Connection</h3>";
echo "<p style='color: green;'>✅ Successfully connected to database 'student_db'</p>";

// Check if students table exists
echo "<h3>2. Students Table</h3>";
$result = $conn->query("SHOW TABLES LIKE 'students'");
if ($result->num_rows > 0) {
    echo "<p style='color: green;'>✅ Students table exists</p>";
    
    // Check table structure
    $columns = $conn->query("SHOW COLUMNS FROM students");
    $required_columns = ['roll_number', 'father_name', 'dob', 'blood_group', 'aadhaar_number', 'address', 'section'];
    $existing_columns = [];
    
    while ($col = $columns->fetch_assoc()) {
        $existing_columns[] = $col['Field'];
    }
    
    echo "<h4>Required Columns Check:</h4>";
    echo "<ul>";
    foreach ($required_columns as $req_col) {
        if (in_array($req_col, $existing_columns)) {
            echo "<li style='color: green;'>✅ {$req_col} - exists</li>";
        } else {
            echo "<li style='color: red;'>❌ {$req_col} - missing</li>";
        }
    }
    echo "</ul>";
    
} else {
    echo "<p style='color: red;'>❌ Students table does not exist</p>";
}

// Check total students
echo "<h3>3. Student Data</h3>";
$result = $conn->query("SELECT COUNT(*) as total FROM students");
if ($result) {
    $row = $result->fetch_assoc();
    echo "<p>Total students: <strong>{$row['total']}</strong></p>";
    
    if ($row['total'] == 0) {
        echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
        echo "<h4 style='color: #856404; margin-top: 0;'>⚠️ No Students Found</h4>";
        echo "<p style='color: #856404; margin-bottom: 0;'>The students table is empty. You need to run the setup scripts:</p>";
        echo "<ol style='color: #856404;'>";
        echo "<li><a href='setup_database.php'>setup_database.php</a> - to create/update table structure</li>";
        echo "<li><a href='add_new_students.php'>add_new_students.php</a> - to add all the student data</li>";
        echo "</ol>";
        echo "</div>";
    }
} else {
    echo "<p style='color: red;'>Error checking student count: " . $conn->error . "</p>";
}

// Check for new students specifically
$result = $conn->query("SELECT COUNT(*) as count FROM students WHERE roll_number LIKE '22N81A62%'");
if ($result) {
    $row = $result->fetch_assoc();
    echo "<p>Students with roll numbers 22N81A62xxx: <strong>{$row['count']}</strong></p>";
    
    if ($row['count'] == 0) {
        echo "<p style='color: orange;'>⚠️ No new students found. Please run <a href='add_new_students.php'>add_new_students.php</a></p>";
    }
}

// Check if Lingala Bishma exists
echo "<h3>4. Specific Student Check</h3>";
$result = $conn->query("SELECT roll_number, full_name, email FROM students WHERE full_name LIKE '%LINGALA%' OR full_name LIKE '%BISHMA%'");
if ($result && $result->num_rows > 0) {
    echo "<p style='color: green;'>✅ Found Lingala Bishma:</p>";
    while ($row = $result->fetch_assoc()) {
        echo "<ul>";
        echo "<li><strong>Roll:</strong> {$row['roll_number']}</li>";
        echo "<li><strong>Name:</strong> {$row['full_name']}</li>";
        echo "<li><strong>Email:</strong> {$row['email']}</li>";
        echo "</ul>";
    }
} else {
    echo "<p style='color: red;'>❌ Lingala Bishma not found in database</p>";
}

echo "<h3>5. Next Steps</h3>";
echo "<div style='background: #e7f3ff; border: 1px solid #b3d9ff; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
echo "<h4 style='color: #004085; margin-top: 0;'>📋 Action Plan</h4>";
echo "<ol style='color: #004085;'>";
echo "<li>First run: <a href='setup_database.php'><strong>setup_database.php</strong></a> (to ensure table structure is correct)</li>";
echo "<li>Then run: <a href='add_new_students.php'><strong>add_new_students.php</strong></a> (to add all student data)</li>";
echo "<li>Finally test: <a href='test_specific_login.php'><strong>test_specific_login.php</strong></a> (to verify the specific login)</li>";
echo "</ol>";
echo "</div>";

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
li {
    margin: 5px 0;
}
</style>
