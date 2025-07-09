-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql213.infinityfree.com
-- Generation Time: Jul 08, 2025 at 09:19 PM
-- Server version: 11.4.7-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_39421280_the_catalog_library`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `role` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'admin',
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`admin_id`, `username`, `password`, `first_name`, `last_name`, `email`, `role`, `status`, `created_at`, `last_login`, `updated_at`) VALUES
(1, 'admin', '$2y$10$6D3/nj1nRDu6OAluerByvuzGiAGoDo7FY.H7pkDSakv9Rbw7EN/eq', 'System', 'Administrator', 'admin@thecatalog.com', 'super_admin', 'active', '2025-06-30 09:20:08', '2025-07-09 01:18:00', '2025-07-09 01:18:00');

-- --------------------------------------------------------

--
-- Table structure for table `book_copies`
--

CREATE TABLE `book_copies` (
  `copy_id` int(11) NOT NULL,
  `title_id` int(11) NOT NULL,
  `book_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `copy_number` int(11) NOT NULL,
  `condition_status` enum('Excellent','Good','Fair','Poor','Damaged') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'Excellent',
  `acquisition_date` date DEFAULT NULL,
  `location` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `status` enum('Available','Borrowed','Reserved','Maintenance','Archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'Available',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `book_copies`
--

INSERT INTO `book_copies` (`copy_id`, `title_id`, `book_id`, `copy_number`, `condition_status`, `acquisition_date`, `location`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'AGAUG071996-FIC00101', 1, 'Excellent', '2025-06-30', 'Shelf A1', 'Archived', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(2, 1, 'AGAUG071996-FIC00102', 2, 'Good', '2025-06-30', 'Shelf A1', 'Borrowed', NULL, '2025-06-30 01:15:19', '2025-07-09 00:19:52'),
(3, 2, 'BEDEC071015-FIC00201', 1, 'Excellent', '2025-06-30', 'Shelf A2', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(4, 2, 'BEDEC071015-FIC00202', 2, 'Excellent', '2025-06-30', 'Shelf A2', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(5, 2, 'BEDEC071015-FIC00203', 3, 'Good', '2025-06-30', 'Shelf A2', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(6, 3, 'ITOCT072022-FIC00301', 1, 'Excellent', '2025-06-30', 'Shelf A3', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(7, 3, 'ITOCT072022-FIC00302', 2, 'Good', '2025-06-30', 'Shelf A3', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(8, 4, 'LISEP072001-FIC00401', 1, 'Excellent', '2025-06-30', 'Shelf A4', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(9, 5, 'THSEP071937-FIC00501', 1, 'Excellent', '2025-06-30', 'Shelf A5', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(10, 5, 'THSEP071937-FIC00502', 2, 'Excellent', '2025-06-30', 'Shelf A5', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(11, 5, 'THSEP071937-FIC00503', 3, 'Good', '2025-06-30', 'Shelf A5', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(12, 5, 'THSEP071937-FIC00504', 4, 'Good', '2025-06-30', 'Shelf A5', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(13, 6, 'THMAR072020-FIC00601', 1, 'Excellent', '2025-06-30', 'Shelf A6', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(14, 7, 'TMAUG072020-FIC00701', 1, 'Excellent', '2025-06-30', 'Shelf A7', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(15, 7, 'TMAUG072020-FIC00702', 2, 'Good', '2025-06-30', 'Shelf A7', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(16, 8, 'THSEP072011-FIC00801', 1, 'Excellent', '2025-06-30', 'Shelf A8', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(17, 9, 'THJUL072021-FIC00901', 1, 'Excellent', '2025-06-30', 'Shelf A9', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(18, 10, 'TOJUL072022-FIC01001', 1, 'Excellent', '2025-06-30', 'Shelf A10', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(19, 11, 'BROCT072022-NON01101', 1, 'Excellent', '2025-06-30', 'Shelf B1', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(20, 12, 'HINOV072016-NON01201', 1, 'Excellent', '2025-06-30', 'Shelf B2', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-08 14:59:30'),
(21, 12, 'HINOV072016-NON01202', 2, 'Good', '2025-06-30', 'Shelf B2', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-08 14:59:30'),
(22, 13, 'HIAUG071946-NON01301', 1, 'Excellent', '2025-06-30', 'Shelf B3', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(23, 14, 'IAOCT072017-NON01401', 1, 'Excellent', '2025-06-30', 'Shelf B4', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(24, 15, 'MAOCT072018-NON01501', 1, 'Excellent', '2025-06-30', 'Shelf B5', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(25, 16, 'STFEB072012-NON01601', 1, 'Excellent', '2025-06-30', 'Shelf B6', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(26, 17, 'THOCT071998-NON01701', 1, 'Excellent', '2025-06-30', 'Shelf B7', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(27, 18, 'THJUN071947-NON01801', 1, 'Excellent', '2025-06-30', 'Shelf B8', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(28, 18, 'THJUN071947-NON01802', 2, 'Good', '2025-06-30', 'Shelf B8', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(29, 18, 'THJUN071947-NON01803', 3, 'Fair', '2025-06-30', 'Shelf B8', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(30, 19, 'THFEB072010-NON01901', 1, 'Excellent', '2025-06-30', 'Shelf B9', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(31, 20, 'THJUN072016-NON02001', 1, 'Excellent', '2025-06-30', 'Shelf B10', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(32, 21, 'ALJUL071865-CHI02101', 1, 'Excellent', '2025-06-30', 'Shelf C1', 'Borrowed', NULL, '2025-06-30 01:15:19', '2025-07-07 08:35:19'),
(33, 21, 'ALJUL071865-CHI02102', 2, 'Good', '2025-06-30', 'Shelf C1', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(34, 22, 'ANJUN071908-CHI02201', 1, 'Excellent', '2025-06-30', 'Shelf C2', 'Borrowed', NULL, '2025-06-30 01:15:19', '2025-07-08 14:26:54'),
(35, 23, 'CHOCT071952-CHI02301', 1, 'Excellent', '2025-06-30', 'Shelf C3', 'Borrowed', NULL, '2025-06-30 01:15:19', '2025-07-08 09:39:57'),
(36, 23, 'CHOCT071952-CHI02302', 2, 'Good', '2025-06-30', 'Shelf C3', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(37, 23, 'CHOCT071952-CHI02303', 3, 'Fair', '2025-06-30', 'Shelf C3', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(38, 24, 'HAJUN071997-CHI02401', 1, 'Excellent', '2025-06-30', 'Shelf C4', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(39, 24, 'HAJUN071997-CHI02402', 2, 'Excellent', '2025-06-30', 'Shelf C4', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(40, 24, 'HAJUN071997-CHI02403', 3, 'Good', '2025-06-30', 'Shelf C4', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(41, 24, 'HAJUN071997-CHI02404', 4, 'Good', '2025-06-30', 'Shelf C4', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(42, 24, 'HAJUN071997-CHI02405', 5, 'Fair', '2025-06-30', 'Shelf C4', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(43, 25, 'NAMAY072025-CHI02501', 1, 'Excellent', '2025-06-30', 'Shelf C5', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(44, 26, 'THOCT071950-CHI02601', 1, 'Excellent', '2025-06-30', 'Shelf C6', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(45, 26, 'THOCT071950-CHI02602', 2, 'Good', '2025-06-30', 'Shelf C6', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(46, 27, 'TGOCT071964-CHI02701', 1, 'Excellent', '2025-06-30', 'Shelf C7', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(47, 28, 'THAPR071943-CHI02801', 1, 'Excellent', '2025-06-30', 'Shelf C8', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(48, 28, 'THAPR071943-CHI02802', 2, 'Good', '2025-06-30', 'Shelf C8', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(49, 29, 'THAUG072003-CHI02901', 1, 'Excellent', '2025-06-30', 'Shelf C9', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(50, 30, 'WHNOV071963-CHI03001', 1, 'Excellent', '2025-06-30', 'Shelf C10', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(51, 31, 'FINOV072018-ROM03101', 1, 'Excellent', '2025-06-30', 'Shelf D1', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(52, 32, 'IWJUN072018-ROM03201', 1, 'Excellent', '2025-06-30', 'Shelf D2', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(53, 33, 'LOSEP072016-ROM03301', 1, 'Excellent', '2025-06-30', 'Shelf D3', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(54, 34, 'NEJAN072006-ROM03401', 1, 'Excellent', '2025-06-30', 'Shelf D4', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-08 10:13:29'),
(55, 35, 'PSMAY072015-ROM03501', 1, 'Excellent', '2025-06-30', 'Shelf D5', 'Borrowed', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(56, 36, 'PAOCT072008-ROM03601', 1, 'Excellent', '2025-06-30', 'Shelf D6', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(57, 37, 'REMAY072019-ROM03701', 1, 'Excellent', '2025-06-30', 'Shelf D7', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(58, 38, 'THJAN072012-ROM03801', 1, 'Excellent', '2025-06-30', 'Shelf D8', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(59, 38, 'THJAN072012-ROM03802', 2, 'Good', '2025-06-30', 'Shelf D8', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(60, 39, 'THJUN072017-ROM03901', 1, 'Excellent', '2025-06-30', 'Shelf D9', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(61, 40, 'TOAPR072014-ROM04001', 1, 'Excellent', '2025-06-30', 'Shelf D10', 'Borrowed', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(62, 40, 'TOAPR072014-ROM04002', 2, 'Good', '2025-06-30', 'Shelf D10', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(63, 41, 'ABAPR071988-SCI04101', 1, 'Excellent', '2025-06-30', 'Shelf E1', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(64, 42, 'ASMAY072017-SCI04201', 1, 'Excellent', '2025-06-30', 'Shelf E2', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(65, 43, 'EVAUG072016-SCI04301', 1, 'Excellent', '2025-06-30', 'Shelf E3', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(66, 44, 'HOMAR072018-SCI04401', 1, 'Excellent', '2025-06-30', 'Shelf E4', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(67, 45, 'THOCT072019-SCI04501', 1, 'Excellent', '2025-06-30', 'Shelf E5', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(68, 46, 'THFEB072015-SCI04601', 1, 'Excellent', '2025-06-30', 'Shelf E6', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(69, 47, 'THJUN072024-SCI04701', 1, 'Excellent', '2025-06-30', 'Shelf E7', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(70, 48, 'THMAY072016-SCI04801', 1, 'Excellent', '2025-06-30', 'Shelf E8', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(71, 49, 'WHSEP072022-SCI04901', 1, 'Excellent', '2025-06-30', 'Shelf E9', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(72, 50, 'WHSEP072017-SCI05001', 1, 'Excellent', '2025-06-30', 'Shelf E10', 'Available', NULL, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(73, 51, 'OHJAN081990-CHI05101', 1, 'Excellent', '2025-07-08', NULL, 'Available', NULL, '2025-07-08 09:50:25', '2025-07-09 00:21:15'),
(74, 51, 'OHJAN081990-CHI05102', 2, 'Excellent', '2025-07-08', NULL, 'Available', NULL, '2025-07-08 09:50:25', '2025-07-09 00:21:15'),
(75, 51, 'OHJAN081990-CHI05103', 3, 'Excellent', '2025-07-08', NULL, 'Available', NULL, '2025-07-08 09:50:25', '2025-07-09 00:21:15'),
(76, 51, 'OHJAN081990-CHI05104', 4, 'Excellent', '2025-07-08', NULL, 'Available', NULL, '2025-07-08 09:50:25', '2025-07-09 00:21:15'),
(100, 72, 'LOAUG082022-ROM07201', 1, 'Excellent', '2025-07-08', NULL, 'Archived', NULL, '2025-07-08 23:49:46', '2025-07-09 00:21:23'),
(101, 72, 'LOAUG082022-ROM07202', 2, 'Excellent', '2025-07-08', NULL, 'Archived', NULL, '2025-07-08 23:49:46', '2025-07-09 00:21:23'),
(102, 72, 'LOAUG082022-ROM07203', 3, 'Excellent', '2025-07-08', NULL, 'Archived', NULL, '2025-07-08 23:49:46', '2025-07-09 00:21:23'),
(109, 76, 'THMAR082007-FAN07601', 1, 'Excellent', '2025-07-08', NULL, 'Available', NULL, '2025-07-09 00:01:59', '2025-07-09 00:01:59'),
(110, 76, 'THMAR082007-FAN07602', 2, 'Excellent', '2025-07-08', NULL, 'Available', NULL, '2025-07-09 00:01:59', '2025-07-09 00:01:59'),
(111, 76, 'THMAR082007-FAN07603', 3, 'Excellent', '2025-07-08', NULL, 'Available', NULL, '2025-07-09 00:01:59', '2025-07-09 00:01:59'),
(119, 79, 'WHJAN092016-NON07901', 1, 'Excellent', '2025-07-09', NULL, 'Available', NULL, '2025-07-09 00:12:48', '2025-07-09 00:12:48'),
(120, 79, 'WHJAN092016-NON07902', 2, 'Excellent', '2025-07-09', NULL, 'Available', NULL, '2025-07-09 00:12:48', '2025-07-09 00:12:48');

-- --------------------------------------------------------

--
-- Table structure for table `book_titles`
--

CREATE TABLE `book_titles` (
  `title_id` int(11) NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `author` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `published_year` int(11) DEFAULT NULL,
  `published_month` varchar(20) DEFAULT NULL,
  `book_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `status` enum('Available','Borrowed','Archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'Available',
  `total_copies` int(11) DEFAULT 1,
  `available_copies` int(11) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `book_titles`
--

INSERT INTO `book_titles` (`title_id`, `title`, `author`, `category`, `published_year`, `published_month`, `book_image`, `status`, `total_copies`, `available_copies`, `created_at`, `updated_at`) VALUES
(1, 'A Game of Thrones', 'George R.R. Martin', 'Fiction', 1996, 'August', 'fic1.png', 'Borrowed', 2, 0, '2025-06-30 01:15:19', '2025-07-09 00:19:52'),
(2, 'Before The Coffee Gets Cold', 'Toshikazu Kawaguchi', 'Fiction', 2015, 'December', 'fic2.png', 'Available', 3, 3, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(3, 'It Starts With Us', 'Colleen Hoover', 'Fiction', 2022, 'October', 'fic3.png', 'Available', 2, 2, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(4, 'Life of Pi', 'Yann Martel', 'Fiction', 2001, 'September', 'fic4.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(5, 'The Hobbit', 'J.R.R. Tolkien', 'Fiction', 1937, 'September', 'fic5.png', 'Available', 4, 4, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(6, 'The House in the Cerulean Sea', 'TJ Klune', 'Fiction', 2020, 'March', 'fic6.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(7, 'The Midnight Library', 'Matt Haig', 'Fiction', 2020, 'August', 'fic7.png', 'Available', 2, 2, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(8, 'The Night Circus', 'Erin Morgenstern', 'Fiction', 2011, 'September', 'fic8.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(9, 'The Paper Palace', 'Miranda Cowley Heller', 'Fiction', 2021, 'July', 'fic9.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(10, 'Tomorrow, and Tomorrow, and Tomorrow', 'Gabrielle Zevin', 'Fiction', 2022, 'July', 'fic10.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(11, 'Braiding Sweetgrass', 'Robin Wall Kimmerer', 'Non-Fiction', 2022, 'October', 'nonfic1.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(12, 'Hidden Figures', 'Margot Lee Shetterly', 'Non-Fiction', 2016, 'November', 'nonfic2.png', 'Available', 2, 2, '2025-06-30 01:15:19', '2025-07-08 14:59:30'),
(13, 'Hiroshima', 'John Hersey', 'Non-Fiction', 1946, 'August', 'nonfic3.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(14, 'I Am Not Your Perfect Mexican Daughter', 'Erika L. Sánchez', 'Non-Fiction', 2017, 'October', 'nonfic4.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(15, 'Malala: My Story of Standing Up for Girls Rights', 'Malala Yousafzai', 'Non-Fiction', 2018, 'October', 'nonfic5.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(16, 'Steve Jobs: The Man Who Thought Different', 'Karen Blumenthal', 'Non-Fiction', 2012, 'February', 'nonfic6.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(17, 'The 7 Habits of Highly Effective Teens', 'Sean Covey', 'Non-Fiction', 1998, 'October', 'nonfic7.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(18, 'The Diary of a Young Girl', 'Anne Frank', 'Non-Fiction', 1947, 'June', 'nonfic8.png', 'Available', 3, 3, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(19, 'The Immortal Life of Henrietta Lacks', 'Rebecca Skloot', 'Non-Fiction', 2010, 'February', 'nonfic9.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(20, 'The Radium Girls', 'Kate Moore', 'Non-Fiction', 2016, 'June', 'nonfic10.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(21, 'Alice\'s Adventures in Wonderland', 'Lewis Carroll', 'Childrens Books', 1865, 'July', 'ch1.png', 'Available', 2, 1, '2025-06-30 01:15:19', '2025-07-09 00:25:57'),
(22, 'Anne of Green Gables', 'L.M. Montgomery', 'Childrens Books', 1908, 'June', 'ch2.png', 'Borrowed', 1, 0, '2025-06-30 01:15:19', '2025-07-08 14:26:54'),
(23, 'Charlottes Web', 'E.B. White', 'Childrens Books', 1952, 'October', 'ch3.png', 'Available', 3, 2, '2025-06-30 01:15:19', '2025-07-08 09:39:57'),
(24, 'Harry Potter and the Sorcerers Stone', 'J.K. Rowling', 'Childrens Books', 1997, 'June', 'ch4.png', 'Available', 5, 5, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(25, 'National Geographic Kids Almanac (Latest Edition)', 'National Geographic', 'Childrens Books', 2025, 'May', 'ch5.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(26, 'The Chronicles of Narnia: The Lion, the Witch and the Wardrobe', 'C.S. Lewis', 'Childrens Books', 1950, 'October', 'ch6.png', 'Available', 2, 2, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(27, 'The Giving Tree', 'Shel Silverstein', 'Childrens Books', 1964, 'October', 'ch7.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(28, 'The Little Prince', 'Antoine de Saint-Exupéry', 'Childrens Books', 1943, 'April', 'ch8.png', 'Available', 2, 2, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(29, 'The Tale of Despereaux', 'Kate DiCamillo', 'Childrens Books', 2003, 'August', 'ch9.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(30, 'Where the Wild Things Are', 'Maurice Sendak', 'Childrens Books', 1963, 'November', 'ch10.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(31, 'Five Feet Apart', 'Mikki Daughtry, Rachael Lippincott & Tobias Iaconis', 'Romance', 2018, 'November', 'rom1.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(32, 'I Want to Die but I Want to Eat Tteokbokki', 'Baek Sehee', 'Romance', 2018, 'June', 'rom2.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(33, 'Love & Gelato', 'Jenna Evans Welch', 'Romance', 2016, 'September', 'rom3.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(34, 'New Moon', 'Stephenie Meyer', 'Romance', 2006, 'January', 'rom4.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-08 10:13:29'),
(35, 'P.S I Still Love You', 'Jenny Han', 'Romance', 2015, 'May', 'rom5.png', 'Borrowed', 1, 0, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(36, 'Paper Towns', 'John Green', 'Romance', 2008, 'October', 'rom6.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(37, 'Red, White & Royal Blue', 'Casey McQuiston', 'Romance', 2019, 'May', 'rom7.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(38, 'The Fault in Our Stars', 'John Green', 'Romance', 2012, 'January', 'rom8.png', 'Available', 2, 2, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(39, 'The Seven Husbands of Evelyn Hugo', 'Taylor Jenkins Reid', 'Romance', 2017, 'June', 'rom9.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(40, 'To All The Boys I have Loved Before', 'Jenny Han', 'Romance', 2014, 'April', 'rom10.png', 'Borrowed', 2, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(41, 'A Brief History of Time', 'Stephen Hawking', 'Science and Technology', 1988, 'April', 'st1.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(42, 'Astrophysics for People in a Hurry', 'Neil DeGrasse Tyson', 'Science and Technology', 2017, 'May', 'st2.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(43, 'Everything You Need to Ace Science in One Big Fat Notebook', 'Workman Publishing', 'Science and Technology', 2016, 'August', 'st3.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(44, 'How Science Works', 'DK Publishing', 'Science and Technology', 2018, 'March', 'st4.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(45, 'The Body', 'Bill Bryson', 'Science and Technology', 2019, 'October', 'st5.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(46, 'The Boy Who Harnessed the Wind', 'William Kamkwamba', 'Science and Technology', 2015, 'February', 'st6.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(47, 'The Brain at Rest', 'Joseph Jebelli, PhD', 'Science and Technology', 2024, 'June', 'st7.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(48, 'The Gene: An Intimate History', 'Siddhartha Mukherjee', 'Science and Technology', 2016, 'May', 'st8.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(49, 'What If? 2', 'Randall Munroe', 'Science and Technology', 2022, 'September', 'st9.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(50, 'Why We Sleep', 'Matthew Walker, PhD', 'Science and Technology', 2017, 'September', 'st10.png', 'Available', 1, 1, '2025-06-30 01:15:19', '2025-07-07 04:32:00'),
(51, 'Oh, the Places You\'ll Go!', 'Dr. Seuss', 'Childrens Books', 1990, 'January', 'testing.png', 'Available', 4, 4, '2025-07-08 09:50:25', '2025-07-09 00:21:15'),
(72, 'Love on the Brain', 'Ali Hazelwood', 'Romance', 2022, 'August', '686dae9a241ac_1752018586.png', 'Archived', 3, 0, '2025-07-08 23:49:46', '2025-07-09 00:21:23'),
(76, 'The Name of the Wind', 'Patrick Rothfuss', 'Fantasy', 2007, 'March', '686db1783d803_1752019320.png', 'Available', 3, 3, '2025-07-09 00:01:59', '2025-07-09 00:01:59'),
(79, 'When Breath Becomes Air', 'Paul Kalanithi', 'Non-Fiction', 2016, 'January', '686db40002be2_1752019968.png', 'Available', 2, 2, '2025-07-09 00:12:48', '2025-07-09 00:12:48');

-- --------------------------------------------------------

--
-- Table structure for table `borrowed_books`
--

CREATE TABLE `borrowed_books` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `copy_id` int(11) DEFAULT NULL,
  `title_id` int(11) DEFAULT NULL,
  `book_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `borrow_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `status` enum('borrowed','returned','overdue','renewed') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'borrowed',
  `renewal_count` int(11) DEFAULT 0,
  `fine_amount` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrowed_books`
--

INSERT INTO `borrowed_books` (`id`, `user_id`, `copy_id`, `title_id`, `book_id`, `borrow_date`, `due_date`, `return_date`, `status`, `renewal_count`, `fine_amount`, `created_at`, `updated_at`) VALUES
(21, 1, 55, 35, 'PSMAY072015-ROM03501', '2025-06-26', '2025-07-01', '2025-07-07', 'returned', 0, '0.00', '2025-06-30 08:50:06', '2025-07-07 04:29:02'),
(22, 2, 61, 40, 'TOAPR072014-ROM04001', '2025-06-30', '2025-07-07', NULL, 'borrowed', 0, '0.00', '2025-06-30 08:52:29', '2025-07-07 04:29:02'),
(23, 1, 32, 21, 'ALJUL071865-CHI02101', '2025-07-07', '2025-07-14', NULL, 'borrowed', 0, '0.00', '2025-07-07 08:35:19', '2025-07-07 08:35:19'),
(24, 1, 54, 34, 'NEJAN072006-ROM03401', '2025-07-07', '2025-07-14', '2025-07-08', 'returned', 0, '0.00', '2025-07-07 08:36:04', '2025-07-08 10:13:29'),
(25, 2, 35, 23, 'CHOCT071952-CHI02301', '2025-07-08', '2025-07-15', NULL, 'borrowed', 0, '0.00', '2025-07-08 09:39:57', '2025-07-08 09:39:57'),
(26, 1, 34, 22, 'ANJUN071908-CHI02201', '2025-07-08', '2025-07-15', NULL, 'borrowed', 0, '0.00', '2025-07-08 14:26:54', '2025-07-08 14:26:54'),
(27, 3, 2, 1, 'AGAUG071996-FIC00102', '2025-07-09', '2025-07-16', NULL, 'borrowed', 0, '0.00', '2025-07-09 00:19:52', '2025-07-09 00:19:52');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `first_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `last_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `first_name`, `last_name`, `email`, `created_at`) VALUES
(1, '202310256', '$2y$10$dkzAYfQpqk.ddkvnOH6v1exTr5WB3o1WYcXHGCMbCHPwWuKsz9jcm', 'Belle', 'Acedillo', 'belleacedillo@gmail.com', '2025-06-30 08:50:06'),
(2, '202310047', '$2y$10$SYA/cbBzGc2v8noErvfsLeWEtHHGXoDsMo8WPzNXVOhQeAGy73ZfC', 'Richielle', 'Gutierrez', 'richielleann@gmail.com', '2025-06-30 08:52:29'),
(3, '202310123', '$2y$10$inzm0qHY2AXVMYETWb5F2eoTcSPmYJKydPZ9APHR8hrIbUKRXfqly', 'John', 'Doe', 'johndoe@gmail.com', '2025-07-08 10:12:44'),
(4, '202310456', '$2y$10$eBruJ1bfKynUphBVKxXfJuYV082n8PY19cZq5q2L5G2Qb9TQZZUy6', 'Jane', 'Austin', 'janeaustin@gmail.com', '2025-07-08 10:17:38');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `book_copies`
--
ALTER TABLE `book_copies`
  ADD PRIMARY KEY (`copy_id`),
  ADD UNIQUE KEY `unique_book_id` (`book_id`),
  ADD UNIQUE KEY `unique_title_copy` (`title_id`,`copy_number`),
  ADD KEY `idx_title_id` (`title_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_book_id` (`book_id`);

--
-- Indexes for table `book_titles`
--
ALTER TABLE `book_titles`
  ADD PRIMARY KEY (`title_id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_author` (`author`),
  ADD KEY `idx_title` (`title`);

--
-- Indexes for table `borrowed_books`
--
ALTER TABLE `borrowed_books`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `copy_id` (`copy_id`),
  ADD KEY `title_id` (`title_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_due_date` (`due_date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `book_copies`
--
ALTER TABLE `book_copies`
  MODIFY `copy_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=121;

--
-- AUTO_INCREMENT for table `book_titles`
--
ALTER TABLE `book_titles`
  MODIFY `title_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `borrowed_books`
--
ALTER TABLE `borrowed_books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `book_copies`
--
ALTER TABLE `book_copies`
  ADD CONSTRAINT `book_copies_ibfk_1` FOREIGN KEY (`title_id`) REFERENCES `book_titles` (`title_id`) ON DELETE CASCADE;

--
-- Constraints for table `borrowed_books`
--
ALTER TABLE `borrowed_books`
  ADD CONSTRAINT `borrowed_books_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `borrowed_books_ibfk_2` FOREIGN KEY (`copy_id`) REFERENCES `book_copies` (`copy_id`),
  ADD CONSTRAINT `borrowed_books_ibfk_3` FOREIGN KEY (`title_id`) REFERENCES `book_titles` (`title_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
