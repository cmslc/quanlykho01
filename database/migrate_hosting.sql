-- ============================================================
-- Migration: Ensure hosting database has all required tables/columns
-- Safe to run multiple times (uses IF NOT EXISTS / IF NOT column checks)
-- Run this on hosting phpMyAdmin
-- ============================================================

-- 1. Add missing columns to orders table
-- total_packages
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'total_packages');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `total_packages` INT(11) NOT NULL DEFAULT 0 AFTER `grand_total`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- order_type
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'order_type');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `order_type` ENUM(''purchase'',''shipping'') NOT NULL DEFAULT ''purchase'' AFTER `customer_id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- product_type
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'product_type');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `product_type` ENUM(''retail'',''wholesale'') NOT NULL DEFAULT ''retail'' AFTER `order_type`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- cargo_type
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'cargo_type');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `cargo_type` VARCHAR(20) DEFAULT NULL AFTER `product_type`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- product_code
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'product_code');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `product_code` VARCHAR(100) DEFAULT NULL AFTER `cargo_type`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- packed_date
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'packed_date');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `packed_date` DATETIME DEFAULT NULL AFTER `cn_warehouse_date`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- note_internal
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'note_internal');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `note_internal` TEXT DEFAULT NULL AFTER `note`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- domestic_cost
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'domestic_cost');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `domestic_cost` DECIMAL(12,0) NOT NULL DEFAULT 0 AFTER `shipping_method`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- custom_rate_kg
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'custom_rate_kg');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `custom_rate_kg` DECIMAL(12,0) DEFAULT NULL AFTER `domestic_cost`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- custom_rate_cbm
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'custom_rate_cbm');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `custom_rate_cbm` DECIMAL(12,0) DEFAULT NULL AFTER `custom_rate_kg`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Allow customer_id to be NULL
ALTER TABLE `orders` MODIFY COLUMN `customer_id` INT DEFAULT NULL;

-- Update orders status enum to include all values
ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('pending','purchased','cn_shipped','cn_warehouse','packed','shipping','vn_warehouse','delivered','cancelled') NOT NULL DEFAULT 'pending';

-- 2. Create packages table
CREATE TABLE IF NOT EXISTS `packages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `package_code` varchar(50) NOT NULL,
  `tracking_cn` varchar(100) DEFAULT NULL,
  `tracking_intl` varchar(100) DEFAULT NULL,
  `tracking_vn` varchar(100) DEFAULT NULL,
  `weight_actual` decimal(10,2) DEFAULT 0.00,
  `weight_volume` decimal(10,2) DEFAULT 0.00,
  `weight_charged` decimal(10,2) DEFAULT 0.00,
  `length_cm` decimal(10,2) DEFAULT 0.00,
  `width_cm` decimal(10,2) DEFAULT 0.00,
  `height_cm` decimal(10,2) DEFAULT 0.00,
  `shipping_method` enum('road','sea','air') DEFAULT 'road',
  `status` enum('pending','cn_warehouse','packed','shipping','vn_warehouse','delivered') NOT NULL DEFAULT 'pending',
  `cn_warehouse_date` datetime DEFAULT NULL,
  `packed_date` datetime DEFAULT NULL,
  `shipping_date` datetime DEFAULT NULL,
  `vn_warehouse_date` datetime DEFAULT NULL,
  `delivered_date` datetime DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `create_date` datetime DEFAULT current_timestamp(),
  `update_date` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `package_code` (`package_code`),
  KEY `idx_pkg_code` (`package_code`),
  KEY `idx_pkg_tracking_cn` (`tracking_cn`),
  KEY `idx_pkg_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create package_orders table
CREATE TABLE IF NOT EXISTS `package_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `package_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `note` text DEFAULT NULL,
  `create_date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pkg_order` (`package_id`,`order_id`),
  KEY `idx_po_package` (`package_id`),
  KEY `idx_po_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create package_status_history table
CREATE TABLE IF NOT EXISTS `package_status_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `package_id` int(11) NOT NULL,
  `old_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) NOT NULL,
  `note` text DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `create_date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_psh_package` (`package_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Create order_status_history table
CREATE TABLE IF NOT EXISTS `order_status_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `old_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) NOT NULL,
  `note` text DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `create_date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_osh_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Rename staff roles: staff_cn → staffcn, staff_vn → staffvn
ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin','staffcn','staffvn','customer','staff_cn','staff_vn') NOT NULL DEFAULT 'customer';
UPDATE `users` SET `role` = 'staffcn' WHERE `role` = 'staff_cn';
UPDATE `users` SET `role` = 'staffvn' WHERE `role` = 'staff_vn';
ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin','staffcn','staffvn','customer') NOT NULL DEFAULT 'customer';

-- Done!
SELECT 'Migration completed successfully!' AS result;
