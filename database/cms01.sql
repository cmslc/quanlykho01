-- CMS01 - Warehouse & Shipping Management System
-- Database Schema

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `cms01` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `cms01`;

-- ========================================
-- 1. Settings (key-value config)
-- ========================================
CREATE TABLE `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `value` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`name`, `value`) VALUES
('title', 'CMS01 - Quản lý Kho & Vận chuyển'),
('timezone', 'Asia/Ho_Chi_Minh'),
('session_login', '86400'),
('type_password', 'bcrypt'),
('status', '1'),
('home_page', 'home'),
('domains', 'localhost'),
('exchange_rate_cny_vnd', '3500'),
('shipping_rate_road', '25000'),
('shipping_rate_sea', '15000'),
('shipping_rate_air', '120000'),
('service_fee_percent', '3'),
('volume_divisor', '6000'),
('currency', 'VND');

-- ========================================
-- 2. Users (4 roles)
-- ========================================
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `email` VARCHAR(255) DEFAULT NULL,
    `password` VARCHAR(255) NOT NULL,
    `fullname` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `token` VARCHAR(255) DEFAULT NULL,
    `role` ENUM('admin','staff_cn','staff_vn','customer') NOT NULL DEFAULT 'customer',
    `banned` TINYINT(1) NOT NULL DEFAULT 0,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `language` VARCHAR(5) DEFAULT 'vi',
    `ip` VARCHAR(45) DEFAULT NULL,
    `device` VARCHAR(500) DEFAULT NULL,
    `time_session` INT DEFAULT 0,
    `otp` VARCHAR(10) DEFAULT NULL,
    `otp_token` VARCHAR(255) DEFAULT NULL,
    `create_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `update_date` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin account (password: admin123)
INSERT INTO `users` (`username`, `email`, `password`, `fullname`, `role`, `active`) VALUES
('admin', 'admin@cms01.com', '$2y$10$kF3ULEwPLWIC9VQDAP.WM.4EGn2eFBoBANAO3uO2dzl5yf243ffVy', 'Administrator', 'admin', 1);

