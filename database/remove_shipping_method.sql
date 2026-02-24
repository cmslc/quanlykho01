-- Remove shipping_method columns from all tables
-- System only uses road transport, no need for shipping method classification

ALTER TABLE `packages` DROP COLUMN IF EXISTS `shipping_method`;
ALTER TABLE `orders` DROP COLUMN IF EXISTS `shipping_method`;
ALTER TABLE `bags` DROP COLUMN IF EXISTS `shipping_method`;
ALTER TABLE `shipments` DROP COLUMN IF EXISTS `shipping_method`;

-- Also remove unused shipping rate settings (sea/air)
DELETE FROM `settings` WHERE `name` IN ('shipping_rate_sea', 'shipping_rate_air');
