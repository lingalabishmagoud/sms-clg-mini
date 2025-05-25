<?php
// Database connection parameters
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "student_db";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    echo "Database created or already exists<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
    die("Cannot continue without database. Please check your MySQL configuration.");
}

// Select the database
$conn->select_db($dbname);

// Output all actions for debugging
echo "<div style='background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px;'>";
echo "<h3>Database Setup Progress:</h3>";
echo "<ul>";
echo "<li>Connected to MySQL server</li>";
echo "<li>Created or verified database: $dbname</li>";
echo "</ul>";
echo "</div>";

// Create students table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS students (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    student_id VARCHAR(20) UNIQUE,
    course VARCHAR(100) NOT NULL,
    program VARCHAR(100),
    batch VARCHAR(20),
    year INT(2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "<li>Created or verified students table</li>";
} else {
    echo "<li style='color: red;'>Error creating students table: " . $conn->error . "</li>";
}

// Create faculty table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS faculty (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    faculty_id VARCHAR(20) UNIQUE,
    department VARCHAR(100) NOT NULL,
    position VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "<li>Created or verified faculty table</li>";
} else {
    echo "<li style='color: red;'>Error creating faculty table: " . $conn->error . "</li>";
}

// Create courses table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS courses (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) NOT NULL UNIQUE,
    course_name VARCHAR(100) NOT NULL,
    description TEXT,
    credits INT(2) NOT NULL,
    faculty_id INT(11),
    department VARCHAR(100) NOT NULL,
    semester VARCHAR(50),
    max_students INT(11) DEFAULT 30,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (faculty_id) REFERENCES faculty(id) ON DELETE SET NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "<li>Created or verified courses table</li>";
} else {
    echo "<li style='color: red;'>Error creating courses table: " . $conn->error . "</li>";
}

// Create course_enrollment table for student-course relationships
$sql = "CREATE TABLE IF NOT EXISTS course_enrollment (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    student_id INT(11) NOT NULL,
    course_id INT(11) NOT NULL,
    enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'completed', 'dropped') DEFAULT 'active',
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY (student_id, course_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "<li>Created or verified course_enrollment table</li>";
} else {
    echo "<li style='color: red;'>Error creating course_enrollment table: " . $conn->error . "</li>";
}

// Create grades table if it doesn't exist (simplified - no quiz integration)
$sql = "CREATE TABLE IF NOT EXISTS grades (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    student_id INT(11) NOT NULL,
    course_id INT(11) NOT NULL,
    assignment_name VARCHAR(100) NOT NULL,
    grade_value DECIMAL(5,2) NOT NULL,
    max_grade DECIMAL(5,2) NOT NULL,
    grade_type ENUM('manual', 'assignment', 'exam') DEFAULT 'manual',
    comments TEXT,
    graded_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "<li>Created or verified grades table</li>";
} else {
    echo "<li style='color: red;'>Error creating grades table: " . $conn->error . "</li>";
}

// Quiz system removed - no quiz tables needed

// Drop and recreate files table to use subject_id instead of course_id
$conn->query("DROP TABLE IF EXISTS files");

// Create files table with subject_id instead of course_id
$sql = "CREATE TABLE IF NOT EXISTS files (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size INT(11) NOT NULL,
    uploaded_by_type ENUM('student', 'faculty', 'admin') NOT NULL,
    uploaded_by INT(11) NOT NULL,
    subject_id INT(11),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    description TEXT,
    visibility ENUM('public', 'private', 'faculty', 'students') DEFAULT 'private',
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "<li>Created or verified files table (updated for subjects)</li>";
} else {
    echo "<li style='color: red;'>Error creating files table: " . $conn->error . "</li>";
}

// Create notifications table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS notifications (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    created_by INT(11) NOT NULL,
    created_by_type ENUM('faculty', 'admin', 'system') DEFAULT 'faculty',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    target_type ENUM('all', 'course', 'student', 'faculty') NOT NULL,
    target_id INT(11),
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (created_by) REFERENCES faculty(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "<li>Created or verified notifications table</li>";
} else {
    echo "<li style='color: red;'>Error creating notifications table: " . $conn->error . "</li>";
}

// Create admin table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS admin (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "<li>Created or verified admin table</li>";
} else {
    echo "<li style='color: red;'>Error creating admin table: " . $conn->error . "</li>";
}

// Create enrollments table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS enrollments (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    student_id INT(11) NOT NULL,
    course_id INT(11) NOT NULL,
    enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'completed', 'dropped') DEFAULT 'active',
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY (student_id, course_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "<li>Created or verified enrollments table</li>";
} else {
    echo "<li style='color: red;'>Error creating enrollments table: " . $conn->error . "</li>";
}

// Create attendance table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS attendance (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    course_id INT(11) NOT NULL,
    student_id INT(11) NOT NULL,
    date DATE NOT NULL,
    status ENUM('present', 'absent', 'late', 'excused') NOT NULL DEFAULT 'absent',
    remarks TEXT,
    marked_by INT(11) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY (course_id, student_id, date)
)";

