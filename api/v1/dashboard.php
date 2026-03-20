<?php
/**
 * API Dashboard endpoints
 * GET /api/v1/dashboard.php - Thống kê tổng quan
 */

require_once(__DIR__.'/bootstrap.php');
require_once(__DIR__.'/jwt.php');

$user = api_auth();
api_method('GET');

$monthStart = date('Y-m-01');

// Đếm đơn theo trạng thái
$statusCounts = $ToryHub->get_list_safe(
    "SELECT `status`, COUNT(*) as cnt FROM `orders` GROUP BY `status`"
);
$statuses = [];
foreach ($statusCounts as $row) {
    $statuses[$row['status']] = (int)$row['cnt'];
}

// Tổng đơn hôm nay
$today = date('Y-m-d');
$todayOrders = $ToryHub->get_row_safe(
    "SELECT COUNT(*) as cnt FROM `orders` WHERE DATE(`create_date`) = ?",
    [$today]
);

// Tổng packages
$totalPackages = $ToryHub->get_row_safe(
    "SELECT COUNT(*) as cnt FROM `packages`"
);

// Tổng khách hàng
$totalCustomers = $ToryHub->get_row_safe(
    "SELECT COUNT(*) as cnt FROM `customers`"
);

// Doanh thu tháng
$revenueThisMonth = $ToryHub->get_row_safe(
    "SELECT COALESCE(SUM(grand_total),0) as total FROM `orders` WHERE `status` != 'cancelled' AND DATE(create_date) >= ?",
    [$monthStart]
)['total'];

// Đơn tháng này
$ordersThisMonth = $ToryHub->get_row_safe(
    "SELECT COUNT(*) as cnt FROM `orders` WHERE DATE(create_date) >= ?",
    [$monthStart]
)['cnt'];

// Kiện đang chuyển
$packagesInTransit = $ToryHub->get_row_safe(
    "SELECT COUNT(*) as cnt FROM `packages` WHERE `status` IN ('cn_warehouse','shipping','vn_warehouse')"
)['cnt'];

// Chờ xử lý (tại kho TQ)
$pendingOrders = $ToryHub->get_row_safe(
    "SELECT COUNT(*) as cnt FROM `orders` WHERE `status` = 'cn_warehouse'"
)['cnt'];

// Tỷ giá
$exchangeRate = get_exchange_rate();

// Warehouse status
$cnWarehouseOrders = $ToryHub->get_row_safe(
    "SELECT COUNT(*) as cnt FROM `orders` WHERE `status` = 'cn_warehouse'"
)['cnt'];
$shippingOrders = $ToryHub->get_row_safe(
    "SELECT COUNT(*) as cnt FROM `orders` WHERE `status` = 'shipping'"
)['cnt'];
$vnWarehouseOrders = $ToryHub->get_row_safe(
    "SELECT COUNT(*) as cnt FROM `orders` WHERE `status` = 'vn_warehouse'"
)['cnt'];

// Đơn hàng gần đây
$recentOrders = $ToryHub->get_list_safe(
    "SELECT o.id, o.product_code, o.product_name, o.grand_total, o.status, o.create_date, c.fullname as customer_name
     FROM `orders` o LEFT JOIN `customers` c ON o.customer_id = c.id
     WHERE o.product_code IS NOT NULL AND o.product_code != ''
     ORDER BY o.create_date DESC LIMIT 10", []
);

api_success([
    'stats' => [
        'orders_by_status'   => $statuses,
        'orders_today'       => (int)($todayOrders['cnt'] ?? 0),
        'total_packages'     => (int)($totalPackages['cnt'] ?? 0),
        'total_customers'    => (int)($totalCustomers['cnt'] ?? 0),
        'revenue_this_month' => (float)($revenueThisMonth ?? 0),
        'orders_this_month'  => (int)($ordersThisMonth ?? 0),
        'packages_in_transit'=> (int)($packagesInTransit ?? 0),
        'pending_orders'     => (int)($pendingOrders ?? 0),
        'exchange_rate'      => (float)$exchangeRate,
        'warehouse' => [
            'cn_warehouse'   => (int)($cnWarehouseOrders ?? 0),
            'shipping'       => (int)($shippingOrders ?? 0),
            'vn_warehouse'   => (int)($vnWarehouseOrders ?? 0),
            'pending'        => (int)($pendingOrders ?? 0),
        ],
        'recent_orders'      => $recentOrders ?: [],
    ]
]);
