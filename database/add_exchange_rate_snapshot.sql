-- Snapshot tỉ giá tại thời điểm tạo dữ liệu
-- Dữ liệu cũ (NULL) sẽ fallback về tỉ giá hiện tại trong code

ALTER TABLE `expenses` ADD COLUMN `exchange_rate` DECIMAL(10,2) DEFAULT NULL AFTER `amount`;
ALTER TABLE `salaries` ADD COLUMN `exchange_rate` DECIMAL(10,2) DEFAULT NULL AFTER `currency`;
