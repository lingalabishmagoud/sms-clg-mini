<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Complete Database Cleanup and Student Setup</h2>";

// Step 1: Show current students
echo "<h3>Step 1: Current Students in Database</h3>";
$result = $conn->query("SELECT id, roll_number, full_name, email FROM students ORDER BY id");
if ($result && $result->num_rows > 0) {
    echo "<p>Found {$result->num_rows} existing students:</p>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Roll Number</th><th>Name</th><th>Email</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>" . ($row['roll_number'] ?: 'NULL') . "</td>";
        echo "<td>{$row['full_name']}</td>";
        echo "<td>{$row['email']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No students found in database.</p>";
}

// Step 2: Delete ALL existing students to start fresh
echo "<h3>Step 2: Cleaning All Existing Students</h3>";
$delete_result = $conn->query("DELETE FROM students");
if ($delete_result) {
    echo "<p style='color: green;'>✅ Successfully deleted all existing students</p>";
    echo "<p>Affected rows: <strong>" . $conn->affected_rows . "</strong></p>";
} else {
    echo "<p style='color: red;'>❌ Error deleting students: " . $conn->error . "</p>";
}

// Step 3: Reset auto-increment
$conn->query("ALTER TABLE students AUTO_INCREMENT = 1");
echo "<p>✅ Reset student ID counter</p>";

// Step 4: Add your specific student first
echo "<h3>Step 3: Adding Your Student Account</h3>";

$your_data = [
    'roll_number' => '22N81A6254',
    'full_name' => 'LINGALA BISHMA GOUD',
    'father_name' => 'LINGALA GNANESHWAR GOUD',
    'email' => 'lingala.bishma@student.college.edu',
    'password' => 'Student@254',
    'dob' => '2004-11-16',
    'blood_group' => 'O+',
    'aadhaar_number' => '572023000000',
    'phone_number' => '9182981209',
    'address' => 'Ramanthapur, Hyderabad',
    'section' => 'CS-A',
    'department' => 'Cyber Security',
    'year' => 3,
    'semester' => '1st',
    'course' => 'Cyber Security',
    'program' => 'B.Tech',
    'batch' => '2022'
];

$username = 'lingala_bishma_goud';
$student_id = 'STU1A6254';

$stmt = $conn->prepare("INSERT INTO students (username, full_name, email, password, roll_number, father_name, dob, blood_group, aadhaar_number, phone_number, address, section, department, year, semester, course, program, batch, student_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->bind_param("ssssssssssssssissss",
    $username,
    $your_data['full_name'],
    $your_data['email'],
    $your_data['password'],
    $your_data['roll_number'],
    $your_data['father_name'],
    $your_data['dob'],
    $your_data['blood_group'],
    $your_data['aadhaar_number'],
    $your_data['phone_number'],
    $your_data['address'],
    $your_data['section'],
    $your_data['department'],
    $your_data['year'],
    $your_data['semester'],
    $your_data['course'],
    $your_data['program'],
    $your_data['batch'],
    $student_id
);

if ($stmt->execute()) {
    echo "<p style='color: green;'>✅ Successfully added your student account!</p>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>Roll Number</td><td>{$your_data['roll_number']}</td></tr>";
    echo "<tr><td>Name</td><td>{$your_data['full_name']}</td></tr>";
    echo "<tr><td>Email</td><td>{$your_data['email']}</td></tr>";
    echo "<tr><td>Password</td><td>{$your_data['password']}</td></tr>";
    echo "<tr><td>Section</td><td>{$your_data['section']}</td></tr>";
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ Failed to add your account: " . $stmt->error . "</p>";
}
$stmt->close();

// Step 5: Add a few more sample students for testing
echo "<h3>Step 4: Adding Sample Students</h3>";

