-- Add shipment unloading check columns
ALTER TABLE `shipments`
  ADD COLUMN `checked_by` INT(11) DEFAULT NULL AFTER `note`,
  ADD COLUMN `checked_date` DATETIME DEFAULT NULL AFTER `checked_by`,
  ADD COLUMN `check_matched` INT(11) NOT NULL DEFAULT 0 AFTER `checked_date`,
  ADD COLUMN `check_missing` INT(11) NOT NULL DEFAULT 0 AFTER `check_matched`,
  ADD COLUMN `check_extra` INT(11) NOT NULL DEFAULT 0 AFTER `check_missing`,
  ADD COLUMN `check_notes` TEXT DEFAULT NULL AFTER `check_extra`;

-- Track check status per package in shipment
ALTER TABLE `shipment_packages`
  ADD COLUMN `check_status` ENUM('unchecked','matched','missing') NOT NULL DEFAULT 'unchecked' AFTER `added_at`,
  ADD COLUMN `check_note` VARCHAR(255) DEFAULT NULL AFTER `check_status`;

-- Extra items found during check (not in original shipment list)
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