if ($conn->query($sql) === TRUE) {
    echo "<li>Created or verified attendance table</li>";
} else {
    echo "<li style='color: red;'>Error creating attendance table: " . $conn->error . "</li>";
}

// Create subjects table for schedule management
$sql = "CREATE TABLE IF NOT EXISTS subjects (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    subject_code VARCHAR(20) NOT NULL UNIQUE,
    subject_name VARCHAR(100) NOT NULL,
    abbreviation VARCHAR(10) NOT NULL,
    faculty_id INT(11),
    department VARCHAR(100) NOT NULL,
    credits INT(2) DEFAULT 3,
    subject_type ENUM('theory', 'lab', 'practical', 'workshop') DEFAULT 'theory',
    lab_room VARCHAR(50),
    max_students_per_lab INT(11) DEFAULT 30,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (faculty_id) REFERENCES faculty(id) ON DELETE SET NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "<li>Created or verified subjects table</li>";
} else {
    echo "<li style='color: red;'>Error creating subjects table: " . $conn->error . "</li>";
}

// Create periods table for schedule management
$sql = "CREATE TABLE IF NOT EXISTS periods (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    period_number INT(2) NOT NULL,
    period_name VARCHAR(20) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_break BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "<li>Created or verified periods table</li>";
} else {
    echo "<li style='color: red;'>Error creating periods table: " . $conn->error . "</li>";
}

// Create schedules table for timetable management
$sql = "CREATE TABLE IF NOT EXISTS schedules (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    section VARCHAR(20) NOT NULL,
    room_number VARCHAR(20) NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NOT NULL,
    period_id INT(11) NOT NULL,
    subject_id INT(11),
    faculty_id INT(11),
    lab_group VARCHAR(10),
    lab_location VARCHAR(50),
    is_lab BOOLEAN DEFAULT FALSE,
    effective_from DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (period_id) REFERENCES periods(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (faculty_id) REFERENCES faculty(id) ON DELETE SET NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "<li>Created or verified schedules table</li>";
} else {
    echo "<li style='color: red;'>Error creating schedules table: " . $conn->error . "</li>";
}

// Drop old forum_topics table if it exists and recreate with subject_id
$conn->query("DROP TABLE IF EXISTS forum_replies");
$conn->query("DROP TABLE IF EXISTS forum_topics");

// Create forum_topics table with subject_id instead of course_id
$sql = "CREATE TABLE IF NOT EXISTS forum_topics (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    subject_id INT(11) NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_by_id INT(11) NOT NULL,
    created_by_type ENUM('student', 'faculty', 'admin') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_pinned TINYINT(1) DEFAULT 0,
    is_locked TINYINT(1) DEFAULT 0,
    views INT(11) DEFAULT 0,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "<li>Created or verified forum_topics table (updated for subjects)</li>";
} else {
    echo "<li style='color: red;'>Error creating forum_topics table: " . $conn->error . "</li>";
}

// Create forum_replies table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS forum_replies (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    topic_id INT(11) NOT NULL,
    content TEXT NOT NULL,
    created_by_id INT(11) NOT NULL,
    created_by_type ENUM('student', 'faculty', 'admin') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (topic_id) REFERENCES forum_topics(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "<li>Created or verified forum_replies table</li>";
} else {
    echo "<li style='color: red;'>Error creating forum_replies table: " . $conn->error . "</li>";
}

// Create departments table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS departments (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    dept_code VARCHAR(10) NOT NULL UNIQUE,
    dept_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "<li>Created or verified departments table</li>";
} else {
    echo "<li style='color: red;'>Error creating departments table: " . $conn->error . "</li>";
}

// Create roll_numbers table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS roll_numbers (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    roll_number VARCHAR(20) NOT NULL UNIQUE,
    year_of_joining INT(4) NOT NULL,
    college_code VARCHAR(5) NOT NULL,
    dept_code VARCHAR(5) NOT NULL,
    dept_name VARCHAR(100) NOT NULL,
    student_number VARCHAR(10) NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    used_by_student_id INT(11) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at TIMESTAMP NULL,
    FOREIGN KEY (used_by_student_id) REFERENCES students(id) ON DELETE SET NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "<li>Created or verified roll_numbers table</li>";
} else {
    echo "<li style='color: red;'>Error creating roll_numbers table: " . $conn->error . "</li>";
}

// Update existing tables with missing columns
echo "<li>Checking for missing columns in existing tables...</li>";

// Check and add missing columns to students table
$columns_to_add = [
    'username' => 'VARCHAR(50) UNIQUE',
    'student_id' => 'VARCHAR(20) UNIQUE',
    'program' => 'VARCHAR(100)',
    'batch' => 'VARCHAR(20)',
    'roll_number' => 'VARCHAR(20) UNIQUE',
    'phone_number' => 'VARCHAR(15)',
    'semester' => 'VARCHAR(10)',
    'department' => 'VARCHAR(100)',
    'father_name' => 'VARCHAR(100)',
    'dob' => 'DATE',
    'blood_group' => 'VARCHAR(10)',
    'aadhaar_number' => 'VARCHAR(20)',
    'address' => 'TEXT',
    'section' => 'VARCHAR(10)'
];

