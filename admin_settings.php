<?php
// Start session
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];

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

// Handle settings actions
$action = isset($_GET['action']) ? $_GET['action'] : 'general';
$message = '';

// Check if settings table exists, if not create it
$result = $conn->query("SHOW TABLES LIKE 'settings'");
if ($result->num_rows == 0) {
    $sql = "CREATE TABLE settings (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        setting_group VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    if ($conn->query($sql) === TRUE) {
        // Insert default settings
        $default_settings = [
            ['system_name', 'Student Management System', 'general'],
            ['system_email', 'admin@example.com', 'general'],
            ['allow_registration', '1', 'general'],
            ['maintenance_mode', '0', 'general'],
            ['current_academic_year', '2023-2024', 'academic'],
            ['current_semester', 'Fall', 'academic'],
            ['grading_scale', 'A:90-100,B:80-89,C:70-79,D:60-69,F:0-59', 'academic'],
            ['smtp_host', 'smtp.example.com', 'email'],
            ['smtp_port', '587', 'email'],
            ['smtp_username', 'notifications@example.com', 'email'],
            ['smtp_password', 'password123', 'email'],
            ['smtp_encryption', 'tls', 'email'],
            ['backup_frequency', 'weekly', 'backup'],
            ['backup_retention', '30', 'backup'],
            ['backup_path', 'backups/', 'backup']
        ];

        foreach ($default_settings as $setting) {
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $setting[0], $setting[1], $setting[2]);
            $stmt->execute();
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_settings'])) {
        // Update settings
        foreach ($_POST as $key => $value) {
            if ($key !== 'update_settings' && $key !== 'action') {
                $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->bind_param("ss", $value, $key);
                $stmt->execute();
            }
        }
        $message = "Settings updated successfully!";
    } elseif (isset($_POST['update_admin'])) {
        // Update admin profile
        $full_name = $_POST['full_name'];
        $email = $_POST['email'];
        $username = $_POST['username'];
        $password = isset($_POST['password']) && !empty($_POST['password']) ? $_POST['password'] : null;

        if ($password) {
            $stmt = $conn->prepare("UPDATE admin SET full_name = ?, email = ?, username = ?, password = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $full_name, $email, $username, $password, $admin_id);
        } else {
            $stmt = $conn->prepare("UPDATE admin SET full_name = ?, email = ?, username = ? WHERE id = ?");
            $stmt->bind_param("sssi", $full_name, $email, $username, $admin_id);
        }

        if ($stmt->execute()) {
            $message = "Admin profile updated successfully!";
        } else {
            $message = "Error updating admin profile: " . $conn->error;
        }
    } elseif (isset($_POST['backup_database'])) {
        // Simulate database backup
        $backup_path = 'backups/';
        if (!file_exists($backup_path)) {
            mkdir($backup_path, 0777, true);
        }

        $backup_file = $backup_path . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $message = "Database backup initiated. Backup file: " . $backup_file;

        // In a real application, you would use mysqldump or a similar tool
        // For this demo, we'll just create an empty file
        file_put_contents($backup_file, "-- Database backup simulation\n-- Date: " . date('Y-m-d H:i:s'));
    }
}

