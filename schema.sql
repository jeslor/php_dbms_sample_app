-- Trust Hardware - Database Schema
-- Compatible structure with the legacy shop-management schema
-- (users / categories / products / sales / settings / reset_requests)

CREATE DATABASE IF NOT EXISTS `trust_hardware_db`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

USE `trust_hardware_db`;

-- ---------------------------------------------------------------
-- users
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `role` enum('admin','employee') NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------
-- categories
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------
-- products
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `sku` varchar(50) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `low_stock_threshold` int(11) NOT NULL DEFAULT 10,
  `min_price` decimal(12,0) NOT NULL,
  `max_price` decimal(12,0) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------
-- sales
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_code` varchar(20) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(12,0) NOT NULL,
  `total` decimal(12,0) NOT NULL,
  `cashier` varchar(50) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `sale_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------
-- settings
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------
-- reset_requests (kept for schema compatibility with legacy system)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reset_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role` enum('admin','employee') NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','denied') DEFAULT 'pending',
  `request_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_date` timestamp NULL DEFAULT NULL,
  `resolved_by` varchar(50) DEFAULT NULL,
  `new_password_hash` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------
-- Seed data
-- ---------------------------------------------------------------

-- Default admin account: admin / admin123
-- Employee accounts are created from the Admin > Employees page (name + password each).
INSERT IGNORE INTO `users` (`username`, `role`, `password_hash`, `full_name`) VALUES
('admin', 'admin', '$2y$10$5iByUuRz.wTeXyIV8EMjSO3bxy3X6HZmYZdQkL8aRhfMq7D7RwNiq', 'Store Administrator');

INSERT IGNORE INTO `categories` (`name`) VALUES
('Power Tools'), ('Hand Tools'), ('Fasteners'), ('Plumbing'), ('Electrical'), ('Paint & Supplies'), ('Safety Gear'), ('Building Materials');

INSERT IGNORE INTO `products` (`name`, `sku`, `category_id`, `stock`, `low_stock_threshold`, `min_price`, `max_price`) VALUES
('Bosch Cordless Drill 18V', 'PWR-0001', 1, 14, 5,  320000, 380000),
('Angle Grinder 850W', 'PWR-0002', 1, 6,  5,  180000, 220000),
('Claw Hammer 16oz', 'HND-0001', 2, 40, 10, 15000,  22000),
('Adjustable Wrench 10"', 'HND-0002', 2, 25, 10, 18000,  25000),
('Assorted Screws Pack (200pc)', 'FAS-0001', 3, 60, 15, 8000,   12000),
('M8 Hex Bolts (50pc)', 'FAS-0002', 3, 3,  10, 10000,  14000),
('PVC Pipe 1/2" (3m)', 'PLB-0001', 4, 30, 10, 12000,  16000),
('Pipe Wrench 14"', 'PLB-0002', 4, 9,  5,  45000,  55000),
('Electrical Cable 2.5mm (100m)', 'ELE-0001', 5, 8,  5,  150000, 180000),
('LED Bulb 12W', 'ELE-0002', 5, 120, 20, 6000,   9000),
('Emulsion Paint 4L (White)', 'PNT-0001', 6, 18, 5,  65000,  80000),
('Paint Roller Set', 'PNT-0002', 6, 22, 5,  15000,  20000),
('Safety Helmet', 'SAF-0001', 7, 0,  5,  25000,  32000),
('Work Gloves (Pair)', 'SAF-0002', 7, 50, 10, 7000,   10000),
('Cement 50kg Bag', 'BLD-0001', 8, 45, 10, 38000,  42000);

INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('tax_rate', '0'),
('store_name', 'Trust Hardware'),
('store_address', ''),
('store_phone', ''),
('receipt_footer', 'Thank you for shopping with us!'),
('receipt_width', '80');
