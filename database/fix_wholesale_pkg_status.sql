-- Fix kiện hàng của đơn wholesale đã về kho VN nhưng packages chưa đổi status
-- Chỉ cập nhật kiện đã rời TQ (packed/loading/shipping), giữ nguyên cn_warehouse

-- 1. Đơn đã xác nhận đủ: cập nhật kiện đã rời TQ → vn_warehouse
UPDATE `packages` p
JOIN `package_orders` po ON p.id = po.package_id
JOIN `orders` o ON po.order_id = o.id
SET p.status = 'vn_warehouse',
    p.vn_warehouse_date = NOW(),
    p.update_date = NOW()
WHERE o.product_type = 'wholesale'
  AND o.note LIKE '%Xác nhận đủ kiện%'
  AND p.status IN ('packed', 'loading', 'shipping');

-- 2. Đơn đã quét (status = vn_warehouse) nhưng chưa xác nhận: cập nhật kiện đã rời TQ
UPDATE `packages` p
JOIN `package_orders` po ON p.id = po.package_id
JOIN `orders` o ON po.order_id = o.id
SET p.status = 'vn_warehouse',
    p.vn_warehouse_date = NOW(),
    p.update_date = NOW()
WHERE o.product_type = 'wholesale'
  AND o.status = 'vn_warehouse'
  AND p.status IN ('packed', 'loading', 'shipping');
