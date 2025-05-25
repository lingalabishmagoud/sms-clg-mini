<?php
// Handle AJAX requests
if (isset($_POST['action'])) {
    $conn = new mysqli("localhost", "root", "", "student_db");

    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }

    switch ($_POST['action']) {
        case 'add_roll_number':
            $roll_number = $_POST['roll_number'];

            // Validate and extract data from roll number
            if (preg_match('/^(\d{2})(N81)(A\d{2})(\d+)$/', $roll_number, $matches)) {
                $year_from_roll = "20" . $matches[1];
                $college_code = $matches[2];
                $dept_code = $matches[3];
                $student_number = $matches[4];



                // Get department name from database
                $dept_result = $conn->query("SELECT dept_name FROM departments WHERE dept_code = '$dept_code'");
                if ($dept_result->num_rows > 0) {
                    $dept_name = $dept_result->fetch_assoc()['dept_name'];

                    // Check if roll number already exists
                    $check = $conn->prepare("SELECT id FROM roll_numbers WHERE roll_number = ?");
                    $check->bind_param("s", $roll_number);
                    $check->execute();

                    if ($check->get_result()->num_rows == 0) {
                        // Insert new roll number
                        $stmt = $conn->prepare("INSERT INTO roll_numbers (roll_number, year_of_joining, college_code, dept_code, dept_name, student_number) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("sissss", $roll_number, $year_from_roll, $college_code, $dept_code, $dept_name, $student_number);

                        if ($stmt->execute()) {
                            echo json_encode(['success' => true, 'message' => "Roll number $roll_number added successfully for $dept_name department"]);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Error adding roll number: ' . $stmt->error]);
                        }
                        $stmt->close();
                    } else {
                        echo json_encode(['success' => false, 'message' => "Roll number $roll_number already exists in the database"]);
                    }
                    $check->close();
                } else {
                    // Get available departments for better error message
                    $all_depts = $conn->query("SELECT dept_code, dept_name FROM departments");
                    $available = [];
                    while ($row = $all_depts->fetch_assoc()) {
                        $available[] = $row['dept_code'] . ' (' . $row['dept_name'] . ')';
                    }
                    $available_list = implode(', ', $available);
                    echo json_encode(['success' => false, 'message' => "Department code '$dept_code' not found. Available departments: $available_list"]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid roll number format']);
            }
            break;

        case 'delete_roll_number':
            $id = $_POST['id'];

            // Check if roll number is used
            $check = $conn->prepare("SELECT is_used FROM roll_numbers WHERE id = ?");
            $check->bind_param("i", $id);
            $check->execute();
            $result = $check->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if (!$row['is_used']) {
                    // Delete only if not used
                    $stmt = $conn->prepare("DELETE FROM roll_numbers WHERE id = ?");
                    $stmt->bind_param("i", $id);

                    if ($stmt->execute()) {
                        echo json_encode(['success' => true, 'message' => 'Roll number deleted successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Error deleting roll number']);
                    }
                    $stmt->close();
                } else {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete used roll number']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Roll number not found']);
            }
            $check->close();
            break;

        case 'add_department':
            $dept_code = $_POST['dept_code'];
            $dept_name = $_POST['dept_name'];
            $description = $_POST['description'];

            // Check if department code already exists
            $check = $conn->prepare("SELECT id FROM departments WHERE dept_code = ?");
            $check->bind_param("s", $dept_code);
            $check->execute();

            if ($check->get_result()->num_rows == 0) {
                $stmt = $conn->prepare("INSERT INTO departments (dept_code, dept_name, description) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $dept_code, $dept_name, $description);

                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Department added successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error adding department']);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'message' => 'Department code already exists']);
            }
            $check->close();
            break;
    }

    $conn->close();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roll Numbers Management - Student Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .status-available { color: green; font-weight: bold; }
        .status-used { color: red; font-weight: bold; }
        .table-container { max-height: 600px; overflow-y: auto; }
        .stats-card { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .search-container { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .btn-action { padding: 2px 8px; margin: 1px; }
        .modal-header { background: #f8f9fa; }
        .highlight { background-color: yellow; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center mb-4">🎓 Roll Numbers Management System</h1>

        <!-- Action Buttons -->
        <div class="row mb-4">
            <div class="col-md-12 text-center">
                <button class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#addRollModal">
                    <i class="fas fa-plus"></i> Add Roll Number
                </button>
                <button class="btn btn-info btn-lg" data-bs-toggle="modal" data-bs-target="#addDeptModal">
                    <i class="fas fa-building"></i> Add Department
                </button>
                <button class="btn btn-warning btn-lg" onclick="location.reload()">
                    <i class="fas fa-refresh"></i> Refresh
                </button>
            </div>
        </div>

        <?php
        $conn = new mysqli("localhost", "root", "", "student_db");

        if ($conn->connect_error) {
            echo "<div class='alert alert-danger'>Database connection failed: " . $conn->connect_error . "</div>";
            exit;
        }

        // Get search and filter parameters
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

        // Get statistics
        $total_result = $conn->query("SELECT COUNT(*) as total FROM roll_numbers");
        $used_result = $conn->query("SELECT COUNT(*) as used FROM roll_numbers WHERE is_used = TRUE");
        $available_result = $conn->query("SELECT COUNT(*) as available FROM roll_numbers WHERE is_used = FALSE");

        $total = $total_result->fetch_assoc()['total'];
        $used = $used_result->fetch_assoc()['used'];
        $available = $available_result->fetch_assoc()['available'];

        // Get departments for filter
        $dept_result = $conn->query("SELECT DISTINCT dept_code, dept_name FROM departments ORDER BY dept_name");
        $departments = [];
        while ($row = $dept_result->fetch_assoc()) {
            $departments[] = $row;
        }
        ?>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card text-center">
                    <h3 class="text-primary"><?php echo $total; ?></h3>
                    <p>Total Roll Numbers</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card text-center">
                    <h3 class="text-success"><?php echo $available; ?></h3>
                    <p>Available for Signup</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card text-center">
                    <h3 class="text-danger"><?php echo $used; ?></h3>
                    <p>Already Used</p>
                </div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="search-container">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">🔍 Search Roll Numbers</label>
                    <input type="text" class="form-control" id="search" name="search"
                           value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="Search by roll number, year, or student name...">
                </div>
                <div class="col-md-3">
                    <label for="filter" class="form-label">📂 Filter by Status</label>
                    <select class="form-control" id="filter" name="filter">
                        <option value="all" <?php echo ($filter == 'all') ? 'selected' : ''; ?>>All Roll Numbers</option>
                        <option value="available" <?php echo ($filter == 'available') ? 'selected' : ''; ?>>Available Only</option>
                        <option value="used" <?php echo ($filter == 'used') ? 'selected' : ''; ?>>Used Only</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="dept_filter" class="form-label">🏢 Filter by Department</label>
                    <select class="form-control" id="dept_filter" name="dept_filter">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['dept_code']; ?>"
                                    <?php echo (isset($_GET['dept_filter']) && $_GET['dept_filter'] == $dept['dept_code']) ? 'selected' : ''; ?>>
                                <?php echo $dept['dept_code'] . ' - ' . $dept['dept_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </div>
            </form>

            <?php if ($search || $filter != 'all' || isset($_GET['dept_filter'])): ?>
                <div class="mt-3">
                    <a href="roll_numbers_management.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                    <span class="ms-3 text-muted">
                        Showing results for:
                        <?php if ($search): ?>Search: "<?php echo htmlspecialchars($search); ?>" <?php endif; ?>
                        <?php if ($filter != 'all'): ?>Status: <?php echo ucfirst($filter); ?> <?php endif; ?>
                        <?php if (isset($_GET['dept_filter']) && $_GET['dept_filter']): ?>
                            Department: <?php
                            foreach ($departments as $dept) {
                                if ($dept['dept_code'] == $_GET['dept_filter']) {
                                    echo $dept['dept_name'];
                                    break;
                                }
                            }
                            ?>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Roll Numbers Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Roll Numbers Database</h5>
                <div>
                    <span class="badge bg-primary">Total: <?php echo $total; ?></span>
                    <span class="badge bg-success">Available: <?php echo $available; ?></span>
                    <span class="badge bg-danger">Used: <?php echo $used; ?></span>
                </div>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <th>Roll Number</th>
                                <th>Year</th>
                                <th>College</th>
                                <th>Dept Code</th>
                                <th>Department</th>
                                <th>Student #</th>
                                <th>Status</th>
                                <th>Used By</th>
                                <th>Used Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Build query based on filters
                            $where_conditions = [];
                            $params = [];

                            // Status filter
                            if ($filter == 'available') {
                                $where_conditions[] = "r.is_used = FALSE";
                            } elseif ($filter == 'used') {
                                $where_conditions[] = "r.is_used = TRUE";
                            }

                            // Department filter
                            if (isset($_GET['dept_filter']) && $_GET['dept_filter']) {
                                $where_conditions[] = "r.dept_code = ?";
                                $params[] = $_GET['dept_filter'];
                            }

                            // Search filter
                            if ($search) {
                                $where_conditions[] = "(r.roll_number LIKE ? OR r.year_of_joining LIKE ? OR r.dept_name LIKE ? OR s.full_name LIKE ?)";
                                $search_param = "%$search%";
                                $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
                            }

                            $where_clause = "";
                            if (!empty($where_conditions)) {
                                $where_clause = "WHERE " . implode(" AND ", $where_conditions);
                            }

                            $sql = "SELECT r.*, s.full_name, s.email
                                   FROM roll_numbers r
                                   LEFT JOIN students s ON r.used_by_student_id = s.id
                                   $where_clause
                                   ORDER BY r.roll_number";

                            if (!empty($params)) {
                                $stmt = $conn->prepare($sql);
                                if (!empty($params)) {
                                    $types = str_repeat('s', count($params));
                                    $stmt->bind_param($types, ...$params);
                                }
                                $stmt->execute();
                                $result = $stmt->get_result();
                            } else {
                                $result = $conn->query($sql);
                            }

                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    $status_class = $row['is_used'] ? 'status-used' : 'status-available';
                                    $status_text = $row['is_used'] ? 'USED' : 'AVAILABLE';
                                    $used_by = $row['is_used'] ? $row['full_name'] . " (" . $row['email'] . ")" : '-';
                                    $used_date = $row['used_at'] ? date('Y-m-d H:i', strtotime($row['used_at'])) : '-';

                                    // Highlight search terms
                                    $display_roll = $row['roll_number'];
                                    if ($search) {
                                        $display_roll = str_ireplace($search, "<span class='highlight'>$search</span>", $display_roll);
                                    }

                                    echo "<tr>";
                                    echo "<td><strong>$display_roll</strong></td>";
                                    echo "<td>{$row['year_of_joining']}</td>";
                                    echo "<td>{$row['college_code']}</td>";
                                    echo "<td>{$row['dept_code']}</td>";
                                    echo "<td>{$row['dept_name']}</td>";
                                    echo "<td>{$row['student_number']}</td>";
                                    echo "<td><span class='$status_class'>$status_text</span></td>";
                                    echo "<td>$used_by</td>";
                                    echo "<td>$used_date</td>";
                                    echo "<td>";

                                    if (!$row['is_used']) {
                                        echo "<button class='btn btn-danger btn-action' onclick='deleteRollNumber({$row['id']}, \"{$row['roll_number']}\")'>";
                                        echo "<i class='fas fa-trash'></i>";
                                        echo "</button>";
                                    } else {
                                        echo "<span class='text-muted'>Protected</span>";
                                    }

                                    echo "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='10' class='text-center'>No roll numbers found for the selected criteria.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php $conn->close(); ?>

        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5>Quick Actions:</h5>
                        <div class="btn-group" role="group">
                            <a href="student_signup.php" class="btn btn-primary">🎓 Student Signup</a>
                            <a href="test_roll_validation.php" class="btn btn-secondary">🧪 Test Validation</a>
                            <a href="check_students_table.php" class="btn btn-info">👥 View Students</a>
                            <a href="setup_database.php" class="btn btn-warning">🔧 Database Setup</a>
                            <a href="signup_demo.php" class="btn btn-success">📖 Demo Guide</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="alert alert-info mt-4">
            <h5>📝 How to Use:</h5>
            <ul class="mb-0">
                <li><strong>🔍 Search:</strong> Search by roll number, year, or student name</li>
                <li><strong>📂 Filter:</strong> Filter by status (Available/Used) or department</li>
                <li><strong>➕ Add Roll Number:</strong> Enter only the roll number - system auto-extracts department info</li>
                <li><strong>🗑️ Delete:</strong> Only available (unused) roll numbers can be deleted</li>
                <li><strong>🏢 Add Department:</strong> Add new departments with their codes for new branches</li>
                <li><strong>🔒 Protected:</strong> Used roll numbers cannot be deleted (data integrity)</li>
            </ul>
        </div>
    </div>

    <!-- Add Roll Number Modal -->
    <div class="modal fade" id="addRollModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">➕ Add New Roll Number</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addRollForm">
                        <div class="mb-3">
                            <label for="rollNumber" class="form-label">Roll Number</label>
                            <input type="text" class="form-control" id="rollNumber" name="roll_number"
                                   placeholder="e.g., 22N81A6255" required>
                            <div class="form-text">
                                Format: YYNXXAXXNN (e.g., 22N81A6255)<br>
                                System will auto-extract: Year, College Code, Department Code
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <strong>Note:</strong> The system will automatically extract:
                            <ul class="mb-0 mt-2">
                                <li>Year of joining from first 2 digits</li>
                                <li>College code (N81)</li>
                                <li>Department code and name from database</li>
                                <li>Student number from last digits</li>
                            </ul>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="addRollNumber()">Add Roll Number</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Department Modal -->
    <div class="modal fade" id="addDeptModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">🏢 Add New Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addDeptForm">
                        <div class="mb-3">
                            <label for="deptCode" class="form-label">Department Code</label>
                            <input type="text" class="form-control" id="deptCode" name="dept_code"
                                   placeholder="e.g., A68" required>
                            <div class="form-text">Format: A## (e.g., A68 for new department)</div>
                        </div>
                        <div class="mb-3">
                            <label for="deptName" class="form-label">Department Name</label>
                            <input type="text" class="form-control" id="deptName" name="dept_name"
                                   placeholder="e.g., Artificial Intelligence" required>
                        </div>
                        <div class="mb-3">
                            <label for="deptDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="deptDescription" name="description" rows="3"
                                      placeholder="Department description..."></textarea>
                        </div>
                        <div class="alert alert-warning">
                            <strong>Important:</strong> Once added, this department code will be available for roll number validation.
                            Make sure the code follows the format A## (A + 2 digits).
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-info" onclick="addDepartment()">Add Department</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Container -->
    <div id="alertContainer" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showAlert(message, type = 'success') {
            const alertContainer = document.getElementById('alertContainer');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            alertContainer.appendChild(alertDiv);

            // Auto remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        function addRollNumber() {
            const rollNumber = document.getElementById('rollNumber').value;

            if (!rollNumber) {
                showAlert('Please enter a roll number', 'danger');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'add_roll_number');
            formData.append('roll_number', rollNumber);

            fetch('roll_numbers_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    document.getElementById('rollNumber').value = '';
                    bootstrap.Modal.getInstance(document.getElementById('addRollModal')).hide();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                showAlert('Error adding roll number', 'danger');
            });
        }

        function addDepartment() {
            const deptCode = document.getElementById('deptCode').value;
            const deptName = document.getElementById('deptName').value;
            const description = document.getElementById('deptDescription').value;

            if (!deptCode || !deptName) {
                showAlert('Please fill in required fields', 'danger');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'add_department');
            formData.append('dept_code', deptCode);
            formData.append('dept_name', deptName);
            formData.append('description', description);

            fetch('roll_numbers_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    document.getElementById('addDeptForm').reset();
                    bootstrap.Modal.getInstance(document.getElementById('addDeptModal')).hide();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                showAlert('Error adding department', 'danger');
            });
        }

        function deleteRollNumber(id, rollNumber) {
            if (confirm(`Are you sure you want to delete roll number ${rollNumber}?\n\nThis action cannot be undone.`)) {
                const formData = new FormData();
                formData.append('action', 'delete_roll_number');
                formData.append('id', id);

                fetch('roll_numbers_management.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert(data.message, 'danger');
                    }
                })
                .catch(error => {
                    showAlert('Error deleting roll number', 'danger');
                });
            }
        }

        // Auto-submit search form on Enter
        document.getElementById('search').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });
    </script>
</body>
</html>
