-- Add 'loading' status to packages and orders tables
-- This is needed for the shipment loading workflow (Đang xếp xe)

-- Packages table
ALTER TABLE `packages`
  MODIFY COLUMN `status` ENUM('pending','cn_warehouse','packed','loading','shipping','vn_warehouse','delivered') NOT NULL DEFAULT 'pending';

-- Orders table
ALTER TABLE `orders`
  MODIFY COLUMN `status` ENUM('pending','purchased','cn_shipped','cn_warehouse','packed','loading','shipping','vn_warehouse','delivered','cancelled') NOT NULL DEFAULT 'pending';
