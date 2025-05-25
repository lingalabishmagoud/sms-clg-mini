<?php
// Start session
session_start();

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Faculty Session Debug</h2>";

// Show session data
echo "<h3>Session Data:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Check if faculty is logged in
if (isset($_SESSION['faculty_id'])) {
    $faculty_id = $_SESSION['faculty_id'];
    echo "<h3>Faculty ID from Session: " . $faculty_id . "</h3>";
    
    // Get faculty information
    $stmt = $conn->prepare("SELECT * FROM faculty WHERE id = ?");
    $stmt->bind_param("i", $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $faculty = $result->fetch_assoc();
        echo "<h3>Faculty Information from Database:</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        foreach ($faculty as $key => $value) {
            echo "<tr><td><strong>$key</strong></td><td>" . htmlspecialchars($value) . "</td></tr>";
        }
        echo "</table>";
        
        // Test subject count query
        echo "<h3>Subject Count Query Test:</h3>";
        $stmt2 = $conn->prepare("SELECT COUNT(*) as count FROM subjects WHERE faculty_id = ?");
        $stmt2->bind_param("i", $faculty_id);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        $subject_count = ($result2->num_rows > 0) ? $result2->fetch_assoc()['count'] : 0;
        echo "<p><strong>Subject Count:</strong> " . $subject_count . "</p>";
        
        // Show actual subjects assigned
        $stmt3 = $conn->prepare("SELECT * FROM subjects WHERE faculty_id = ?");
        $stmt3->bind_param("i", $faculty_id);
        $stmt3->execute();
        $result3 = $stmt3->get_result();
        
        if ($result3->num_rows > 0) {
            echo "<h3>Subjects Assigned to This Faculty:</h3>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>ID</th><th>Code</th><th>Subject Name</th><th>Faculty ID</th></tr>";
            while ($subject = $result3->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $subject['id'] . "</td>";
                echo "<td>" . htmlspecialchars($subject['abbreviation']) . "</td>";
                echo "<td>" . htmlspecialchars($subject['subject_name']) . "</td>";
                echo "<td>" . $subject['faculty_id'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: red;'>No subjects assigned to faculty ID: " . $faculty_id . "</p>";
        }
        
        $stmt2->close();
        $stmt3->close();
    } else {
        echo "<p style='color: red;'>Faculty not found in database with ID: " . $faculty_id . "</p>";
    }
    $stmt->close();
} else {
    echo "<p style='color: red;'>No faculty_id in session. User not logged in as faculty.</p>";
}

echo "<hr>";

// Show all faculty in database
echo "<h3>All Faculty in Database:</h3>";
$all_faculty = $conn->query("SELECT id, full_name, email FROM faculty ORDER BY id");
if ($all_faculty->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Subject Count</th></tr>";
    while ($faculty = $all_faculty->fetch_assoc()) {
        // Get subject count for each faculty
        $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM subjects WHERE faculty_id = ?");
        $count_stmt->bind_param("i", $faculty['id']);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count = $count_result->fetch_assoc()['count'];
        $count_stmt->close();
        
        echo "<tr>";
        echo "<td>" . $faculty['id'] . "</td>";
        echo "<td>" . htmlspecialchars($faculty['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars($faculty['email']) . "</td>";
        echo "<td>" . $count . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>No faculty found in database!</p>";
}

echo "<hr>";

// Show all subjects with faculty assignments
echo "<h3>All Subjects with Faculty Assignments:</h3>";
$all_subjects = $conn->query("
    SELECT s.id, s.abbreviation, s.subject_name, s.faculty_id, f.full_name as faculty_name
    FROM subjects s
    LEFT JOIN faculty f ON s.faculty_id = f.id
    ORDER BY s.id
");

if ($all_subjects->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Subject ID</th><th>Code</th><th>Subject Name</th><th>Faculty ID</th><th>Faculty Name</th></tr>";
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
echo "<p><a href='faculty_login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Faculty Login</a></p>";
echo "<p><a href='fix_subject_assignments.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Fix Subject Assignments</a></p>";
echo "<p><a href='faculty_dashboard.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Faculty Dashboard</a></p>";

$conn->close();
?>
