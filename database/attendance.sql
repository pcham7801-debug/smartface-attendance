-- SmartFace Attendance Monitoring System Database Schema
-- SQL Import File for phpMyAdmin / MySQL

CREATE DATABASE IF NOT EXISTS `smartface_attendance` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `smartface_attendance`;

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------

-- Table structure for table `users`
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` VARCHAR(50) DEFAULT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `middle_name` VARCHAR(100) DEFAULT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'student') NOT NULL DEFAULT 'student',
  `course` VARCHAR(100) DEFAULT NULL,
  `year_level` VARCHAR(20) DEFAULT NULL,
  `section` VARCHAR(50) DEFAULT NULL,
  `contact_number` VARCHAR(30) DEFAULT NULL,
  `profile_picture` VARCHAR(255) DEFAULT 'default_avatar.png',
  `face_registered` TINYINT(1) DEFAULT 0,
  `must_change_password` TINYINT(1) DEFAULT 0,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- Table structure for table `admins`
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `username` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- Table structure for table `students`
DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `student_id` VARCHAR(50) NOT NULL UNIQUE,
  `course` VARCHAR(100) NOT NULL,
  `year_level` VARCHAR(20) NOT NULL,
  `section` VARCHAR(50) NOT NULL,
  `face_registered` TINYINT(1) DEFAULT 0,
  `face_data` LONGTEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- Table structure for table `subjects`
DROP TABLE IF EXISTS `subjects`;
CREATE TABLE `subjects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subject_code` VARCHAR(30) NOT NULL UNIQUE,
  `subject_name` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `instructor` VARCHAR(150) NOT NULL,
  `room` VARCHAR(50) NOT NULL,
  `day` VARCHAR(50) NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `semester` VARCHAR(20) NOT NULL DEFAULT '1st Semester',
  `school_year` VARCHAR(30) NOT NULL DEFAULT '2026-2027',
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- Table structure for table `student_subjects`
DROP TABLE IF EXISTS `student_subjects`;
CREATE TABLE `student_subjects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `enrollment_status` ENUM('enrolled', 'dropped') DEFAULT 'enrolled',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_student_subject` (`student_id`, `subject_id`),
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- Table structure for table `attendance`
DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `attendance_date` DATE NOT NULL,
  `time_in` TIME NOT NULL,
  `time_out` TIME DEFAULT NULL,
  `status` ENUM('Present', 'Late', 'Absent', 'Excused', 'Incomplete') NOT NULL DEFAULT 'Present',
  `verification_method` VARCHAR(50) DEFAULT 'Face Recognition',
  `remarks` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_student_subject_date` (`student_id`, `subject_id`, `attendance_date`),
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- Table structure for table `face_records`
DROP TABLE IF EXISTS `face_records`;
CREATE TABLE `face_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL UNIQUE,
  `face_encoding` LONGTEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- Table structure for table `audit_logs`
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `description` TEXT NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- Table structure for table `settings`
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- Default Settings Data
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('school_name', 'SmartFace Institute of Technology'),
('grace_period_minutes', '15'),
('school_year', '2026-2027'),
('semester', '1st Semester');

-- --------------------------------------------------------

-- Default Administrator Account
-- Password for admin: admin123
INSERT INTO `users` (`id`, `student_id`, `first_name`, `middle_name`, `last_name`, `email`, `username`, `password`, `role`, `must_change_password`, `status`) VALUES
(1, NULL, 'System', 'Super', 'Administrator', 'admin@smartface.edu', 'admin', '$2y$12$jnnofBJLEib3p1vtOacTX.9hCohsMyYwxpwp5mqKudV0cEK.x4aea', 'admin', 1, 'active');

INSERT INTO `admins` (`id`, `user_id`, `name`, `email`, `username`, `password`, `status`) VALUES
(1, 1, 'System Administrator', 'admin@smartface.edu', 'admin', '$2y$12$jnnofBJLEib3p1vtOacTX.9hCohsMyYwxpwp5mqKudV0cEK.x4aea', 'active');

-- --------------------------------------------------------

-- Sample Student Account
-- Password for student: student123
INSERT INTO `users` (`id`, `student_id`, `first_name`, `middle_name`, `last_name`, `email`, `username`, `password`, `role`, `course`, `year_level`, `section`, `contact_number`, `profile_picture`, `face_registered`, `status`) VALUES
(2, '2026-0001', 'Juan', 'Santos', 'Dela Cruz', 'juan@student.edu', 'student', '$2y$12$I3DD2DcWgvhq3EcPcaZpgOfPMgJMvEL7DSps0UbPEFFT3f3LwPNca', 'student', 'BSIT', '3rd Year', 'Section A', '09123456789', 'default_avatar.png', 0, 'active'),
(3, '2026-0002', 'Maria', 'Clara', 'De Los Santos', 'maria@student.edu', 'maria', '$2y$12$I3DD2DcWgvhq3EcPcaZpgOfPMgJMvEL7DSps0UbPEFFT3f3LwPNca', 'student', 'BSCS', '2nd Year', 'Section B', '09187654321', 'default_avatar.png', 0, 'active');

INSERT INTO `students` (`id`, `user_id`, `student_id`, `course`, `year_level`, `section`, `face_registered`) VALUES
(1, 2, '2026-0001', 'BSIT', '3rd Year', 'Section A', 0),
(2, 3, '2026-0002', 'BSCS', '2nd Year', 'Section B', 0);

-- --------------------------------------------------------

-- Sample Subjects Data
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `description`, `instructor`, `room`, `day`, `start_time`, `end_time`, `semester`, `school_year`, `status`) VALUES
(1, 'IT101', 'Introduction to Information Technology', 'Overview of IT concepts and computer hardware', 'Juan Instructor', 'Laboratory 1', 'Monday', '08:00:00', '10:00:00', '1st Semester', '2026-2027', 'active'),
(2, 'IM102', 'Information Management', 'Database systems and SQL fundamentals', 'Maria Professor', 'Laboratory 2', 'Tuesday', '10:00:00', '12:00:00', '1st Semester', '2026-2027', 'active'),
(3, 'WEB103', 'Web Development', 'HTML, CSS, JavaScript, and PHP fullstack development', 'Tech Master', 'Laboratory 3', 'Wednesday', '13:00:00', '16:00:00', '1st Semester', '2026-2027', 'active'),
(4, 'NET104', 'Networking', 'Computer network architecture and protocols', 'Net Specialist', 'Room 301', 'Thursday', '14:00:00', '17:00:00', '1st Semester', '2026-2027', 'active');

-- --------------------------------------------------------

-- Enrolment of Sample Students to Subjects
INSERT INTO `student_subjects` (`student_id`, `subject_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(2, 2),
(2, 3);

-- --------------------------------------------------------

-- Sample Attendance History Records
INSERT INTO `attendance` (`student_id`, `subject_id`, `attendance_date`, `time_in`, `time_out`, `status`, `verification_method`) VALUES
(1, 1, DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY), '07:55:00', '10:00:00', 'Present', 'Face Recognition'),
(1, 2, DATE_SUB(CURRENT_DATE(), INTERVAL 6 DAY), '10:18:00', '12:02:00', 'Late', 'Face Recognition'),
(1, 3, DATE_SUB(CURRENT_DATE(), INTERVAL 5 DAY), '12:58:00', '16:00:00', 'Present', 'Face Recognition'),
(2, 2, DATE_SUB(CURRENT_DATE(), INTERVAL 6 DAY), '09:55:00', '12:00:00', 'Present', 'Face Recognition');

-- Audit Log Seed
INSERT INTO `audit_logs` (`user_id`, `action`, `description`, `ip_address`) VALUES
(1, 'System Initialization', 'Database schema imported with seed data successfully', '127.0.0.1');

SET FOREIGN_KEY_CHECKS = 1;
