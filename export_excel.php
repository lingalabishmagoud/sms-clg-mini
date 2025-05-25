<?php
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=students.xls");

$conn = new mysqli("localhost", "root", "", "student_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT * FROM students";
$result = $conn->query($sql);

echo "ID\tFull Name\tEmail\tCourse\tYear\n";

while ($row = $result->fetch_assoc()) {
    echo $row['id'] . "\t" . $row['full_name'] . "\t" . $row['email'] . "\t" . $row['course'] . "\t" . $row['year'] . "\n";
}

$conn->close();
?>