// Quiz system removed - no quiz-related columns needed for grades table

foreach ($columns_to_add as $column => $definition) {
    $result = $conn->query("SHOW COLUMNS FROM students LIKE '$column'");
    if ($result->num_rows == 0) {
        $sql = "ALTER TABLE students ADD COLUMN $column $definition";
        if ($conn->query($sql) === TRUE) {
            echo "<li>Added $column column to students table</li>";
        } else {
            echo "<li style='color: red;'>Error adding $column column to students table: " . $conn->error . "</li>";
        }
    }
}

// Quiz-related grade columns removed

// Check and add missing columns to course_enrollment table
$enrollment_columns = [
    'enrollment_date' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    'status' => "ENUM('active', 'completed', 'dropped') DEFAULT 'active'"
];

foreach ($enrollment_columns as $column => $definition) {
    $result = $conn->query("SHOW COLUMNS FROM course_enrollment LIKE '$column'");
    if ($result->num_rows == 0) {
        $sql = "ALTER TABLE course_enrollment ADD COLUMN $column $definition";
        if ($conn->query($sql) === TRUE) {
            echo "<li>Added column '$column' to course_enrollment table</li>";
        } else {
            echo "<li style='color: red;'>Error adding column '$column' to course_enrollment table: " . $conn->error . "</li>";
        }
    }
}

// Check and add missing columns to courses table
$course_columns = [
    'semester' => 'VARCHAR(50)',
    'max_students' => 'INT(11) DEFAULT 30'
];

foreach ($course_columns as $column => $definition) {
    $result = $conn->query("SHOW COLUMNS FROM courses LIKE '$column'");
    if ($result->num_rows == 0) {
        $sql = "ALTER TABLE courses ADD COLUMN $column $definition";
        if ($conn->query($sql) === TRUE) {
            echo "<li>Added $column column to courses table</li>";
        } else {
            echo "<li style='color: red;'>Error adding $column column to courses table: " . $conn->error . "</li>";
        }
    }
}

// Check and add missing columns to faculty table
$faculty_columns = [
    'username' => 'VARCHAR(50) UNIQUE',
    'faculty_id' => 'VARCHAR(20) UNIQUE',
    'position' => 'VARCHAR(100)'
];

foreach ($faculty_columns as $column => $definition) {
    $result = $conn->query("SHOW COLUMNS FROM faculty LIKE '$column'");
    if ($result->num_rows == 0) {
        $sql = "ALTER TABLE faculty ADD COLUMN $column $definition";
        if ($conn->query($sql) === TRUE) {
            echo "<li>Added $column column to faculty table</li>";
        } else {
            echo "<li style='color: red;'>Error adding $column column to faculty table: " . $conn->error . "</li>";
        }
    }
}

// Check and add missing columns to files table
$file_columns = [
    'title' => 'VARCHAR(255) NOT NULL DEFAULT \'Untitled\'',
    'uploaded_by' => 'INT(11)',
    'uploaded_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    'visibility' => 'ENUM(\'public\', \'private\', \'faculty\', \'students\') DEFAULT \'private\''
];

foreach ($file_columns as $column => $definition) {
    $result = $conn->query("SHOW COLUMNS FROM files LIKE '$column'");
    if ($result->num_rows == 0) {
        $sql = "ALTER TABLE files ADD COLUMN $column $definition";
        if ($conn->query($sql) === TRUE) {
            echo "<li>Added $column column to files table</li>";
        } else {
            echo "<li style='color: red;'>Error adding $column column to files table: " . $conn->error . "</li>";
        }
    }
}

// Check and add missing columns to notifications table
$notification_columns = [
    'created_by_type' => 'ENUM(\'faculty\', \'admin\', \'system\') DEFAULT \'faculty\'',
    'read_at' => 'TIMESTAMP NULL'
];

foreach ($notification_columns as $column => $definition) {
    $result = $conn->query("SHOW COLUMNS FROM notifications LIKE '$column'");
    if ($result->num_rows == 0) {
        $sql = "ALTER TABLE notifications ADD COLUMN $column $definition";
        if ($conn->query($sql) === TRUE) {
            echo "<li>Added $column column to notifications table</li>";
        } else {
            echo "<li style='color: red;'>Error adding $column column to notifications table: " . $conn->error . "</li>";
        }
    }
}

// Migrate uploaded_by_id to uploaded_by if needed
$result = $conn->query("SHOW COLUMNS FROM files LIKE 'uploaded_by_id'");
if ($result->num_rows > 0) {
    // Check if uploaded_by column exists and is empty
    $check_result = $conn->query("SELECT COUNT(*) as count FROM files WHERE uploaded_by IS NULL OR uploaded_by = 0");
    if ($check_result) {
        $check_row = $check_result->fetch_assoc();
        if ($check_row['count'] > 0) {
            $sql = "UPDATE files SET uploaded_by = uploaded_by_id WHERE uploaded_by IS NULL OR uploaded_by = 0";
            if ($conn->query($sql) === TRUE) {
                echo "<li>Migrated uploaded_by_id data to uploaded_by column</li>";
            } else {
                echo "<li style='color: red;'>Error migrating uploaded_by_id data: " . $conn->error . "</li>";
            }
        }
    }
}

// Check if students table is empty, if so add sample data
$result = $conn->query("SELECT COUNT(*) as count FROM students");
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    // Add sample students
    $sql = "INSERT INTO students (username, full_name, email, password, student_id, course, program, batch, year) VALUES
        ('john_doe', 'John Doe', 'john@example.com', 'password123', 'STU001', 'Computer Science', 'Computer Science', '2023', 2),
        ('jane_smith', 'Jane Smith', 'jane@example.com', 'password123', 'STU002', 'Business Administration', 'Business Administration', '2022', 3),
        ('michael_johnson', 'Michael Johnson', 'michael@example.com', 'password123', 'STU003', 'Engineering', 'Engineering', '2024', 1),
        ('emily_davis', 'Emily Davis', 'emily@example.com', 'password123', 'STU004', 'Psychology', 'Psychology', '2021', 4),
        ('robert_wilson', 'Robert Wilson', 'robert@example.com', 'password123', 'STU005', 'Mathematics', 'Mathematics', '2023', 2)";

    if ($conn->query($sql) === TRUE) {
        echo "<li>Added sample students</li>";
    } else {
        echo "<li style='color: red;'>Error adding sample students: " . $conn->error . "</li>";
    }
}

