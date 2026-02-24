-- Add 'loading' status to packages, orders, and bags tables
-- This is needed for the shipment loading workflow (Đang xếp xe)

-- Packages table
ALTER TABLE `packages`
  MODIFY COLUMN `status` ENUM('pending','cn_warehouse','packed','loading','shipping','vn_warehouse','delivered') NOT NULL DEFAULT 'pending';

-- Orders table
ALTER TABLE `orders`
  MODIFY COLUMN `status` ENUM('pending','purchased','cn_shipped','cn_warehouse','packed','loading','shipping','vn_warehouse','delivered','cancelled') NOT NULL DEFAULT 'pending';

-- Bags table (thêm 'loading' giữa 'sealed' và 'shipping')
ALTER TABLE `bags`
  MODIFY COLUMN `status` ENUM('open','sealed','loading','shipping','arrived') NOT NULL DEFAULT 'open';
