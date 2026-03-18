-- Thêm cột volume_actual (tổng khối hàng nhập tay) vào bảng orders
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'volume_actual');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `volume_actual` DECIMAL(10,6) DEFAULT NULL AFTER `weight_charged`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