// Clear existing faculty data and insert new teachers
echo "<h3>Updating Faculty Data...</h3>";
$conn->query("DELETE FROM faculty");
echo "<li>Cleared existing faculty data</li>";

// Add phone column to faculty table if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM faculty LIKE 'phone'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE faculty ADD COLUMN phone VARCHAR(15)";
    if ($conn->query($sql) === TRUE) {
        echo "<li>Added phone column to faculty table</li>";
    }
}

// Insert new faculty members based on provided information
$faculty_data = [
    ['Dr. K. Subba Rao', 'subbarao@college.edu', 'Cyber Security', 'Professor', '9986991545'],
    ['Mr. Mukesh Gilda', 'mukesh@college.edu', 'Cyber Security', 'Assistant Professor', '9177508064'],
    ['Mrs. P. Sandhya Rani', 'sandhya@college.edu', 'Cyber Security', 'Assistant Professor', '9502060155'],
    ['Mr. J. Naresh Kumar', 'naresh@college.edu', 'Cyber Security', 'Assistant Professor', '9704768449'],
    ['Mr. R. Anbarasu', 'anbarasu@college.edu', 'Cyber Security', 'Assistant Professor', '9042932195']
];

foreach ($faculty_data as $faculty) {
    $hashed_password = password_hash('password123', PASSWORD_DEFAULT);
    $username = strtolower(str_replace([' ', '.'], ['_', ''], $faculty[0]));
    $faculty_id = 'FAC' . str_pad(array_search($faculty, $faculty_data) + 1, 3, '0', STR_PAD_LEFT);

    $stmt = $conn->prepare("INSERT INTO faculty (username, full_name, email, password, faculty_id, department, position, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssss", $username, $faculty[0], $faculty[1], $hashed_password, $faculty_id, $faculty[2], $faculty[3], $faculty[4]);
    if ($stmt->execute()) {
        echo "<li>Added faculty: " . $faculty[0] . " (Phone: " . $faculty[4] . ")</li>";
    }
    $stmt->close();
}

// Insert period timings
echo "<h3>Setting up Period Timings...</h3>";
$conn->query("DELETE FROM periods");
$periods_data = [
    [1, 'Period I', '09:00:00', '10:00:00', false],
    [2, 'Period II', '10:00:00', '10:50:00', false],
    [3, 'Break', '10:50:00', '11:10:00', true],
    [4, 'Period III', '11:10:00', '11:50:00', false],
    [5, 'Period IV', '11:50:00', '12:40:00', false],
    [6, 'Lunch', '12:40:00', '13:30:00', true],
    [7, 'Period V', '13:30:00', '14:20:00', false],
    [8, 'Period VI', '14:20:00', '15:10:00', false],
    [9, 'Period VII', '15:10:00', '16:00:00', false]
];

