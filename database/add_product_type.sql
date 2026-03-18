-- Migration: Add product_type, product_code columns to orders table
-- Also fix customer_id to allow NULL for retail orders (no customer)

-- Add product_type column (retail = hàng lẻ, wholesale = hàng lô)
ALTER TABLE `orders` ADD COLUMN `product_type` ENUM('retail','wholesale') NOT NULL DEFAULT 'retail' AFTER `order_type`;

-- Add product_code column
ALTER TABLE `orders` ADD COLUMN `product_code` VARCHAR(100) DEFAULT NULL AFTER `product_type`;

-- Allow customer_id to be NULL for retail orders
ALTER TABLE `orders` DROP FOREIGN KEY `orders_ibfk_1`;
ALTER TABLE `orders` MODIFY COLUMN `customer_id` INT DEFAULT NULL;
ALTER TABLE `orders` ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE RESTRICT;
