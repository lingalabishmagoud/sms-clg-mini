-- Create tables for classroom management and discussions

-- Create classrooms table
CREATE TABLE IF NOT EXISTS `classrooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `classroom_name` varchar(50) NOT NULL,
  `year` int(1) NOT NULL,
  `semester` varchar(10) NOT NULL,
  `department` varchar(100) NOT NULL,
  `class_incharge_id` int(11) DEFAULT NULL,
  `room_number` varchar(20) NOT NULL,
  `capacity` int(3) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `classroom_name` (`classroom_name`),
  KEY `class_incharge_id` (`class_incharge_id`),
  FOREIGN KEY (`class_incharge_id`) REFERENCES `faculty` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create classroom_discussions table
CREATE TABLE IF NOT EXISTS `classroom_discussions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `classroom_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `created_by_id` int(11) NOT NULL,
  `created_by_type` enum('student','faculty','admin') NOT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `views` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `classroom_id` (`classroom_id`),
  KEY `created_by_id` (`created_by_id`),
  KEY `created_by_type` (`created_by_type`),
  FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create classroom_discussion_replies table
CREATE TABLE IF NOT EXISTS `classroom_discussion_replies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `topic_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_by_id` int(11) NOT NULL,
  `created_by_type` enum('student','faculty','admin') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `topic_id` (`topic_id`),
  KEY `created_by_id` (`created_by_id`),
  KEY `created_by_type` (`created_by_type`),
  FOREIGN KEY (`topic_id`) REFERENCES `classroom_discussions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default classrooms (CS-A and CS-B for 3rd year, 2nd semester)
INSERT INTO `classrooms` (`classroom_name`, `year`, `semester`, `department`, `room_number`, `capacity`) VALUES
('CS-A', 3, '2nd', 'Cyber Security', '303', 60),
('CS-B', 3, '2nd', 'Cyber Security', '307', 60);

-- Update existing students to match classroom sections
UPDATE `students` SET `section` = 'CS-A' WHERE `section` = 'CS-A' OR `roll_number` LIKE '%001' OR `roll_number` LIKE '%002' OR `roll_number` LIKE '%003' OR `roll_number` LIKE '%004' OR `roll_number` LIKE '%005' OR `roll_number` LIKE '%006' OR `roll_number` LIKE '%007' OR `roll_number` LIKE '%008' OR `roll_number` LIKE '%009' OR `roll_number` LIKE '%010' OR `roll_number` LIKE '%011' OR `roll_number` LIKE '%012' OR `roll_number` LIKE '%013' OR `roll_number` LIKE '%014' OR `roll_number` LIKE '%015' OR `roll_number` LIKE '%016' OR `roll_number` LIKE '%017' OR `roll_number` LIKE '%018' OR `roll_number` LIKE '%019' OR `roll_number` LIKE '%020' OR `roll_number` LIKE '%021' OR `roll_number` LIKE '%022' OR `roll_number` LIKE '%023' OR `roll_number` LIKE '%024' OR `roll_number` LIKE '%025' OR `roll_number` LIKE '%026' OR `roll_number` LIKE '%027' OR `roll_number` LIKE '%028' OR `roll_number` LIKE '%029' OR `roll_number` LIKE '%030';

UPDATE `students` SET `section` = 'CS-B' WHERE `section` = 'CS-B' OR `roll_number` LIKE '%031' OR `roll_number` LIKE '%032' OR `roll_number` LIKE '%033' OR `roll_number` LIKE '%034' OR `roll_number` LIKE '%035' OR `roll_number` LIKE '%036' OR `roll_number` LIKE '%037' OR `roll_number` LIKE '%038' OR `roll_number` LIKE '%039' OR `roll_number` LIKE '%040' OR `roll_number` LIKE '%041' OR `roll_number` LIKE '%042' OR `roll_number` LIKE '%043' OR `roll_number` LIKE '%044' OR `roll_number` LIKE '%045' OR `roll_number` LIKE '%046' OR `roll_number` LIKE '%047' OR `roll_number` LIKE '%048' OR `roll_number` LIKE '%049' OR `roll_number` LIKE '%050' OR `roll_number` LIKE '%051' OR `roll_number` LIKE '%052' OR `roll_number` LIKE '%053' OR `roll_number` LIKE '%054' OR `roll_number` LIKE '%055' OR `roll_number` LIKE '%056' OR `roll_number` LIKE '%057' OR `roll_number` LIKE '%058' OR `roll_number` LIKE '%059' OR `roll_number` LIKE '%060';

-- Fix student semester display (change from 1st to 2nd semester)
UPDATE `students` SET `semester` = '2nd' WHERE `year` = 3;
