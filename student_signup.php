<?php
// Initialize variables
$full_name = $email = $password = $confirm_password = $department = $year = $semester = $phone_number = $roll_number = "";
$error = "";
$success = false;

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $department = $_POST['department'];
    $year = $_POST['year'];
    $semester = $_POST['semester'];
    $phone_number = $_POST['phone_number'];
    $roll_number = $_POST['roll_number'];

    // Simple validation
    if (empty($full_name) || empty($email) || empty($password) || empty($department) || empty($year) || empty($semester) || empty($phone_number) || empty($roll_number)) {
        $error = "All fields are required";
    } elseif ($password != $confirm_password) {
        $error = "Passwords do not match";
    } else {
        // Connect to database
        $conn = new mysqli("localhost", "root", "", "student_db");

        // Check connection
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        // Validate roll number format
        if (!preg_match('/^(\d{2})(N81)(A\d{2})(\d+)$/', $roll_number, $matches)) {
            $error = "Invalid roll number format. Expected format: YYNXXAXXNNN (e.g., 22N81A6254)";
        } else {
            $year_from_roll = $matches[1];
            $college_code = $matches[2];
            $dept_code_from_roll = $matches[3];

            // Check if roll number exists in roll_numbers table
            $check_roll = $conn->prepare("SELECT id, dept_code, dept_name, is_used FROM roll_numbers WHERE roll_number = ?");
            $check_roll->bind_param("s", $roll_number);
            $check_roll->execute();
            $roll_result = $check_roll->get_result();

            if ($roll_result->num_rows == 0) {
                $error = "Invalid roll number. This roll number is not registered in our system.";
            } else {
                $roll_data = $roll_result->fetch_assoc();

                if ($roll_data['is_used']) {
                    $error = "An account with this roll number already exists.";
                } else {
                    // Validate department consistency
                    if ($roll_data['dept_code'] !== $department) {
                        $error = "Department mismatch. Roll number belongs to " . $roll_data['dept_name'] . " department.";
                    } else {
                        // Check if email already exists
                        $check_email = $conn->prepare("SELECT id FROM students WHERE email = ?");
                        $check_email->bind_param("s", $email);
                        $check_email->execute();
                        $email_result = $check_email->get_result();

                        if ($email_result->num_rows > 0) {
                            $error = "Email already exists. Please use a different email.";
                        } else {
                            // Check if roll number is already used in students table
                            $check_roll_used = $conn->prepare("SELECT id FROM students WHERE roll_number = ?");
                            $check_roll_used->bind_param("s", $roll_number);
                            $check_roll_used->execute();
                            $roll_used_result = $check_roll_used->get_result();

                            if ($roll_used_result->num_rows > 0) {
                                $error = "An account with this roll number already exists.";
                            } else {
                                // For testing purposes, we're not hashing the password
                                // In a real application, you would use password_hash() here

                                // Insert new student
                                $stmt = $conn->prepare("INSERT INTO students (full_name, email, password, department, year, semester, phone_number, roll_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                                $stmt->bind_param("ssssssss", $full_name, $email, $password, $roll_data['dept_name'], $year, $semester, $phone_number, $roll_number);

                                if ($stmt->execute()) {
                                    $student_id = $conn->insert_id;

                                    // Update roll_numbers table to mark as used
                                    $update_roll = $conn->prepare("UPDATE roll_numbers SET is_used = TRUE, used_by_student_id = ?, used_at = NOW() WHERE roll_number = ?");
                                    $update_roll->bind_param("is", $student_id, $roll_number);
                                    $update_roll->execute();
                                    $update_roll->close();

                                    // Automatically enroll student in department-specific courses
                                    $dept_courses = $conn->prepare("SELECT id FROM courses WHERE department = ?");
                                    $dept_courses->bind_param("s", $roll_data['dept_name']);
                                    $dept_courses->execute();
                                    $courses_result = $dept_courses->get_result();

                                    if ($courses_result->num_rows > 0) {
                                        while ($course = $courses_result->fetch_assoc()) {
                                            $enroll_stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id, enrollment_date, status) VALUES (?, ?, NOW(), 'active')");
                                            $enroll_stmt->bind_param("ii", $student_id, $course['id']);
                                            $enroll_stmt->execute();
                                            $enroll_stmt->close();
                                        }
                                    }
                                    $dept_courses->close();

                                    // Automatically enroll student in department-specific subjects
                                    $dept_subjects = $conn->prepare("SELECT id FROM subjects WHERE department = ?");
                                    $dept_subjects->bind_param("s", $roll_data['dept_name']);
                                    $dept_subjects->execute();
                                    $subjects_result = $dept_subjects->get_result();

                                    if ($subjects_result->num_rows > 0) {
                                        while ($subject = $subjects_result->fetch_assoc()) {
                                            $subject_enroll_stmt = $conn->prepare("INSERT INTO student_subject_enrollment (student_id, subject_id, status) VALUES (?, ?, 'active')");
                                            $subject_enroll_stmt->bind_param("ii", $student_id, $subject['id']);
                                            $subject_enroll_stmt->execute();
                                            $subject_enroll_stmt->close();
                                        }
                                    }
                                    $dept_subjects->close();

                                    $success = true;
                                    // Clear form data
                                    $full_name = $email = $password = $confirm_password = $department = $year = $semester = $phone_number = $roll_number = "";
                                } else {
                                    $error = "Error: " . $stmt->error;
                                }

                                $stmt->close();
                            }
                            $check_roll_used->close();
                        }
                        $check_email->close();
                    }
                }
            }
            $check_roll->close();
        }

        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Signup - Student Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="form-container">
                    <h2 class="form-title">Student Registration</h2>

                    <?php if ($success): ?>
                        <div class="alert alert-success" role="alert">
                            Registration successful! <a href="student_login.php">Login here</a>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>

                        <div class="mb-3">
                            <label for="phone_number" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($phone_number); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="roll_number" class="form-label">Roll Number</label>
                            <input type="text" class="form-control" id="roll_number" name="roll_number" value="<?php echo htmlspecialchars($roll_number); ?>" placeholder="e.g., 22N81A6254" required>
                            <div class="form-text">Enter your pre-registered roll number</div>
                        </div>

                        <div class="mb-3">
                            <label for="department" class="form-label">Department</label>
                            <select class="form-control" id="department" name="department" required>
                                <option value="">Select Department</option>
                                <option value="A62" <?php echo ($department == 'A62') ? 'selected' : ''; ?>>Cyber Security</option>
                                <option value="A05" <?php echo ($department == 'A05') ? 'selected' : ''; ?>>CSE</option>
                                <option value="A67" <?php echo ($department == 'A67') ? 'selected' : ''; ?>>Data Science</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="year" class="form-label">Year</label>
                            <select class="form-control" id="year" name="year" required>
                                <option value="">Select Year</option>
                                <option value="1st" <?php echo ($year == '1st') ? 'selected' : ''; ?>>1st Year</option>
                                <option value="2nd" <?php echo ($year == '2nd') ? 'selected' : ''; ?>>2nd Year</option>
                                <option value="3rd" <?php echo ($year == '3rd') ? 'selected' : ''; ?>>3rd Year</option>
                                <option value="4th" <?php echo ($year == '4th') ? 'selected' : ''; ?>>4th Year</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="semester" class="form-label">Semester</label>
                            <select class="form-control" id="semester" name="semester" required>
                                <option value="">Select Semester</option>
                                <option value="1st" <?php echo ($semester == '1st') ? 'selected' : ''; ?>>1st Semester</option>
                                <option value="2nd" <?php echo ($semester == '2nd') ? 'selected' : ''; ?>>2nd Semester</option>
                            </select>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Register</button>
                        </div>
                    </form>

                    <div class="mt-3 text-center">
                        <p>Already have an account? <a href="student_login.php">Login here</a></p>
                        <p><a href="index.html">Back to Home</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
