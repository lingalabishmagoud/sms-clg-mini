<?php
$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn = new mysqli("localhost", "root", "", "student_db");
    
    if ($conn->connect_error) {
        $error = "Connection failed: " . $conn->connect_error;
    } else {
        if (isset($_POST['add_single'])) {
            // Add single roll number
            $roll_number = $_POST['roll_number'];
            $year = $_POST['year'];
            $dept_code = $_POST['dept_code'];
            
            // Validate format
            if (preg_match('/^(\d{2})(N81)(A\d{2})(\d+)$/', $roll_number, $matches)) {
                $year_from_roll = $matches[1];
                $college_code = $matches[2];
                $dept_code_from_roll = $matches[3];
                $student_number = $matches[4];
                
                // Get department name
                $dept_names = ['A62' => 'Cyber Security', 'A05' => 'CSE', 'A67' => 'Data Science'];
                $dept_name = $dept_names[$dept_code_from_roll] ?? 'Unknown';
                
                // Check if already exists
                $check = $conn->prepare("SELECT id FROM roll_numbers WHERE roll_number = ?");
                $check->bind_param("s", $roll_number);
                $check->execute();
                $result = $check->get_result();
                
                if ($result->num_rows > 0) {
                    $error = "Roll number already exists!";
                } else {
                    // Insert new roll number
                    $stmt = $conn->prepare("INSERT INTO roll_numbers (roll_number, year_of_joining, college_code, dept_code, dept_name, student_number) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sissss", $roll_number, $year, $college_code, $dept_code_from_roll, $dept_name, $student_number);
                    
                    if ($stmt->execute()) {
                        $message = "Roll number added successfully!";
                    } else {
                        $error = "Error adding roll number: " . $stmt->error;
                    }
                    $stmt->close();
                }
                $check->close();
            } else {
                $error = "Invalid roll number format!";
            }
        } elseif (isset($_POST['add_batch'])) {
            // Add batch of roll numbers
            $year = $_POST['batch_year'];
            $dept_code = $_POST['batch_dept'];
            $start_num = (int)$_POST['start_num'];
            $end_num = (int)$_POST['end_num'];
            
            $dept_names = ['A62' => 'Cyber Security', 'A05' => 'CSE', 'A67' => 'Data Science'];
            $dept_name = $dept_names[$dept_code];
            
            $added = 0;
            $errors = [];
            
            for ($i = $start_num; $i <= $end_num; $i++) {
                $student_num = str_pad($i, 2, '0', STR_PAD_LEFT);
                $roll_number = $year . "N81" . $dept_code . $student_num;
                
                // Check if already exists
                $check = $conn->prepare("SELECT id FROM roll_numbers WHERE roll_number = ?");
                $check->bind_param("s", $roll_number);
                $check->execute();
                $result = $check->get_result();
                
                if ($result->num_rows == 0) {
                    // Insert new roll number
                    $stmt = $conn->prepare("INSERT INTO roll_numbers (roll_number, year_of_joining, college_code, dept_code, dept_name, student_number) VALUES (?, ?, 'N81', ?, ?, ?)");
                    $stmt->bind_param("sisss", $roll_number, $year, $dept_code, $dept_name, $student_num);
                    
                    if ($stmt->execute()) {
                        $added++;
                    } else {
                        $errors[] = "Error adding $roll_number: " . $stmt->error;
                    }
                    $stmt->close();
                }
                $check->close();
            }
            
            $message = "Added $added roll numbers successfully!";
            if (!empty($errors)) {
                $error = implode("<br>", $errors);
            }
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
    <title>Add Roll Numbers - Student Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h1 class="text-center mb-4">➕ Add Roll Numbers</h1>
                
                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Single Roll Number -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Add Single Roll Number</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="roll_number" class="form-label">Roll Number</label>
                                <input type="text" class="form-control" id="roll_number" name="roll_number" placeholder="e.g., 22N81A6255" required>
                                <div class="form-text">Format: YYNXXAXXNN (e.g., 22N81A6255)</div>
                            </div>
                            <div class="mb-3">
                                <label for="year" class="form-label">Year of Joining</label>
                                <select class="form-control" id="year" name="year" required>
                                    <option value="">Select Year</option>
                                    <option value="2022">2022</option>
                                    <option value="2023">2023</option>
                                    <option value="2024">2024</option>
                                    <option value="2025">2025</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="dept_code" class="form-label">Department</label>
                                <select class="form-control" id="dept_code" name="dept_code" required>
                                    <option value="">Select Department</option>
                                    <option value="A62">A62 - Cyber Security</option>
                                    <option value="A05">A05 - CSE</option>
                                    <option value="A67">A67 - Data Science</option>
                                </select>
                            </div>
                            <button type="submit" name="add_single" class="btn btn-primary">Add Roll Number</button>
                        </form>
                    </div>
                </div>
                
                <!-- Batch Roll Numbers -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Add Batch of Roll Numbers</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="batch_year" class="form-label">Year</label>
                                        <select class="form-control" id="batch_year" name="batch_year" required>
                                            <option value="">Select Year</option>
                                            <option value="22">2022</option>
                                            <option value="23">2023</option>
                                            <option value="24">2024</option>
                                            <option value="25">2025</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="batch_dept" class="form-label">Department</label>
                                        <select class="form-control" id="batch_dept" name="batch_dept" required>
                                            <option value="">Select Department</option>
                                            <option value="A62">A62 - Cyber Security</option>
                                            <option value="A05">A05 - CSE</option>
                                            <option value="A67">A67 - Data Science</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="start_num" class="form-label">Start Number</label>
                                        <input type="number" class="form-control" id="start_num" name="start_num" min="1" max="99" placeholder="1" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="end_num" class="form-label">End Number</label>
                                        <input type="number" class="form-control" id="end_num" name="end_num" min="1" max="99" placeholder="50" required>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-info">
                                <strong>Example:</strong> Year: 22, Department: A62, Start: 1, End: 10<br>
                                Will create: 22N81A6201, 22N81A6202, ..., 22N81A6210
                            </div>
                            <button type="submit" name="add_batch" class="btn btn-success">Add Batch</button>
                        </form>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="text-center">
                    <a href="roll_numbers_management.php" class="btn btn-primary">📋 View All Roll Numbers</a>
                    <a href="student_signup.php" class="btn btn-secondary">🎓 Student Signup</a>
                    <a href="signup_demo.php" class="btn btn-info">📖 Demo Guide</a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
