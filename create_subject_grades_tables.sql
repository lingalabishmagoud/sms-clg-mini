-- Create subject-based grades system tables

-- Create subject_grades table (replacing course-based grades)
CREATE TABLE IF NOT EXISTS `subject_grades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `assessment_type` enum('Internal Assessment 1','Internal Assessment 2','Internal Assessment 3','Mid Semester','End Semester','Assignment','Project','Lab','Viva') NOT NULL,
  `marks_obtained` decimal(5,2) NOT NULL,
  `max_marks` decimal(5,2) NOT NULL,
  `grade_letter` varchar(2) DEFAULT NULL,
  `grade_points` decimal(3,2) DEFAULT NULL,
  `comments` text,
  `graded_by` int(11) NOT NULL,
  `graded_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `subject_id` (`subject_id`),
  KEY `graded_by` (`graded_by`),
  FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`graded_by`) REFERENCES `faculty` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create student_subject_enrollment table (to track which students are enrolled in which subjects)
CREATE TABLE IF NOT EXISTS `student_subject_enrollment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `enrollment_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('active','completed','dropped') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_subject_unique` (`student_id`,`subject_id`),
  KEY `student_id` (`student_id`),
  KEY `subject_id` (`subject_id`),
  FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create semester_results table (to store SGPA and CGPA)
CREATE TABLE IF NOT EXISTS `semester_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `year` int(1) NOT NULL,
  `semester` varchar(10) NOT NULL,
  `sgpa` decimal(4,2) DEFAULT NULL,
  `cgpa` decimal(4,2) DEFAULT NULL,
  `total_credits` int(3) DEFAULT NULL,
  `credits_earned` int(3) DEFAULT NULL,
  `status` enum('ongoing','completed') NOT NULL DEFAULT 'ongoing',
  `calculated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_semester_unique` (`student_id`,`year`,`semester`),
  KEY `student_id` (`student_id`),
  FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Auto-enroll all existing students in subjects based on their department
INSERT IGNORE INTO `student_subject_enrollment` (`student_id`, `subject_id`)
SELECT s.id, sub.id
FROM `students` s
JOIN `subjects` sub ON s.department = sub.department
WHERE s.year = 3 AND s.semester = '2nd';

-- Create initial semester results for all students
INSERT IGNORE INTO `semester_results` (`student_id`, `year`, `semester`, `total_credits`)
SELECT s.id, s.year, s.semester, 
       (SELECT SUM(credits) FROM subjects WHERE department = s.department) as total_credits
FROM `students` s
WHERE s.year = 3 AND s.semester = '2nd';

-- Add some sample grades for testing (optional)
-- You can uncomment these lines to add sample data

-- INSERT INTO `subject_grades` (`student_id`, `subject_id`, `assessment_type`, `marks_obtained`, `max_marks`, `grade_letter`, `grade_points`, `graded_by`) VALUES
-- (1, 1, 'Internal Assessment 1', 85.00, 100.00, 'A', 9.00, 1),
-- (1, 1, 'Internal Assessment 2', 78.00, 100.00, 'B', 8.00, 1),
-- (1, 2, 'Internal Assessment 1', 92.00, 100.00, 'A', 9.00, 2),
-- (2, 1, 'Internal Assessment 1', 76.00, 100.00, 'B', 8.00, 1),
-- (2, 2, 'Internal Assessment 1', 88.00, 100.00, 'A', 9.00, 2);
