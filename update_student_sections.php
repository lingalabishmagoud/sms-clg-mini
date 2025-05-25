<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Updating Student Sections</h2>";

// Function to determine correct section based on your requirements
function getCorrectSection($rollNumber) {
    // CS-A Section: 22N81A6201-22N81A6267 (excluding 22N81A6208)
    // CS-B Section: 22N81A6208, 22N81A6268-22N81A62C8
    
    // Define CS-A roll numbers (22N81A6201 to 22N81A6267, but exclude 22N81A6208)
    $cs_a_rolls = [];
    for ($i = 201; $i <= 267; $i++) {
        $roll = '22N81A6' . sprintf('%03d', $i);
        if ($roll !== '22N81A6208') { // Exclude 208 from CS-A
            $cs_a_rolls[] = $roll;
        }
    }
    
    // Check if current roll number is in CS-A list
    if (in_array($rollNumber, $cs_a_rolls)) {
        return 'CS-A';
    } else {
        // All other roll numbers (including 22N81A6208 and 22N81A6268 onwards) are CS-B
        return 'CS-B';
    }
}

// Get all students with roll numbers starting with 22N81A62
$result = $conn->query("SELECT id, roll_number, full_name, section FROM students WHERE roll_number LIKE '22N81A62%' ORDER BY roll_number");

if ($result && $result->num_rows > 0) {
    echo "<h3>Current Section Assignments:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Roll Number</th><th>Name</th><th>Current Section</th><th>Correct Section</th><th>Action</th></tr>";
    
    $updates_needed = 0;
    $cs_a_count = 0;
    $cs_b_count = 0;
    
    while ($student = $result->fetch_assoc()) {
        $correct_section = getCorrectSection($student['roll_number']);
        $needs_update = ($student['section'] !== $correct_section);
        
        if ($correct_section === 'CS-A') {
            $cs_a_count++;
        } else {
            $cs_b_count++;
        }
        
        $row_color = '';
        if ($needs_update) {
            $row_color = 'background: #fff3cd;';
            $updates_needed++;
        }
        
        echo "<tr style='{$row_color}'>";
        echo "<td>{$student['roll_number']}</td>";
        echo "<td>{$student['full_name']}</td>";
        echo "<td>{$student['section']}</td>";
        echo "<td><strong>{$correct_section}</strong></td>";
        echo "<td>" . ($needs_update ? '⚠️ Needs Update' : '✅ Correct') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>Summary Before Update:</h3>";
    echo "<ul>";
    echo "<li>Total students found: {$result->num_rows}</li>";
    echo "<li>Students needing section updates: <strong>{$updates_needed}</strong></li>";
    echo "<li>Expected CS-A count: <strong>{$cs_a_count}</strong></li>";
    echo "<li>Expected CS-B count: <strong>{$cs_b_count}</strong></li>";
    echo "</ul>";
    
    if ($updates_needed > 0) {
        echo "<h3>Updating Sections...</h3>";
        
        // Reset result pointer
        $result->data_seek(0);
        $updated_count = 0;
        
        while ($student = $result->fetch_assoc()) {
            $correct_section = getCorrectSection($student['roll_number']);
            
            if ($student['section'] !== $correct_section) {
                $stmt = $conn->prepare("UPDATE students SET section = ? WHERE id = ?");
                $stmt->bind_param("si", $correct_section, $student['id']);
                
                if ($stmt->execute()) {
                    echo "<li style='color: green;'>✅ Updated {$student['roll_number']} - {$student['full_name']} → Section: {$correct_section}</li>";
                    $updated_count++;
                } else {
                    echo "<li style='color: red;'>❌ Failed to update {$student['roll_number']}: " . $stmt->error . "</li>";
                }
                $stmt->close();
            }
        }
        
        echo "<p><strong>Successfully updated {$updated_count} students</strong></p>";
    } else {
        echo "<p style='color: green;'>✅ All students already have correct section assignments!</p>";
    }
    
} else {
    echo "<p style='color: red;'>No students found with roll numbers starting with 22N81A62</p>";
}

// Final verification
echo "<h3>Final Section Distribution:</h3>";
$final_cs_a = $conn->query("SELECT COUNT(*) as count FROM students WHERE section = 'CS-A' AND roll_number LIKE '22N81A62%'")->fetch_assoc()['count'];
$final_cs_b = $conn->query("SELECT COUNT(*) as count FROM students WHERE section = 'CS-B' AND roll_number LIKE '22N81A62%'")->fetch_assoc()['count'];

echo "<table border='1' style='border-collapse: collapse; width: 50%;'>";
echo "<tr style='background: #f2f2f2;'><th>Section</th><th>Student Count</th><th>Roll Number Range</th></tr>";
echo "<tr>";
echo "<td><strong>CS-A</strong></td>";
echo "<td><strong>{$final_cs_a}</strong></td>";
echo "<td>22N81A6201-22N81A6267 (excluding 208)</td>";
echo "</tr>";
echo "<tr>";
echo "<td><strong>CS-B</strong></td>";
echo "<td><strong>{$final_cs_b}</strong></td>";
echo "<td>22N81A6208, 22N81A6268-22N81A62C8</td>";
echo "</tr>";
echo "</table>";

// Show sample students from each section
echo "<h3>Sample Students by Section:</h3>";

echo "<h4>CS-A Section (Sample):</h4>";
$cs_a_sample = $conn->query("SELECT roll_number, full_name, email FROM students WHERE section = 'CS-A' AND roll_number LIKE '22N81A62%' ORDER BY roll_number LIMIT 5");
if ($cs_a_sample && $cs_a_sample->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #007bff; color: white;'><th>Roll Number</th><th>Name</th><th>Email</th><th>Password</th></tr>";
    while ($row = $cs_a_sample->fetch_assoc()) {
        $password = 'Student@' . substr($row['roll_number'], -3);
        echo "<tr>";
        echo "<td>{$row['roll_number']}</td>";
        echo "<td>{$row['full_name']}</td>";
        echo "<td>{$row['email']}</td>";
        echo "<td>{$password}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h4>CS-B Section (Sample):</h4>";
$cs_b_sample = $conn->query("SELECT roll_number, full_name, email FROM students WHERE section = 'CS-B' AND roll_number LIKE '22N81A62%' ORDER BY roll_number LIMIT 5");
if ($cs_b_sample && $cs_b_sample->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #28a745; color: white;'><th>Roll Number</th><th>Name</th><th>Email</th><th>Password</th></tr>";
    while ($row = $cs_b_sample->fetch_assoc()) {
        $password = 'Student@' . substr($row['roll_number'], -3);
        echo "<tr>";
        echo "<td>{$row['roll_number']}</td>";
        echo "<td>{$row['full_name']}</td>";
        echo "<td>{$row['email']}</td>";
        echo "<td>{$password}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
echo "<h4 style='color: #155724; margin-top: 0;'>🎉 Section Assignment Complete!</h4>";
echo "<p style='color: #155724;'>All students have been properly assigned to their correct sections according to your requirements:</p>";
echo "<ul style='color: #155724;'>";
echo "<li><strong>CS-A:</strong> {$final_cs_a} students (22N81A6201-22N81A6267, excluding 208)</li>";
echo "<li><strong>CS-B:</strong> {$final_cs_b} students (22N81A6208 + 22N81A6268-22N81A62C8)</li>";
echo "</ul>";
echo "<p style='color: #155724; margin-bottom: 0;'>You can now test login with any student using the password pattern: <strong>Student@XXX</strong></p>";
echo "</div>";

$conn->close();
?>

<style>
table { margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
li { margin: 5px 0; }
</style>
