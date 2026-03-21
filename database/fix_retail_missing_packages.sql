-- Fix: Tạo kiện cho các đơn hàng lẻ (retail) chưa có kiện
-- Chạy 1 lần duy nhất để bổ sung kiện cho đơn cũ bị lỗi
-- Ngày: 2026-03-21

-- Bước 1: Tìm các đơn hàng lẻ chưa có kiện
-- SELECT o.id, o.order_code, o.cn_tracking, o.status, o.create_date
-- FROM orders o
-- LEFT JOIN package_orders po ON po.order_id = o.id
-- WHERE o.product_type = 'retail' AND po.id IS NULL;

-- Bước 2: Tạo kiện + link cho từng đơn thiếu (dùng stored procedure tạm)
DELIMITER //
DROP PROCEDURE IF EXISTS fix_missing_packages//
CREATE PROCEDURE fix_missing_packages()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_order_id INT;
    DECLARE v_tracking VARCHAR(100);
    DECLARE v_status VARCHAR(50);
    DECLARE v_created_by INT;
    DECLARE v_create_date DATETIME;
    DECLARE v_pkg_code VARCHAR(50);
    DECLARE v_pkg_id INT;
    DECLARE v_count INT DEFAULT 0;

    DECLARE cur CURSOR FOR
        SELECT o.id, o.cn_tracking, o.status, o.created_by, o.create_date
        FROM orders o
        LEFT JOIN package_orders po ON po.order_id = o.id
        WHERE o.product_type = 'retail' AND po.id IS NULL;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN cur;

    read_loop: LOOP
        FETCH cur INTO v_order_id, v_tracking, v_status, v_created_by, v_create_date;
        IF done THEN
            LEAVE read_loop;
        END IF;

        -- Tạo mã kiện unique
        SET v_pkg_code = CONCAT('PKG', DATE_FORMAT(v_create_date, '%y%m%d'), UPPER(SUBSTRING(MD5(RAND()), 1, 5)));

        -- Insert package
        INSERT INTO packages (package_code, tracking_cn, status, created_by, create_date, update_date)
        VALUES (v_pkg_code, v_tracking, v_status, v_created_by, v_create_date, v_create_date);

        SET v_pkg_id = LAST_INSERT_ID();

        -- Link package to order
        INSERT INTO package_orders (package_id, order_id) VALUES (v_pkg_id, v_order_id);

        SET v_count = v_count + 1;
    END LOOP;

    CLOSE cur;

    SELECT CONCAT('Đã tạo ', v_count, ' kiện cho đơn hàng lẻ thiếu kiện') AS result;
END//
DELIMITER ;

-- Chạy procedure
CALL fix_missing_packages();

-- Dọn dẹp
DROP PROCEDURE IF EXISTS fix_missing_packages;
