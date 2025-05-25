<?php
// Connect to MySQL
$conn = new mysqli("localhost", "root", "", "student_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle Delete action
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM students WHERE id = $delete_id");
    header("Location: view.php");
    exit();
}

// Handle Search query
$search = "";
if (isset($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $sql = "SELECT * FROM students WHERE full_name LIKE '%$search%' OR email LIKE '%$search%' OR course LIKE '%$search%'";
} else {
    $sql = "SELECT * FROM students";
}

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>All Students</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #007BFF; color: white; }
        a.button {
            background-color: #007BFF; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;
            margin-right: 5px;
        }
        a.button.delete { background-color: #dc3545; }
        input[type="text"] {
            width: 300px; padding: 8px; margin-bottom: 10px; font-size: 16px;
        }
        .actions { white-space: nowrap; }
    </style>
</head>
<body>

    <h1>All Students</h1>

    <a href="index.html" class="button">+ Add New Student</a>

    <!-- Search form -->
    <form method="get" action="view.php" style="margin-top: 15px;">
        <input type="text" name="search" placeholder="Search by name, email, or course" value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="button">Search</button>
        <a href="view.php" class="button" style="background-color: gray;">Clear</a>
    </form>

    <!-- Export links -->
    <div style="margin-top: 15px;">
        <a href="export_excel.php" class="button">Export to Excel</a>
        <a href="export_pdf.php" class="button">Export to PDF</a>
    </div>

    <table>
        <tr>
            <th>ID</th>
            <th>Full Name</th>
            <th>Email</th>
            <th>Course</th>
            <th>Year</th>
            <th>Actions</th>
        </tr>

        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= htmlspecialchars($row['course']) ?></td>
                    <td><?= $row['year'] ?></td>
                    <td class="actions">
                        <a href="edit.php?id=<?= $row['id'] ?>" class="button">Edit</a>
                        <a href="view.php?delete_id=<?= $row['id'] ?>" class="button delete" onclick="return confirm('Are you sure you want to delete this student?');">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6">No students found.</td></tr>
        <?php endif; ?>
    </table>

</body>
</html>

<?php $conn->close(); ?>
