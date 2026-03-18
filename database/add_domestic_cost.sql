-- Thêm cột cước nội địa vào bảng bags và orders
ALTER TABLE `bags` ADD COLUMN `domestic_cost` DECIMAL(12,0) NOT NULL DEFAULT 0 AFTER `total_weight`;
ALTER TABLE `orders` ADD COLUMN `domestic_cost` DECIMAL(12,0) NOT NULL DEFAULT 0 AFTER `shipping_method`;

-- Thêm cột đơn giá tùy chỉnh vào bảng bags và orders
ALTER TABLE `bags` ADD COLUMN `custom_rate_kg` DECIMAL(12,0) DEFAULT NULL AFTER `domestic_cost`;
ALTER TABLE `bags` ADD COLUMN `custom_rate_cbm` DECIMAL(12,0) DEFAULT NULL AFTER `custom_rate_kg`;
ALTER TABLE `orders` ADD COLUMN `custom_rate_kg` DECIMAL(12,0) DEFAULT NULL AFTER `domestic_cost`;
ALTER TABLE `orders` ADD COLUMN `custom_rate_cbm` DECIMAL(12,0) DEFAULT NULL AFTER `custom_rate_kg`;
