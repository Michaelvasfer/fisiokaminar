-- phpMyAdmin SQL Dump
-- Hostgator Initialization Script for FisioApp

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Table structure for table `users`
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role` enum('admin','receptionist','therapist','patient') NOT NULL DEFAULT 'patient',
  `name` varchar(255) NOT NULL,
  `dni` varchar(20) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `patient_code` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dni` (`dni`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `appointments`
CREATE TABLE `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `therapist_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `type` varchar(255) DEFAULT 'General Session',
  `status` enum('scheduled','completed','cancelled') DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`therapist_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `treatment_plans`
CREATE TABLE `treatment_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `duration_weeks` int(11) NOT NULL,
  `current_week` int(11) NOT NULL DEFAULT 1,
  `next_eval_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `objectives`
CREATE TABLE `objectives` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_id` int(11) NOT NULL,
  `metric_name` varchar(255) NOT NULL,
  `current_value` varchar(100) NOT NULL,
  `goal_value` varchar(100) NOT NULL,
  `progress_percentage` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`plan_id`) REFERENCES `treatment_plans`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `exercises`
CREATE TABLE `exercises` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`plan_id`) REFERENCES `treatment_plans`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `transactions`
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `type` enum('payment_received','package_purchase','deposit') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transaction_date` datetime NOT NULL,
  `description` varchar(255) NOT NULL,
  `payment_method` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `packages`
CREATE TABLE `packages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_sessions` int(11) NOT NULL,
  `unused_sessions` int(11) NOT NULL,
  `purchase_date` date NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert Mock Data
INSERT INTO `users` (`id`, `role`, `name`, `email`, `password`, `age`, `patient_code`) VALUES
(1, 'therapist', 'Dr. Smith', 'therapist@fisioapp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 40, NULL),
(2, 'patient', 'Sarah Jenkins', 'sarah@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 34, '#PT-88210'),
(3, 'patient', 'John Doe', 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 28, '#44290'),
(4, 'patient', 'Alex Johnson', 'alex@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 45, '#44291'),
(5, 'patient', 'Mike Ross', 'mike@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 30, '#PT-99812'),
(6, 'patient', 'Michael Chen', 'mchen@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 50, '#PT-77123'),
(7, 'admin', 'Super Admin', 'admin@fisioapp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL),
(8, 'receptionist', 'Ana Perez (Recep)', 'frontdesk@fisioapp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 25, NULL);

INSERT INTO `appointments` (`id`, `patient_id`, `therapist_id`, `appointment_date`, `start_time`, `end_time`, `type`, `status`) VALUES
(1, 3, 1, CURDATE(), '09:00:00', '10:00:00', 'Post-Op Knee Rehab • Session 4/10', 'scheduled'),
(2, 2, 1, CURDATE(), '10:30:00', '11:30:00', 'Chronic Back Pain • Initial Assessment', 'scheduled'),
(3, 6, 1, CURDATE(), '13:00:00', '14:00:00', 'Standard Therapy', 'scheduled'),
(4, 5, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '14:00:00', '15:00:00', 'Sports Injury • Session 8/12', 'scheduled');

INSERT INTO `treatment_plans` (`id`, `patient_id`, `title`, `duration_weeks`, `current_week`, `next_eval_date`) VALUES
(1, 4, 'Post-Op ACL Recovery', 12, 4, '2023-10-14'),
(2, 2, 'Post-Op Recovery Phase 1', 8, 2, '2023-11-15');

INSERT INTO `objectives` (`id`, `plan_id`, `metric_name`, `current_value`, `goal_value`, `progress_percentage`) VALUES
(1, 1, 'Range of Motion (Extension)', '120°', '140°', 85),
(2, 1, 'Quadriceps Strength', 'Level 3', 'Level 5', 60);

INSERT INTO `exercises` (`id`, `plan_id`, `name`) VALUES
(1, 1, 'Seated Knee Extension'),
(2, 1, 'Quad Sets'),
(3, 1, 'Heel Slides');

INSERT INTO `transactions` (`id`, `patient_id`, `type`, `amount`, `transaction_date`, `description`, `payment_method`) VALUES
(1, 4, 'payment_received', 120.00, '2023-10-24 10:00:00', 'Payment Received', 'Visa ****4242'),
(2, 4, 'package_purchase', -650.00, '2023-10-12 09:00:00', '10-Session Pack', 'Credit Card'),
(3, 4, 'deposit', 200.00, '2023-10-01 08:00:00', 'Deposit Payment', 'Cash');

INSERT INTO `packages` (`id`, `patient_id`, `name`, `total_sessions`, `unused_sessions`, `purchase_date`) VALUES
(1, 4, '10-Session Physio Pack', 10, 14, '2023-10-12'), 
(2, 4, '8-Session Rehab Pack', 8, 8, '2023-09-05');

COMMIT;
