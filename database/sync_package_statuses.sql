-- Đồng bộ trạng thái mã hàng: thêm returned/damaged vào ENUM, thêm loading_date

-- 1. Packages: thêm returned, damaged vào ENUM
ALTER TABLE `packages`
  MODIFY COLUMN `status` ENUM('pending','cn_warehouse','packed','loading','shipping','vn_warehouse','delivered','returned','damaged') NOT NULL DEFAULT 'pending';

-- 2. Packages: thêm cột loading_date
ALTER TABLE `packages`
  ADD COLUMN IF NOT EXISTS `loading_date` DATETIME DEFAULT NULL AFTER `packed_date`;

-- 3. Orders: thêm cột loading_date
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `loading_date` DATETIME DEFAULT NULL AFTER `packed_date`;

-- 4. Cập nhật loading_date cho kiện đang ở trạng thái loading trở lên mà chưa có loading_date
UPDATE `packages`
SET `loading_date` = COALESCE(`shipping_date`, `update_date`)
WHERE `status` IN ('loading','shipping','vn_warehouse','delivered') AND `loading_date` IS NULL;

UPDATE `orders`
SET `loading_date` = COALESCE(`shipping_date`, `update_date`)
WHERE `status` IN ('loading','shipping','vn_warehouse','delivered') AND `loading_date` IS NULL;