-- ========================================
-- 3. Customers
-- ========================================
CREATE TABLE `customers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_code` VARCHAR(20) NOT NULL UNIQUE,
    `fullname` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `address_vn` TEXT DEFAULT NULL,
    `address_cn` TEXT DEFAULT NULL,
    `zalo` VARCHAR(50) DEFAULT NULL,
    `wechat` VARCHAR(50) DEFAULT NULL,
    `customer_type` ENUM('normal','vip','agent') NOT NULL DEFAULT 'normal',
    `total_orders` INT NOT NULL DEFAULT 0,
    `total_spent` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `note` TEXT DEFAULT NULL,
    `user_id` INT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `create_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `update_date` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_customer_code` (`customer_code`),
    INDEX `idx_phone` (`phone`),
    INDEX `idx_customer_type` (`customer_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 4. Orders
-- ========================================
CREATE TABLE `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_code` VARCHAR(30) NOT NULL UNIQUE,
    `customer_id` INT DEFAULT NULL,
    `order_type` ENUM('purchase','shipping') NOT NULL DEFAULT 'purchase',
    `product_type` ENUM('retail','wholesale') NOT NULL DEFAULT 'retail',
    `product_code` VARCHAR(100) DEFAULT NULL,
    `platform` ENUM('taobao','1688','alibaba','other') DEFAULT 'taobao',
    `source_url` TEXT DEFAULT NULL,
    `status` ENUM('pending','purchased','cn_shipped','cn_warehouse','shipping','vn_warehouse','delivered','cancelled') NOT NULL DEFAULT 'pending',

    -- Product info
    `product_name` VARCHAR(500) DEFAULT NULL,
    `product_image` VARCHAR(500) DEFAULT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `unit_price_cny` DECIMAL(12,2) DEFAULT 0.00,
    `total_cny` DECIMAL(12,2) DEFAULT 0.00,
    `exchange_rate` DECIMAL(10,2) DEFAULT 0.00,
    `total_vnd` DECIMAL(15,2) DEFAULT 0.00,

    -- Fees
    `service_fee` DECIMAL(12,2) DEFAULT 0.00,
    `shipping_fee_cn` DECIMAL(12,2) DEFAULT 0.00,
    `shipping_fee_intl` DECIMAL(12,2) DEFAULT 0.00,
    `packing_fee` DECIMAL(12,2) DEFAULT 0.00,
    `insurance_fee` DECIMAL(12,2) DEFAULT 0.00,
    `other_fee` DECIMAL(12,2) DEFAULT 0.00,
    `total_fee` DECIMAL(12,2) DEFAULT 0.00,
    `grand_total` DECIMAL(15,2) DEFAULT 0.00,

    -- Shipping method
    `shipping_method` ENUM('road','sea','air') DEFAULT 'road',

    -- Tracking
    `cn_tracking` VARCHAR(100) DEFAULT NULL,
    `intl_tracking` VARCHAR(100) DEFAULT NULL,
    `vn_tracking` VARCHAR(100) DEFAULT NULL,

    -- Weight & dimensions
    `weight_actual` DECIMAL(8,2) DEFAULT NULL,
    `weight_volume` DECIMAL(8,2) DEFAULT NULL,
    `weight_charged` DECIMAL(8,2) DEFAULT NULL,
    `dimensions` VARCHAR(50) DEFAULT NULL,
    `length_cm` DECIMAL(8,2) DEFAULT NULL,
    `width_cm` DECIMAL(8,2) DEFAULT NULL,
    `height_cm` DECIMAL(8,2) DEFAULT NULL,

    -- Notes
    `note` TEXT DEFAULT NULL,
    `note_internal` TEXT DEFAULT NULL,

    -- Scan timestamps
    `cn_warehouse_date` DATETIME DEFAULT NULL,
    `shipping_date` DATETIME DEFAULT NULL,
    `vn_warehouse_date` DATETIME DEFAULT NULL,
    `delivered_date` DATETIME DEFAULT NULL,

    -- Payment
    `is_paid` TINYINT(1) NOT NULL DEFAULT 0,
    `paid_date` DATETIME DEFAULT NULL,

    -- Metadata
    `created_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `create_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `update_date` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_order_code` (`order_code`),
    INDEX `idx_customer_id` (`customer_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_platform` (`platform`),
    INDEX `idx_cn_tracking` (`cn_tracking`),
    INDEX `idx_intl_tracking` (`intl_tracking`),
    INDEX `idx_vn_tracking` (`vn_tracking`),
    INDEX `idx_create_date` (`create_date`),
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 5. Order Status History
-- ========================================
CREATE TABLE `order_status_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `old_status` VARCHAR(30) DEFAULT NULL,
    `new_status` VARCHAR(30) NOT NULL,
    `note` TEXT DEFAULT NULL,
    `changed_by` INT DEFAULT NULL,
    `create_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_order_id` (`order_id`),
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 6. Transactions (Finance)
-- ========================================
CREATE TABLE `transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `order_id` INT DEFAULT NULL,
    `type` ENUM('deposit','payment','refund','adjustment') NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `balance_before` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `balance_after` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `description` TEXT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `create_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_customer_id` (`customer_id`),
    INDEX `idx_order_id` (`order_id`),
    INDEX `idx_type` (`type`),
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 7. Languages
-- ========================================
CREATE TABLE `languages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lang` VARCHAR(5) NOT NULL UNIQUE,
    `name` VARCHAR(50) NOT NULL,
    `lang_default` TINYINT(1) NOT NULL DEFAULT 0,
    `status` TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `languages` (`lang`, `name`, `lang_default`, `status`) VALUES
('vi', 'Tiếng Việt', 1, 1),
('zh', '中文', 0, 1),
('en', 'English', 0, 1);

-- ========================================
-- 8. Translations
-- ========================================
CREATE TABLE `translate` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lang_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `value` TEXT NOT NULL,
    INDEX `idx_lang_name` (`lang_id`, `name`),
    FOREIGN KEY (`lang_id`) REFERENCES `languages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 9. Logs
-- ========================================
CREATE TABLE `logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `ip` VARCHAR(45) DEFAULT NULL,
    `create_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 10. Failed Attempts (Security)
-- ========================================
CREATE TABLE `failed_attempts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `attempts` INT NOT NULL DEFAULT 1,
    `type` VARCHAR(30) NOT NULL,
    `create_gettime` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ip_type` (`ip_address`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 11. Banned IPs
-- ========================================
CREATE TABLE `banned_ips` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip` VARCHAR(45) NOT NULL,
    `attempts` INT DEFAULT 0,
    `banned` TINYINT(1) NOT NULL DEFAULT 1,
    `reason` TEXT DEFAULT NULL,
    `create_gettime` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ip_banned` (`ip`, `banned`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 12. Packages (Kiện hàng)
-- ========================================
CREATE TABLE `packages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `package_code` VARCHAR(50) NOT NULL UNIQUE,
    `tracking_cn` VARCHAR(100) DEFAULT NULL,
    `tracking_intl` VARCHAR(100) DEFAULT NULL,
    `tracking_vn` VARCHAR(100) DEFAULT NULL,
    `weight_actual` DECIMAL(10,2) DEFAULT 0,
    `weight_volume` DECIMAL(10,2) DEFAULT 0,
    `weight_charged` DECIMAL(10,2) DEFAULT 0,
    `length_cm` DECIMAL(10,2) DEFAULT 0,
    `width_cm` DECIMAL(10,2) DEFAULT 0,
    `height_cm` DECIMAL(10,2) DEFAULT 0,
    `shipping_method` ENUM('road','sea','air') DEFAULT 'road',
    `status` ENUM('pending','cn_warehouse','shipping','vn_warehouse','delivered') NOT NULL DEFAULT 'pending',
    `cn_warehouse_date` DATETIME DEFAULT NULL,
    `shipping_date` DATETIME DEFAULT NULL,
    `vn_warehouse_date` DATETIME DEFAULT NULL,
    `delivered_date` DATETIME DEFAULT NULL,
    `note` TEXT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `create_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `update_date` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_pkg_code` (`package_code`),
    INDEX `idx_pkg_tracking_cn` (`tracking_cn`),
    INDEX `idx_pkg_tracking_intl` (`tracking_intl`),
    INDEX `idx_pkg_tracking_vn` (`tracking_vn`),
    INDEX `idx_pkg_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 13. Package-Order Junction (Many-to-Many)
-- ========================================
CREATE TABLE `package_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `package_id` INT NOT NULL,
    `order_id` INT NOT NULL,
    `note` TEXT DEFAULT NULL,
    `create_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_pkg_order` (`package_id`, `order_id`),
    INDEX `idx_po_package` (`package_id`),
    INDEX `idx_po_order` (`order_id`),
    FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 14. Package Status History
-- ========================================
CREATE TABLE `package_status_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `package_id` INT NOT NULL,
    `old_status` VARCHAR(30) DEFAULT NULL,
    `new_status` VARCHAR(30) NOT NULL,
    `note` TEXT DEFAULT NULL,
    `changed_by` INT DEFAULT NULL,
    `create_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_psh_package` (`package_id`),
    FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
