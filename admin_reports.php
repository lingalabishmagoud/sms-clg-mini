<?php
// Start session
session_start();

// For testing purposes, we're not enforcing authentication
// In a real application, you would check if the user is logged in
$admin_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 1;
$admin_name = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : "System Administrator";

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get admin information
$admin = null;
$stmt = $conn->prepare("SELECT * FROM admin WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $admin = $result->fetch_assoc();
} else {
    // For testing, create a dummy admin if not found
    $admin = [
        'id' => 1,
        'username' => 'admin',
        'full_name' => $admin_name,
        'email' => 'admin@example.com'
    ];
}

// Handle report generation
$report_type = isset($_GET['type']) ? $_GET['type'] : 'student_performance';
$message = '';

// Get report data based on type
$report_data = [];
$chart_data = [];

switch ($report_type) {
    case 'student_performance':
        // Get student performance data
        $result = $conn->query("
            SELECT s.id, s.full_name, s.email, s.course, s.year, 
                   AVG(g.grade_value/g.max_grade * 100) as avg_grade,
                   COUNT(DISTINCT g.course_id) as course_count
            FROM students s
            LEFT JOIN grades g ON s.id = g.student_id
            GROUP BY s.id
            ORDER BY avg_grade DESC
        ");
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $report_data[] = $row;
                // Prepare chart data
                if (!empty($row['avg_grade'])) {
                    $chart_data[] = [
                        'name' => $row['full_name'],
                        'value' => round($row['avg_grade'], 2)
                    ];
                }
            }
        }
        break;
        
    case 'course_statistics':
        // Get course statistics
        $result = $conn->query("
            SELECT c.id, c.course_code, c.course_name, c.credits, f.full_name as faculty_name,
                   COUNT(DISTINCT ce.student_id) as student_count,
                   AVG(g.grade_value/g.max_grade * 100) as avg_grade
            FROM courses c
            LEFT JOIN faculty f ON c.faculty_id = f.id
            LEFT JOIN course_enrollment ce ON c.id = ce.course_id
            LEFT JOIN grades g ON c.id = g.course_id
            GROUP BY c.id
            ORDER BY student_count DESC
        ");
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $report_data[] = $row;
                // Prepare chart data
                $chart_data[] = [
                    'name' => $row['course_code'],
                    'students' => $row['student_count'],
                    'avg_grade' => round($row['avg_grade'] ?? 0, 2)
                ];
            }
        }
        break;
        
    case 'faculty_workload':
        // Get faculty workload
        $result = $conn->query("
            SELECT f.id, f.full_name, f.email, f.department,
                   COUNT(DISTINCT c.id) as course_count,
                   SUM(c.credits) as total_credits,
                   COUNT(DISTINCT ce.student_id) as student_count
            FROM faculty f
            LEFT JOIN courses c ON f.id = c.faculty_id
            LEFT JOIN course_enrollment ce ON c.id = ce.course_id
            GROUP BY f.id
            ORDER BY course_count DESC
        ");
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $report_data[] = $row;
                // Prepare chart data
                $chart_data[] = [
                    'name' => $row['full_name'],
                    'courses' => $row['course_count'],
                    'students' => $row['student_count']
                ];
            }
        }
        break;
        
    case 'enrollment_trends':
        // Get enrollment trends by department
        $result = $conn->query("
            SELECT c.department, COUNT(DISTINCT ce.student_id) as student_count
            FROM courses c
            JOIN course_enrollment ce ON c.id = ce.course_id
            GROUP BY c.department
            ORDER BY student_count DESC
        ");
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $report_data[] = $row;
                // Prepare chart data
                $chart_data[] = [
                    'name' => $row['department'],
                    'value' => $row['student_count']
                ];
            }
        }
        break;
}

