<?php
// Start session
session_start();

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Quick Faculty Login Test</h2>";

// Show all faculty members
echo "<h3>Available Faculty Members:</h3>";
$faculty_result = $conn->query("SELECT id, full_name, email FROM faculty ORDER BY id");

if ($faculty_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Action</th></tr>";
    while ($faculty = $faculty_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $faculty['id'] . "</td>";
        echo "<td>" . htmlspecialchars($faculty['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars($faculty['email']) . "</td>";
        echo "<td><a href='?login_as=" . $faculty['id'] . "' style='background: #007bff; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;'>Login as this faculty</a></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>No faculty found in database!</p>";
}

// Handle quick login
if (isset($_GET['login_as'])) {
    $faculty_id = (int)$_GET['login_as'];
    
    // Get faculty details
    $stmt = $conn->prepare("SELECT id, full_name, email FROM faculty WHERE id = ?");
    $stmt->bind_param("i", $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $faculty = $result->fetch_assoc();
        
        // Set session variables
        $_SESSION['faculty_id'] = $faculty['id'];
        $_SESSION['faculty_name'] = $faculty['full_name'];
        $_SESSION['user_type'] = 'faculty';
        
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h4>✅ Successfully logged in as:</h4>";
        echo "<p><strong>ID:</strong> " . $faculty['id'] . "</p>";
        echo "<p><strong>Name:</strong> " . htmlspecialchars($faculty['full_name']) . "</p>";
        echo "<p><strong>Email:</strong> " . htmlspecialchars($faculty['email']) . "</p>";
        echo "</div>";
        
        // Test subject count immediately
        $stmt2 = $conn->prepare("SELECT COUNT(*) as count FROM subjects WHERE faculty_id = ?");
        $stmt2->bind_param("i", $faculty_id);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        $subject_count = ($result2->num_rows > 0) ? $result2->fetch_assoc()['count'] : 0;
        
        echo "<div style='background: #cce5ff; color: #004085; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h4>📊 Subject Count Test:</h4>";
        echo "<p><strong>Subjects assigned to this faculty:</strong> " . $subject_count . "</p>";
        echo "</div>";
        
        // Show assigned subjects
        if ($subject_count > 0) {
            $stmt3 = $conn->prepare("SELECT * FROM subjects WHERE faculty_id = ?");
            $stmt3->bind_param("i", $faculty_id);
            $stmt3->execute();
            $result3 = $stmt3->get_result();
            
            echo "<h4>📚 Assigned Subjects:</h4>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>Code</th><th>Subject Name</th><th>Credits</th></tr>";
            while ($subject = $result3->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($subject['abbreviation']) . "</td>";
                echo "<td>" . htmlspecialchars($subject['subject_name']) . "</td>";
                echo "<td>" . $subject['credits'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            $stmt3->close();
        }
        
        $stmt2->close();
        
        echo "<hr>";
        echo "<h3>🚀 Quick Actions:</h3>";
        echo "<p><a href='faculty_dashboard.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Faculty Dashboard</a></p>";
        echo "<p><a href='debug_faculty_session.php' style='background: #ffc107; color: black; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Debug Session</a></p>";
        
    } else {
        echo "<p style='color: red;'>Faculty not found!</p>";
    }
    $stmt->close();
}

// Show current session
if (isset($_SESSION['faculty_id'])) {
    echo "<hr>";
    echo "<div style='background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>🔐 Current Session:</h4>";
    echo "<p><strong>Faculty ID:</strong> " . $_SESSION['faculty_id'] . "</p>";
    echo "<p><strong>Faculty Name:</strong> " . htmlspecialchars($_SESSION['faculty_name']) . "</p>";
    echo "<p><strong>User Type:</strong> " . $_SESSION['user_type'] . "</p>";
    echo "<p><a href='?logout=1' style='background: #dc3545; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;'>Logout</a></p>";
    echo "</div>";
}

// Handle logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>🚪 Logged out successfully!</h4>";
    echo "<p><a href='quick_faculty_login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Refresh Page</a></p>";
    echo "</div>";
}

echo "<hr>";
echo "<h3>🔧 Other Tools:</h3>";
echo "<p><a href='fix_subject_assignments.php' style='background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Fix Subject Assignments</a></p>";
echo "<p><a href='setup_database.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Run Database Setup</a></p>";

$conn->close();
?>