foreach ($periods_data as $period) {
    $stmt = $conn->prepare("INSERT INTO periods (period_number, period_name, start_time, end_time, is_break) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $period[0], $period[1], $period[2], $period[3], $period[4]);
    if ($stmt->execute()) {
        echo "<li>Added period: " . $period[1] . " (" . $period[2] . " - " . $period[3] . ")</li>";
    }
    $stmt->close();
}

// Insert subjects based on provided schedule
echo "<h3>Setting up Subjects...</h3>";
$conn->query("DELETE FROM subjects");

// Get faculty IDs for subject assignment
$faculty_map = [];
$faculty_result = $conn->query("SELECT id, full_name FROM faculty");
while ($row = $faculty_result->fetch_assoc()) {
    $faculty_map[$row['full_name']] = $row['id'];
}

$subjects_data = [
    ['CSE', 'Cyber Security Essentials', 'CSE', $faculty_map['Dr. K. Subba Rao'] ?? 1, 'Cyber Security', 3],
    ['CCDF', 'Cyber Crime Investigation & Digital Forensics', 'CCDF', $faculty_map['Mr. Mukesh Gilda'] ?? 2, 'Cyber Security', 3],
    ['ADA', 'Algorithms Design and Analysis', 'ADA', $faculty_map['Mrs. P. Sandhya Rani'] ?? 3, 'Cyber Security', 4],
    ['DEVOPS', 'DevOps (Professional Elective III)', 'DEVOPS', $faculty_map['Mr. J. Naresh Kumar'] ?? 4, 'Cyber Security', 3],
    ['FIOT', 'FIOT (Open Elective I)', 'FIOT', $faculty_map['Mr. R. Anbarasu'] ?? 5, 'Cyber Security', 3],
    ['ES', 'Environmental Science', 'ES', $faculty_map['Mr. R. Anbarasu'] ?? 5, 'Cyber Security', 2],
    ['IOMP', 'Industrial Oriented Mini Project', 'IOMP', $faculty_map['Mrs. P. Sandhya Rani'] ?? 3, 'Cyber Security', 2],
    ['LIBRARY', 'Library', 'LIB', null, 'General', 0],
    ['SPORTS', 'Sports', 'SPORTS', null, 'General', 0]
];

foreach ($subjects_data as $subject) {
    $stmt = $conn->prepare("INSERT INTO subjects (subject_code, subject_name, abbreviation, faculty_id, department, credits) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssisi", $subject[0], $subject[1], $subject[2], $subject[3], $subject[4], $subject[5]);
    if ($stmt->execute()) {
        echo "<li>Added subject: " . $subject[1] . " (" . $subject[2] . ")</li>";
    }
    $stmt->close();
}

// Insert schedule data for CS-A section
echo "<h3>Setting up Class Schedule for CS-A...</h3>";
$conn->query("DELETE FROM schedules");

// Get subject and period IDs
$subject_map = [];
$subject_result = $conn->query("SELECT id, abbreviation FROM subjects");
while ($row = $subject_result->fetch_assoc()) {
    $subject_map[$row['abbreviation']] = $row['id'];
}

$period_map = [];
$period_result = $conn->query("SELECT id, period_number FROM periods WHERE is_break = FALSE");
while ($row = $period_result->fetch_assoc()) {
    $period_map[$row['period_number']] = $row['id'];
}

// Schedule data based on provided timetable
$schedule_data = [
    // Monday
    ['CS-A', '307', 'Monday', $period_map[1], $subject_map['DEVOPS'], $faculty_map['Mr. J. Naresh Kumar'], null, null, false],
    ['CS-A', '307', 'Monday', $period_map[2], $subject_map['DEVOPS'], $faculty_map['Mr. J. Naresh Kumar'], null, null, false],
    ['CS-A', '307', 'Monday', $period_map[4], $subject_map['CSE'], $faculty_map['Dr. K. Subba Rao'], null, null, false],
    ['CS-A', '307', 'Monday', $period_map[5], $subject_map['CSE'], $faculty_map['Dr. K. Subba Rao'], null, null, false],
    ['CS-A', '307', 'Monday', $period_map[7], $subject_map['ADA'], $faculty_map['Mrs. P. Sandhya Rani'], null, null, false],
    ['CS-A', '307', 'Monday', $period_map[8], $subject_map['ES'], $faculty_map['Mr. R. Anbarasu'], null, null, false],
    ['CS-A', '307', 'Monday', $period_map[9], $subject_map['LIB'], null, null, null, false],

    // Tuesday
    ['CS-A', '307', 'Tuesday', $period_map[1], $subject_map['FIOT'], $faculty_map['Mr. R. Anbarasu'], null, null, false],
    ['CS-A', '307', 'Tuesday', $period_map[2], $subject_map['FIOT'], $faculty_map['Mr. R. Anbarasu'], null, null, false],
    ['CS-A', '307', 'Tuesday', $period_map[4], $subject_map['CCDF'], $faculty_map['Mr. Mukesh Gilda'], null, null, false],
    ['CS-A', '307', 'Tuesday', $period_map[5], $subject_map['CCDF'], $faculty_map['Mr. Mukesh Gilda'], null, null, false],
    ['CS-A', '307', 'Tuesday', $period_map[7], $subject_map['ADA'], $faculty_map['Mrs. P. Sandhya Rani'], null, null, false],
    ['CS-A', '307', 'Tuesday', $period_map[8], $subject_map['SPORTS'], null, null, null, false],

    // Wednesday
    ['CS-A', '307', 'Wednesday', $period_map[1], $subject_map['CCDF'], $faculty_map['Mr. Mukesh Gilda'], 'B1', 'B1', true],
    ['CS-A', '307', 'Wednesday', $period_map[2], $subject_map['CSE'], $faculty_map['Mr. R. Anbarasu'], 'B2', 'B2', true],
    ['CS-A', '307', 'Wednesday', $period_map[4], $subject_map['IOMP'], $faculty_map['Mrs. P. Sandhya Rani'], null, null, false],
    ['CS-A', '307', 'Wednesday', $period_map[7], $subject_map['CCDF'], $faculty_map['Mr. Mukesh Gilda'], null, null, false],
    ['CS-A', '307', 'Wednesday', $period_map[8], $subject_map['FIOT'], $faculty_map['Mr. R. Anbarasu'], null, null, false],
    ['CS-A', '307', 'Wednesday', $period_map[9], $subject_map['DEVOPS'], $faculty_map['Mr. J. Naresh Kumar'], null, null, false],

    // Thursday
    ['CS-A', '307', 'Thursday', $period_map[1], $subject_map['CSE'], $faculty_map['Mr. R. Anbarasu'], 'B1', 'B1', true],
    ['CS-A', '307', 'Thursday', $period_map[2], $subject_map['CCDF'], $faculty_map['Mr. Mukesh Gilda'], 'B2', 'B2', true],
    ['CS-A', '307', 'Thursday', $period_map[4], $subject_map['CCDF'], $faculty_map['Mr. Mukesh Gilda'], null, null, false],
    ['CS-A', '307', 'Thursday', $period_map[5], $subject_map['DEVOPS'], $faculty_map['Mr. J. Naresh Kumar'], null, null, false],
    ['CS-A', '307', 'Thursday', $period_map[7], $subject_map['FIOT'], $faculty_map['Mr. R. Anbarasu'], null, null, false],
    ['CS-A', '307', 'Thursday', $period_map[8], $subject_map['DEVOPS'], $faculty_map['Mr. J. Naresh Kumar'], null, null, false],
    ['CS-A', '307', 'Thursday', $period_map[9], $subject_map['DEVOPS'], $faculty_map['Mr. J. Naresh Kumar'], null, null, false],

    // Friday
    ['CS-A', '307', 'Friday', $period_map[1], $subject_map['ES'], $faculty_map['Mr. R. Anbarasu'], null, null, false],
    ['CS-A', '307', 'Friday', $period_map[2], $subject_map['ADA'], $faculty_map['Mrs. P. Sandhya Rani'], null, null, false],
    ['CS-A', '307', 'Friday', $period_map[4], $subject_map['CSE'], $faculty_map['Dr. K. Subba Rao'], null, null, false],
    ['CS-A', '307', 'Friday', $period_map[5], $subject_map['FIOT'], $faculty_map['Mr. R. Anbarasu'], null, null, false],
    ['CS-A', '307', 'Friday', $period_map[7], $subject_map['DEVOPS'], $faculty_map['Mr. J. Naresh Kumar'], null, null, false],
    ['CS-A', '307', 'Friday', $period_map[8], $subject_map['DEVOPS'], $faculty_map['Mr. J. Naresh Kumar'], null, null, false],

    // Saturday
    ['CS-A', '307', 'Saturday', $period_map[1], $subject_map['ADA'], $faculty_map['Mrs. P. Sandhya Rani'], null, null, false],
    ['CS-A', '307', 'Saturday', $period_map[2], $subject_map['ADA'], $faculty_map['Mrs. P. Sandhya Rani'], null, null, false],
    ['CS-A', '307', 'Saturday', $period_map[4], $subject_map['CSE'], $faculty_map['Dr. K. Subba Rao'], null, null, false],
    ['CS-A', '307', 'Saturday', $period_map[5], $subject_map['FIOT'], $faculty_map['Mr. R. Anbarasu'], null, null, false],
    ['CS-A', '307', 'Saturday', $period_map[7], $subject_map['DEVOPS'], $faculty_map['Mr. J. Naresh Kumar'], null, null, false],
    ['CS-A', '307', 'Saturday', $period_map[8], $subject_map['ADA'], $faculty_map['Mrs. P. Sandhya Rani'], null, null, false],
    ['CS-A', '307', 'Saturday', $period_map[9], $subject_map['ES'], $faculty_map['Mr. R. Anbarasu'], null, null, false]
];

$effective_date = '2025-01-27';
foreach ($schedule_data as $schedule) {
    $stmt = $conn->prepare("INSERT INTO schedules (section, room_number, day_of_week, period_id, subject_id, faculty_id, lab_group, lab_location, is_lab, effective_from) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssiiiisss", $schedule[0], $schedule[1], $schedule[2], $schedule[3], $schedule[4], $schedule[5], $schedule[6], $schedule[7], $schedule[8], $effective_date);
    if ($stmt->execute()) {
        $subject_name = array_search($schedule[4], $subject_map) ?: 'Unknown';
        echo "<li>Added schedule: " . $schedule[2] . " Period " . array_search($schedule[3], $period_map) . " - " . $subject_name . "</li>";
    }
    $stmt->close();
}

// Check if courses table is empty, if so add sample data
$result = $conn->query("SELECT COUNT(*) as count FROM courses");
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    // Get faculty IDs
    $faculty_result = $conn->query("SELECT id, department FROM faculty LIMIT 3");
    $faculty_ids = [];
    $departments = [];

    if ($faculty_result->num_rows > 0) {
        while ($faculty_row = $faculty_result->fetch_assoc()) {
            $faculty_ids[] = $faculty_row['id'];
            $departments[$faculty_row['id']] = $faculty_row['department'];
        }
    } else {
        // Default values if no faculty found
        $faculty_ids = [1, 1, 1];
        $departments = [1 => 'Computer Science'];
    }

    // Add sample courses
    $sql = "INSERT INTO courses (course_code, course_name, description, credits, faculty_id, department, semester, max_students) VALUES
        ('CS101', 'Introduction to Programming', 'Basic programming concepts using Python', 3, {$faculty_ids[0]}, '{$departments[$faculty_ids[0]]}', 'Fall 2023', 30),
        ('CS202', 'Data Structures', 'Advanced data structures and algorithms', 4, {$faculty_ids[0]}, '{$departments[$faculty_ids[0]]}', 'Spring 2024', 25),
        ('BUS101', 'Introduction to Business', 'Fundamentals of business management', 3, {$faculty_ids[1]}, '{$departments[$faculty_ids[1]]}', 'Fall 2023', 40),
        ('ENG101', 'Engineering Principles', 'Basic engineering concepts and applications', 4, {$faculty_ids[2]}, '{$departments[$faculty_ids[2]]}', 'Spring 2024', 20),
        ('MATH101', 'Calculus I', 'Introduction to differential and integral calculus', 3, {$faculty_ids[0]}, '{$departments[$faculty_ids[0]]}', 'Fall 2023', 35)";

    if ($conn->query($sql) === TRUE) {
        echo "<li>Added sample courses</li>";
    } else {
        echo "<li style='color: red;'>Error adding sample courses: " . $conn->error . "</li>";
    }

    // Add sample course enrollments
    $course_result = $conn->query("SELECT id FROM courses LIMIT 5");
    $course_ids = [];

    if ($course_result->num_rows > 0) {
        while ($course_row = $course_result->fetch_assoc()) {
            $course_ids[] = $course_row['id'];
        }
    }

    $student_result = $conn->query("SELECT id FROM students LIMIT 5");
    $student_ids = [];

    if ($student_result->num_rows > 0) {
        while ($student_row = $student_result->fetch_assoc()) {
            $student_ids[] = $student_row['id'];
        }
    }

    if (!empty($course_ids) && !empty($student_ids)) {
        $enrollment_values = [];

        // Enroll each student in 2-3 courses
        foreach ($student_ids as $student_id) {
            $num_courses = rand(2, 3);
            $selected_courses = array_rand(array_flip($course_ids), $num_courses);

            if (!is_array($selected_courses)) {
                $selected_courses = [$selected_courses];
            }

            foreach ($selected_courses as $course_id) {
                $enrollment_values[] = "($student_id, $course_id)";
            }
        }

        if (!empty($enrollment_values)) {
            $sql = "INSERT INTO enrollments (student_id, course_id) VALUES " . implode(", ", $enrollment_values);

            if ($conn->query($sql) === TRUE) {
                echo "<li>Added sample course enrollments</li>";
            } else {
                echo "<li style='color: red;'>Error adding sample course enrollments: " . $conn->error . "</li>";
            }
        }
    }
}

