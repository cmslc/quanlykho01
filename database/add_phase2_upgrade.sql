-- Phase 2 Upgrade: Tách bao, Phân vùng kho, Thông báo khách
-- Run after all previous migrations

-- ===== Warehouse Zones (Vùng/kệ kho) =====
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

-- ===== Customer Notifications Log =====
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

-- ===== Add zone columns to packages =====
ALTER TABLE `packages` ADD COLUMN IF NOT EXISTS `zone_id` INT(11) DEFAULT NULL;
ALTER TABLE `packages` ADD COLUMN IF NOT EXISTS `shelf_position` VARCHAR(50) DEFAULT NULL;

-- ===== Add telegram_chat_id to customers =====
ALTER TABLE `customers` ADD COLUMN IF NOT EXISTS `telegram_chat_id` VARCHAR(50) DEFAULT NULL;