// Handle export to Excel
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="report_' . $report_type . '_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Output Excel content
    echo '<table border="1">';
    
    // Output headers
    if (!empty($report_data)) {
        echo '<tr>';
        foreach (array_keys($report_data[0]) as $header) {
            echo '<th>' . str_replace('_', ' ', ucfirst($header)) . '</th>';
        }
        echo '</tr>';
        
        // Output data
        foreach ($report_data as $row) {
            echo '<tr>';
            foreach ($row as $value) {
                echo '<td>' . $value . '</td>';
            }
            echo '</tr>';
        }
    }
    
    echo '</table>';
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.html");
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Student Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Student Management System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_students.php">Students</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_faculty.php">Faculty</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_courses.php">Courses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="admin_reports.php">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_settings.php">Settings</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        Welcome, <?php echo htmlspecialchars($admin['full_name']); ?>
                    </span>
                    <a href="?logout=1" class="btn btn-light btn-sm">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2><i class="fas fa-chart-bar me-2"></i>Reports</h2>
                    <div>
                        <a href="?type=<?php echo $report_type; ?>&export=excel" class="btn btn-success">
                            <i class="fas fa-file-excel me-2"></i>Export to Excel
                        </a>
                    </div>
                </div>
                <p class="text-muted">Generate and analyze system reports</p>
            </div>
        </div>

        <!-- Report Types -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <a href="?type=student_performance" class="btn <?php echo $report_type == 'student_performance' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                <i class="fas fa-user-graduate me-2"></i>Student Performance
                            </a>
                            <a href="?type=course_statistics" class="btn <?php echo $report_type == 'course_statistics' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                <i class="fas fa-book me-2"></i>Course Statistics
                            </a>
                            <a href="?type=faculty_workload" class="btn <?php echo $report_type == 'faculty_workload' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                <i class="fas fa-chalkboard-teacher me-2"></i>Faculty Workload
                            </a>
                            <a href="?type=enrollment_trends" class="btn <?php echo $report_type == 'enrollment_trends' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                <i class="fas fa-chart-line me-2"></i>Enrollment Trends
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Visualization -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <canvas id="reportChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Data -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <?php 
                            $title = str_replace('_', ' ', ucwords($report_type));
                            echo $title . ' Report';
                            ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <?php if (!empty($report_data)): ?>
                                    <tr>
                                        <?php foreach (array_keys($report_data[0]) as $header): ?>
                                        <th><?php echo str_replace('_', ' ', ucfirst($header)); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endif; ?>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $key => $value): ?>
                                        <td>
                                            <?php 
                                            if ($key == 'avg_grade' && !is_null($value)) {
                                                echo round($value, 2) . '%';
                                            } else {
                                                echo htmlspecialchars($value ?? 'N/A');
                                            }
                                            ?>
                                        </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>Student Management System</h5>
                    <p>A comprehensive platform for students and faculty.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>&copy; 2023 Student Management System. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart Initialization -->
    <script>
        // Initialize chart based on report type
        const ctx = document.getElementById('reportChart').getContext('2d');
        
        <?php if ($report_type == 'student_performance'): ?>
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($chart_data, 'name')); ?>,
                datasets: [{
                    label: 'Average Grade (%)',
                    data: <?php echo json_encode(array_column($chart_data, 'value')); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
        <?php elseif ($report_type == 'course_statistics'): ?>
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($chart_data, 'name')); ?>,
                datasets: [{
                    label: 'Number of Students',
                    data: <?php echo json_encode(array_column($chart_data, 'students')); ?>,
                    backgroundColor: 'rgba(255, 99, 132, 0.5)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }, {
                    label: 'Average Grade (%)',
                    data: <?php echo json_encode(array_column($chart_data, 'avg_grade')); ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.5)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1,
                    type: 'line'
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        <?php elseif ($report_type == 'faculty_workload'): ?>
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($chart_data, 'name')); ?>,
                datasets: [{
                    label: 'Number of Courses',
                    data: <?php echo json_encode(array_column($chart_data, 'courses')); ?>,
                    backgroundColor: 'rgba(153, 102, 255, 0.5)',
                    borderColor: 'rgba(153, 102, 255, 1)',
                    borderWidth: 1
                }, {
                    label: 'Number of Students',
                    data: <?php echo json_encode(array_column($chart_data, 'students')); ?>,
                    backgroundColor: 'rgba(255, 159, 64, 0.5)',
                    borderColor: 'rgba(255, 159, 64, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        <?php elseif ($report_type == 'enrollment_trends'): ?>
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_column($chart_data, 'name')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($chart_data, 'value')); ?>,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.5)',
                        'rgba(54, 162, 235, 0.5)',
                        'rgba(255, 206, 86, 0.5)',
                        'rgba(75, 192, 192, 0.5)',
                        'rgba(153, 102, 255, 0.5)',
                        'rgba(255, 159, 64, 0.5)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)'
                    ],
                    borderWidth: 1
                }]
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
