-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 06, 2025 at 04:10 PM
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
-- Database: `leave_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `leaves`
--

CREATE TABLE `leaves` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `leave_type` int(11) NOT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leaves`
--

INSERT INTO `leaves` (`id`, `user_id`, `leave_type`, `purpose`, `duration`, `start_date`, `end_date`, `status`, `applied_at`) VALUES
(1, 1, 3, 'Going out with my spouse BABY!!!!!!!!', 10, '2025-05-04', '2025-05-13', 'rejected', '2025-05-30 14:40:47'),
(2, 4, 4, 'Im about to conceive a child', 105, '2025-05-06', '2025-08-18', 'approved', '2025-05-30 14:41:22'),
(3, 1, 1, 'oisdhfoisdfosdf', 5, '2025-05-29', '2025-06-02', 'approved', '2025-05-30 18:04:06'),
(4, 5, 5, 'gotta look for my pregnant wife', 7, '2025-05-31', '2025-06-06', 'approved', '2025-05-31 12:06:25'),
(5, 5, 3, 'family vacation', 10, '2025-05-31', '2025-06-09', 'approved', '2025-05-31 14:17:18'),
(6, 5, 2, 'I gotta rest so I healed', 5, '2025-06-01', '2025-06-05', 'approved', '2025-06-01 01:00:11'),
(7, 7, 7, 'Attending my sons gradudation', 7, '2025-06-26', '2025-07-02', 'rejected', '2025-06-02 10:09:01'),
(8, 7, 7, 'Attending my sons gradudation', 7, '2025-06-26', '2025-07-02', 'rejected', '2025-06-02 10:09:01'),
(9, 7, 7, 'Attending my sons gradudation', 7, '2025-06-26', '2025-07-02', 'rejected', '2025-06-02 10:09:29'),
(10, 7, 7, 'Attending my sons gradudation', 7, '2025-06-26', '2025-07-02', 'rejected', '2025-06-02 10:09:29'),
(11, 7, 7, 'Attending my sons gradudation', 7, '2025-06-26', '2025-07-02', 'rejected', '2025-06-02 10:10:06'),
(12, 5, 2, 'gala', 5, '2025-06-02', '2025-06-06', 'rejected', '2025-06-02 11:19:59'),
(13, 5, 7, 'wala lang', 7, '2025-06-02', '2025-06-08', 'rejected', '2025-06-02 11:20:15'),
(14, 6, 2, 'To comply with doctor requirements of rest before working again', 5, '2025-06-06', '2025-06-10', 'approved', '2025-06-06 08:50:40'),
(15, 6, 3, 'Have to go to America for vacation', 10, '2025-06-06', '2025-06-15', 'rejected', '2025-06-06 10:02:01'),
(16, 8, 2, 'Im Sick I could not able to go to office and I going to go at the hospital for checking', 5, '2025-06-06', '2025-06-10', 'approved', '2025-06-06 12:54:00'),
(17, 6, 7, 'Need to attend graduation of my son', 7, '2025-06-06', '2025-06-12', 'approved', '2025-06-06 13:23:10'),
(18, 8, 7, 'I need to attend the graduation of my son', 7, '2025-06-06', '2025-06-12', 'approved', '2025-06-06 13:32:29');

-- --------------------------------------------------------

--
-- Table structure for table `leave_balances`
--

CREATE TABLE `leave_balances` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `balance` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--

CREATE TABLE `leave_types` (
  `id` int(11) NOT NULL,
  `name` varchar(80) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `days` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_types`
--

INSERT INTO `leave_types` (`id`, `name`, `description`, `days`) VALUES
(1, 'Service Incentive Leave (SIL)', '5 days paid leave per year after 1 year of service (required by law).', 0),
(2, 'Sick Leave', 'Paid leave for health-related issues. Number of days varies by company (common: 5–15 days).', 0),
(3, 'Vacation Leave', 'Paid leave for personal rest or travel. Not mandated but widely practiced (common: 10–15 days).', 0),
(4, 'Maternity Leave', '105 days paid leave (can extend up to 120 days for solo parents); covered by SSS and RA 11210.', 0),
(5, 'Paternity Leave', '7 days paid leave for married male employees (up to 4 children).', 0),
(6, 'Bereavement Leave', 'Leave for death of immediate family. Commonly 3–5 days; not required by law.', 0),
(7, 'Solo Parent Leave', '7 days per year for solo parents (RA 8972), after 1 year of service.', 0),
(8, 'Special Leave for Women', 'Up to 2 months leave for surgery due to gynecological disorders (RA 9710).', 0),
(9, 'Mourning Leave', 'This policy provides employees with paid time off in the event of the death of an immediate or extended family member. It is intended to allow employees time to grieve, attend funeral services, and manage related personal matters.', 7);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `job_title` varchar(100) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `birthday` date NOT NULL,
  `department` varchar(100) NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `date_of_joining` date NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `address` text NOT NULL,
  `role` enum('admin','employee') DEFAULT 'employee',
  `profile_picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `employee_id`, `name`, `email`, `password`, `job_title`, `contact_number`, `birthday`, `department`, `gender`, `date_of_joining`, `status`, `address`, `role`, `profile_picture`) VALUES