// Get settings for the current action
$settings = [];
$result = $conn->query("SELECT * FROM settings WHERE setting_group = '$action' ORDER BY id");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
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
    <title>System Settings - Student Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                        <a class="nav-link" href="admin_reports.php">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="admin_settings.php">Settings</a>
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
                <h2><i class="fas fa-cogs me-2"></i>System Settings</h2>
                <p class="text-muted">Configure system parameters and preferences</p>
            </div>
        </div>

        <?php if (!empty($message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Settings Navigation -->
            <div class="col-md-3 mb-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Settings Categories</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="?action=general" class="list-group-item list-group-item-action <?php echo $action == 'general' ? 'active' : ''; ?>">
                            <i class="fas fa-sliders-h me-2"></i>General Settings
                        </a>
                        <a href="?action=academic" class="list-group-item list-group-item-action <?php echo $action == 'academic' ? 'active' : ''; ?>">
                            <i class="fas fa-graduation-cap me-2"></i>Academic Settings
                        </a>
                        <a href="?action=email" class="list-group-item list-group-item-action <?php echo $action == 'email' ? 'active' : ''; ?>">
                            <i class="fas fa-envelope me-2"></i>Email Settings
                        </a>
                        <a href="?action=backup" class="list-group-item list-group-item-action <?php echo $action == 'backup' ? 'active' : ''; ?>">
                            <i class="fas fa-database me-2"></i>Backup & Restore
                        </a>
                        <a href="?action=profile" class="list-group-item list-group-item-action <?php echo $action == 'profile' ? 'active' : ''; ?>">
                            <i class="fas fa-user-cog me-2"></i>Admin Profile
                        </a>
                    </div>
                </div>
            </div>

            <!-- Settings Content -->
            <div class="col-md-9">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <?php
                            switch ($action) {
                                case 'general':
                                    echo '<i class="fas fa-sliders-h me-2"></i>General Settings';
                                    break;
                                case 'academic':
                                    echo '<i class="fas fa-graduation-cap me-2"></i>Academic Settings';
                                    break;
                                case 'email':
                                    echo '<i class="fas fa-envelope me-2"></i>Email Settings';
                                    break;
                                case 'backup':
                                    echo '<i class="fas fa-database me-2"></i>Backup & Restore';
                                    break;
                                case 'profile':
                                    echo '<i class="fas fa-user-cog me-2"></i>Admin Profile';
                                    break;
                            }
                            ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($action == 'profile'): ?>
                            <!-- Admin Profile Form -->
                            <form method="post" action="">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($admin['username']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="full_name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($admin['full_name']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">New Password (leave blank to keep current)</label>
                                    <input type="password" class="form-control" id="password" name="password">
                                </div>
                                <button type="submit" name="update_admin" class="btn btn-primary">Update Profile</button>
                            </form>
                        <?php elseif ($action == 'backup'): ?>
                            <!-- Backup & Restore Form -->
                            <form method="post" action="">
                                <div class="mb-3">
                                    <label for="backup_frequency" class="form-label">Backup Frequency</label>
                                    <select class="form-select" id="backup_frequency" name="backup_frequency">
                                        <option value="daily" <?php echo isset($settings['backup_frequency']) && $settings['backup_frequency'] == 'daily' ? 'selected' : ''; ?>>Daily</option>
                                        <option value="weekly" <?php echo isset($settings['backup_frequency']) && $settings['backup_frequency'] == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                        <option value="monthly" <?php echo isset($settings['backup_frequency']) && $settings['backup_frequency'] == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="backup_retention" class="form-label">Backup Retention (days)</label>
                                    <input type="number" class="form-control" id="backup_retention" name="backup_retention" value="<?php echo htmlspecialchars($settings['backup_retention'] ?? '30'); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="backup_path" class="form-label">Backup Path</label>
                                    <input type="text" class="form-control" id="backup_path" name="backup_path" value="<?php echo htmlspecialchars($settings['backup_path'] ?? 'backups/'); ?>">
                                </div>
                                <div class="d-flex justify-content-between">
                                    <button type="submit" name="update_settings" class="btn btn-primary">Save Settings</button>
                                    <button type="submit" name="backup_database" class="btn btn-success">
                                        <i class="fas fa-download me-2"></i>Backup Database Now
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <!-- Other Settings Forms -->
                            <form method="post" action="">
                                <?php foreach ($settings as $key => $value): ?>
                                <div class="mb-3">
                                    <label for="<?php echo $key; ?>" class="form-label">
                                        <?php echo ucwords(str_replace('_', ' ', $key)); ?>
                                    </label>

                                    <?php if ($key == 'allow_registration' || $key == 'maintenance_mode'): ?>
                                        <select class="form-select" id="<?php echo $key; ?>" name="<?php echo $key; ?>">
                                            <option value="1" <?php echo $value == '1' ? 'selected' : ''; ?>>Enabled</option>
                                            <option value="0" <?php echo $value == '0' ? 'selected' : ''; ?>>Disabled</option>
                                        </select>
                                    <?php elseif ($key == 'current_semester'): ?>
                                        <select class="form-select" id="<?php echo $key; ?>" name="<?php echo $key; ?>">
                                            <option value="Fall" <?php echo $value == 'Fall' ? 'selected' : ''; ?>>Fall</option>
                                            <option value="Spring" <?php echo $value == 'Spring' ? 'selected' : ''; ?>>Spring</option>
                                            <option value="Summer" <?php echo $value == 'Summer' ? 'selected' : ''; ?>>Summer</option>
                                        </select>
                                    <?php elseif ($key == 'smtp_encryption'): ?>
                                        <select class="form-select" id="<?php echo $key; ?>" name="<?php echo $key; ?>">
                                            <option value="tls" <?php echo $value == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                            <option value="ssl" <?php echo $value == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                            <option value="none" <?php echo $value == 'none' ? 'selected' : ''; ?>>None</option>
                                        </select>
                                    <?php elseif ($key == 'smtp_password'): ?>
                                        <input type="password" class="form-control" id="<?php echo $key; ?>" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars($value); ?>">
                                    <?php elseif ($key == 'grading_scale'): ?>
                                        <textarea class="form-control" id="<?php echo $key; ?>" name="<?php echo $key; ?>" rows="3"><?php echo htmlspecialchars($value); ?></textarea>
                                        <small class="form-text text-muted">Format: A:90-100,B:80-89,C:70-79,D:60-69,F:0-59</small>
                                    <?php else: ?>
                                        <input type="text" class="form-control" id="<?php echo $key; ?>" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars($value); ?>">
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>

                                <button type="submit" name="update_settings" class="btn btn-primary">Save Settings</button>
                            </form>
                        <?php endif; ?>
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
</body>
</html>
