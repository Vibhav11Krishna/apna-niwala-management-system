-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Sep 22, 2026 at 05:13 PM
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
-- Database: `apna_niwala_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `client_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `phone_no` varchar(15) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`client_id`, `name`, `address`, `phone_no`, `created_at`) VALUES
(1, 'pulkit krishna', 'Flat no. 403 rps more malti kunj near rp', '07209749002', '2026-09-21 21:34:17'),
(2, 'Jayant krishna', 'Flat no. 403 rps more malti kunj near rps law college ram raj path', '88989898989', '2026-09-21 22:21:02'),
(3, 'Rahul sharma', 'gola road', '987654321', '2026-09-22 14:19:36');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `expense_date` date NOT NULL,
  `category` enum('Vegetables','Grocery','Essentials','Other') NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `meal_name` varchar(150) NOT NULL,
  `diet_type` enum('Veg','Non-Veg') NOT NULL,
  `meal_date` date NOT NULL,
  `due_date` date NOT NULL,
  `meal_amount` decimal(10,2) NOT NULL,
  `amount_received` decimal(10,2) NOT NULL,
  `total_due` decimal(10,2) NOT NULL,
  `payment_mode` enum('Cash','UPI','Bank Transfer','Credit') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `client_id`, `meal_name`, `diet_type`, `meal_date`, `due_date`, `meal_amount`, `amount_received`, `total_due`, `payment_mode`, `created_at`, `remarks`) VALUES
(1, 1, 'Monthly Veg Plan (26 Meals)', 'Veg', '2026-09-21', '2026-09-28', 2899.00, 2899.00, 0.00, 'Cash', '2026-09-21 21:34:17', NULL),
(2, 1, 'Monthly Non-Veg Plan (26 Meals)', 'Non-Veg', '2026-09-22', '2026-09-29', 3399.00, 3399.00, 0.00, 'Cash', '2026-09-21 22:20:42', ''),
(3, 2, 'Mini Meal (2 Roti + Rice + Dal/Sabzi)', 'Veg', '2026-09-22', '2026-09-29', 79.00, 79.00, 0.00, 'Cash', '2026-09-21 22:21:02', ''),
(4, 3, 'Monthly Veg Plan (26 Meals)', 'Veg', '2026-09-03', '2026-09-30', 2899.00, 1500.00, 1399.00, 'Cash', '2026-09-22 14:19:36', 'less spicy');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`client_id`),
  ADD UNIQUE KEY `phone_no` (`phone_no`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `client_id` (`client_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `client_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
