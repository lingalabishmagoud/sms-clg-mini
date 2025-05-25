<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Cleaning Existing Data and Adding New Students</h2>";

// Step 1: Check existing students
echo "<h3>Step 1: Checking Existing Students</h3>";
$result = $conn->query("SELECT COUNT(*) as total FROM students");
$row = $result->fetch_assoc();
echo "<p>Current total students in database: <strong>{$row['total']}</strong></p>";

// Check for existing students with our roll number pattern
$result = $conn->query("SELECT roll_number, full_name, email FROM students WHERE roll_number LIKE '22N81A62%' ORDER BY roll_number");
if ($result->num_rows > 0) {
    echo "<p>Found {$result->num_rows} existing students with roll numbers 22N81A62xxx:</p>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Roll Number</th><th>Name</th><th>Email</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['roll_number']}</td>";
        echo "<td>{$row['full_name']}</td>";
        echo "<td>{$row['email']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Step 2: Remove existing students with our roll number pattern
echo "<h3>Step 2: Removing Existing Students (22N81A62xxx pattern)</h3>";
$delete_result = $conn->query("DELETE FROM students WHERE roll_number LIKE '22N81A62%'");
if ($delete_result) {
    echo "<p style='color: green;'>✅ Successfully removed existing students with roll numbers 22N81A62xxx</p>";
    echo "<p>Affected rows: <strong>" . $conn->affected_rows . "</strong></p>";
} else {
    echo "<p style='color: red;'>❌ Error removing existing students: " . $conn->error . "</p>";
}

// Step 3: Add all new students
echo "<h3>Step 3: Adding All New Students</h3>";

// Function to generate email from name
function generateEmail($name) {
    $name = strtolower($name);
    $name = str_replace(['.', ','], '', $name);
    $parts = explode(' ', trim($name));
    if (count($parts) >= 2) {
        return $parts[0] . '.' . $parts[1] . '@student.college.edu';
    } else {
        return str_replace(' ', '', $name) . '@student.college.edu';
    }
}

// Function to generate password
function generatePassword($rollNumber) {
    return 'Student@' . substr($rollNumber, -3);
}

// Function to determine section based on roll number
function getSection($rollNumber) {
    $studentNumber = substr($rollNumber, -3);
    $numericPart = intval($studentNumber);

    // CS-A: 22N81A6201-22N81A6267 (01-67)
    // CS-B: 22N81A6268-22N81A62C8 (68 onwards)
    if ($numericPart >= 1 && $numericPart <= 67) {
        return 'CS-A';
    } else {
        return 'CS-B';
    }
}

// Student data array (first 20 students for testing)
$students_data = [
    ['22N81A6201', 'SARA SYED NOORUDDIN', 'SYED NOORUDDIN', '2004-06-22', 'O+', '891827000000', '9700155612', 'Janapriya Mahanagar, Meerpet, Hyderabad'],
    ['22N81A6202', 'MANDA SAMYUKTHA', 'MANDA MAHENDER', '2004-12-11', 'O+', '534192000000', '8885088288', 'Rajbhavan Staff Quarters, Somajiguda, Hyderabad'],
    ['22N81A6203', 'V. SHIVA SRAVANI', 'V. ANJANEYULU', '2003-12-12', 'O+', '650684000000', '8688909906', 'Chinthal Cherukupally Colony'],
    ['22N81A6204', 'NAGOJU SRILAXMI', 'NAGOJU BRAHMA CHARY', '2005-02-08', 'B+', '532572000000', '9542029245', 'Vinayaka Hills Ph-2, Almasguda, B.N Reddy, R.R District'],
    ['22N81A6205', 'DUNDHIGALLA SATYA PRIYA', 'D. SANTOSH KUMAR', '2003-09-28', 'O+', '659866000000', '9959883464', 'Prashanthi Hills, Meerpet, Rangareddy, Telangana'],
    ['22N81A6254', 'LINGALA BISHMA GOUD', 'LINGALA GNANESHWAR GOUD', '2004-11-16', '', '572023000000', '9182981209', 'Ramanthapur, Hyderabad'],
    ['22N81A6255', 'NAGAPURI PRAVEEN', 'NAGAPURI CHANDRAIAH', '2004-03-28', 'O+', '367154000000', '7093138356', 'Golnaka, Amberpet, Hyderabad'],
    ['22N81A6256', 'DAMARLA GOUTHAM', 'D KRISHNAIAH', '2004-11-26', 'O+', '621218000000', '8247054524', 'Omkar Nagar, Hasthinapuram'],
    ['22N81A6257', 'KORABANDY TEJODHAR', 'KORABANDY SHYAMSUNDAR', '2003-01-26', 'B+', '899435000000', '8985926891', 'Jai Suryapatnam, Nadergul'],
    ['22N81A6258', 'RAMAVATH KANIF NAIK', 'RAMAVATH PANDU NAIK', '2003-02-16', 'O+', '592740000000', '9666225418', 'Sridhar Colony, Jilluguda'],
    ['22N81A6269', 'GARLAPALLY MAMATHA', 'GARLAPALLY SRISAILAM', '2004-06-17', 'B+', '779433000000', '9347981938', 'Lalitha Nagar, Jillelaguda'],
    ['22N81A6270', 'KANCHARLA KAVYA', 'KANCHARLA PRASAD', '2003-06-03', 'A+', '485125000000', '7095157474', 'Chinthalapalem, Zarugumalli, Prakasam District'],
    ['22N81A6271', 'DEBBATI NAVEEN', 'D. SRINIVAS', '2004-09-14', 'B+', '736096000000', '6305021056', 'Sirpur Kagaznagar, Guntlapet'],
    ['22N81A6272', 'GUTHA ABHINAY REDDY', 'GUTHA UPENDER REDDY', '2004-11-27', 'O-', '733506000000', '7780439387', 'Balaji Nagar, Chityala, Nalgonda District'],
    ['22N81A6273', 'GADDAM KRISHNA CHAITANYA', 'GADDAM KRISHNA GOUD', '2004-02-11', 'B+', '862387000000', '8686839993', 'Malkajgiri'],
    ['22N81A6274', 'AREGONI PAVAN KUMAR', 'AREGONI JAGANNADHAM', '2005-04-10', 'O+', '931879000000', '9951667649', 'Hastinapuram'],
    ['22N81A6275', 'MOGILI VINEETH KUMAR', 'MOGILI MALLIKARJUNA', '2005-01-05', 'B+', '243643000000', '7989806035', 'Meerpet'],
    ['22N81A6276', 'MUMMIDI DEEPTHI', 'MUMMIDI SRINIVAS', '2004-07-30', 'B+', '518994000000', '8520055943', 'Green Hills Colony, Karmanghat'],
    ['22N81A6277', 'PEDADA SRI LAXMI', 'PEDADA SHANKAR', '2005-05-25', 'B+', '537892000000', '9885031879', 'Meerpet, Hyderabad'],
    ['22N81A6278', 'CHINTALA MANIKANTA', 'CHINTALA LINGAIAH', '2004-04-28', 'B+', '892318000000', '8639200663', 'Vepur, Yadadri Bhuvanagiri']
];

$login_credentials = [];
$success_count = 0;

foreach ($students_data as $student) {
    $roll_number = $student[0];
    $full_name = $student[1];
    $father_name = $student[2];
    $dob = $student[3];
    $blood_group = $student[4];
    $aadhaar_number = $student[5];
    $phone_number = $student[6];
    $address = $student[7];

    // Generate email and password
    $email = generateEmail($full_name);
    $password = generatePassword($roll_number); // Plain text password for testing

    // Determine section
    $section = getSection($roll_number);

    // Generate username from name
    $username = strtolower(str_replace([' ', '.', ','], ['_', '', ''], $full_name));

    // Set default values
    $department = 'Cyber Security';
    $year = 3; // 3rd year for 2022 batch
    $semester = '1st';
    $course = 'Cyber Security';
    $program = 'B.Tech';
    $batch = '2022';
    $student_id = 'STU' . substr($roll_number, -6);

    // Insert student
    $stmt = $conn->prepare("INSERT INTO students (username, full_name, email, password, roll_number, father_name, dob, blood_group, aadhaar_number, phone_number, address, section, department, year, semester, course, program, batch, student_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("sssssssssssssissss",
        $username, $full_name, $email, $password, $roll_number,
        $father_name, $dob, $blood_group, $aadhaar_number, $phone_number,
        $address, $section, $department, $year, $semester, $course, $program, $batch, $student_id
    );

    if ($stmt->execute()) {
        echo "<li style='color: green;'>✅ Added: $full_name (Roll: $roll_number, Section: $section)</li>";
        $success_count++;

        // Store login credentials
        $login_credentials[] = [
            'roll_number' => $roll_number,
            'name' => $full_name,
            'email' => $email,
            'password' => $password,
            'section' => $section
        ];
    } else {
        echo "<li style='color: red;'>❌ Failed to add $full_name: " . $stmt->error . "</li>";
    }
    $stmt->close();
}

echo "<h3>Step 4: Summary</h3>";
echo "<p><strong>Successfully added {$success_count} students</strong></p>";

// Show login credentials
echo "<h3>Step 5: Login Credentials (Ready to Use)</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f2f2f2;'><th>Roll Number</th><th>Name</th><th>Email</th><th>Password</th><th>Section</th></tr>";

foreach ($login_credentials as $cred) {
    $bg_color = ($cred['roll_number'] == '22N81A6254') ? 'background: #fff3cd;' : '';
    echo "<tr style='{$bg_color}'>";
    echo "<td>{$cred['roll_number']}</td>";
    echo "<td>{$cred['name']}</td>";
    echo "<td>{$cred['email']}</td>";
    echo "<td><strong>{$cred['password']}</strong></td>";
    echo "<td>{$cred['section']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
echo "<h4 style='color: #155724; margin-top: 0;'>🎉 Ready to Test!</h4>";
echo "<p style='color: #155724;'><strong>Your login credentials:</strong></p>";
echo "<p style='color: #155724;'><strong>Email:</strong> lingala.bishma@student.college.edu</p>";
echo "<p style='color: #155724;'><strong>Password:</strong> Student@254</p>";
echo "<p style='color: #155724; margin-bottom: 0;'><a href='student_login.php' style='color: #155724;'><strong>→ Try logging in now!</strong></a></p>";
echo "</div>";

$conn->close();
?>

<style>
table { margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
li { margin: 5px 0; }
</style>
