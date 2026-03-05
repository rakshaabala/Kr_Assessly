-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 27, 2026 at 11:52 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_kr_assessly`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_account`
--

CREATE TABLE `admin_account` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_account`
--

INSERT INTO `admin_account` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'admin', '$2y$10$3lfwtMjHBCGt9iC9BFqZwucQZNFJbAW.jcHzLM5MP.DxDLPm9Y6IW', '2026-01-23 10:19:06');

-- --------------------------------------------------------

--
-- Table structure for table `faculty_account`
--

CREATE TABLE `faculty_account` (
  `id` int(11) NOT NULL,
  `name_of_faculty` varchar(255) NOT NULL,
  `faculty_id` varchar(50) NOT NULL,
  `department` varchar(100) NOT NULL,
  `mobile_number` varchar(15) NOT NULL,
  `mail_id` varchar(255) NOT NULL,
  `role` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faculty_account`
--

INSERT INTO `faculty_account` (`id`, `name_of_faculty`, `faculty_id`, `department`, `mobile_number`, `mail_id`, `role`, `password`, `created_at`, `updated_at`, `status`) VALUES
(1, 'Dr. Ramesh', 'FC001', 'Mechanical', '0147258369', 'ramesh@gmail.com', 'Professor', '$2y$10$tEc2lcxkDNFLfA2QsOm/xuCCD3e/W9idqLgFXl89s8KoN.1VViAXG', '2026-01-04 16:06:32', '2026-01-27 10:48:20', 'active'),
(3, 'Principal', 'FAC002', 'Computer Science', '9876543212', 'principal@gmail.com', 'Professor', '$2y$10$mHw2vKgChau7Y6Cm1go37uFn.EMNlpidOVdU0PxoLRAWhm/A4n9VO', '2026-01-27 06:21:50', '2026-01-27 06:21:50', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `hosted_tests`
--

CREATE TABLE `hosted_tests` (
  `id` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `test_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `test_duration` int(11) NOT NULL COMMENT 'Duration in minutes',
  `question_shuffle` enum('yes','no') DEFAULT 'no',
  `option_shuffle` enum('yes','no') DEFAULT 'no',
  `show_answers` enum('yes','no') DEFAULT 'no',
  `hosted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('scheduled','ongoing','completed','cancelled') DEFAULT 'scheduled'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hosted_tests`
--

INSERT INTO `hosted_tests` (`id`, `test_id`, `test_date`, `start_time`, `end_time`, `test_duration`, `question_shuffle`, `option_shuffle`, `show_answers`, `hosted_at`, `status`) VALUES
(7, 6, '2026-01-24', '14:30:00', '14:36:00', 1, 'yes', 'yes', 'no', '2026-01-24 08:00:38', 'scheduled'),
(20, 28, '2026-01-27', '16:11:00', '16:14:00', 1, 'yes', 'yes', 'no', '2026-01-27 10:41:48', 'scheduled');

-- --------------------------------------------------------

--
-- Table structure for table `hosted_test_students`
--

CREATE TABLE `hosted_test_students` (
  `id` int(11) NOT NULL,
  `hosted_test_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `test_status` enum('not_started','in_progress','completed','absent') DEFAULT 'not_started',
  `start_time` timestamp NULL DEFAULT NULL,
  `submit_time` timestamp NULL DEFAULT NULL,
  `total_score` decimal(6,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hosted_test_students`
--

INSERT INTO `hosted_test_students` (`id`, `hosted_test_id`, `student_id`, `test_status`, `start_time`, `submit_time`, `total_score`) VALUES
(15, 7, 3, 'not_started', NULL, NULL, 0.00),
(37, 20, 3, 'not_started', NULL, NULL, 0.00),
(38, 20, 4, 'not_started', NULL, NULL, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `question_type` enum('mcq','fillup','truefalse') NOT NULL,
  `question_text` text NOT NULL,
  `option_a` varchar(500) DEFAULT NULL,
  `option_b` varchar(500) DEFAULT NULL,
  `option_c` varchar(500) DEFAULT NULL,
  `option_d` varchar(500) DEFAULT NULL,
  `correct_answer` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `questions`
--

INSERT INTO `questions` (`id`, `section_id`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`) VALUES
(29, 8, 'mcq', 'What is 2+2?', '3', '4', '5', '6', 'B', '2026-01-06 15:28:52'),
(30, 8, 'mcq', 'Capital of France?', 'London', 'Paris', 'Rome', 'Berlin', 'B', '2026-01-06 15:28:52'),
(31, 8, 'truefalse', 'Earth is flat', 'True', 'False', '', '', 'False', '2026-01-06 15:28:52'),
(32, 8, 'fillup', 'The capital of India is ____', '', '', '', '', 'New Delhi', '2026-01-06 15:28:52'),
(34, 9, 'mcq', 'Capital of France?', 'London', 'Paris', 'Rome', 'Busan', 'D', '2026-01-06 15:28:52'),
(35, 9, 'truefalse', 'Earth is flat', NULL, NULL, NULL, NULL, 'True', '2026-01-06 15:28:52'),
(36, 9, 'fillup', 'The capital of India is ____', '', '', '', '', 'New Delhi', '2026-01-06 15:28:52'),
(48, 8, 'mcq', 'What is 2+2?', '3', '4', '5', '6', 'A', '2026-01-24 01:13:09'),
(49, 8, 'mcq', 'Capital of France?', 'London', 'Paris', 'Rome', 'Berlin', 'B', '2026-01-24 01:13:09'),
(50, 8, 'truefalse', 'Earth is flat', 'True', 'False', '', '', 'False', '2026-01-24 01:13:09'),
(110, 23, 'mcq', 'Capital of France?', 'London', 'Paris', 'Rome', 'Busan', 'B', '2026-01-27 06:53:49'),
(111, 23, 'truefalse', 'Earth is flat', '', '', '', '', 'True', '2026-01-27 06:53:49'),
(112, 23, 'fillup', 'The capital of India is ____', '', '', '', '', 'New Delhi', '2026-01-27 06:53:49'),
(133, 30, 'mcq', 'Capital of France?', 'London', 'Paris', 'Rome', 'Busan', 'D', '2026-01-27 07:28:52'),
(134, 30, 'truefalse', 'Earth is flat', '', '', '', '', 'True', '2026-01-27 07:28:52'),
(135, 30, 'fillup', 'The capital of India is ____', '', '', '', '', 'New Delhi', '2026-01-27 07:28:52'),
(140, 32, 'mcq', 'What is 2+2?', '3', '4', '5', '6', 'A', '2026-01-27 07:55:14'),
(141, 32, 'mcq', 'Capital of France?', 'London', 'Paris', 'Rome', 'Berlin', 'B', '2026-01-27 07:55:14'),
(142, 32, 'truefalse', 'Earth is flat', NULL, NULL, NULL, NULL, 'True', '2026-01-27 07:55:14'),
(143, 32, 'fillup', 'The capital of India is ____', '', '', '', '', 'New Delhi', '2026-01-27 07:55:14'),
(144, 32, 'mcq', 'efgf', 'fs', 'fs', 'c', 'wq', 'A', '2026-01-27 07:57:17'),
(153, 35, 'mcq', 'Capital of France?', 'London', 'Paris', 'Rome', 'Busan', 'D', '2026-01-27 08:10:30'),
(154, 35, 'truefalse', 'Earth is flat', '', '', '', '', 'True', '2026-01-27 08:10:30'),
(155, 35, 'fillup', 'The capital of India is ____', '', '', '', '', 'New Delhi', '2026-01-27 08:10:30'),
(156, 36, 'mcq', 'Capital of France?', 'London', 'Paris', 'Rome', 'Busan', 'D', '2026-01-27 08:44:17'),
(157, 36, 'truefalse', 'Earth is flat', '', '', '', '', 'True', '2026-01-27 08:44:17'),
(158, 36, 'fillup', 'The capital of India is ____', '', '', '', '', 'New Delhi', '2026-01-27 08:44:17'),
(159, 37, 'mcq', 'Capital of France?', 'London', 'Paris', 'Rome', 'Busan', 'D', '2026-01-27 10:48:58'),
(160, 37, 'truefalse', 'Earth is flat', NULL, NULL, NULL, NULL, 'False', '2026-01-27 10:48:58'),
(161, 37, 'fillup', 'The capital of India is ____', '', '', '', '', 'New Delhi', '2026-01-27 10:48:58'),
(162, 37, 'truefalse', 'zhjm,', 'True', 'False', '', '', 'True', '2026-01-27 10:49:26');

-- --------------------------------------------------------

--
-- Table structure for table `section_scores`
--

CREATE TABLE `section_scores` (
  `id` int(11) NOT NULL,
  `hosted_test_student_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `section_score` decimal(6,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_account`
--

CREATE TABLE `student_account` (
  `id` int(11) NOT NULL,
  `name_of_student` varchar(255) NOT NULL,
  `register_number` varchar(50) NOT NULL,
  `programme` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `batch` varchar(10) NOT NULL,
  `year` varchar(10) NOT NULL,
  `section` varchar(10) NOT NULL,
  `mobile_number` varchar(15) NOT NULL,
  `mail_id` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_account`
--

INSERT INTO `student_account` (`id`, `name_of_student`, `register_number`, `programme`, `department`, `batch`, `year`, `section`, `mobile_number`, `mail_id`, `password`, `created_at`, `updated_at`, `status`) VALUES
(3, 'Angu Jayalakshmi R', '927622BIT004', 'B.Tech', 'IT', '2022-2026', '4', 'A', '0987654321', 'angujaya@gmail.com', '$2y$10$z0EzPBi4iJ1.wbEWxtyfme5d.3lMUbom3fwmkmKD5eKhoPghTAqvm', '2026-01-04 16:06:56', '2026-01-24 03:39:16', 'active'),
(4, 'Siva', '927622BCS004', 'B.Tech', 'IT', '2024-2028', '2', 'B', '9876543210', 'siva@gmail.com', '$2y$10$cd6l.ZFJZjpft7gzpQCtwexebsxPfWxYeJvycniHaOO9iKO2iPUxS', '2026-01-04 16:06:56', '2026-01-27 10:48:37', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `student_answers`
--

CREATE TABLE `student_answers` (
  `id` int(11) NOT NULL,
  `hosted_test_student_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `student_answer` text DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `marks_obtained` decimal(5,2) DEFAULT 0.00,
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tests`
--

CREATE TABLE `tests` (
  `id` int(11) NOT NULL,
  `test_name` varchar(255) NOT NULL,
  `test_code` varchar(50) NOT NULL,
  `created_by_type` enum('admin','faculty') NOT NULL,
  `created_by_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('draft','active','inactive') DEFAULT 'draft'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tests`
--

INSERT INTO `tests` (`id`, `test_name`, `test_code`, `created_by_type`, `created_by_id`, `created_at`, `updated_at`, `status`) VALUES
(6, 'ABC', 'TESTEBC329EE', 'admin', 1, '2026-01-06 15:28:52', '2026-01-06 15:28:52', 'active'),
(19, 'aesdrtfgybhunjuwiufjh', 'TEST2AED8843', 'faculty', 1, '2026-01-27 06:53:49', '2026-01-27 06:53:49', 'active'),
(26, 'asdf`123456', 'TESTA0A0E2C8', 'admin', 1, '2026-01-27 07:28:52', '2026-01-27 07:28:52', 'active'),
(28, 'GK Test', 'TESTE5AF5107', 'faculty', 3, '2026-01-27 07:55:14', '2026-01-27 07:55:14', 'active'),
(31, 'durga', 'TEST78613C5D', 'admin', 1, '2026-01-27 08:10:30', '2026-01-27 08:10:30', 'active'),
(32, 'durga01', 'TEST777BAC19', 'admin', 1, '2026-01-27 08:44:17', '2026-01-27 08:44:17', 'active'),
(33, 'AZSDFGHAsdfgh', 'TESTE8B48B65', 'admin', 1, '2026-01-27 10:48:58', '2026-01-27 10:48:58', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `test_sections`
--

CREATE TABLE `test_sections` (
  `id` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `section_name` varchar(255) NOT NULL,
  `section_order` int(11) NOT NULL,
  `total_questions` int(11) NOT NULL,
  `questions_to_display` int(11) NOT NULL,
  `marks_per_question` decimal(5,2) NOT NULL,
  `negative_marks` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `test_sections`
--

INSERT INTO `test_sections` (`id`, `test_id`, `section_name`, `section_order`, `total_questions`, `questions_to_display`, `marks_per_question`, `negative_marks`, `created_at`) VALUES
(8, 6, 'GK', 1, 7, 4, 1.00, 0.00, '2026-01-06 15:28:52'),
(9, 6, 'GK1', 2, 5, 3, 1.00, 0.00, '2026-01-06 15:28:52'),
(23, 19, 'qcrdvaqbhcnswmlopwfoqnfwi', 1, 3, 3, 1.00, 0.00, '2026-01-27 06:53:49'),
(30, 26, '12345y6qweg', 1, 3, 3, 1.00, 0.00, '2026-01-27 07:28:52'),
(32, 28, 'durga', 1, 5, 4, 1.00, 0.25, '2026-01-27 07:55:14'),
(35, 31, 'h', 1, 3, 3, 1.00, 0.00, '2026-01-27 08:10:30'),
(36, 32, '15', 1, 3, 3, 1.00, 0.00, '2026-01-27 08:44:17'),
(37, 33, '12', 1, 4, 3, 1.00, 0.00, '2026-01-27 10:48:58');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_account`
--
ALTER TABLE `admin_account`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `faculty_account`
--
ALTER TABLE `faculty_account`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `faculty_id` (`faculty_id`),
  ADD UNIQUE KEY `mail_id` (`mail_id`),
  ADD KEY `idx_faculty_id` (`faculty_id`),
  ADD KEY `idx_department` (`department`),
  ADD KEY `idx_role` (`role`);

--
-- Indexes for table `hosted_tests`
--
ALTER TABLE `hosted_tests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `test_id` (`test_id`),
  ADD KEY `idx_hosted_test_date` (`test_date`);

--
-- Indexes for table `hosted_test_students`
--
ALTER TABLE `hosted_test_students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_test` (`hosted_test_id`,`student_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `idx_student_test_status` (`test_status`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `section_id` (`section_id`);

--
-- Indexes for table `section_scores`
--
ALTER TABLE `section_scores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_section` (`hosted_test_student_id`,`section_id`),
  ADD KEY `section_id` (`section_id`);

--
-- Indexes for table `student_account`
--
ALTER TABLE `student_account`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `register_number` (`register_number`),
  ADD UNIQUE KEY `mail_id` (`mail_id`),
  ADD KEY `idx_register_number` (`register_number`),
  ADD KEY `idx_department` (`department`),
  ADD KEY `idx_batch` (`batch`);

--
-- Indexes for table `student_answers`
--
ALTER TABLE `student_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hosted_test_student_id` (`hosted_test_student_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `tests`
--
ALTER TABLE `tests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `test_code` (`test_code`),
  ADD KEY `idx_test_status` (`status`);

--
-- Indexes for table `test_sections`
--
ALTER TABLE `test_sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `test_id` (`test_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_account`
--
ALTER TABLE `admin_account`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `faculty_account`
--
ALTER TABLE `faculty_account`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `hosted_tests`
--
ALTER TABLE `hosted_tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `hosted_test_students`
--
ALTER TABLE `hosted_test_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=163;

--
-- AUTO_INCREMENT for table `section_scores`
--
ALTER TABLE `section_scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_account`
--
ALTER TABLE `student_account`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `student_answers`
--
ALTER TABLE `student_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tests`
--
ALTER TABLE `tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `test_sections`
--
ALTER TABLE `test_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `hosted_tests`
--
ALTER TABLE `hosted_tests`
  ADD CONSTRAINT `hosted_tests_ibfk_1` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hosted_test_students`
--
ALTER TABLE `hosted_test_students`
  ADD CONSTRAINT `hosted_test_students_ibfk_1` FOREIGN KEY (`hosted_test_id`) REFERENCES `hosted_tests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `hosted_test_students_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `student_account` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `test_sections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `section_scores`
--
ALTER TABLE `section_scores`
  ADD CONSTRAINT `section_scores_ibfk_1` FOREIGN KEY (`hosted_test_student_id`) REFERENCES `hosted_test_students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `section_scores_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `test_sections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_answers`
--
ALTER TABLE `student_answers`
  ADD CONSTRAINT `student_answers_ibfk_1` FOREIGN KEY (`hosted_test_student_id`) REFERENCES `hosted_test_students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `test_sections`
--
ALTER TABLE `test_sections`
  ADD CONSTRAINT `test_sections_ibfk_1` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
