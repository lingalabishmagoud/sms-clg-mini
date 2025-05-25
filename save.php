<?php
// Connect to MySQL
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get data from form
$full_name = $_POST['full_name'];
$email = $_POST['email'];
$course = $_POST['course'];
$year = $_POST['year'];

// Insert data
$sql = "INSERT INTO students (full_name, email, course, year)
        VALUES ('$full_name', '$email', '$course', '$year')";

if ($conn->query($sql) === TRUE) {
    echo "Student record saved successfully.";
} else {
    echo "Error: " . $conn->error;
}

$conn->close();
?>
