-- Add dimension columns to bags table for volume calculation
ALTER TABLE `bags`
  ADD COLUMN `length_cm` DECIMAL(10,2) DEFAULT 0 AFTER `total_weight`,
  ADD COLUMN `width_cm` DECIMAL(10,2) DEFAULT 0 AFTER `length_cm`,
  ADD COLUMN `height_cm` DECIMAL(10,2) DEFAULT 0 AFTER `width_cm`,
  ADD COLUMN `weight_volume` DECIMAL(10,2) DEFAULT 0 AFTER `height_cm`;
