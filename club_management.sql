-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 09, 2025 at 02:37 PM
-- Server version: 8.0.31
-- PHP Version: 8.0.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `club management`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

DROP TABLE IF EXISTS `admin`;
CREATE TABLE IF NOT EXISTS `admin` (
  `admin_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `name` varchar(100) NOT NULL,
  `role` enum('President','Voice_president','Treasurer','') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_no` bigint NOT NULL,
  `a_password` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `phone_no_2` (`phone_no`),
  UNIQUE KEY `phone_no_3` (`phone_no`),
  UNIQUE KEY `phone_no_4` (`phone_no`),
  KEY `email` (`email`),
  KEY `email_2` (`email`),
  KEY `phone_no` (`phone_no`),
  KEY `email_3` (`email`) USING BTREE,
  KEY `email_4` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`admin_id`, `name`, `role`, `email`, `phone_no`, `a_password`) VALUES
('pre01', 'Shilpa', 'President', 'shilpa02@gmail.com', 8088196967, '$2y$10$7eL6BrdkIvRe.cnc1340e.POV05LxXm8rfYIxWkjJ83wz7jheSqia');

-- --------------------------------------------------------

--
-- Table structure for table `club_photos`
--

DROP TABLE IF EXISTS `club_photos`;
CREATE TABLE IF NOT EXISTS `club_photos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `photo_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `club_photos`
--

INSERT INTO `club_photos` (`id`, `photo_path`, `uploaded_at`) VALUES
(4, 'uploads/club_photos/cp2.jpg', '2025-05-02 16:55:03'),
(3, 'uploads/club_photos/cp.jpg', '2025-05-02 16:54:49');

-- --------------------------------------------------------

--
-- Table structure for table `event`
--

DROP TABLE IF EXISTS `event`;
CREATE TABLE IF NOT EXISTS `event` (
  `event_id` varchar(20) NOT NULL,
  `event_type` enum('meeting','workshop','social','cultural','educational','other') NOT NULL DEFAULT 'other',
  `event_name` varchar(200) NOT NULL,
  `event_date` date NOT NULL,
  `event_location` varchar(200) NOT NULL,
  `e_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `member_share` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `event`
--

INSERT INTO `event` (`event_id`, `event_type`, `event_name`, `event_date`, `event_location`, `e_description`, `total_amount`, `member_share`) VALUES
('EVT-68137a44b806c', 'social', 'remedy', '2025-05-03', 'Sirsi', 'cifiufob', NULL, NULL),
('EVT-6813bf73ea3ea', 'educational', 'qizz', '2025-05-05', 'JSS SMI UG and PG Studies,Dharwad', 'quizz event on 10.00 pm', '1000.00', '150.00'),
('EVT-6813cb278be95', 'cultural', 'dance event', '2025-05-04', 'JSS SMI UG and PG Studies,Dharwad', 'at 10 0 clock', '1000.00', '250.00'),
('EVT-6814787bb1752', 'social', 'sgdgy', '2025-05-07', 'sharwad', 'jhbfihafb habifwb', '1000.00', '100.00'),
('EVT-68187ba921dd9', 'cultural', 'game', '2025-05-08', 'Sirsi', 'kniu hdbuibeod', '1000.00', '100.00'),
('EVT-681b96ff3cd10', 'workshop', 'workshop', '2025-05-10', 'Sirsi', 'jbwiuuqh', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `event_photos`
--

DROP TABLE IF EXISTS `event_photos`;
CREATE TABLE IF NOT EXISTS `event_photos` (
  `photo_id` varchar(20) NOT NULL,
  `event_id` varchar(20) NOT NULL,
  `photo_url` varchar(255) NOT NULL,
  `upload_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`photo_id`),
  KEY `event_id` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

DROP TABLE IF EXISTS `feedback`;
CREATE TABLE IF NOT EXISTS `feedback` (
  `feedback_id` int NOT NULL,
  `member_id` varchar(20) NOT NULL,
  `event_id` varchar(20) NOT NULL,
  `feedback` tinytext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`feedback_id`),
  KEY `member_id` (`member_id`),
  KEY `event_id` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`feedback_id`, `member_id`, `event_id`, `feedback`, `created_at`) VALUES
(1, 'MEM05232', 'EVT-6813cb278be95', 'it was wonderful!!', '2025-05-08 15:28:51'),
(2, 'MEM05232', 'EVT-68137a44b806c', 'okay', '2025-05-08 15:44:09');

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

DROP TABLE IF EXISTS `members`;
CREATE TABLE IF NOT EXISTS `members` (
  `member_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `m_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `m_email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `m_phone_no` varchar(255) NOT NULL,
  `join_date` timestamp NOT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `m_password` varchar(200) NOT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`member_id`),
  UNIQUE KEY `email` (`m_email`),
  UNIQUE KEY `phone_no` (`m_phone_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`member_id`, `m_name`, `m_email`, `m_phone_no`, `join_date`, `address`, `m_password`, `profile_photo`) VALUES
