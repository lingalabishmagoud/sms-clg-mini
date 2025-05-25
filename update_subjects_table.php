<?php
// Database update script to add missing columns to subjects table
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Updating Subjects Table Schema</h2>";

// Check if subject_type column exists
$result = $conn->query("SHOW COLUMNS FROM subjects LIKE 'subject_type'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE subjects ADD COLUMN subject_type ENUM('theory', 'lab', 'practical', 'workshop') DEFAULT 'theory' AFTER credits";
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✅ Added subject_type column</p>";
    } else {
        echo "<p style='color: red;'>❌ Error adding subject_type column: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ️ subject_type column already exists</p>";
}

// Check if lab_room column exists
$result = $conn->query("SHOW COLUMNS FROM subjects LIKE 'lab_room'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE subjects ADD COLUMN lab_room VARCHAR(50) AFTER subject_type";
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✅ Added lab_room column</p>";
    } else {
        echo "<p style='color: red;'>❌ Error adding lab_room column: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ️ lab_room column already exists</p>";
}

// Check if max_students_per_lab column exists
$result = $conn->query("SHOW COLUMNS FROM subjects LIKE 'max_students_per_lab'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE subjects ADD COLUMN max_students_per_lab INT(11) DEFAULT 30 AFTER lab_room";
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✅ Added max_students_per_lab column</p>";
    } else {
        echo "<p style='color: red;'>❌ Error adding max_students_per_lab column: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ️ max_students_per_lab column already exists</p>";
}

echo "<h3>Database Update Complete!</h3>";
echo "<p><a href='admin_lab_subjects.php'>Go to Lab Subjects Management</a></p>";

$conn->close();
?>
