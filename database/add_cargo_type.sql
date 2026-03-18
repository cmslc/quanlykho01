-- Add cargo_type column to orders table (for wholesale: easy/difficult transport)
ALTER TABLE `orders` ADD COLUMN `cargo_type` VARCHAR(20) DEFAULT NULL AFTER `product_type`;
