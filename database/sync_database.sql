-- ============================================================
-- Đồng bộ CSDL - Chạy tất cả migration chưa áp dụng
-- Safe to run multiple times (uses IF NOT EXISTS, IF EXISTS)
-- Chạy trên phpMyAdmin hoặc MySQL CLI
-- ============================================================

-- ===== 1. Bags: thêm cột kích thước =====
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bags' AND COLUMN_NAME = 'length_cm');
SET @sql = IF(@col = 0, 'ALTER TABLE `bags` ADD COLUMN `length_cm` DECIMAL(10,2) DEFAULT 0 AFTER `total_weight`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bags' AND COLUMN_NAME = 'width_cm');
SET @sql = IF(@col = 0, 'ALTER TABLE `bags` ADD COLUMN `width_cm` DECIMAL(10,2) DEFAULT 0 AFTER `length_cm`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bags' AND COLUMN_NAME = 'height_cm');
SET @sql = IF(@col = 0, 'ALTER TABLE `bags` ADD COLUMN `height_cm` DECIMAL(10,2) DEFAULT 0 AFTER `width_cm`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bags' AND COLUMN_NAME = 'weight_volume');
SET @sql = IF(@col = 0, 'ALTER TABLE `bags` ADD COLUMN `weight_volume` DECIMAL(10,2) DEFAULT 0 AFTER `height_cm`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ===== 2. Thêm 'loading' status vào packages, orders, bags =====
ALTER TABLE `packages`
  MODIFY COLUMN `status` ENUM('pending','cn_warehouse','packed','loading','shipping','vn_warehouse','delivered') NOT NULL DEFAULT 'pending';

ALTER TABLE `orders`
  MODIFY COLUMN `status` ENUM('pending','purchased','cn_shipped','cn_warehouse','packed','loading','shipping','vn_warehouse','delivered','cancelled') NOT NULL DEFAULT 'pending';

ALTER TABLE `bags`
  MODIFY COLUMN `status` ENUM('open','sealed','loading','shipping','arrived') NOT NULL DEFAULT 'open';

-- ===== 3. Xóa cột shipping_method (không dùng nữa) =====
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'packages' AND COLUMN_NAME = 'shipping_method');
SET @sql = IF(@col > 0, 'ALTER TABLE `packages` DROP COLUMN `shipping_method`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'shipping_method');
SET @sql = IF(@col > 0, 'ALTER TABLE `orders` DROP COLUMN `shipping_method`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bags' AND COLUMN_NAME = 'shipping_method');
SET @sql = IF(@col > 0, 'ALTER TABLE `bags` DROP COLUMN `shipping_method`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'shipping_method');
SET @sql = IF(@col > 0, 'ALTER TABLE `shipments` DROP COLUMN `shipping_method`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

DELETE FROM `settings` WHERE `name` IN ('shipping_rate_sea', 'shipping_rate_air');

-- ===== 4. Shipment check columns =====
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'checked_by');
SET @sql = IF(@col = 0, 'ALTER TABLE `shipments` ADD COLUMN `checked_by` INT(11) DEFAULT NULL AFTER `note`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'checked_date');
SET @sql = IF(@col = 0, 'ALTER TABLE `shipments` ADD COLUMN `checked_date` DATETIME DEFAULT NULL AFTER `checked_by`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'check_matched');
SET @sql = IF(@col = 0, 'ALTER TABLE `shipments` ADD COLUMN `check_matched` INT(11) NOT NULL DEFAULT 0 AFTER `checked_date`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'check_missing');
SET @sql = IF(@col = 0, 'ALTER TABLE `shipments` ADD COLUMN `check_missing` INT(11) NOT NULL DEFAULT 0 AFTER `check_matched`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'check_extra');
SET @sql = IF(@col = 0, 'ALTER TABLE `shipments` ADD COLUMN `check_extra` INT(11) NOT NULL DEFAULT 0 AFTER `check_missing`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'check_notes');
SET @sql = IF(@col = 0, 'ALTER TABLE `shipments` ADD COLUMN `check_notes` TEXT DEFAULT NULL AFTER `check_extra`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- shipment_packages: check columns
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shipment_packages' AND COLUMN_NAME = 'check_status');
SET @sql = IF(@col = 0, "ALTER TABLE `shipment_packages` ADD COLUMN `check_status` ENUM('unchecked','matched','missing') NOT NULL DEFAULT 'unchecked' AFTER `added_at`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shipment_packages' AND COLUMN_NAME = 'check_note');
SET @sql = IF(@col = 0, 'ALTER TABLE `shipment_packages` ADD COLUMN `check_note` VARCHAR(255) DEFAULT NULL AFTER `check_status`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Bảng extra items khi kiểm đếm
CREATE TABLE IF NOT EXISTS `shipment_check_extras` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `shipment_id` INT(11) NOT NULL,
    `barcode` VARCHAR(100) NOT NULL,
    `package_id` INT(11) DEFAULT NULL,
    `note` VARCHAR(255) DEFAULT NULL,
    `scanned_by` INT(11) DEFAULT NULL,
    `scanned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_shipment_id` (`shipment_id`),
    CONSTRAINT `fk_check_extras_shipment` FOREIGN KEY (`shipment_id`) REFERENCES `shipments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== 5. StaffVN module tables =====

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

-- Orders: photo columns
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'receive_photo');
SET @sql = IF(@col = 0, 'ALTER TABLE `orders` ADD COLUMN `receive_photo` VARCHAR(500) DEFAULT NULL AFTER `note_internal`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'delivery_photo');
SET @sql = IF(@col = 0, 'ALTER TABLE `orders` ADD COLUMN `delivery_photo` VARCHAR(500) DEFAULT NULL AFTER `receive_photo`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Packages: photo + received_by columns
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'packages' AND COLUMN_NAME = 'receive_photo');
SET @sql = IF(@col = 0, 'ALTER TABLE `packages` ADD COLUMN `receive_photo` VARCHAR(500) DEFAULT NULL AFTER `note`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'packages' AND COLUMN_NAME = 'received_by');
SET @sql = IF(@col = 0, 'ALTER TABLE `packages` ADD COLUMN `received_by` INT DEFAULT NULL AFTER `receive_photo`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ===== 6. Phase 2: Phân vùng kho + Thông báo khách =====

-- Warehouse zones
CREATE TABLE IF NOT EXISTS `warehouse_zones` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `zone_code` VARCHAR(20) NOT NULL,
    `zone_name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `create_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_zone_code` (`zone_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer notifications log
CREATE TABLE IF NOT EXISTS `customer_notifications` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `customer_id` INT(11) NOT NULL,
    `order_id` INT(11) DEFAULT NULL,
    `package_id` INT(11) DEFAULT NULL,
    `type` ENUM('arrived_vn','ready_delivery','delivered','custom') NOT NULL,
    `channel` ENUM('email','telegram','both') NOT NULL DEFAULT 'email',
    `message` TEXT NOT NULL,
    `sent_by` INT(11) DEFAULT NULL,
    `sent_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_customer` (`customer_id`),
    KEY `idx_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Packages: zone columns
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'packages' AND COLUMN_NAME = 'zone_id');
SET @sql = IF(@col = 0, 'ALTER TABLE `packages` ADD COLUMN `zone_id` INT(11) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'packages' AND COLUMN_NAME = 'shelf_position');
SET @sql = IF(@col = 0, 'ALTER TABLE `packages` ADD COLUMN `shelf_position` VARCHAR(50) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Customers: telegram chat_id
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'telegram_chat_id');
SET @sql = IF(@col = 0, 'ALTER TABLE `customers` ADD COLUMN `telegram_chat_id` VARCHAR(50) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ===== DONE =====
SELECT 'Database sync completed!' AS result;
