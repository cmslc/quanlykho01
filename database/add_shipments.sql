-- Shipments (Chuyến xe vận chuyển)
CREATE TABLE IF NOT EXISTS `shipments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `shipment_code` VARCHAR(50) NOT NULL UNIQUE,
  `truck_plate` VARCHAR(20) DEFAULT NULL,
  `driver_name` VARCHAR(100) DEFAULT NULL,
  `driver_phone` VARCHAR(20) DEFAULT NULL,
  `route` VARCHAR(255) DEFAULT NULL,
  `max_weight` DECIMAL(10,2) DEFAULT NULL,
  `shipping_method` VARCHAR(20) DEFAULT 'road',
  `status` ENUM('preparing','in_transit','arrived','completed') DEFAULT 'preparing',
  `total_packages` INT DEFAULT 0,
  `total_weight` DECIMAL(10,2) DEFAULT 0,
  `total_cbm` DECIMAL(10,6) DEFAULT 0,
  `shipping_cost` DECIMAL(15,2) DEFAULT NULL,
  `note` TEXT,
  `departed_date` DATETIME DEFAULT NULL,
  `arrived_date` DATETIME DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `create_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `update_date` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Junction table: shipment <-> packages
CREATE TABLE IF NOT EXISTS `shipment_packages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `shipment_id` INT NOT NULL,
  `package_id` INT NOT NULL,
  `added_by` INT DEFAULT NULL,
  `added_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_shipment_pkg` (`shipment_id`, `package_id`),
  FOREIGN KEY (`shipment_id`) REFERENCES `shipments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
