ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin','staffcn','finance_cn','staffvn','customer') NOT NULL DEFAULT 'customer';
