-- Add order_type column to orders table
-- Run this on existing database: purchase = mua hộ, shipping = ký gửi vận chuyển
ALTER TABLE `orders` ADD COLUMN `order_type` ENUM('purchase','shipping') NOT NULL DEFAULT 'purchase' AFTER `customer_id`;
