-- Thêm cột missing_count để lưu số kiện thiếu (thay vì parse từ note)
ALTER TABLE `orders` ADD COLUMN `missing_count` INT(11) NOT NULL DEFAULT 0 AFTER `total_packages`;
