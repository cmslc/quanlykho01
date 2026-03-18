-- Bảng bao hàng (bags)
CREATE TABLE IF NOT EXISTS `bags` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `bag_code` VARCHAR(50) NOT NULL DEFAULT '',
    `status` ENUM('open','sealed','shipping','arrived') NOT NULL DEFAULT 'open',
    `total_packages` INT(11) NOT NULL DEFAULT 0,
    `total_weight` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `shipping_method` VARCHAR(20) NOT NULL DEFAULT 'road',
    `note` TEXT DEFAULT NULL,
    `images` TEXT DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `sealed_by` INT(11) DEFAULT NULL,
    `sealed_date` DATETIME DEFAULT NULL,
    `create_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `update_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `bag_code` (`bag_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng liên kết bao - kiện hàng
CREATE TABLE IF NOT EXISTS `bag_packages` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `bag_id` INT(11) NOT NULL,
    `package_id` INT(11) NOT NULL,
    `scanned_by` INT(11) DEFAULT NULL,
    `scanned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_bag_package` (`bag_id`, `package_id`),
    KEY `idx_bag_id` (`bag_id`),
    KEY `idx_package_id` (`package_id`),
    CONSTRAINT `fk_bag_packages_bag` FOREIGN KEY (`bag_id`) REFERENCES `bags`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bag_packages_package` FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Thêm cột images vào bags
ALTER TABLE `bags` ADD COLUMN `images` TEXT DEFAULT NULL AFTER `note`;

-- Thêm trạng thái packed vào packages (nếu cột status là ENUM, cần ALTER)
-- Nếu status là VARCHAR thì không cần alter, chỉ cần code hỗ trợ giá trị mới
-- ALTER TABLE `packages` MODIFY `status` VARCHAR(20) NOT NULL DEFAULT 'pending';

-- Thêm cột bag_id vào packages để tra cứu nhanh (optional, đã có junction table)
