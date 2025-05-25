<?php
$conn = new mysqli("localhost", "root", "", "student_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Roll Numbers for Testing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        .available { color: green; font-weight: bold; }
        .used { color: red; }
        .suggestion { background: #e8f5e8; padding: 10px; border-radius: 5px; margin: 5px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center mb-4">📋 Available Roll Numbers for Testing</h1>
        
        <div class="alert alert-info">
            <h5>🎯 Purpose:</h5>
            <p>This page shows you which roll numbers you can use for testing the "Add Roll Number" functionality.</p>
        </div>

        <?php
        // Get existing roll numbers
        $existing = [];
        $result = $conn->query("SELECT roll_number FROM roll_numbers");
        while ($row = $result->fetch_assoc()) {
            $existing[] = $row['roll_number'];
        }

        // Get departments
        $departments = [];
        $dept_result = $conn->query("SELECT dept_code, dept_name FROM departments");
        while ($row = $dept_result->fetch_assoc()) {
            $departments[$row['dept_code']] = $row['dept_name'];
        }
        ?>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5>✅ Available Roll Numbers (You Can Add These)</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $suggestions = [];
                        
                        // Generate suggestions for each department
                        foreach ($departments as $code => $name) {
                            echo "<h6>$name ($code):</h6>";
                            $found_available = false;
                            
                            // Check numbers 56-70 for 2022 batch
                            for ($i = 56; $i <= 70; $i++) {
                                $roll = "22N81" . $code . str_pad($i, 2, '0', STR_PAD_LEFT);
                                if (!in_array($roll, $existing)) {
                                    echo "<div class='suggestion'>$roll</div>";
                                    $suggestions[] = $roll;
                                    $found_available = true;
                                    if (count($suggestions) >= 3) break; // Limit suggestions
                                }
                            }
                            
                            // Check numbers 10-20 for 2023 batch
                            for ($i = 10; $i <= 20; $i++) {
                                $roll = "23N81" . $code . str_pad($i, 2, '0', STR_PAD_LEFT);
                                if (!in_array($roll, $existing)) {
                                    echo "<div class='suggestion'>$roll</div>";
                                    $suggestions[] = $roll;
                                    $found_available = true;
                                    if (count($suggestions) >= 6) break;
                                }
                            }
                            
                            if (!$found_available) {
                                echo "<p class='text-muted'>All suggested numbers are taken</p>";
                            }
                            echo "<hr>";
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5>❌ Existing Roll Numbers (Already in Database)</h5>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <?php
                        foreach ($departments as $code => $name) {
                            echo "<h6>$name ($code):</h6>";
                            $found_existing = false;
                            
                            foreach ($existing as $roll) {
                                if (strpos($roll, $code) !== false) {
                                    echo "<span class='used'>$roll</span><br>";
                                    $found_existing = true;
                                }
                            }
                            
                            if (!$found_existing) {
                                echo "<p class='text-muted'>No existing roll numbers</p>";
                            }
                            echo "<hr>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-warning mt-4">
            <h5>🧪 How to Test:</h5>
            <ol>
                <li>Copy any roll number from the "Available" section above</li>
                <li>Go to <a href="roll_numbers_management.php">Roll Numbers Management</a></li>
                <li>Click "Add Roll Number" button</li>
                <li>Paste the roll number and submit</li>
                <li>It should be added successfully!</li>
            </ol>
        </div>

        <div class="alert alert-info">
            <h5>📝 Why 22N81A6255 Failed:</h5>
            <p>The roll number <code>22N81A6255</code> already exists in the database, which is why you got an error. 
            The system correctly prevents duplicate roll numbers to maintain data integrity.</p>
        </div>

        <div class="text-center mt-4">
            <a href="roll_numbers_management.php" class="btn btn-primary btn-lg">
                🔧 Go to Roll Numbers Management
            </a>
            <a href="student_signup.php" class="btn btn-success btn-lg">
                🎓 Test Student Signup
            </a>
        </div>

        <?php
        // Show a quick test of one suggested number
        if (!empty($suggestions)) {
            $test_roll = $suggestions[0];
            echo "<div class='alert alert-success mt-4'>";
            echo "<h5>🎯 Quick Test Suggestion:</h5>";
            echo "<p>Try adding this roll number: <strong>$test_roll</strong></p>";
            echo "<p>This should work because it doesn't exist in the database yet.</p>";
            echo "</div>";
        }
        ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>