// Check if admin table is empty, if so add a default admin
$result = $conn->query("SELECT COUNT(*) as count FROM admin");
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    // Add default admin
    $sql = "INSERT INTO admin (username, password, email, full_name) VALUES
        ('admin', 'admin123', 'admin@example.com', 'System Administrator')";

    if ($conn->query($sql) === TRUE) {
        echo "<li>Added default admin (username: admin, password: admin123)</li>";
    } else {
        echo "<li style='color: red;'>Error adding default admin: " . $conn->error . "</li>";
    }
}

// Check if departments table is empty, if so add sample data
$result = $conn->query("SELECT COUNT(*) as count FROM departments");
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    // Add sample departments (old format)
    $sql = "INSERT INTO departments (dept_code, dept_name, description) VALUES
        ('CS', 'Computer Science', 'Department of Computer Science and Information Technology'),
        ('ENG', 'Engineering', 'Department of Engineering and Applied Sciences'),
        ('BUS', 'Business', 'Department of Business Administration and Management'),
        ('MATH', 'Mathematics', 'Department of Mathematics and Statistics'),
        ('PHYS', 'Physics', 'Department of Physics and Applied Physics'),
        ('CHEM', 'Chemistry', 'Department of Chemistry and Chemical Engineering')";

    if ($conn->query($sql) === TRUE) {
        echo "<li>Added sample departments</li>";
    } else {
        echo "<li style='color: red;'>Error adding sample departments: " . $conn->error . "</li>";
    }
}