('MEM05232', 'chandan', 'chandanbhat01@gmail.com', '9087896754', '2025-05-07 16:41:54', 'hulekal,sirsi,uttarakannada', '$2y$10$PFK1JHQ54EntSY5Az5YLb.EYdjsoMkK0Ug7SjeYgIs.Rhc4kkweMW', 'uploads/profile_photos/member_MEM05232_1746635807.jpg'),
('MEM78577', 'varshini', 'varshinihegde83@gmail.com', '8088196965', '2025-05-02 18:02:47', 'kalave,tq sirsi', '$2y$10$pA6eJzI0XqiHZkYt9tXRh.bLEKMZBMhu9Z4.rx7ws6phMm9GsOpp.', 'uploads/profile_photos/member_MEM78577_1746435539.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `pending_approval`
--

DROP TABLE IF EXISTS `pending_approval`;
CREATE TABLE IF NOT EXISTS `pending_approval` (
  `pending_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'UUID()',
  `p_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `p_email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `p_phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `achievements` mediumblob NOT NULL,
  `address` varchar(255) NOT NULL,
  `p_status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'pending',
  `registration_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`pending_id`),
  UNIQUE KEY `emal` (`p_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pending_approval`
--

INSERT INTO `pending_approval` (`pending_id`, `p_name`, `p_email`, `p_phone`, `password`, `achievements`, `address`, `p_status`, `registration_date`) VALUES
('6813113c0c8cf', 'ganapati', 'ganpatihegde@gmail.com', '12368634790', '', 0x75706c6f6164732f7368612e706466, 'jknowofuofbo3gbo3hogh', 'pending', '2025-05-01 06:14:20');

-- --------------------------------------------------------

--
-- Table structure for table `user_table`
--

DROP TABLE IF EXISTS `user_table`;
CREATE TABLE IF NOT EXISTS `user_table` (
  `user_id` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `pending_id` varchar(20) NOT NULL,
  `u_name` varchar(20) NOT NULL,
  `u_email` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `u_phone` varchar(20) NOT NULL,
  `verification_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  PRIMARY KEY (`user_id`),
  KEY `pending_id` (`pending_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `workassignment`
--

DROP TABLE IF EXISTS `workassignment`;
CREATE TABLE IF NOT EXISTS `workassignment` (
  `assignment_id` int NOT NULL AUTO_INCREMENT,
  `event_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `member_id` varchar(20) NOT NULL,
  `description` text NOT NULL,
  `w_status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'pending',
  `rejection_reason` text NOT NULL,
  `payment_status` enum('pending','completed','failed') NOT NULL DEFAULT 'pending',
  `transaction_id` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`assignment_id`),
  KEY `workassignment_ibfk_3` (`member_id`),
  KEY `event_id` (`event_id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `workassignment`
--

INSERT INTO `workassignment` (`assignment_id`, `event_id`, `member_id`, `description`, `w_status`, `rejection_reason`, `payment_status`, `transaction_id`) VALUES
(6, 'EVT-68137a44b806c', 'MEM05232', 'anchor', '', 'previuos commitment', 'pending', NULL),
(7, 'EVT-68137a44b806c', 'MEM05232', 'collector', 'approved', '', 'pending', NULL),
(11, 'EVT-6813bf73ea3ea', 'MEM78577', 'coordinator', 'pending', '', 'pending', NULL),
(13, 'EVT-6813cb278be95', 'MEM78577', 'Event participation', 'approved', '', 'completed', 'kjbdbwd'),
(14, 'EVT-6814787bb1752', 'MEM78577', 'Event participation', 'approved', '', 'completed', 'kjbdjbd'),
(15, 'EVT-6814787bb1752', 'MEM78577', 'anchor', 'approved', '', 'pending', NULL),
(16, 'EVT-68187ba921dd9', 'MEM78577', 'maintainer', 'approved', '', 'completed', 'pauaju'),
(17, 'EVT-6813cb278be95', 'MEM05232', 'Event participation', 'approved', '', 'completed', 'kbgg'),
(18, 'EVT-6813bf73ea3ea', 'MEM05232', 'Event participation', 'approved', '', 'completed', 'jriugr');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `event_photos`
--
ALTER TABLE `event_photos`
  ADD CONSTRAINT `event_photos_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `event` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `event` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_table`
--
ALTER TABLE `user_table`
  ADD CONSTRAINT `user_table_ibfk_1` FOREIGN KEY (`pending_id`) REFERENCES `pending_approval` (`pending_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `workassignment`
--
ALTER TABLE `workassignment`
  ADD CONSTRAINT `workassignment_ibfk_3` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `workassignment_ibfk_4` FOREIGN KEY (`event_id`) REFERENCES `event` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
