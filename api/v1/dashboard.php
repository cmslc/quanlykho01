<?php
/**
 * API Dashboard endpoints
 * GET /api/v1/dashboard.php - Thống kê tổng quan
 */

require_once(__DIR__.'/bootstrap.php');
require_once(__DIR__.'/jwt.php');

$user = api_auth();
api_method('GET');

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

api_success([
    'stats' => [
        'orders_by_status' => $statuses,
        'orders_today'     => (int)($todayOrders['cnt'] ?? 0),
        'total_packages'   => (int)($totalPackages['cnt'] ?? 0),
        'total_customers'  => (int)($totalCustomers['cnt'] ?? 0)
    ]
]);