// Always ensure the correct roll number format departments exist
$required_departments = [
    ['A62', 'Cyber Security', 'Department of Cyber Security and Information Assurance'],
    ['A05', 'CSE', 'Department of Computer Science and Engineering'],
    ['A67', 'Data Science', 'Department of Data Science and Analytics']
];

foreach ($required_departments as $dept) {
    $check = $conn->query("SELECT id FROM departments WHERE dept_code = '{$dept[0]}'");
    if ($check->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO departments (dept_code, dept_name, description) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $dept[0], $dept[1], $dept[2]);
        if ($stmt->execute()) {
            echo "<li>Added required department: {$dept[0]} - {$dept[1]}</li>";
        } else {
            echo "<li style='color: red;'>Error adding required department {$dept[0]}: " . $stmt->error . "</li>";
        }
        $stmt->close();
    }
}

// Check if roll_numbers table is empty, if so add sample data
$result = $conn->query("SELECT COUNT(*) as count FROM roll_numbers");
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    // Add sample roll numbers for testing
    $roll_numbers_data = [
        // 2022 batch - Cyber Security (A62)
        ['22N81A6201', 2022, 'N81', 'A62', 'Cyber Security', '01'],
        ['22N81A6202', 2022, 'N81', 'A62', 'Cyber Security', '02'],
        ['22N81A6203', 2022, 'N81', 'A62', 'Cyber Security', '03'],
        ['22N81A6254', 2022, 'N81', 'A62', 'Cyber Security', '54'], // Your example

        // 2022 batch - CSE (A05)
        ['22N81A0501', 2022, 'N81', 'A05', 'CSE', '01'],
        ['22N81A0502', 2022, 'N81', 'A05', 'CSE', '02'],
        ['22N81A0503', 2022, 'N81', 'A05', 'CSE', '03'],

        // 2022 batch - Data Science (A67)
        ['22N81A6701', 2022, 'N81', 'A67', 'Data Science', '01'],
        ['22N81A6702', 2022, 'N81', 'A67', 'Data Science', '02'],
        ['22N81A6703', 2022, 'N81', 'A67', 'Data Science', '03'],

        // 2023 batch - Cyber Security (A62)
        ['23N81A6201', 2023, 'N81', 'A62', 'Cyber Security', '01'],
        ['23N81A6202', 2023, 'N81', 'A62', 'Cyber Security', '02'],

        // 2023 batch - CSE (A05)
        ['23N81A0501', 2023, 'N81', 'A05', 'CSE', '01'],
        ['23N81A0502', 2023, 'N81', 'A05', 'CSE', '02'],

        // 2023 batch - Data Science (A67)
        ['23N81A6701', 2023, 'N81', 'A67', 'Data Science', '01'],
        ['23N81A6702', 2023, 'N81', 'A67', 'Data Science', '02']
    ];

    $values = [];
    foreach ($roll_numbers_data as $data) {
        $values[] = "('{$data[0]}', {$data[1]}, '{$data[2]}', '{$data[3]}', '{$data[4]}', '{$data[5]}')";
    }

    $sql = "INSERT INTO roll_numbers (roll_number, year_of_joining, college_code, dept_code, dept_name, student_number) VALUES " . implode(", ", $values);

    if ($conn->query($sql) === TRUE) {
        echo "<li>Added sample roll numbers for testing</li>";
    } else {
        echo "<li style='color: red;'>Error adding sample roll numbers: " . $conn->error . "</li>";
    }
}

