<?php
// Database cleanup script to remove unwanted courses and consolidate system
// This script will clean up the unwanted courses (MATH101, ENG101, BUS101, CS202, CS101)

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Database Cleanup Script</h2>";
echo "<p>Removing unwanted courses and consolidating system...</p>";

// Step 1: Remove unwanted courses from courses table
echo "<h3>Step 1: Removing Unwanted Courses</h3>";

$unwanted_courses = ['MATH101', 'ENG101', 'BUS101', 'CS202', 'CS101'];

foreach ($unwanted_courses as $course_code) {
    // First check if course exists
    $check_stmt = $conn->prepare("SELECT id, course_name FROM courses WHERE course_code = ?");
    $check_stmt->bind_param("s", $course_code);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $course = $result->fetch_assoc();
        echo "<p>Found course: {$course_code} - {$course['course_name']}</p>";
        
        // Delete the course
        $delete_stmt = $conn->prepare("DELETE FROM courses WHERE course_code = ?");
        $delete_stmt->bind_param("s", $course_code);
        
        if ($delete_stmt->execute()) {
            echo "<p style='color: green;'>✅ Successfully deleted course: {$course_code}</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to delete course: {$course_code} - " . $delete_stmt->error . "</p>";
        }
        $delete_stmt->close();
    } else {
        echo "<p style='color: orange;'>⚠️ Course not found: {$course_code}</p>";
    }
    $check_stmt->close();
}

// Step 2: Clean up any related enrollments or data
echo "<h3>Step 2: Cleaning Related Data</h3>";

// Check if there are any enrollments table references to clean up
$tables_to_check = ['enrollments', 'grades', 'forum_topics', 'forum_replies'];

foreach ($tables_to_check as $table) {
    // Check if table exists
    $table_check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($table_check->num_rows > 0) {
        // Check if table has course_id column
        $column_check = $conn->query("SHOW COLUMNS FROM $table LIKE 'course_id'");
        if ($column_check->num_rows > 0) {
            // Clean up orphaned records (course_id that no longer exists in courses table)
            $cleanup_sql = "DELETE FROM $table WHERE course_id NOT IN (SELECT id FROM courses)";
            if ($conn->query($cleanup_sql)) {
                $affected_rows = $conn->affected_rows;
                if ($affected_rows > 0) {
                    echo "<p style='color: green;'>✅ Cleaned up $affected_rows orphaned records from $table</p>";
                } else {
                    echo "<p>✅ No orphaned records found in $table</p>";
                }
            } else {
                echo "<p style='color: red;'>❌ Failed to clean up $table: " . $conn->error . "</p>";
            }
        }
    }
}

// Step 3: Display current courses count
echo "<h3>Step 3: Current System Status</h3>";

$courses_result = $conn->query("SELECT COUNT(*) as count FROM courses");
$courses_count = $courses_result->fetch_assoc()['count'];

$subjects_result = $conn->query("SELECT COUNT(*) as count FROM subjects");
$subjects_count = $subjects_result->fetch_assoc()['count'];

echo "<p><strong>Remaining Courses:</strong> $courses_count</p>";
echo "<p><strong>Current Subjects:</strong> $subjects_count</p>";

// Step 4: Show remaining courses if any
if ($courses_count > 0) {
    echo "<h4>Remaining Courses:</h4>";
    $remaining_courses = $conn->query("SELECT course_code, course_name, credits FROM courses ORDER BY course_code");
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr><th>Course Code</th><th>Course Name</th><th>Credits</th></tr>";
    while ($course = $remaining_courses->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($course['course_code']) . "</td>";
        echo "<td>" . htmlspecialchars($course['course_name']) . "</td>";
        echo "<td>" . $course['credits'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Step 5: Show current subjects
echo "<h4>Current Subjects:</h4>";
$current_subjects = $conn->query("SELECT s.subject_code, s.subject_name, s.credits, f.full_name as faculty_name FROM subjects s LEFT JOIN faculty f ON s.faculty_id = f.id ORDER BY s.subject_code");
if ($current_subjects->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr><th>Subject Code</th><th>Subject Name</th><th>Credits</th><th>Faculty</th></tr>";
    while ($subject = $current_subjects->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($subject['subject_code']) . "</td>";
        echo "<td>" . htmlspecialchars($subject['subject_name']) . "</td>";
        echo "<td>" . $subject['credits'] . "</td>";
        echo "<td>" . htmlspecialchars($subject['faculty_name'] ?: 'Not Assigned') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>No subjects found!</p>";
}

echo "<h3>✅ Cleanup Complete!</h3>";
echo "<p>The unwanted courses have been removed from the system.</p>";
echo "<p><a href='admin_dashboard.php'>Return to Admin Dashboard</a></p>";

$conn->close();
?>
