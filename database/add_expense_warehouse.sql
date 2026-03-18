-- Thêm cột warehouse cho bảng expenses để phân biệt chi phí kho TQ/VN
ALTER TABLE `expenses` ADD COLUMN IF NOT EXISTS `warehouse` ENUM('cn','vn') NOT NULL DEFAULT 'cn' AFTER `category`;

-- Cập nhật expenses hiện tại dựa trên role người tạo
UPDATE `expenses` e
JOIN `users` u ON e.created_by = u.id
SET e.warehouse = CASE
    WHEN u.role IN ('staffvn', 'finance_vn') THEN 'vn'
    ELSE 'cn'
END;
