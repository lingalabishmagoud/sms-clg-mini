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

// Get discussion statistics
$stats = [];

// Total discussions across all classrooms
$result = $conn->query("SELECT COUNT(*) as total_topics FROM classroom_discussions");
$stats['total_topics'] = $result->fetch_assoc()['total_topics'];

// Total replies
$result = $conn->query("SELECT COUNT(*) as total_replies FROM classroom_discussion_replies");
$stats['total_replies'] = $result->fetch_assoc()['total_replies'];

// Active classrooms (with discussions)
$result = $conn->query("
    SELECT COUNT(DISTINCT classroom_id) as active_classrooms
    FROM classroom_discussions
");
$stats['active_classrooms'] = $result->fetch_assoc()['active_classrooms'];

// Recent activity (last 7 days)
$result = $conn->query("
    SELECT COUNT(*) as recent_activity
    FROM classroom_discussions
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$stats['recent_activity'] = $result->fetch_assoc()['recent_activity'];

// Get classroom discussion overview
$classroom_stats = [];
$result = $conn->query("
    SELECT
        c.id,
        c.classroom_name,
        c.year,
        c.semester,
        c.department,
        f.full_name as incharge_name,
        COUNT(DISTINCT cd.id) as topic_count,
        COUNT(DISTINCT cdr.id) as reply_count,
        MAX(cd.created_at) as last_activity
    FROM classrooms c
    LEFT JOIN faculty f ON c.class_incharge_id = f.id
    LEFT JOIN classroom_discussions cd ON c.id = cd.classroom_id
    LEFT JOIN classroom_discussion_replies cdr ON cd.id = cdr.topic_id
    GROUP BY c.id, c.classroom_name, c.year, c.semester, c.department, f.full_name
    ORDER BY topic_count DESC, last_activity DESC
");

while ($row = $result->fetch_assoc()) {
    $classroom_stats[] = $row;
}

// Get recent discussions across all classrooms
$recent_discussions = [];
$result = $conn->query("
    SELECT
        cd.*,
        c.classroom_name,
        c.year,
        c.semester,
        CASE
            WHEN cd.created_by_type = 'student' THEN s.full_name
            WHEN cd.created_by_type = 'faculty' THEN f.full_name
            WHEN cd.created_by_type = 'admin' THEN 'Administrator'
            ELSE 'Unknown'
        END as author_name,
        (SELECT COUNT(*) FROM classroom_discussion_replies WHERE topic_id = cd.id) as reply_count
    FROM classroom_discussions cd
    LEFT JOIN classrooms c ON cd.classroom_id = c.id
    LEFT JOIN students s ON cd.created_by_id = s.id AND cd.created_by_type = 'student'
    LEFT JOIN faculty f ON cd.created_by_id = f.id AND cd.created_by_type = 'faculty'
    ORDER BY cd.created_at DESC
    LIMIT 10
");

while ($row = $result->fetch_assoc()) {
    $recent_discussions[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Discussions - Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .classroom-card {
            transition: transform 0.2s;
        }
        .classroom-card:hover {
            transform: translateY(-2px);
        }
        .activity-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }
        .activity-high { background-color: #28a745; }
        .activity-medium { background-color: #ffc107; }
        .activity-low { background-color: #dc3545; }
        .activity-none { background-color: #6c757d; }
    </style>
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
                        <a class="nav-link" href="admin_subjects.php">Subjects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_grades.php">Grades</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_departments.php">Departments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_classrooms.php">Classrooms</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_schedule.php">Schedules</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="admin_discussions.php">Discussions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_reports.php">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_settings.php">Settings</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        Welcome, <?php echo htmlspecialchars($admin_name); ?>
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
                <h2><i class="fas fa-comments me-2"></i>Discussion Monitoring Dashboard</h2>
                <p class="text-muted">Monitor and oversee classroom discussions between students and faculty</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="classroom_discussions.php?user_type=admin" class="btn btn-primary">
                    <i class="fas fa-eye me-2"></i>View All Discussions
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?php echo $stats['total_topics']; ?></h4>
                                <p class="mb-0">Total Topics</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-comments fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?php echo $stats['total_replies']; ?></h4>
                                <p class="mb-0">Total Replies</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-reply fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?php echo $stats['active_classrooms']; ?></h4>
                                <p class="mb-0">Active Classrooms</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-school fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?php echo $stats['recent_activity']; ?></h4>
                                <p class="mb-0">Recent Activity</p>
                                <small>(Last 7 days)</small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Classroom Overview -->
        <div class="row">
            <div class="col-md-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-school me-2"></i>Classroom Discussion Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Classroom</th>
                                        <th>Year/Sem</th>
                                        <th>Incharge</th>
                                        <th>Activity</th>
                                        <th>Topics</th>
                                        <th>Replies</th>
                                        <th>Last Activity</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($classroom_stats as $classroom): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($classroom['classroom_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($classroom['department']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $classroom['year']; ?>Y</span>
                                            <span class="badge bg-secondary"><?php echo $classroom['semester']; ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($classroom['incharge_name'] ?: 'Not Assigned'); ?></td>
                                        <td>
                                            <?php
                                            $activity_level = 'none';
                                            if ($classroom['topic_count'] > 10) $activity_level = 'high';
                                            elseif ($classroom['topic_count'] > 5) $activity_level = 'medium';
                                            elseif ($classroom['topic_count'] > 0) $activity_level = 'low';
                                            ?>
                                            <span class="activity-indicator activity-<?php echo $activity_level; ?>"></span>
                                            <?php echo ucfirst($activity_level); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $classroom['topic_count']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $classroom['reply_count']; ?></span>
                                        </td>
                                        <td>
                                            <?php if ($classroom['last_activity']): ?>
                                                <small><?php echo date('M j, Y', strtotime($classroom['last_activity'])); ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">No activity</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="classroom_discussions.php?classroom_id=<?php echo $classroom['id']; ?>&user_type=admin"
                                               class="btn btn-sm btn-outline-primary" title="Monitor Discussions">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Discussions -->
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Recent Discussions</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($recent_discussions) > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_discussions as $discussion): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">
                                                <a href="classroom_discussions.php?classroom_id=<?php echo $discussion['classroom_id']; ?>&action=topic&topic_id=<?php echo $discussion['id']; ?>&user_type=admin"
                                                   class="text-decoration-none">
                                                    <?php echo htmlspecialchars(substr($discussion['title'], 0, 40)); ?><?php echo strlen($discussion['title']) > 40 ? '...' : ''; ?>
                                                </a>
                                            </h6>
                                            <small><?php echo date('M j', strtotime($discussion['created_at'])); ?></small>
                                        </div>
                                        <p class="mb-1">
                                            <small class="text-muted">
                                                in <strong><?php echo htmlspecialchars($discussion['classroom_name']); ?></strong>
                                                by <?php echo htmlspecialchars($discussion['author_name']); ?>
                                                <span class="badge badge-sm bg-<?php echo $discussion['created_by_type'] == 'faculty' ? 'success' : 'info'; ?>">
                                                    <?php echo ucfirst($discussion['created_by_type']); ?>
                                                </span>
                                            </small>
                                        </p>
                                        <small>
                                            <i class="fas fa-comments me-1"></i><?php echo $discussion['reply_count']; ?> replies
                                            <i class="fas fa-eye ms-2 me-1"></i><?php echo $discussion['views']; ?> views
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-comments fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">No discussions yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-tools me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <a href="admin_classrooms.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-plus me-2"></i>Manage Classrooms
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="classroom_discussions.php?user_type=admin" class="btn btn-outline-success w-100">
                                    <i class="fas fa-eye me-2"></i>View All Discussions
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="admin_students.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-users me-2"></i>Manage Students
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="admin_faculty.php" class="btn btn-outline-warning w-100">
                                    <i class="fas fa-chalkboard-teacher me-2"></i>Manage Faculty
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>