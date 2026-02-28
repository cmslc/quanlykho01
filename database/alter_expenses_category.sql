-- Đổi cột category từ ENUM sang VARCHAR để nhập danh mục tự do
ALTER TABLE `expenses` MODIFY COLUMN `category` varchar(100) NOT NULL;

-- Xóa các bản ghi có category rỗng (do ENUM không nhận giá trị tự do)
DELETE FROM `expenses` WHERE `category` = '';
