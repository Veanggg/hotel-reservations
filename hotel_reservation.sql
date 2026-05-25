-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 25, 2026 at 03:33 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hotel_reservation`
--

-- --------------------------------------------------------

--
-- Table structure for table `guests`
--

CREATE TABLE `guests` (
  `guest_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `id_number` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guests`
--

INSERT INTO `guests` (`guest_id`, `first_name`, `last_name`, `email`, `phone`, `address`, `id_number`, `created_at`) VALUES
(1, 'Brent', 'Alcover', 'brentalcover@email.com', '09267668987', 'Munting Indang, Nasugbu', '1', '2026-05-22 09:12:41'),
(2, 'Jasmine', 'Ramos', 'mamen@email.com', '0923876985', 'Cumba, Lian Batangas', '1245', '2026-05-22 09:12:41'),
(3, 'Ezra', 'Sabina', 'Ezrasaabina@email.com', '0987684756', 'Bolbok, Tuy', '345678', '2026-05-22 09:12:41'),
(4, 'ric', 'ruzzel', 'ruzl@example.com', '9876543210', 'bagong pook', '3456', '2026-05-22 09:31:44'),
(5, 'Vea', 'Laparan', 'veang@gmail.com', '09367928148', 'Prenza', '566777', '2026-05-23 14:45:45'),
(6, 'Christine', 'Colecha', 'tin@gmail.com', '0987674857', NULL, NULL, '2026-05-23 15:09:04'),
(7, 'Kelly', 'Delas Alas', 'kelly@gmail.com', '09876574356', 'Brgy. Quatro', '34567', '2026-05-24 05:37:00'),
(8, 'Marc', 'Mercado', 'marc@gmail.com', '0956897345', 'Luyahan', '8765', '2026-05-24 05:38:42'),
(9, 'Ezra', 'Mendoza', 'ezra@example.com', '9876543210', NULL, NULL, '2026-05-25 12:29:50'),
(10, 'Ric', 'Badlis', 'rriz@gmail.com', '0987657967', NULL, NULL, '2026-05-25 12:33:37');

-- --------------------------------------------------------

--
-- Table structure for table `guest_services`
--

CREATE TABLE `guest_services` (
  `guest_service_id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `total_price` decimal(10,2) NOT NULL,
  `service_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guest_services`
--

