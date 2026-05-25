-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: May 04, 2026 at 06:52 AM
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
-- Database: `ice_tubig`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_add_ice_stock` (IN `p_qty` INT, IN `p_product_name` VARCHAR(80), IN `p_kg` DECIMAL(5,2), IN `p_freeze_duration_hours` INT, IN `p_price` DECIMAL(10,2), IN `p_instant` BOOLEAN)   BEGIN
                DECLARE counter INT DEFAULT 0;
                DECLARE target_status ENUM('NOT_AVAILABLE', 'AVAILABLE', 'SOLD');
                SET target_status = IF(p_instant OR p_freeze_duration_hours = 0, 'AVAILABLE', 'NOT_AVAILABLE');
                WHILE counter < p_qty DO
                    INSERT INTO ice_stocks (status, product_name, kg, freeze_duration_hours, price)
                    VALUES (target_status, p_product_name, p_kg, IF(p_instant, 0, p_freeze_duration_hours), p_price);
                    SET counter = counter + 1;
                END WHILE;
            END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_dashboard_summary` ()   BEGIN
                SELECT
                    COALESCE(SUM(status = 'AVAILABLE'), 0) AS available_count,
                    COALESCE(SUM(status = 'NOT_AVAILABLE'), 0) AS freezing_count,
                    (SELECT COUNT(*) FROM ice_sales) AS sold_count,
                    (SELECT COUNT(*) FROM ice_activity_log) AS activity_count
                FROM ice_stocks;
            END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_refresh_ice_availability` ()   BEGIN
                UPDATE ice_stocks
                SET status = 'AVAILABLE'
                WHERE status = 'NOT_AVAILABLE'
                AND TIMESTAMPDIFF(HOUR, time_added, NOW()) >= (
                    SELECT freeze_duration_hours FROM system_settings WHERE id = 1
                );
            END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_sell_stock` (IN `p_stock_id` INT, IN `p_sold_by_user_id` INT)   BEGIN
                DECLARE v_status ENUM('NOT_AVAILABLE', 'AVAILABLE', 'SOLD');
                DECLARE EXIT HANDLER FOR NOT FOUND SET v_status = NULL;
                SELECT status INTO v_status FROM ice_stocks WHERE stock_id = p_stock_id;
                IF v_status IS NULL THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stock not found';
                ELSEIF v_status != 'AVAILABLE' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stock not available for sale';
                ELSE
                    INSERT INTO ice_sales (stock_id, price, sold_by_user_id)
                    SELECT stock_id, price, p_sold_by_user_id FROM ice_stocks WHERE stock_id = p_stock_id;
                    UPDATE ice_stocks SET status = 'SOLD' WHERE stock_id = p_stock_id;
                END IF;
            END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `notification_id` int(11) NOT NULL,
  `event_type` varchar(40) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(160) NOT NULL,
  `message` text NOT NULL,
  `severity` varchar(20) NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_notifications`
--

INSERT INTO `admin_notifications` (`notification_id`, `event_type`, `user_id`, `title`, `message`, `severity`, `is_read`, `created_at`) VALUES
(1, 'SALE_BLOCKED', 2, 'Sale Blocked', 'Ruzzel tried to sell Ice, but they are not On Site.', 'warning', 1, '2026-05-02 12:26:58'),
(2, 'LATE_CLOCK_IN', 2, 'Late Clock In', 'Ruzzel clocked in at 2026-05-02 12:27 (expected 08:00). Status: LATE.', 'warning', 1, '2026-05-02 12:27:13'),
(3, 'SALE_RECORDED', 2, 'Sale Recorded', 'Ruzzel sold ICE BAG (25 kg) for ₱ 35.00. Stock #217 is now Sold.', 'success', 1, '2026-05-02 12:27:53'),
(4, 'SALE_RECORDED', 2, 'Sale Recorded', 'Ruzzel sold Ice (25 kg) for ₱ 35.00. Stock #144 is now Sold.', 'success', 1, '2026-05-02 12:28:02'),
(5, 'SALE_BLOCKED', 2, 'Sale Blocked', 'Ruzzel tried to sell TUBE ICE, but they are not On Site.', 'warning', 1, '2026-05-04 09:45:53'),
(6, 'LATE_CLOCK_IN', 2, 'Late Clock In', 'Ruzzel clocked in at 2026-05-04 09:46 (expected 08:00). Status: Late.', 'warning', 1, '2026-05-04 09:46:17'),
(7, 'SALE_RECORDED', 2, 'Sale Recorded', 'Ruzzel sold TUBE ICE (25 kg) for ₱ 35.00. Stock #227 is now Sold.', 'success', 1, '2026-05-04 09:46:36'),
(8, 'EARLY_TIME_OUT', 2, 'Early Time Out', 'Ruzzel timed out at 2026-05-04 09:50 (expected 17:00). Status: Late and Left Early.', 'warning', 1, '2026-05-04 09:50:04');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `created_by_user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`announcement_id`, `title`, `message`, `created_by_user_id`, `created_at`, `is_active`) VALUES
(1, 'REMINDER', 'Go To our shop Early as possible', 1, '2026-04-30 10:29:19', 1),
(2, 'REMINDER', 'wag ka mag benta ng wala site warning na saken', 1, '2026-05-04 09:51:31', 1);

-- --------------------------------------------------------

--
-- Table structure for table `announcement_recipients`
--

