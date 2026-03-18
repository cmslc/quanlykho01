-- Thêm status 'completed' (Đã nhận đủ) cho bảng bags
ALTER TABLE `bags`
  MODIFY COLUMN `status` ENUM('open','sealed','loading','shipping','arrived','completed') NOT NULL DEFAULT 'open';
