SET NAMES utf8mb4;

-- Bảng lương nhân viên
CREATE TABLE IF NOT EXISTS `salaries` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `month` TINYINT NOT NULL,
    `year` SMALLINT NOT NULL,
    `currency` ENUM('VND','CNY') NOT NULL DEFAULT 'VND',
    `base_salary` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `allowance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `bonus` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `deduction` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `net_salary` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `work_days` TINYINT DEFAULT NULL,
    `note` TEXT DEFAULT NULL,
    `status` ENUM('draft','confirmed','paid') NOT NULL DEFAULT 'draft',
    `paid_date` DATETIME DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `create_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `update_date` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_month` (`user_id`, `month`, `year`),
    KEY `idx_month_year` (`month`, `year`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_salaries_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