INSERT INTO `guest_services` (`guest_service_id`, `reservation_id`, `service_id`, `quantity`, `total_price`, `service_date`) VALUES
(1, 1, 1, 2, 30.00, '2026-05-22 09:12:41'),
(2, 1, 5, 3, 60.00, '2026-05-22 09:12:41');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','credit_card','bank_transfer','online') NOT NULL,
  `payment_type` enum('full','half','remaining') DEFAULT 'full',
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','completed','failed') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `reservation_id`, `amount`, `payment_method`, `payment_type`, `payment_date`, `status`) VALUES
(1, 1, 6600.00, 'credit_card', 'full', '2026-05-22 09:12:41', 'completed'),
(3, 3, 4400.00, 'cash', 'full', '2026-05-22 09:12:41', 'completed'),
(5, 5, 400.00, 'cash', 'remaining', '2026-05-23 17:11:03', 'completed'),
(9, 7, 3300.00, 'cash', 'remaining', '2026-05-24 05:30:34', 'completed'),
(10, 14, 1466.67, 'cash', 'remaining', '2026-05-25 12:18:16', 'completed'),
(11, 15, 1466.67, '', 'remaining', '2026-05-25 12:19:02', 'completed'),
(12, 16, 1166.67, 'credit_card', 'half', '2026-05-25 12:21:17', 'completed'),
(13, 16, 1166.66, 'credit_card', 'remaining', '2026-05-25 12:22:15', 'completed'),
(14, 17, 20000.00, '', 'full', '2026-05-25 12:29:50', 'completed'),
(15, 18, 56000.00, 'credit_card', 'full', '2026-05-25 12:33:37', 'completed');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `reservation_id` int(11) NOT NULL,
  `guest_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `check_in_date` date NOT NULL,
  `check_in_time` time NOT NULL DEFAULT curtime(),
  `check_out_date` date NOT NULL,
  `check_out_time` time NOT NULL DEFAULT '12:00:00',
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('confirmed','checked_in','checked_out','cancelled') DEFAULT 'confirmed',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`reservation_id`, `guest_id`, `room_id`, `check_in_date`, `check_in_time`, `check_out_date`, `check_out_time`, `total_amount`, `status`, `created_by`, `created_at`) VALUES
(1, 1, 3, '2024-01-15', '17:12:41', '2024-01-18', '12:00:00', 6600.00, 'checked_out', 1, '2026-05-22 09:12:41'),
(3, 3, 4, '2024-02-01', '17:12:41', '2024-02-03', '12:00:00', 4400.00, 'checked_out', 2, '2026-05-22 09:12:41'),
(5, 5, 1, '2026-05-23', '22:45:00', '2026-05-24', '06:00:00', 400.00, 'checked_out', 4, '2026-05-23 14:45:45'),
(7, 5, 2, '2026-05-24', '00:23:00', '2026-05-26', '18:00:00', 3300.00, 'checked_in', 4, '2026-05-23 16:23:35'),
(14, 6, 6, '2026-05-25', '20:17:00', '2026-05-26', '12:00:00', 1466.67, 'checked_in', 1, '2026-05-25 12:18:07'),
(15, 1, 3, '2026-05-25', '20:18:00', '2026-05-26', '12:00:00', 1466.67, 'checked_in', 1, '2026-05-25 12:18:57'),
(16, 6, 8, '2026-05-25', '20:20:00', '2026-05-26', '12:00:00', 2333.33, 'checked_in', 3, '2026-05-25 12:21:17'),
(17, 9, 16, '2026-05-25', '20:26:00', '2026-05-27', '12:00:00', 20000.00, 'checked_in', 2, '2026-05-25 12:29:50'),
(18, 10, 19, '2026-05-25', '20:32:00', '2026-05-30', '12:00:00', 56000.00, 'checked_in', 5, '2026-05-25 12:33:37');

-- --------------------------------------------------------

--
-- Stand-in structure for view `reservation_details`
-- (See below for the actual view)
--
CREATE TABLE `reservation_details` (
`reservation_id` int(11)
,`guest_name` varchar(101)
,`guest_email` varchar(100)
,`guest_phone` varchar(20)
,`room_number` varchar(10)
,`type_name` varchar(50)
,`check_in_date` date
,`check_out_date` date
,`total_amount` decimal(10,2)
,`status` enum('confirmed','checked_in','checked_out','cancelled')
,`nights_stayed` int(7)
,`created_by_user` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `revenue_report`
-- (See below for the actual view)
--
CREATE TABLE `revenue_report` (
`month` varchar(7)
,`payment_count` bigint(21)
,`total_revenue` decimal(32,2)
,`average_payment` decimal(14,6)
);

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `room_id` int(11) NOT NULL,
  `room_number` varchar(10) NOT NULL,
  `type_id` int(11) NOT NULL,
  `floor_number` int(11) NOT NULL,
  `status` enum('available','occupied','maintenance','reserved') DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`room_id`, `room_number`, `type_id`, `floor_number`, `status`) VALUES
(1, '101', 1, 1, 'available'),
(2, '102', 1, 1, 'occupied'),
(3, '103', 2, 1, 'occupied'),
(4, '104', 2, 1, 'available'),
(5, '105', 8, 1, 'maintenance'),
(6, '201', 2, 2, 'occupied'),
(7, '202', 3, 2, 'available'),
(8, '203', 3, 2, 'occupied'),
(9, '204', 9, 2, 'available'),
(10, '205', 3, 2, 'available'),
(11, '301', 4, 3, 'available'),
(12, '302', 5, 3, 'available'),
(13, '303', 4, 3, 'available'),
(14, '304', 5, 3, 'available'),
(15, '401', 6, 4, 'available'),
(16, '402', 7, 4, 'occupied'),
(17, '403', 10, 4, 'available'),
(19, '11', 7, 20, 'occupied');

-- --------------------------------------------------------

--
-- Stand-in structure for view `room_occupancy`
-- (See below for the actual view)
--
CREATE TABLE `room_occupancy` (
`type_name` varchar(50)
,`total_rooms` bigint(21)
,`available_rooms` decimal(22,0)
,`occupied_rooms` decimal(22,0)
,`maintenance_rooms` decimal(22,0)
,`reserved_rooms` decimal(22,0)
);

-- --------------------------------------------------------

--
-- Table structure for table `room_types`
--

CREATE TABLE `room_types` (
  `type_id` int(11) NOT NULL,
  `type_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `max_occupancy` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_types`
--

INSERT INTO `room_types` (`type_id`, `type_name`, `description`, `base_price`, `max_occupancy`) VALUES
(1, 'Standard Single', 'Comfortable single room with basic amenities', 1200.00, 1),
(2, 'Standard Double', 'Spacious double room with queen bed', 2200.00, 2),
(3, 'Deluxe Room', 'Premium room with city view and mini bar', 3500.00, 2),
(4, 'Suite', 'Luxury suite with separate living area', 6500.00, 4),
(5, 'Family Room', 'Large room suitable for families', 4000.00, 6),
(6, 'Executive Suite', 'Executive suite with office space and premium amenities', 8500.00, 2),
(7, 'Penthouse', 'Top floor penthouse with panoramic views', 12000.00, 4),
(8, 'Budget Room', 'Basic room for budget-conscious travelers', 750.00, 1),
(9, 'Twin Room', 'Room with two single beds', 1800.00, 2),
(10, 'Connecting Rooms', 'Two connecting rooms ideal for families', 5000.00, 4);

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `service_id` int(11) NOT NULL,
  `service_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`service_id`, `service_name`, `description`, `price`) VALUES
