<?php
define("IN_SITE", true);
require_once(__DIR__.'/../../libs/db.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../libs/lang.php');
require_once(__DIR__.'/../../libs/helper.php');
require_once(__DIR__.'/../../libs/session.php');

header('Content-Type: application/json');

$CMSNT = new DB();

$order_code = check_string(input_post('order_code'));

if (empty($order_code)) {
    echo json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập mã đơn hàng')]);
    exit();
}

// Find order by order_code
$order = $CMSNT->get_row_safe("SELECT `id`, `order_code`, `product_name`, `quantity`, `status`,
    `cn_tracking`, `intl_tracking`, `vn_tracking`, `create_date`, `update_date`
    FROM `orders` WHERE `order_code` = ?", [$order_code]);

if (!$order) {
    echo json_encode(['status' => 'error', 'msg' => __('Không tìm thấy đơn hàng')]);
    exit();
}

// Status labels for display in JS
$statusFlow = ['cn_warehouse', 'shipping', 'vn_warehouse', 'delivered', 'cancelled'];
$status_labels = [];
foreach ($statusFlow as $s) {
    $status_labels[$s] = strip_tags(display_order_status($s));
}

// Get status history
$history = $CMSNT->get_list_safe("SELECT `old_status`, `new_status`, `note`, `create_date`
    FROM `order_status_history` WHERE `order_id` = ? ORDER BY `create_date` ASC", [$order['id']]);

$historyData = [];
foreach ($history as $h) {
    $historyData[] = [
        'old_status_badge' => display_order_status($h['old_status']),
        'new_status_badge' => display_order_status($h['new_status']),
        'note'             => htmlspecialchars($h['note'] ?? ''),
        'date'             => date('d/m H:i', strtotime($h['create_date'])),
    ];
}

echo json_encode([
    'status' => 'success',
    'data'   => [
        'order_code'    => htmlspecialchars($order['order_code']),
        'product_name'  => htmlspecialchars($order['product_name'] ?? ''),
        'quantity'      => $order['quantity'],
        'status'        => $order['status'],
        'status_badge'  => display_order_status($order['status']),
        'status_labels' => $status_labels,
        'cn_tracking'   => htmlspecialchars($order['cn_tracking'] ?? ''),
        'intl_tracking' => htmlspecialchars($order['intl_tracking'] ?? ''),
        'vn_tracking'   => htmlspecialchars($order['vn_tracking'] ?? ''),
        'create_date'   => $order['create_date'],
        'update_date'   => $order['update_date'],
        'history'       => $historyData,
    ]
]);