$sample_students = [
    ['22N81A6201', 'SARA SYED NOORUDDIN', 'SYED NOORUDDIN', '2004-06-22', 'O+', '891827000000', '9700155612', 'Janapriya Mahanagar, Meerpet, Hyderabad'],
    ['22N81A6202', 'MANDA SAMYUKTHA', 'MANDA MAHENDER', '2004-12-11', 'O+', '534192000000', '8885088288', 'Rajbhavan Staff Quarters, Somajiguda, Hyderabad'],
    ['22N81A6203', 'V. SHIVA SRAVANI', 'V. ANJANEYULU', '2003-12-12', 'O+', '650684000000', '8688909906', 'Chinthal Cherukupally Colony'],
    ['22N81A6269', 'GARLAPALLY MAMATHA', 'GARLAPALLY SRISAILAM', '2004-06-17', 'B+', '779433000000', '9347981938', 'Lalitha Nagar, Jillelaguda'],
    ['22N81A6270', 'KANCHARLA KAVYA', 'KANCHARLA PRASAD', '2003-06-03', 'A+', '485125000000', '7095157474', 'Chinthalapalem, Zarugumalli, Prakasam District']
];

$added_count = 0;
foreach ($sample_students as $student) {
    $roll_number = $student[0];
    $full_name = $student[1];
    $father_name = $student[2];
    $dob = $student[3];
    $blood_group = $student[4];
    $aadhaar_number = $student[5];
    $phone_number = $student[6];
    $address = $student[7];

    // Generate email and other details
    $name_parts = explode(' ', strtolower($full_name));
    $email = $name_parts[0] . '.' . $name_parts[1] . '@student.college.edu';
    $password = 'Student@' . substr($roll_number, -3);
    $section = (intval(substr($roll_number, -3)) <= 67) ? 'CS-A' : 'CS-B';
    $username = strtolower(str_replace([' ', '.'], ['_', ''], $full_name));
    $student_id = 'STU' . substr($roll_number, -6);

    $stmt = $conn->prepare("INSERT INTO students (username, full_name, email, password, roll_number, father_name, dob, blood_group, aadhaar_number, phone_number, address, section, department, year, semester, course, program, batch, student_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("ssssssssssssssissss",
        $username, $full_name, $email, $password, $roll_number,
        $father_name, $dob, $blood_group, $aadhaar_number, $phone_number,
        $address, $section, 'Cyber Security', 3, '1st', 'Cyber Security', 'B.Tech', '2022', $student_id
    );

    if ($stmt->execute()) {
        echo "<li style='color: green;'>✅ Added: $full_name (Roll: $roll_number, Section: $section)</li>";
        $added_count++;
    } else {
        echo "<li style='color: red;'>❌ Failed to add $full_name: " . $stmt->error . "</li>";
    }
    $stmt->close();
}

echo "<p>Successfully added {$added_count} sample students</p>";

// Step 6: Verify your account
echo "<h3>Step 5: Final Verification</h3>";
$verify_stmt = $conn->prepare("SELECT roll_number, full_name, email, password, section FROM students WHERE email = ?");
$test_email = 'lingala.bishma@student.college.edu';
$verify_stmt->bind_param("s", $test_email);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows == 1) {
    $student = $verify_result->fetch_assoc();
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
    echo "<h4 style='color: #155724; margin-top: 0;'>🎉 SUCCESS! Your Account is Ready</h4>";
    echo "<p style='color: #155724;'><strong>Login Credentials:</strong></p>";
    echo "<p style='color: #155724;'><strong>Email:</strong> {$student['email']}</p>";
    echo "<p style='color: #155724;'><strong>Password:</strong> {$student['password']}</p>";
    echo "<p style='color: #155724;'><strong>Roll Number:</strong> {$student['roll_number']}</p>";
    echo "<p style='color: #155724;'><strong>Section:</strong> {$student['section']}</p>";
    echo "<p style='color: #155724; margin-bottom: 0;'><a href='student_login.php' style='color: #155724; text-decoration: none; font-weight: bold; font-size: 18px;'>→ CLICK HERE TO LOGIN NOW!</a></p>";
    echo "</div>";
} else {
    echo "<p style='color: red;'>❌ Verification failed - account not found</p>";
}

// Show final student count
$final_count = $conn->query("SELECT COUNT(*) as total FROM students")->fetch_assoc()['total'];
echo "<h3>Final Summary</h3>";
echo "<p><strong>Total students in database:</strong> {$final_count}</p>";
echo "<p><strong>Your account status:</strong> Ready for login</p>";

$verify_stmt->close();
$conn->close();
?>

<style>
table { margin: 10px 0; border-collapse: collapse; width: 100%; }
th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
th { background-color: #f2f2f2; }
li { margin: 5px 0; }
</style>
