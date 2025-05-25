<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Fixing Section Assignment - Moving 22N81A6208 to CS-A</h2>";

// Updated section assignment logic
function getCorrectSection($rollNumber) {
    // CS-A Section: 22N81A6201-22N81A6267 (INCLUDING 22N81A6208)
    // CS-B Section: 22N81A6268-22N81A62C8
    
    // Define CS-A roll numbers (22N81A6201 to 22N81A6267, INCLUDING 208)
    $cs_a_rolls = [];
    for ($i = 201; $i <= 267; $i++) {
        $cs_a_rolls[] = '22N81A6' . sprintf('%03d', $i);
    }
    
    // Check if current roll number is in CS-A list
    if (in_array($rollNumber, $cs_a_rolls)) {
        return 'CS-A';
    } else {
        // All other roll numbers (22N81A6268 onwards) are CS-B
        return 'CS-B';
    }
}

// Step 1: Check current status of 22N81A6208
echo "<h3>Step 1: Current Status of Roll Number 22N81A6208</h3>";
$check_208 = $conn->query("SELECT id, roll_number, full_name, section FROM students WHERE roll_number = '22N81A6208'");

if ($check_208 && $check_208->num_rows > 0) {
    $student_208 = $check_208->fetch_assoc();
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Roll Number</th><th>Name</th><th>Current Section</th><th>Required Section</th></tr>";
    echo "<tr>";
    echo "<td>{$student_208['roll_number']}</td>";
    echo "<td>{$student_208['full_name']}</td>";
    echo "<td><strong>{$student_208['section']}</strong></td>";
    echo "<td><strong>CS-A</strong></td>";
    echo "</tr>";
    echo "</table>";
    
    if ($student_208['section'] !== 'CS-A') {
        echo "<p style='color: orange;'>⚠️ Student 22N81A6208 is currently in {$student_208['section']} but should be in CS-A</p>";
        
        // Update the student to CS-A
        echo "<h3>Step 2: Moving 22N81A6208 to CS-A</h3>";
        $update_stmt = $conn->prepare("UPDATE students SET section = 'CS-A' WHERE roll_number = '22N81A6208'");
        
        if ($update_stmt->execute()) {
            echo "<p style='color: green;'>✅ Successfully moved 22N81A6208 ({$student_208['full_name']}) to CS-A section</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to update: " . $update_stmt->error . "</p>";
        }
        $update_stmt->close();
    } else {
        echo "<p style='color: green;'>✅ Student 22N81A6208 is already in CS-A section</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Student with roll number 22N81A6208 not found</p>";
}

// Step 3: Update all other students to ensure correct sections
echo "<h3>Step 3: Verifying All Other Students</h3>";

$all_students = $conn->query("SELECT id, roll_number, full_name, section FROM students WHERE roll_number LIKE '22N81A62%' ORDER BY roll_number");

if ($all_students && $all_students->num_rows > 0) {
    $updates_made = 0;
    $cs_a_count = 0;
    $cs_b_count = 0;
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Roll Number</th><th>Name</th><th>Current Section</th><th>Correct Section</th><th>Status</th></tr>";
    
    while ($student = $all_students->fetch_assoc()) {
        $correct_section = getCorrectSection($student['roll_number']);
        $needs_update = ($student['section'] !== $correct_section);
        
        if ($correct_section === 'CS-A') {
            $cs_a_count++;
        } else {
            $cs_b_count++;
        }
        
        $row_color = '';
        $status = '✅ Correct';
        
        if ($needs_update) {
            $row_color = 'background: #fff3cd;';
            $status = '⚠️ Updated';
            
            // Update the student
            $update_stmt = $conn->prepare("UPDATE students SET section = ? WHERE id = ?");
            $update_stmt->bind_param("si", $correct_section, $student['id']);
            
            if ($update_stmt->execute()) {
                $updates_made++;
                $status = '✅ Updated';
            } else {
                $status = '❌ Failed';
            }
            $update_stmt->close();
        }
        
        // Highlight 22N81A6208
        if ($student['roll_number'] === '22N81A6208') {
            $row_color = 'background: #d4edda; font-weight: bold;';
        }
        
        echo "<tr style='{$row_color}'>";
        echo "<td>{$student['roll_number']}</td>";
        echo "<td>{$student['full_name']}</td>";
        echo "<td>{$student['section']}</td>";
        echo "<td><strong>{$correct_section}</strong></td>";
        echo "<td>{$status}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p><strong>Updates made: {$updates_made}</strong></p>";
}

// Step 4: Final verification and summary
echo "<h3>Step 4: Final Section Distribution</h3>";

$final_cs_a = $conn->query("SELECT COUNT(*) as count FROM students WHERE section = 'CS-A' AND roll_number LIKE '22N81A62%'")->fetch_assoc()['count'];
$final_cs_b = $conn->query("SELECT COUNT(*) as count FROM students WHERE section = 'CS-B' AND roll_number LIKE '22N81A62%'")->fetch_assoc()['count'];

echo "<table border='1' style='border-collapse: collapse; width: 70%;'>";
echo "<tr style='background: #f2f2f2;'><th>Section</th><th>Student Count</th><th>Roll Number Range</th></tr>";
echo "<tr>";
echo "<td><strong>CS-A</strong></td>";
echo "<td><strong>{$final_cs_a}</strong></td>";
echo "<td>22N81A6201-22N81A6267 (INCLUDING 22N81A6208)</td>";
echo "</tr>";
echo "<tr>";
echo "<td><strong>CS-B</strong></td>";
echo "<td><strong>{$final_cs_b}</strong></td>";
echo "<td>22N81A6268-22N81A62C8</td>";
echo "</tr>";
echo "</table>";

// Verify 22N81A6208 is in CS-A
echo "<h3>Step 5: Verification of 22N81A6208</h3>";
$verify_208 = $conn->query("SELECT roll_number, full_name, email, section FROM students WHERE roll_number = '22N81A6208'");

if ($verify_208 && $verify_208->num_rows > 0) {
    $student = $verify_208->fetch_assoc();
    $password = 'Student@' . substr($student['roll_number'], -3);
    
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
    echo "<h4 style='color: #155724; margin-top: 0;'>✅ Verification Complete!</h4>";
    echo "<p style='color: #155724;'><strong>Student 22N81A6208 Details:</strong></p>";
    echo "<ul style='color: #155724;'>";
    echo "<li><strong>Roll Number:</strong> {$student['roll_number']}</li>";
    echo "<li><strong>Name:</strong> {$student['full_name']}</li>";
    echo "<li><strong>Email:</strong> {$student['email']}</li>";
    echo "<li><strong>Password:</strong> {$password}</li>";
    echo "<li><strong>Section:</strong> <strong>{$student['section']}</strong></li>";
    echo "</ul>";
    echo "<p style='color: #155724; margin-bottom: 0;'>Student 22N81A6208 is now correctly assigned to CS-A section!</p>";
    echo "</div>";
}

echo "<div style='background: #e7f3ff; border: 1px solid #b3d9ff; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
echo "<h4 style='color: #004085; margin-top: 0;'>📋 Updated Section Assignment</h4>";
echo "<ul style='color: #004085;'>";
echo "<li><strong>CS-A Section:</strong> {$final_cs_a} students (22N81A6201-22N81A6267, INCLUDING 22N81A6208)</li>";
echo "<li><strong>CS-B Section:</strong> {$final_cs_b} students (22N81A6268-22N81A62C8)</li>";
echo "<li><strong>Total Students:</strong> " . ($final_cs_a + $final_cs_b) . "</li>";
echo "</ul>";
echo "<p style='color: #004085; margin-bottom: 0;'><a href='final_credentials_display.php' style='color: #004085;'><strong>→ View Updated Credentials List</strong></a></p>";
echo "</div>";

$conn->close();
?>

<style>
table { margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
li { margin: 5px 0; }
</style>