// Check if password column exists in students table
$result = $conn->query("SHOW COLUMNS FROM students LIKE 'password'");
if ($result->num_rows == 0) {
    // Add password column to students table
    $sql = "ALTER TABLE students ADD COLUMN password VARCHAR(255) NOT NULL DEFAULT 'password123'";

    if ($conn->query($sql) === TRUE) {
        echo "<li>Added password column to students table</li>";
    } else {
        echo "<li style='color: red;'>Error adding password column: " . $conn->error . "</li>";
    }
}

echo "</ul>";
echo "</div>";

echo "<div style='background-color: #d4edda; color: #155724; padding: 20px; border-radius: 5px; margin-bottom: 20px;'>";
echo "<h3>Setup Complete!</h3>";
echo "<p>The database has been successfully set up with all required tables and sample data.</p>";
echo "<p>You can now use the system with the following credentials:</p>";
echo "<ul>";
echo "<li><strong>Student Login:</strong> Email: john@example.com, Password: password123</li>";
echo "<li><strong>Faculty Login:</strong> Email: james@example.com, Password: password123</li>";
echo "<li><strong>Admin Login:</strong> Username: admin, Password: admin123</li>";
echo "</ul>";
echo "</div>";

echo "<div class='text-center'>";
echo "<a href='index.html' style='display: inline-block; margin-top: 20px; padding: 10px 20px; background-color: #007BFF; color: white; text-decoration: none; border-radius: 5px;'>Go to Homepage</a>";
echo "</div>";

$conn->close();
?>