CREATE TABLE `announcement_recipients` (
  `announcement_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcement_recipients`
--

INSERT INTO `announcement_recipients` (`announcement_id`, `user_id`, `is_read`, `read_at`) VALUES
(1, 2, 1, '2026-04-30 10:31:38'),
(2, 2, 1, '2026-05-04 09:51:59');

-- --------------------------------------------------------

--
-- Table structure for table `employee_shift_logs`
--

CREATE TABLE `employee_shift_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `shift_date` date NOT NULL,
  `expected_in` time NOT NULL,
  `expected_out` time NOT NULL,
  `actual_in` datetime DEFAULT NULL,
  `actual_out` datetime DEFAULT NULL,
  `attendance_status` varchar(20) NOT NULL DEFAULT 'OFFSITE',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_shift_logs`
--

INSERT INTO `employee_shift_logs` (`log_id`, `user_id`, `shift_date`, `expected_in`, `expected_out`, `actual_in`, `actual_out`, `attendance_status`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-04-27', '08:00:00', '17:00:00', '2026-04-27 03:02:53', NULL, 'ON_TIME', '2026-04-27 03:02:53', '2026-04-27 03:02:53'),
(2, 1, '2026-04-28', '08:00:00', '17:00:00', '2026-04-28 10:24:21', '2026-04-28 10:24:30', 'COMPLETED', '2026-04-28 10:24:09', '2026-04-28 10:24:30'),
(6, 1, '2026-04-29', '08:00:00', '17:00:00', NULL, '2026-04-29 07:28:49', 'COMPLETED', '2026-04-29 07:28:49', '2026-04-29 07:28:49'),
(7, 2, '2026-04-29', '08:00:00', '17:00:00', '2026-04-29 07:30:59', '2026-04-29 10:16:18', 'COMPLETED', '2026-04-29 07:30:59', '2026-04-29 10:16:18'),
(19, 2, '2026-05-02', '08:00:00', '17:00:00', '2026-05-02 12:27:13', NULL, 'LATE', '2026-05-02 12:27:13', '2026-05-02 12:27:13'),
(20, 2, '2026-05-04', '08:00:00', '17:00:00', '2026-05-04 09:46:17', '2026-05-04 09:50:04', 'LATE_EARLY_OUT', '2026-05-04 09:46:17', '2026-05-04 09:50:04');

-- --------------------------------------------------------

--
-- Table structure for table `ice_activity_log`
--

CREATE TABLE `ice_activity_log` (
  `event_id` int(11) NOT NULL,
  `stock_id` int(11) DEFAULT NULL,
  `event_time` datetime NOT NULL DEFAULT current_timestamp(),
  `event_type` enum('ADDED','AVAILABLE','SOLD','SALE','CONFIG') NOT NULL,
  `old_status` enum('NOT_AVAILABLE','AVAILABLE','SOLD') DEFAULT NULL,
  `new_status` enum('NOT_AVAILABLE','AVAILABLE','SOLD') DEFAULT NULL,
  `kg` decimal(5,2) DEFAULT 1.00,
  `price` decimal(10,2) DEFAULT 5.00,
  `freeze_duration_hours` int(11) DEFAULT 3,
  `details` text DEFAULT NULL,
  `product_name` varchar(80) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ice_activity_log`
--

INSERT INTO `ice_activity_log` (`event_id`, `stock_id`, `event_time`, `event_type`, `old_status`, `new_status`, `kg`, `price`, `freeze_duration_hours`, `details`, `product_name`) VALUES
(1, 1, '2026-04-17 00:20:28', 'AVAILABLE', 'NOT_AVAILABLE', 'AVAILABLE', 1.00, 5.00, 3, 'Stock status changed from NOT_AVAILABLE to AVAILABLE', NULL),
(2, 2, '2026-04-17 00:20:28', 'AVAILABLE', 'NOT_AVAILABLE', 'AVAILABLE', 1.00, 5.00, 3, 'Stock status changed from NOT_AVAILABLE to AVAILABLE', NULL),
(3, 215, '2026-04-23 22:49:14', 'ADDED', NULL, 'AVAILABLE', 20.00, 500.00, 0, 'New stock added with status AVAILABLE', 'YELLO NI ALLENG SHAN'),
(4, 215, '2026-04-23 22:58:24', 'SALE', 'AVAILABLE', 'SOLD', 1.00, 500.00, 3, 'Sale recorded for stock 215', 'YELLO NI ALLENG SHAN'),
(5, 215, '2026-04-23 22:58:24', 'SOLD', 'AVAILABLE', 'SOLD', 20.00, 500.00, 0, 'Stock status changed from AVAILABLE to SOLD', 'YELLO NI ALLENG SHAN'),
(6, 134, '2026-04-24 11:35:09', 'SALE', 'AVAILABLE', 'SOLD', 1.00, 35.00, 3, 'Sale recorded for stock 134', 'Ice'),
(7, 134, '2026-04-24 11:35:09', 'SOLD', 'AVAILABLE', 'SOLD', 25.00, 35.00, 0, 'Stock status changed from AVAILABLE to SOLD', 'Ice'),
(8, 72, '2026-04-24 11:35:14', 'SALE', 'AVAILABLE', 'SOLD', 1.00, 35.00, 3, 'Sale recorded for stock 72', 'Ice'),
(9, 72, '2026-04-24 11:35:14', 'SOLD', 'AVAILABLE', 'SOLD', 25.00, 35.00, 0, 'Stock status changed from AVAILABLE to SOLD', 'Ice'),
(10, 74, '2026-04-24 11:35:20', 'SALE', 'AVAILABLE', 'SOLD', 1.00, 35.00, 3, 'Sale recorded for stock 74', 'Ice'),
(11, 74, '2026-04-24 11:35:20', 'SOLD', 'AVAILABLE', 'SOLD', 25.00, 35.00, 0, 'Stock status changed from AVAILABLE to SOLD', 'Ice'),
(12, 79, '2026-04-24 11:35:25', 'SALE', 'AVAILABLE', 'SOLD', 1.00, 35.00, 3, 'Sale recorded for stock 79', 'Ice'),
(13, 79, '2026-04-24 11:35:25', 'SOLD', 'AVAILABLE', 'SOLD', 25.00, 35.00, 0, 'Stock status changed from AVAILABLE to SOLD', 'Ice'),
(14, 111, '2026-04-27 02:47:16', 'SALE', 'AVAILABLE', 'SOLD', 1.00, 35.00, 3, 'Sale recorded for stock 111', 'Ice'),
(15, 111, '2026-04-27 02:47:16', 'SOLD', 'AVAILABLE', 'SOLD', 25.00, 35.00, 0, 'Stock status changed from AVAILABLE to SOLD', 'Ice'),
(16, 216, '2026-04-27 20:21:53', 'ADDED', NULL, 'NOT_AVAILABLE', 25.00, 35.00, 1, 'New stock added with status NOT_AVAILABLE', 'ice bag'),
(17, 216, '2026-04-28 07:30:08', 'AVAILABLE', 'NOT_AVAILABLE', 'AVAILABLE', 25.00, 35.00, 1, 'Stock status changed from NOT_AVAILABLE to AVAILABLE', 'ice bag'),
(18, 216, '2026-04-29 07:29:55', 'SALE', 'AVAILABLE', 'SOLD', 1.00, 35.00, 3, 'Sale recorded for stock 216', 'ice bag'),
(19, 216, '2026-04-29 07:29:55', 'SOLD', 'AVAILABLE', 'SOLD', 25.00, 35.00, 1, 'Stock status changed from AVAILABLE to SOLD', 'ice bag'),
(20, 141, '2026-04-29 07:30:05', 'SALE', 'AVAILABLE', 'SOLD', 1.00, 35.00, 3, 'Sale recorded for stock 141', 'Ice'),
(21, 141, '2026-04-29 07:30:05', 'SOLD', 'AVAILABLE', 'SOLD', 25.00, 35.00, 0, 'Stock status changed from AVAILABLE to SOLD', 'Ice'),
(22, 114, '2026-04-29 07:30:11', 'SALE', 'AVAILABLE', 'SOLD', 1.00, 35.00, 3, 'Sale recorded for stock 114', 'Ice'),
(23, 114, '2026-04-29 07:30:11', 'SOLD', 'AVAILABLE', 'SOLD', 25.00, 35.00, 0, 'Stock status changed from AVAILABLE to SOLD', 'Ice'),
(24, 117, '2026-04-29 07:30:17', 'SALE', 'AVAILABLE', 'SOLD', 1.00, 35.00, 3, 'Sale recorded for stock 117', 'Ice'),
(25, 117, '2026-04-29 07:30:17', 'SOLD', 'AVAILABLE', 'SOLD', 25.00, 35.00, 0, 'Stock status changed from AVAILABLE to SOLD', 'Ice'),
(26, 93, '2026-04-29 07:30:28', 'SALE', 'AVAILABLE', 'SOLD', 1.00, 35.00, 3, 'Sale recorded for stock 93', 'Ice'),
(27, 93, '2026-04-29 07:30:28', 'SOLD', 'AVAILABLE', 'SOLD', 25.00, 35.00, 0, 'Stock status changed from AVAILABLE to SOLD', 'Ice'),
(28, 71, '2026-04-29 07:30:32', 'SALE', 'AVAILABLE', 'SOLD', 1.00, 35.00, 3, 'Sale recorded for stock 71', 'Ice'),
(29, 71, '2026-04-29 07:30:32', 'SOLD', 'AVAILABLE', 'SOLD', 25.00, 35.00, 0, 'Stock status changed from AVAILABLE to SOLD', 'Ice'),
(30, 217, '2026-04-29 07:32:31', 'ADDED', NULL, 'NOT_AVAILABLE', 25.00, 35.00, 2, 'New stock added with status NOT_AVAILABLE', 'ICE BAG'),
(31, 125, '2026-04-29 07:45:44', 'SALE', 'AVAILABLE', 'SOLD', 1.00, 35.00, 3, 'Sale recorded for stock 125', 'Ice'),
(32, 125, '2026-04-29 07:45:44', 'SOLD', 'AVAILABLE', 'SOLD', 25.00, 35.00, 0, 'Stock status changed from AVAILABLE to SOLD', 'Ice'),
(33, 127, '2026-04-29 07:46:09', 'SALE', 'AVAILABLE', 'SOLD', 1.00, 35.00, 3, 'Sale recorded for stock 127', 'Ice'),
(34, 127, '2026-04-29 07:46:09', 'SOLD', 'AVAILABLE', 'SOLD', 25.00, 35.00, 0, 'Stock status changed from AVAILABLE to SOLD', 'Ice'),
(35, 130, '2026-04-29 07:46:13', 'SALE', 'AVAILABLE', 'SOLD', 1.00, 35.00, 3, 'Sale recorded for stock 130', 'Ice'),
(36, 130, '2026-04-29 07:46:13', 'SOLD', 'AVAILABLE', 'SOLD', 25.00, 35.00, 0, 'Stock status changed from AVAILABLE to SOLD', 'Ice'),
(37, 69, '2026-04-29 07:46:19', 'SALE', 'AVAILABLE', 'SOLD', 1.00, 35.00, 3, 'Sale recorded for stock 69', 'Ice'),
(38, 69, '2026-04-29 07:46:19', 'SOLD', 'AVAILABLE', 'SOLD', 25.00, 35.00, 0, 'Stock status changed from AVAILABLE to SOLD', 'Ice'),
(41, 217, '2026-04-29 13:48:45', 'AVAILABLE', 'NOT_AVAILABLE', 'AVAILABLE', 25.00, 35.00, 2, 'Stock status changed from NOT_AVAILABLE to AVAILABLE', 'ICE BAG'),
(44, 217, '2026-05-02 12:27:53', 'SALE', 'AVAILABLE', 'SOLD', 1.00, 35.00, 3, 'Sale recorded for stock 217', 'ICE BAG'),
(45, 217, '2026-05-02 12:27:53', 'SOLD', 'AVAILABLE', 'SOLD', 25.00, 35.00, 2, 'Stock status changed from AVAILABLE to SOLD', 'ICE BAG'),
(46, 144, '2026-05-02 12:28:02', 'SALE', 'AVAILABLE', 'SOLD', 1.00, 35.00, 3, 'Sale recorded for stock 144', 'Ice'),
(47, 144, '2026-05-02 12:28:02', 'SOLD', 'AVAILABLE', 'SOLD', 25.00, 35.00, 0, 'Stock status changed from AVAILABLE to SOLD', 'Ice'),
(48, 220, '2026-05-04 09:44:30', 'ADDED', NULL, 'AVAILABLE', 25.00, 35.00, 0, 'New stock added with status AVAILABLE', 'TUBE ICE'),
(49, 221, '2026-05-04 09:44:30', 'ADDED', NULL, 'AVAILABLE', 25.00, 35.00, 0, 'New stock added with status AVAILABLE', 'TUBE ICE'),
(50, 222, '2026-05-04 09:44:30', 'ADDED', NULL, 'AVAILABLE', 25.00, 35.00, 0, 'New stock added with status AVAILABLE', 'TUBE ICE'),
(51, 223, '2026-05-04 09:44:30', 'ADDED', NULL, 'AVAILABLE', 25.00, 35.00, 0, 'New stock added with status AVAILABLE', 'TUBE ICE'),
(52, 224, '2026-05-04 09:44:30', 'ADDED', NULL, 'AVAILABLE', 25.00, 35.00, 0, 'New stock added with status AVAILABLE', 'TUBE ICE'),
(53, 225, '2026-05-04 09:44:30', 'ADDED', NULL, 'AVAILABLE', 25.00, 35.00, 0, 'New stock added with status AVAILABLE', 'TUBE ICE'),
(54, 226, '2026-05-04 09:44:30', 'ADDED', NULL, 'AVAILABLE', 25.00, 35.00, 0, 'New stock added with status AVAILABLE', 'TUBE ICE'),
(55, 227, '2026-05-04 09:44:30', 'ADDED', NULL, 'AVAILABLE', 25.00, 35.00, 0, 'New stock added with status AVAILABLE', 'TUBE ICE'),
(56, 228, '2026-05-04 09:44:30', 'ADDED', NULL, 'AVAILABLE', 25.00, 35.00, 0, 'New stock added with status AVAILABLE', 'TUBE ICE'),
(57, 229, '2026-05-04 09:44:30', 'ADDED', NULL, 'AVAILABLE', 25.00, 35.00, 0, 'New stock added with status AVAILABLE', 'TUBE ICE'),
(58, 230, '2026-05-04 09:44:30', 'ADDED', NULL, 'AVAILABLE', 25.00, 35.00, 0, 'New stock added with status AVAILABLE', 'TUBE ICE'),
(59, 231, '2026-05-04 09:44:30', 'ADDED', NULL, 'AVAILABLE', 25.00, 35.00, 0, 'New stock added with status AVAILABLE', 'TUBE ICE'),
(60, 232, '2026-05-04 09:44:30', 'ADDED', NULL, 'AVAILABLE', 25.00, 35.00, 0, 'New stock added with status AVAILABLE', 'TUBE ICE'),
(61, 233, '2026-05-04 09:44:30', 'ADDED', NULL, 'AVAILABLE', 25.00, 35.00, 0, 'New stock added with status AVAILABLE', 'TUBE ICE'),
(62, 234, '2026-05-04 09:44:30', 'ADDED', NULL, 'AVAILABLE', 25.00, 35.00, 0, 'New stock added with status AVAILABLE', 'TUBE ICE'),
(63, 235, '2026-05-04 09:44:30', 'ADDED', NULL, 'AVAILABLE', 25.00, 35.00, 0, 'New stock added with status AVAILABLE', 'TUBE ICE'),
(64, 236, '2026-05-04 09:44:30', 'ADDED', NULL, 'AVAILABLE', 25.00, 35.00, 0, 'New stock added with status AVAILABLE', 'TUBE ICE'),
(65, 237, '2026-05-04 09:44:30', 'ADDED', NULL, 'AVAILABLE', 25.00, 35.00, 0, 'New stock added with status AVAILABLE', 'TUBE ICE'),
(66, 238, '2026-05-04 09:44:30', 'ADDED', NULL, 'AVAILABLE', 25.00, 35.00, 0, 'New stock added with status AVAILABLE', 'TUBE ICE'),
(67, 239, '2026-05-04 09:44:30', 'ADDED', NULL, 'AVAILABLE', 25.00, 35.00, 0, 'New stock added with status AVAILABLE', 'TUBE ICE'),
(68, 227, '2026-05-04 09:46:36', 'SALE', 'AVAILABLE', 'SOLD', 1.00, 35.00, 3, 'Sale recorded for stock 227', 'TUBE ICE'),
(69, 227, '2026-05-04 09:46:36', 'SOLD', 'AVAILABLE', 'SOLD', 25.00, 35.00, 0, 'Stock status changed from AVAILABLE to SOLD', 'TUBE ICE');

-- --------------------------------------------------------

--
-- Table structure for table `ice_sales`
--

CREATE TABLE `ice_sales` (
  `sale_id` int(11) NOT NULL,
  `stock_id` int(11) NOT NULL,
  `sale_time` datetime NOT NULL DEFAULT current_timestamp(),
  `price` decimal(10,2) NOT NULL,
  `sold_by_user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ice_sales`
--

INSERT INTO `ice_sales` (`sale_id`, `stock_id`, `sale_time`, `price`, `sold_by_user_id`) VALUES
(1, 3, '2026-04-02 13:53:37', 35.00, NULL),
(2, 4, '2026-04-06 00:21:59', 5.00, NULL),
(3, 12, '2026-04-06 00:22:42', 35.00, NULL),
(4, 13, '2026-04-06 00:22:44', 35.00, NULL),
(5, 7, '2026-04-06 00:22:45', 35.00, NULL),
(6, 8, '2026-04-06 00:22:46', 35.00, NULL),
(7, 9, '2026-04-06 00:22:46', 35.00, NULL),
(8, 10, '2026-04-06 00:22:47', 35.00, NULL),
(9, 11, '2026-04-06 00:22:47', 35.00, NULL),
(10, 6, '2026-04-06 00:22:48', 35.00, NULL),
(11, 5, '2026-04-06 00:22:48', 35.00, NULL),
(12, 214, '2026-04-10 07:42:21', 35.00, NULL),
(13, 162, '2026-04-10 07:44:29', 35.00, NULL),
(14, 163, '2026-04-10 07:44:48', 35.00, NULL),
(15, 161, '2026-04-11 12:39:56', 35.00, NULL),
(16, 160, '2026-04-11 12:40:34', 35.00, NULL),
(17, 159, '2026-04-11 12:40:36', 35.00, NULL),
(18, 164, '2026-04-11 12:40:36', 35.00, NULL),
(19, 158, '2026-04-11 12:40:37', 35.00, NULL),
(20, 157, '2026-04-11 12:40:37', 35.00, NULL),
(21, 156, '2026-04-11 12:40:37', 35.00, NULL),
(22, 165, '2026-04-11 12:40:38', 35.00, NULL),
(23, 155, '2026-04-11 12:40:38', 35.00, NULL),
(24, 154, '2026-04-11 12:40:38', 35.00, NULL),
(25, 153, '2026-04-11 12:40:39', 35.00, NULL),
(30, 215, '2026-04-23 22:58:24', 500.00, NULL),
(31, 134, '2026-04-24 11:35:09', 35.00, NULL),
(32, 72, '2026-04-24 11:35:14', 35.00, NULL),
(33, 74, '2026-04-24 11:35:20', 35.00, NULL),
(34, 79, '2026-04-24 11:35:25', 35.00, NULL),
(35, 111, '2026-04-27 02:47:16', 35.00, 1),
(36, 216, '2026-04-29 07:29:55', 35.00, 2),
(37, 141, '2026-04-29 07:30:05', 35.00, 2),
(38, 114, '2026-04-29 07:30:11', 35.00, 2),
(39, 117, '2026-04-29 07:30:17', 35.00, 2),
(40, 93, '2026-04-29 07:30:28', 35.00, 2),
(41, 71, '2026-04-29 07:30:32', 35.00, 2),
(42, 125, '2026-04-29 07:45:44', 35.00, 2),
(43, 127, '2026-04-29 07:46:09', 35.00, 2),
(44, 130, '2026-04-29 07:46:13', 35.00, 2),
(45, 69, '2026-04-29 07:46:19', 35.00, 2),
(47, 217, '2026-05-02 12:27:53', 35.00, 2),
(48, 144, '2026-05-02 12:28:02', 35.00, 2),
(49, 227, '2026-05-04 09:46:36', 35.00, 2);

--
-- Triggers `ice_sales`
--
DELIMITER $$
CREATE TRIGGER `trg_after_ice_sale` AFTER INSERT ON `ice_sales` FOR EACH ROW BEGIN
                INSERT INTO ice_activity_log (
                    stock_id, event_type, old_status, new_status, product_name, price, details
                ) VALUES (
                    NEW.stock_id, 'SALE', 'AVAILABLE', 'SOLD',
                    (SELECT product_name FROM ice_stocks WHERE stock_id = NEW.stock_id),
                    NEW.price,
                    CONCAT('Sale recorded for stock ', NEW.stock_id)
                );
            END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `ice_stocks`
--

CREATE TABLE `ice_stocks` (
  `stock_id` int(11) NOT NULL,
  `time_added` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('NOT_AVAILABLE','AVAILABLE','SOLD') DEFAULT 'NOT_AVAILABLE',
  `kg` decimal(5,2) DEFAULT 1.00,
  `freeze_duration_hours` int(11) DEFAULT 3,
  `price` decimal(10,2) DEFAULT 5.00,
  `product_name` varchar(80) DEFAULT 'Ice'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ice_stocks`
--

INSERT INTO `ice_stocks` (`stock_id`, `time_added`, `status`, `kg`, `freeze_duration_hours`, `price`, `product_name`) VALUES
(1, '2026-04-02 13:16:13', 'AVAILABLE', 1.00, 3, 5.00, 'Ice'),
(2, '2026-04-02 13:37:35', 'AVAILABLE', 1.00, 3, 5.00, 'Ice'),
(3, '2026-04-02 13:52:56', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(4, '2026-04-06 00:21:42', 'SOLD', 25.00, 0, 5.00, 'Ice'),
(5, '2026-04-06 00:22:29', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(6, '2026-04-06 00:22:31', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(7, '2026-04-06 00:22:37', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(8, '2026-04-06 00:22:37', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(9, '2026-04-06 00:22:37', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(10, '2026-04-06 00:22:37', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(11, '2026-04-06 00:22:37', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(12, '2026-04-06 00:22:37', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(13, '2026-04-06 00:22:37', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(14, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(15, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(16, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(17, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(18, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(19, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(20, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(21, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(22, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(23, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(24, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(25, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(26, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(27, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(28, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(29, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(30, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(31, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(32, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(33, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(34, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(35, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(36, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(37, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(38, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(39, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(40, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(41, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(42, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(43, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(44, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(45, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(46, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(47, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(48, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(49, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(50, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(51, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(52, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(53, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(54, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(55, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(56, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(57, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(58, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(59, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(60, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(61, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(62, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(63, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(64, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(65, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(66, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(67, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(68, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(69, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(70, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(71, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(72, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(73, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(74, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(75, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(76, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(77, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(78, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(79, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(80, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(81, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(82, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(83, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(84, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(85, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(86, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(87, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(88, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(89, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(90, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(91, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(92, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(93, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(94, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(95, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(96, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(97, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(98, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(99, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(100, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(101, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(102, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(103, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(104, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(105, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(106, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(107, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(108, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(109, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(110, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(111, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(112, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(113, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(114, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(115, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(116, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(117, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(118, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(119, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(120, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(121, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(122, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(123, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(124, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(125, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(126, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(127, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(128, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(129, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(130, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(131, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(132, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(133, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(134, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(135, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(136, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(137, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(138, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(139, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(140, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(141, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(142, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(143, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(144, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(145, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(146, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(147, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(148, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(149, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(150, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(151, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(152, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(153, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(154, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(155, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(156, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(157, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(158, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(159, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(160, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(161, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(162, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(163, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(164, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(165, '2026-04-06 00:27:01', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(166, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(167, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(168, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(169, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(170, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(171, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(172, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(173, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(174, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(175, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(176, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(177, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(178, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(179, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(180, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(181, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(182, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(183, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(184, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(185, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(186, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(187, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(188, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(189, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(190, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(191, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(192, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(193, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(194, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(195, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(196, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(197, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(198, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(199, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(200, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(201, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(202, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(203, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(204, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(205, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(206, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(207, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(208, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(209, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(210, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(211, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(212, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(213, '2026-04-06 00:27:01', 'AVAILABLE', 25.00, 0, 35.00, 'Ice'),
(214, '2026-04-10 07:17:43', 'SOLD', 25.00, 0, 35.00, 'Ice'),
(215, '2026-04-23 22:49:14', 'SOLD', 20.00, 0, 500.00, 'YELLO NI ALLENG SHAN'),
(216, '2026-04-27 20:21:53', 'SOLD', 25.00, 1, 35.00, 'ice bag'),
(217, '2026-04-29 07:32:31', 'SOLD', 25.00, 2, 35.00, 'ICE BAG'),
(220, '2026-05-04 09:44:30', 'AVAILABLE', 25.00, 0, 35.00, 'TUBE ICE'),
(221, '2026-05-04 09:44:30', 'AVAILABLE', 25.00, 0, 35.00, 'TUBE ICE'),
(222, '2026-05-04 09:44:30', 'AVAILABLE', 25.00, 0, 35.00, 'TUBE ICE'),
(223, '2026-05-04 09:44:30', 'AVAILABLE', 25.00, 0, 35.00, 'TUBE ICE'),
(224, '2026-05-04 09:44:30', 'AVAILABLE', 25.00, 0, 35.00, 'TUBE ICE'),
(225, '2026-05-04 09:44:30', 'AVAILABLE', 25.00, 0, 35.00, 'TUBE ICE'),
(226, '2026-05-04 09:44:30', 'AVAILABLE', 25.00, 0, 35.00, 'TUBE ICE'),
(227, '2026-05-04 09:44:30', 'SOLD', 25.00, 0, 35.00, 'TUBE ICE'),
(228, '2026-05-04 09:44:30', 'AVAILABLE', 25.00, 0, 35.00, 'TUBE ICE'),
(229, '2026-05-04 09:44:30', 'AVAILABLE', 25.00, 0, 35.00, 'TUBE ICE'),
(230, '2026-05-04 09:44:30', 'AVAILABLE', 25.00, 0, 35.00, 'TUBE ICE'),
(231, '2026-05-04 09:44:30', 'AVAILABLE', 25.00, 0, 35.00, 'TUBE ICE'),
(232, '2026-05-04 09:44:30', 'AVAILABLE', 25.00, 0, 35.00, 'TUBE ICE'),
(233, '2026-05-04 09:44:30', 'AVAILABLE', 25.00, 0, 35.00, 'TUBE ICE'),
(234, '2026-05-04 09:44:30', 'AVAILABLE', 25.00, 0, 35.00, 'TUBE ICE'),
(235, '2026-05-04 09:44:30', 'AVAILABLE', 25.00, 0, 35.00, 'TUBE ICE'),
(236, '2026-05-04 09:44:30', 'AVAILABLE', 25.00, 0, 35.00, 'TUBE ICE'),
(237, '2026-05-04 09:44:30', 'AVAILABLE', 25.00, 0, 35.00, 'TUBE ICE'),
(238, '2026-05-04 09:44:30', 'AVAILABLE', 25.00, 0, 35.00, 'TUBE ICE'),
(239, '2026-05-04 09:44:30', 'AVAILABLE', 25.00, 0, 35.00, 'TUBE ICE');

--
-- Triggers `ice_stocks`
--
DELIMITER $$
CREATE TRIGGER `trg_after_insert_ice_stocks` AFTER INSERT ON `ice_stocks` FOR EACH ROW BEGIN
                INSERT INTO ice_activity_log (
                    stock_id, event_type, new_status, product_name, kg, price, freeze_duration_hours, details
                ) VALUES (
                    NEW.stock_id, 'ADDED', NEW.status, NEW.product_name, NEW.kg, NEW.price, NEW.freeze_duration_hours,
                    CONCAT('New stock added with status ', NEW.status)
                );
            END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_update_ice_stocks` AFTER UPDATE ON `ice_stocks` FOR EACH ROW BEGIN
                INSERT INTO ice_activity_log (
                    stock_id, event_type, old_status, new_status, product_name, kg, price, freeze_duration_hours, details
                ) VALUES (
                    NEW.stock_id,
                    CASE
                        WHEN NEW.status = 'SOLD' THEN 'SOLD'
                        WHEN NEW.status = 'AVAILABLE' THEN 'AVAILABLE'
                        ELSE 'CONFIG'
                    END,
                    OLD.status,
                    NEW.status,
                    NEW.product_name,
                    NEW.kg,
                    NEW.price,
                    NEW.freeze_duration_hours,
                    CONCAT('Stock status changed from ', OLD.status, ' to ', NEW.status)
                );
            END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_before_insert_ice_stocks` BEFORE INSERT ON `ice_stocks` FOR EACH ROW BEGIN
                IF NEW.freeze_duration_hours = 0 THEN
                    SET NEW.status = 'AVAILABLE';
                ELSE
                    SET NEW.status = 'NOT_AVAILABLE';
                END IF;
            END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `description`) VALUES
(1, 'admin', 'System administrator with full access'),
(2, 'staff', 'Staff member with limited access');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `freeze_duration_hours` int(11) NOT NULL DEFAULT 3,
  `theme` varchar(20) DEFAULT 'light',
  `shift_start_time` time NOT NULL DEFAULT '08:00:00',
  `shift_end_time` time NOT NULL DEFAULT '17:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `freeze_duration_hours`, `theme`, `shift_start_time`, `shift_end_time`) VALUES
(1, 3, 'light', '08:00:00', '17:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password_hash`, `created_at`, `is_active`) VALUES
(1, 'admin', '240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9', '2026-04-24 11:41:51', 1),
(2, 'Ruzzel', '46eafe2b6969db532c1a2da38ac3dbfcab331adfc14240fd4d7e46e24c5f3eeb', '2026-04-27 02:48:21', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES
(1, 1),
(2, 2);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_activity_log_summary`
-- (See below for the actual view)
--
CREATE TABLE `vw_activity_log_summary` (
`event_type` enum('ADDED','AVAILABLE','SOLD','SALE','CONFIG')
,`events_count` bigint(21)
,`first_event` datetime
,`latest_event` datetime
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_available_products`
-- (See below for the actual view)
--
CREATE TABLE `vw_available_products` (
`product_name` varchar(80)
,`kg` decimal(5,2)
,`price` decimal(10,2)
,`available_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_daily_sales_summary`
-- (See below for the actual view)
--
CREATE TABLE `vw_daily_sales_summary` (
`sale_date` date
,`sale_count` bigint(21)
,`total_revenue` decimal(32,2)
,`average_price` decimal(14,6)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_sales_with_stock`
-- (See below for the actual view)
--
CREATE TABLE `vw_sales_with_stock` (
`sale_id` int(11)
,`stock_id` int(11)
,`product_name` varchar(80)
,`kg` decimal(5,2)
,`stock_price` decimal(10,2)
,`sale_price` decimal(10,2)
,`sale_time` datetime
,`sold_by_user_id` int(11)
,`sold_by_username` varchar(50)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_shift_attendance`
-- (See below for the actual view)
--
CREATE TABLE `vw_shift_attendance` (
`log_id` int(11)
,`user_id` int(11)
,`username` varchar(50)
,`shift_date` date
,`expected_in` varchar(10)
,`expected_out` varchar(10)
,`actual_in` varchar(21)
,`actual_out` varchar(21)
,`attendance_status` varchar(20)
,`created_at` datetime
,`updated_at` datetime
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_stock_availability`
-- (See below for the actual view)
--
CREATE TABLE `vw_stock_availability` (
`stock_id` int(11)
,`product_name` varchar(80)
,`kg` decimal(5,2)
,`price` decimal(10,2)
,`freeze_duration_hours` int(11)
,`status` enum('NOT_AVAILABLE','AVAILABLE','SOLD')
,`time_added` datetime
,`minutes_until_available` bigint(21)
,`available_at` datetime
);

-- --------------------------------------------------------

--
-- Structure for view `vw_activity_log_summary`
--
DROP TABLE IF EXISTS `vw_activity_log_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_activity_log_summary`  AS SELECT `ice_activity_log`.`event_type` AS `event_type`, count(0) AS `events_count`, min(`ice_activity_log`.`event_time`) AS `first_event`, max(`ice_activity_log`.`event_time`) AS `latest_event` FROM `ice_activity_log` GROUP BY `ice_activity_log`.`event_type` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_available_products`
--
DROP TABLE IF EXISTS `vw_available_products`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_available_products`  AS SELECT `ice_stocks`.`product_name` AS `product_name`, `ice_stocks`.`kg` AS `kg`, `ice_stocks`.`price` AS `price`, count(0) AS `available_count` FROM `ice_stocks` WHERE `ice_stocks`.`status` = 'AVAILABLE' GROUP BY `ice_stocks`.`product_name`, `ice_stocks`.`kg`, `ice_stocks`.`price` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_daily_sales_summary`
--
DROP TABLE IF EXISTS `vw_daily_sales_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_daily_sales_summary`  AS SELECT cast(`ice_sales`.`sale_time` as date) AS `sale_date`, count(0) AS `sale_count`, coalesce(sum(`ice_sales`.`price`),0) AS `total_revenue`, coalesce(avg(`ice_sales`.`price`),0) AS `average_price` FROM `ice_sales` GROUP BY cast(`ice_sales`.`sale_time` as date) ORDER BY cast(`ice_sales`.`sale_time` as date) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_sales_with_stock`
--
DROP TABLE IF EXISTS `vw_sales_with_stock`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_sales_with_stock`  AS SELECT `s`.`sale_id` AS `sale_id`, `s`.`stock_id` AS `stock_id`, `i`.`product_name` AS `product_name`, `i`.`kg` AS `kg`, `i`.`price` AS `stock_price`, `s`.`price` AS `sale_price`, `s`.`sale_time` AS `sale_time`, `u`.`user_id` AS `sold_by_user_id`, `u`.`username` AS `sold_by_username` FROM ((`ice_sales` `s` join `ice_stocks` `i` on(`s`.`stock_id` = `i`.`stock_id`)) left join `users` `u` on(`u`.`user_id` = `s`.`sold_by_user_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `vw_shift_attendance`
--
DROP TABLE IF EXISTS `vw_shift_attendance`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_shift_attendance`  AS SELECT `l`.`log_id` AS `log_id`, `l`.`user_id` AS `user_id`, `u`.`username` AS `username`, `l`.`shift_date` AS `shift_date`, time_format(`l`.`expected_in`,'%H:%i') AS `expected_in`, time_format(`l`.`expected_out`,'%H:%i') AS `expected_out`, date_format(`l`.`actual_in`,'%Y-%m-%d %H:%i') AS `actual_in`, date_format(`l`.`actual_out`,'%Y-%m-%d %H:%i') AS `actual_out`, `l`.`attendance_status` AS `attendance_status`, `l`.`created_at` AS `created_at`, `l`.`updated_at` AS `updated_at` FROM (`employee_shift_logs` `l` join `users` `u` on(`u`.`user_id` = `l`.`user_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `vw_stock_availability`
--
DROP TABLE IF EXISTS `vw_stock_availability`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_stock_availability`  AS SELECT `ice_stocks`.`stock_id` AS `stock_id`, `ice_stocks`.`product_name` AS `product_name`, `ice_stocks`.`kg` AS `kg`, `ice_stocks`.`price` AS `price`, `ice_stocks`.`freeze_duration_hours` AS `freeze_duration_hours`, `ice_stocks`.`status` AS `status`, `ice_stocks`.`time_added` AS `time_added`, CASE WHEN `ice_stocks`.`status` = 'NOT_AVAILABLE' THEN timestampdiff(MINUTE,current_timestamp(),`ice_stocks`.`time_added` + interval `ice_stocks`.`freeze_duration_hours` hour) ELSE 0 END AS `minutes_until_available`, CASE WHEN `ice_stocks`.`status` = 'NOT_AVAILABLE' THEN `ice_stocks`.`time_added`+ interval `ice_stocks`.`freeze_duration_hours` hour ELSE `ice_stocks`.`time_added` END AS `available_at` FROM `ice_stocks` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_admin_notifications_created` (`created_at`),
  ADD KEY `idx_admin_notifications_unread` (`is_read`,`created_at`),
  ADD KEY `idx_admin_notifications_event` (`event_type`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`),
  ADD KEY `created_by_user_id` (`created_by_user_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `announcement_recipients`
--
ALTER TABLE `announcement_recipients`
  ADD PRIMARY KEY (`announcement_id`,`user_id`),
  ADD KEY `idx_user_unread` (`user_id`,`is_read`);

--
-- Indexes for table `employee_shift_logs`
--
ALTER TABLE `employee_shift_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD UNIQUE KEY `uniq_user_shift` (`user_id`,`shift_date`),
  ADD KEY `idx_shift_date` (`shift_date`);

--
-- Indexes for table `ice_activity_log`
--
ALTER TABLE `ice_activity_log`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `stock_id` (`stock_id`),
  ADD KEY `event_type` (`event_type`);

--
-- Indexes for table `ice_sales`
--
ALTER TABLE `ice_sales`
  ADD PRIMARY KEY (`sale_id`),
  ADD KEY `stock_id` (`stock_id`),
  ADD KEY `idx_ice_sales_sold_by_user_id` (`sold_by_user_id`);

--
-- Indexes for table `ice_stocks`
--
ALTER TABLE `ice_stocks`
  ADD PRIMARY KEY (`stock_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `username_2` (`username`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`user_id`,`role_id`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `employee_shift_logs`
--
ALTER TABLE `employee_shift_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `ice_activity_log`
--
ALTER TABLE `ice_activity_log`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `ice_sales`
--
ALTER TABLE `ice_sales`
  MODIFY `sale_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `ice_stocks`
--
ALTER TABLE `ice_stocks`
  MODIFY `stock_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=240;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD CONSTRAINT `admin_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `announcement_recipients`
--
ALTER TABLE `announcement_recipients`
  ADD CONSTRAINT `announcement_recipients_ibfk_1` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`announcement_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcement_recipients_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_shift_logs`
--
ALTER TABLE `employee_shift_logs`
  ADD CONSTRAINT `employee_shift_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `ice_sales`
--
ALTER TABLE `ice_sales`
  ADD CONSTRAINT `ice_sales_ibfk_1` FOREIGN KEY (`stock_id`) REFERENCES `ice_stocks` (`stock_id`);

--
-- Constraints for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE CASCADE;

DELIMITER $$
--
-- Events
--
CREATE DEFINER=`root`@`localhost` EVENT `evt_update_ice_availability` ON SCHEDULE EVERY 1 MINUTE STARTS '2026-05-04 12:19:00' ON COMPLETION NOT PRESERVE ENABLE DO UPDATE ice_stocks
                SET status = 'AVAILABLE'
                WHERE status = 'NOT_AVAILABLE'
                AND TIMESTAMPDIFF(HOUR, time_added, NOW()) >= (
                    SELECT freeze_duration_hours FROM system_settings WHERE id = 1
                )$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
