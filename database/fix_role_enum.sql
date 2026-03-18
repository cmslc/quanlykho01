-- ============================================================
-- FIX: Role column + Missing tables
-- Chạy trên phpMyAdmin (local và hosting)
-- An toàn để chạy nhiều lần
-- ============================================================

-- ========== FIX 1: Role column ==========
-- Đổi sang VARCHAR để tránh lỗi ENUM không khớp giá trị
ALTER TABLE `users` MODIFY COLUMN `role` VARCHAR(20) NOT NULL DEFAULT 'customer';

-- Cập nhật giá trị cũ nếu có
UPDATE `users` SET `role` = 'staffcn' WHERE `role` = 'staff_cn';
UPDATE `users` SET `role` = 'staffvn' WHERE `role` = 'staff_vn';

-- ========== FIX 2: Missing StaffVN tables ==========

-- Package photos
CREATE TABLE IF NOT EXISTS `package_photos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `package_id` INT DEFAULT NULL,
    `order_id` INT DEFAULT NULL,
    `photo_path` VARCHAR(500) NOT NULL,
    `photo_type` ENUM('receive','delivery','damage','other') NOT NULL DEFAULT 'receive',
    `note` TEXT DEFAULT NULL,
    `uploaded_by` INT NOT NULL,
    `create_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_pp_package` (`package_id`),
    INDEX `idx_pp_order` (`order_id`),
    INDEX `idx_pp_type` (`photo_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- COD collections
CREATE TABLE IF NOT EXISTS `cod_collections` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `customer_id` INT NOT NULL,
    `amount` DECIMAL(15,0) NOT NULL DEFAULT 0,
    `payment_method` ENUM('cash','transfer','balance') NOT NULL DEFAULT 'cash',
    `transaction_id` INT DEFAULT NULL,
    `note` TEXT DEFAULT NULL,
    `collected_by` INT NOT NULL,
    `create_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_cod_order` (`order_id`),
    INDEX `idx_cod_customer` (`customer_id`),
    INDEX `idx_cod_collector` (`collected_by`),
    INDEX `idx_cod_date` (`create_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Delivery batches
CREATE TABLE IF NOT EXISTS `delivery_batches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `batch_code` VARCHAR(50) NOT NULL UNIQUE,
    `staff_id` INT NOT NULL,
    `status` ENUM('preparing','delivering','completed') NOT NULL DEFAULT 'preparing',
    `total_orders` INT DEFAULT 0,
    `total_amount` DECIMAL(15,0) DEFAULT 0,
    `total_collected` DECIMAL(15,0) DEFAULT 0,
    `note` TEXT DEFAULT NULL,
    `started_date` DATETIME DEFAULT NULL,
    `completed_date` DATETIME DEFAULT NULL,
    `create_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `update_date` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_db_staff` (`staff_id`),
    INDEX `idx_db_status` (`status`),
    INDEX `idx_db_date` (`create_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Delivery batch orders
CREATE TABLE IF NOT EXISTS `delivery_batch_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `batch_id` INT NOT NULL,
    `order_id` INT NOT NULL,
    `delivery_status` ENUM('pending','delivered','failed') NOT NULL DEFAULT 'pending',
    `cod_collected` TINYINT(1) DEFAULT 0,
    `cod_amount` DECIMAL(15,0) DEFAULT 0,
    `delivery_note` TEXT DEFAULT NULL,
    `delivery_photo` VARCHAR(500) DEFAULT NULL,
    `delivered_date` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_batch_order` (`batch_id`, `order_id`),
    INDEX `idx_dbo_batch` (`batch_id`),
    INDEX `idx_dbo_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory checks
CREATE TABLE IF NOT EXISTS `inventory_checks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `check_code` VARCHAR(50) NOT NULL UNIQUE,
    `staff_id` INT NOT NULL,
    `status` ENUM('in_progress','completed') NOT NULL DEFAULT 'in_progress',
    `total_expected` INT DEFAULT 0,
    `total_scanned` INT DEFAULT 0,
    `total_matched` INT DEFAULT 0,
    `total_missing` INT DEFAULT 0,
    `total_extra` INT DEFAULT 0,
    `note` TEXT DEFAULT NULL,
    `completed_date` DATETIME DEFAULT NULL,
    `create_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ic_staff` (`staff_id`),
    INDEX `idx_ic_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory check items
CREATE TABLE IF NOT EXISTS `inventory_check_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `check_id` INT NOT NULL,
    `package_id` INT DEFAULT NULL,
    `order_id` INT DEFAULT NULL,
    `barcode_scanned` VARCHAR(200) NOT NULL,
    `result` ENUM('matched','extra','not_found') NOT NULL DEFAULT 'matched',
    `scan_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ici_check` (`check_id`),
    INDEX `idx_ici_package` (`package_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== FIX 3: Missing columns ==========

-- orders: receive_photo
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'receive_photo');
SET @sql = IF(@col = 0, 'ALTER TABLE `orders` ADD COLUMN `receive_photo` VARCHAR(500) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- orders: delivery_photo
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'delivery_photo');
SET @sql = IF(@col = 0, 'ALTER TABLE `orders` ADD COLUMN `delivery_photo` VARCHAR(500) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- packages: receive_photo
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'packages' AND COLUMN_NAME = 'receive_photo');
SET @sql = IF(@col = 0, 'ALTER TABLE `packages` ADD COLUMN `receive_photo` VARCHAR(500) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- packages: received_by
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'packages' AND COLUMN_NAME = 'received_by');
SET @sql = IF(@col = 0, 'ALTER TABLE `packages` ADD COLUMN `received_by` INT DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Verify
SELECT `id`, `username`, `role` FROM `users` ORDER BY `id`;
SELECT 'All fixes completed!' AS result;