(1, '22-00336', 'Basman, Abdul Ganie II', 'ganiesama@gmail.com', '$2y$10$qCUTv62AMKpp9ptuHOdKw.m3mHa.fHhMxlTYq1OdzCyBbq7sjYBLW', 'Software Engineer', '09260488577', '2003-09-27', 'IT', 'male', '2003-02-08', 'active', 'secret', 'employee', 'uploads/084c2743c5841c83ef0deafb8ec08346.png'),
(2, '19-00907', 'Santos, Louis T.', 'louisantos23@gmail.com', '$2y$10$hYW3rnQeVeeSyp2AdLEN3uJuOxeTpo1iAjzc9Pq5WsKT2SsypeFKm', 'Project Manager', '09875643215', '2001-07-20', 'IT', 'male', '2025-05-12', 'active', 'asdas', 'admin', 'uploads/ecde7e3e1282a39aa5da8086e22e3e70.jpg'),
(4, '22-00343', 'Jarabelo, Cyd', 'cyd@gmail.com', '$2y$10$ppMlpW1HfXM2Bo7xfN0wW.kcVJk0XobSiTc.gFs0eEEBVjw44FItC', 'HR Manager', '098766545678', '2025-05-15', 'HR', 'male', '2025-05-01', 'active', 'asd', 'employee', 'uploads/77a4420b1947825bfded4156e79b40d6.png'),
(5, '022313131', 'Sean Sta Ana', 'seanstaana0510@gmail.com', '$2y$10$awxqBjSjD35PqKqIQRIAmeTTfHQHk.ObaPXTgaNvygFdE2uh.4UqC', 'Software Engineer', '09551255408', '2004-05-10', 'IT', 'male', '1029-05-05', 'active', 'Taguig City', 'employee', 'uploads/99142cbd15d48aecf399ee353189fc53.jpg'),
(6, '12345', 'louis santos', 'louis@gmail.com', '$2y$10$lC9hmPYAiaGCTVVnp4wxKuDR4aYHWEBGIEWbheqbvlbaeHU/un3Te', 'Software Engineer', '09551255408', '2025-06-06', 'IT', 'male', '2025-06-06', 'active', 'Makati city', 'employee', 'uploads/40d48bb3cbe7d1055342c17143947626.jpg'),
(7, '3423424', 'John Cruz', 'john@gmail.com', '$2y$10$/NYYsSKNKZDcMqGTEw1NcOmn6IEFGA8RtsWqXUQ32HrCeuKUv2XO2', 'Accountant', '09538170047', '2025-06-03', 'Finance', 'male', '2025-06-06', 'active', 'Pasig City', 'employee', 'uploads/806f44113f2ac5fb28764f24ad209175.jpg'),
(8, '1314234', 'Juan Dela Cruz', 'juan@gmail.com', '$2y$10$qfvpR6qPKM.lISysPCcTlu/GJUxqJf3Lj.D.0oxCIcuc4Z2vVdEDS', 'Software Engineer', '09551255408', '2025-06-06', 'IT', 'male', '2025-06-06', 'active', 'Makati', 'employee', 'uploads/febdc4ddf0697352814d3a1278bc312e.jpg');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `leaves`
--
ALTER TABLE `leaves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `leave_type` (`leave_type`);

--
-- Indexes for table `leave_balances`
--
ALTER TABLE `leave_balances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `leave_type_id` (`leave_type_id`);

--
-- Indexes for table `leave_types`
--
ALTER TABLE `leave_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `leaves`
--
ALTER TABLE `leaves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `leave_balances`
--
ALTER TABLE `leave_balances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `leaves`
--
ALTER TABLE `leaves`
  ADD CONSTRAINT `leaves_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `leaves_ibfk_2` FOREIGN KEY (`leave_type`) REFERENCES `leave_types` (`id`);

--
-- Constraints for table `leave_balances`
--
ALTER TABLE `leave_balances`
  ADD CONSTRAINT `leave_balances_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `leave_balances_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