(1, 'Room Service', '24/7 room service delivery', 15.00),
(2, 'Laundry Service', 'Professional laundry and dry cleaning', 25.00),
(3, 'Airport Transfer', 'Airport pickup and drop-off', 50.00),
(4, 'Spa Access', 'Full day spa access', 75.00),
(5, 'Breakfast', 'Continental breakfast buffet', 20.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `full_name`, `email`, `phone`, `role`, `created_at`) VALUES
(1, 'admin', '12345678', 'System Administrator', 'admin@hotel.com', '1234567890', 'admin', '2026-05-22 09:12:41'),
(2, 'ezra', '12345', 'Ezra Mendoza', 'ezra@example.com', '9876543210', 'user', '2026-05-22 09:12:41'),
(3, 'tin', '12345', 'Christine Colecha', 'tin@gmail.com', '0987674857', 'user', '2026-05-22 09:12:41'),
(4, 'veang', 'veang', 'vea laparan', 'veang@gmail.com', '09367928148', 'user', '2026-05-23 14:36:01'),
(5, 'ric', '12345', 'Ric Badlis', 'rriz@gmail.com', '0987657967', 'user', '2026-05-25 12:31:25');

-- --------------------------------------------------------

--
-- Structure for view `reservation_details`
--
DROP TABLE IF EXISTS `reservation_details`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `reservation_details`  AS SELECT `r`.`reservation_id` AS `reservation_id`, concat(`g`.`first_name`,' ',`g`.`last_name`) AS `guest_name`, `g`.`email` AS `guest_email`, `g`.`phone` AS `guest_phone`, `room`.`room_number` AS `room_number`, `rt`.`type_name` AS `type_name`, `r`.`check_in_date` AS `check_in_date`, `r`.`check_out_date` AS `check_out_date`, `r`.`total_amount` AS `total_amount`, `r`.`status` AS `status`, to_days(`r`.`check_out_date`) - to_days(`r`.`check_in_date`) AS `nights_stayed`, `u`.`full_name` AS `created_by_user` FROM ((((`reservations` `r` join `guests` `g` on(`r`.`guest_id` = `g`.`guest_id`)) join `rooms` `room` on(`r`.`room_id` = `room`.`room_id`)) join `room_types` `rt` on(`room`.`type_id` = `rt`.`type_id`)) join `users` `u` on(`r`.`created_by` = `u`.`user_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `revenue_report`
--
DROP TABLE IF EXISTS `revenue_report`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `revenue_report`  AS SELECT date_format(`p`.`payment_date`,'%Y-%m') AS `month`, count(`p`.`payment_id`) AS `payment_count`, sum(`p`.`amount`) AS `total_revenue`, avg(`p`.`amount`) AS `average_payment` FROM `payments` AS `p` WHERE `p`.`status` = 'completed' GROUP BY date_format(`p`.`payment_date`,'%Y-%m') ORDER BY date_format(`p`.`payment_date`,'%Y-%m') DESC ;

-- --------------------------------------------------------

--
-- Structure for view `room_occupancy`
--
DROP TABLE IF EXISTS `room_occupancy`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `room_occupancy`  AS SELECT `rt`.`type_name` AS `type_name`, count(`r`.`room_id`) AS `total_rooms`, sum(case when `r`.`status` = 'available' then 1 else 0 end) AS `available_rooms`, sum(case when `r`.`status` = 'occupied' then 1 else 0 end) AS `occupied_rooms`, sum(case when `r`.`status` = 'maintenance' then 1 else 0 end) AS `maintenance_rooms`, sum(case when `r`.`status` = 'reserved' then 1 else 0 end) AS `reserved_rooms` FROM (`room_types` `rt` left join `rooms` `r` on(`rt`.`type_id` = `r`.`type_id`)) GROUP BY `rt`.`type_id`, `rt`.`type_name` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `guests`
--
ALTER TABLE `guests`
  ADD PRIMARY KEY (`guest_id`),
  ADD UNIQUE KEY `id_number` (`id_number`),
  ADD KEY `idx_guests_email` (`email`);

--
-- Indexes for table `guest_services`
--
ALTER TABLE `guest_services`
  ADD PRIMARY KEY (`guest_service_id`),
  ADD KEY `reservation_id` (`reservation_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `reservation_id` (`reservation_id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`reservation_id`),
  ADD KEY `guest_id` (`guest_id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_reservations_dates` (`check_in_date`,`check_out_date`),
  ADD KEY `idx_reservations_status` (`status`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`room_id`),
  ADD UNIQUE KEY `room_number` (`room_number`),
  ADD KEY `type_id` (`type_id`),
  ADD KEY `idx_rooms_status` (`status`);

--
-- Indexes for table `room_types`
--
ALTER TABLE `room_types`
  ADD PRIMARY KEY (`type_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`service_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `guests`
--
ALTER TABLE `guests`
  MODIFY `guest_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `guest_services`
--
ALTER TABLE `guest_services`
  MODIFY `guest_service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `reservation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `room_types`
--
ALTER TABLE `room_types`
  MODIFY `type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `guest_services`
--
ALTER TABLE `guest_services`
  ADD CONSTRAINT `guest_services_ibfk_1` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `guest_services_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`guest_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reservations_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `rooms`
--
ALTER TABLE `rooms`
  ADD CONSTRAINT `rooms_ibfk_1` FOREIGN KEY (`type_id`) REFERENCES `room_types` (`type_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
