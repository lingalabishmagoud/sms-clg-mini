<?php
// Start session
session_start();

// Check if faculty is logged in (for testing, allow access)
if (!isset($_SESSION['faculty_id'])) {
    $_SESSION['faculty_id'] = 1;
    $_SESSION['user_type'] = 'faculty';
    $_SESSION['faculty_name'] = 'Test Faculty';
}

$faculty_id = $_SESSION['faculty_id'];

// Connect to database
$conn = new mysqli("localhost", "root", "", "student_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get faculty info
$stmt = $conn->prepare("SELECT * FROM faculty WHERE id = ?");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();
$faculty = $result->fetch_assoc();
$stmt->close();

// Get subjects taught by this faculty
$subjects = [];
$stmt = $conn->prepare("SELECT * FROM subjects WHERE faculty_id = ? ORDER BY abbreviation");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}
$stmt->close();

// Initialize variables
$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : (isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : 0);
$section = isset($_GET['section']) ? $_GET['section'] : (isset($_POST['section']) ? $_POST['section'] : '');
$date = isset($_GET['date']) ? $_GET['date'] : (isset($_POST['date']) ? $_POST['date'] : date('Y-m-d'));
$message = '';
$message_type = '';

// Get subject details if subject_id is set
$subject = null;
if ($subject_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM subjects WHERE id = ? AND faculty_id = ?");
    $stmt->bind_param("ii", $subject_id, $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $subject = $result->fetch_assoc();
    $stmt->close();

    if (!$subject) {
        $message = "Subject not found or you don't have permission to access it.";
        $message_type = "danger";
        $subject_id = 0;
    }
}

// Get students for this subject and section
$students = [];
if ($subject_id > 0 && $section) {
    $stmt = $conn->prepare("
        SELECT s.*, sse.status as enrollment_status
        FROM students s
        JOIN student_subject_enrollment sse ON s.id = sse.student_id
        WHERE sse.subject_id = ? AND s.section = ? AND sse.status = 'active'
        ORDER BY s.roll_number
    ");
    $stmt->bind_param("is", $subject_id, $section);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();
}

// Handle bulk attendance marking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance']) && $subject_id > 0 && $section) {
    $conn->begin_transaction();

    try {
        // Delete existing attendance records for this date, subject, and section
        $stmt = $conn->prepare("DELETE FROM attendance WHERE subject_id = ? AND section = ? AND date = ?");
        $stmt->bind_param("iss", $subject_id, $section, $date);
        $stmt->execute();
        $stmt->close();

        // Insert new attendance records
        $stmt = $conn->prepare("INSERT INTO attendance (subject_id, section, student_id, date, status, remarks, marked_by) VALUES (?, ?, ?, ?, ?, ?, ?)");

        $present_count = 0;
        $absent_count = 0;

        foreach ($students as $student) {
            $student_id = $student['id'];
            $status = isset($_POST['attendance'][$student_id]) ? $_POST['attendance'][$student_id] : 'present';
            $remarks = isset($_POST['remarks'][$student_id]) ? trim($_POST['remarks'][$student_id]) : '';
            
            $stmt->bind_param("isisssi", $subject_id, $section, $student_id, $date, $status, $remarks, $faculty_id);
            $stmt->execute();
            
            if ($status == 'present') {
                $present_count++;
            } else {
                $absent_count++;
            }
        }

        $stmt->close();
        $conn->commit();

        $message = "Attendance marked successfully for " . date('F j, Y', strtotime($date)) . " - Present: $present_count, Absent: $absent_count";
        $message_type = "success";
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error marking attendance: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Get existing attendance records for this date, subject, and section
$attendance_records = [];
if ($subject_id > 0 && $section) {
    $stmt = $conn->prepare("
        SELECT a.*
        FROM attendance a
        WHERE a.subject_id = ? AND a.section = ? AND a.date = ?
    ");
    $stmt->bind_param("iss", $subject_id, $section, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $attendance_records[$row['student_id']] = $row;
    }
    $stmt->close();
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
    <title>Faculty Attendance - Student Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .attendance-card {
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        .attendance-card:hover {
            border-color: #007bff;
            box-shadow: 0 4px 8px rgba(0,123,255,0.1);
        }
        .student-present {
            background-color: #d4edda;
            border-color: #28a745;
        }
        .student-absent {
            background-color: #f8d7da;
            border-color: #dc3545;
        }
        .bulk-actions {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .status-toggle {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .status-toggle:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Faculty Portal</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="faculty_attendance.php">Attendance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faculty_schedule.php">Schedule</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        Welcome, <?php echo htmlspecialchars($faculty['full_name'] ?? 'Faculty'); ?>
                    </span>
                    <a href="?logout=1" class="btn btn-light btn-sm">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2><i class="fas fa-clipboard-check me-2"></i>Mark Attendance</h2>
                <p class="text-muted">Professional bulk attendance marking system</p>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="btn-group" role="group">
                    <a href="faculty_attendance_reports.php" class="btn btn-outline-info">
                        <i class="fas fa-chart-bar me-1"></i>Reports
                    </a>
                </div>
            </div>
        </div>

        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Subject, Section and Date Selection Form -->
        <div class="card mb-4">
            <div class="card-header bulk-actions">
                <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Attendance Settings</h5>
            </div>
            <div class="card-body">
                <form method="get" action="faculty_attendance.php" class="row g-3">
                    <div class="col-md-4">
                        <label for="subject_id" class="form-label">Select Subject</label>
                        <select name="subject_id" id="subject_id" class="form-select" required>
                            <option value="">-- Select Subject --</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo ($subject_id == $s['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['abbreviation'] . ' - ' . $s['subject_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="section" class="form-label">Select Section</label>
                        <select name="section" id="section" class="form-select" required>
                            <option value="">-- Select Section --</option>
                            <option value="CS-A" <?php echo ($section == 'CS-A') ? 'selected' : ''; ?>>CS-A</option>
                            <option value="CS-B" <?php echo ($section == 'CS-B') ? 'selected' : ''; ?>>CS-B</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="date" class="form-label">Select Date</label>
                        <input type="date" name="date" id="date" class="form-control" value="<?php echo $date; ?>" max="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i>Load Students
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($subject_id > 0 && $section && $subject): ?>
            <!-- Attendance Marking Form -->
            <div class="card">
                <div class="card-header bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-users me-2"></i>
                            <?php echo htmlspecialchars($subject['abbreviation'] . ' - ' . $subject['subject_name']); ?> | 
                            Section <?php echo $section; ?> | 
                            <?php echo date('F j, Y', strtotime($date)); ?>
                        </h5>
                        <span class="badge bg-info"><?php echo count($students); ?> Students</span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (count($students) > 0): ?>
                        <!-- Bulk Actions -->
                        <div class="card mb-4 bulk-actions">
                            <div class="card-body">
                                <h6 class="text-white mb-3"><i class="fas fa-magic me-2"></i>Bulk Actions</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <button type="button" class="btn btn-success w-100" onclick="markAllPresent()">
                                            <i class="fas fa-check-circle me-2"></i>Mark All Present
                                        </button>
                                    </div>
                                    <div class="col-md-6">
                                        <button type="button" class="btn btn-warning w-100" onclick="markAllAbsent()">
                                            <i class="fas fa-times-circle me-2"></i>Mark All Absent
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <form method="post" action="faculty_attendance.php" id="attendanceForm">
                            <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
                            <input type="hidden" name="section" value="<?php echo $section; ?>">
                            <input type="hidden" name="date" value="<?php echo $date; ?>">

                            <div class="row">
                                <?php $count = 1; ?>
                                <?php foreach ($students as $student): ?>
                                    <?php
                                        $current_status = isset($attendance_records[$student['id']])
                                            ? $attendance_records[$student['id']]['status']
                                            : 'present';
                                        $current_remarks = isset($attendance_records[$student['id']])
                                            ? $attendance_records[$student['id']]['remarks']
                                            : '';
                                        $card_class = ($current_status == 'present') ? 'student-present' : 'student-absent';
                                    ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card attendance-card <?php echo $card_class; ?>" id="student-card-<?php echo $student['id']; ?>">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="card-title mb-0"><?php echo htmlspecialchars($student['full_name']); ?></h6>
                                                    <span class="badge bg-secondary"><?php echo $count++; ?></span>
                                                </div>
                                                <p class="text-muted small mb-2">Roll: <?php echo htmlspecialchars($student['roll_number']); ?></p>
                                                
                                                <div class="mb-2">
                                                    <div class="btn-group w-100" role="group">
                                                        <input type="radio" class="btn-check" name="attendance[<?php echo $student['id']; ?>]" 
                                                               id="present-<?php echo $student['id']; ?>" value="present" 
                                                               <?php echo ($current_status == 'present') ? 'checked' : ''; ?>
                                                               onchange="updateStudentCard(<?php echo $student['id']; ?>, 'present')">
                                                        <label class="btn btn-outline-success btn-sm status-toggle" for="present-<?php echo $student['id']; ?>">
                                                            <i class="fas fa-check me-1"></i>Present
                                                        </label>

                                                        <input type="radio" class="btn-check" name="attendance[<?php echo $student['id']; ?>]" 
                                                               id="absent-<?php echo $student['id']; ?>" value="absent" 
                                                               <?php echo ($current_status == 'absent') ? 'checked' : ''; ?>
                                                               onchange="updateStudentCard(<?php echo $student['id']; ?>, 'absent')">
                                                        <label class="btn btn-outline-danger btn-sm status-toggle" for="absent-<?php echo $student['id']; ?>">
                                                            <i class="fas fa-times me-1"></i>Absent
                                                        </label>
                                                    </div>
                                                </div>
                                                
                                                <input type="text" name="remarks[<?php echo $student['id']; ?>]" 
                                                       class="form-control form-control-sm" 
                                                       placeholder="Remarks (optional)" 
                                                       value="<?php echo htmlspecialchars($current_remarks); ?>">
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="text-center mt-4">
                                <button type="submit" name="mark_attendance" class="btn btn-success btn-lg">
                                    <i class="fas fa-save me-2"></i>Save Attendance
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>No students are enrolled in this subject for section <?php echo $section; ?>.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($subject_id > 0): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>Subject not found or you don't have permission to access it.
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Please select a subject, section, and date to mark attendance.
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>Faculty Portal</h5>
                    <p>Professional attendance management system.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>&copy; 2023 Student Management System. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function markAllPresent() {
            const presentRadios = document.querySelectorAll('input[type="radio"][value="present"]');
            presentRadios.forEach(radio => {
                radio.checked = true;
                const studentId = radio.name.match(/\[(\d+)\]/)[1];
                updateStudentCard(studentId, 'present');
            });
        }

        function markAllAbsent() {
            const absentRadios = document.querySelectorAll('input[type="radio"][value="absent"]');
            absentRadios.forEach(radio => {
                radio.checked = true;
                const studentId = radio.name.match(/\[(\d+)\]/)[1];
                updateStudentCard(studentId, 'absent');
            });
        }

        function updateStudentCard(studentId, status) {
            const card = document.getElementById('student-card-' + studentId);
            card.classList.remove('student-present', 'student-absent');
            
            if (status === 'present') {
                card.classList.add('student-present');
            } else {
                card.classList.add('student-absent');
            }
        }

        // Auto-save functionality (optional)
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('attendanceForm');
            if (form) {
                // Add visual feedback for changes
                const radios = form.querySelectorAll('input[type="radio"]');
                radios.forEach(radio => {
                    radio.addEventListener('change', function() {
                        // Add a subtle animation to show the change was registered
                        this.closest('.card').style.transform = 'scale(1.02)';
                        setTimeout(() => {
                            this.closest('.card').style.transform = 'scale(1)';
                        }, 200);
                    });
                });
            }
        });
    </script>
</body>
</html>
