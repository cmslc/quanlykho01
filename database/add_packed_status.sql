-- Add 'packed' status and packed_date column to packages and orders tables
-- This is needed for the bag packing workflow

-- Packages table
ALTER TABLE `packages`
  MODIFY COLUMN `status` ENUM('pending','cn_warehouse','packed','shipping','vn_warehouse','delivered') NOT NULL DEFAULT 'pending',
  ADD COLUMN `packed_date` DATETIME DEFAULT NULL AFTER `cn_warehouse_date`;

-- Orders table
ALTER TABLE `orders`
  MODIFY COLUMN `status` ENUM('pending','purchased','cn_shipped','cn_warehouse','packed','shipping','vn_warehouse','delivered','cancelled') NOT NULL DEFAULT 'pending',
  ADD COLUMN `packed_date` DATETIME DEFAULT NULL AFTER `cn_warehouse_date`;
