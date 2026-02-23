<?php
define('IN_SITE', true);
require_once(__DIR__.'/../../libs/db.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../libs/lang.php');
require_once(__DIR__.'/../../libs/helper.php');
require_once(__DIR__.'/../../libs/session.php');
require_once(__DIR__.'/../../libs/role.php');
require_once(__DIR__.'/../../libs/csrf.php');
require_once(__DIR__.'/../../libs/database/orders.php');
require_once(__DIR__.'/../../models/is_staff_vn.php');

header('Content-Type: application/json; charset=utf-8');

$csrf = new Csrf();
if (!$csrf->validate()) {
    echo json_encode(['status' => 'error', 'msg' => __('Token không hợp lệ')]);
    exit;
}

if (!is_submit('mark_delivered')) {
    echo json_encode(['status' => 'error', 'msg' => __('Yêu cầu không hợp lệ')]);
    exit;
}

$order_id = intval(input_post('order_id'));
$note = trim(input_post('note'));

if (!$order_id) {
    echo json_encode(['status' => 'error', 'msg' => __('Thiếu thông tin đơn hàng')]);
    exit;
}

// Get order
$order = $ToryHub->get_row_safe("SELECT * FROM `orders` WHERE `id` = ?", [$order_id]);
if (!$order) {
    echo json_encode(['status' => 'error', 'msg' => __('Đơn hàng không tồn tại')]);
    exit;
}

// Only allow marking as delivered from vn_warehouse status
if ($order['status'] !== 'vn_warehouse') {
    echo json_encode(['status' => 'error', 'msg' => __('Chỉ có thể giao đơn hàng đang ở kho Việt Nam')]);
    exit;
}

// Update order status to delivered
$Orders = new Orders();
$result = $Orders->updateStatus($order_id, 'delivered', $getUser['id'], $note);

if ($result) {
    // Update delivery date
    $ToryHub->update_safe('orders', [
        'delivered_date' => gettime()
    ], "`id` = ?", [$order_id]);

    add_log($getUser['id'], 'DELIVERY', 'Giao hàng đơn ' . $order['order_code'] . ' - ' . ($note ?: 'Không có ghi chú'));

    echo json_encode(['status' => 'success', 'msg' => __('Đã xác nhận giao hàng thành công')]);
} else {
    echo json_encode(['status' => 'error', 'msg' => __('Lỗi cập nhật trạng thái đơn hàng')]);
}
