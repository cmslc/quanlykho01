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
require_once(__DIR__.'/../../models/is_staffcn.php');

header('Content-Type: application/json; charset=utf-8');

$csrf = new Csrf();
if (!$csrf->validate()) {
    echo json_encode(['status' => 'error', 'msg' => 'Token không hợp lệ']);
    exit;
}

$request = input_post('request_name');

// ======== BULK UPDATE STATUS ========
if ($request === 'bulk_update_status') {
    $order_ids_raw = input_post('order_ids');
    $new_status = input_post('new_status');
    $note = trim(input_post('note') ?: '');

    $order_ids = array_filter(array_map('intval', explode(',', $order_ids_raw ?: '')));
    if (empty($order_ids)) {
        echo json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn ít nhất 1 đơn hàng')]);
        exit;
    }

    $validStatuses = ['cn_warehouse', 'packed', 'loading', 'shipping', 'vn_warehouse', 'delivered', 'cancelled'];
    if (!in_array($new_status, $validStatuses)) {
        echo json_encode(['status' => 'error', 'msg' => __('Trạng thái không hợp lệ')]);
        exit;
    }

    $Orders = new Orders();
    $success = 0;
    $skipped = 0;

    foreach ($order_ids as $oid) {
        $oid = intval($oid);
        $order = $ToryHub->get_row_safe("SELECT * FROM `orders` WHERE `id` = ?", [$oid]);
        if (!$order || $order['status'] === $new_status) {
            $skipped++;
            continue;
        }

        $result = $Orders->updateStatus($oid, $new_status, $getUser['id'], $note ?: __('Cập nhật hàng loạt'));
        if ($result) {
            add_log($getUser['id'], 'update_order_status', 'Đổi trạng thái đơn ' . $order['order_code'] . ': ' . $order['status'] . ' → ' . $new_status);
            $success++;
        }
    }

    $msg = __('Đã cập nhật') . ' ' . $success . '/' . count($order_ids) . ' ' . __('đơn hàng');
    if ($skipped > 0) {
        $msg .= ' (' . __('bỏ qua') . ' ' . $skipped . ')';
    }
    echo json_encode(['status' => 'success', 'msg' => $msg, 'updated' => $success, 'skipped' => $skipped]);
    exit;
}

// ======== SINGLE UPDATE STATUS ========
$order_id = intval(input_post('order_id'));
$new_status = input_post('new_status');
$note = trim(input_post('note'));

$order = $ToryHub->get_row_safe("SELECT * FROM `orders` WHERE `id` = ?", [$order_id]);
if (!$order) {
    echo json_encode(['status' => 'error', 'msg' => __('Đơn hàng không tồn tại')]);
    exit;
}

$validStatuses = ['cn_warehouse', 'packed', 'loading', 'shipping', 'vn_warehouse', 'delivered', 'cancelled'];
if (!in_array($new_status, $validStatuses)) {
    echo json_encode(['status' => 'error', 'msg' => __('Trạng thái không hợp lệ')]);
    exit;
}

if ($order['status'] === $new_status) {
    echo json_encode(['status' => 'error', 'msg' => __('Trạng thái không thay đổi')]);
    exit;
}

$Orders = new Orders();
$result = $Orders->updateStatus($order_id, $new_status, $getUser['id'], $note);

if ($result) {
    add_log($getUser['id'], 'update_order_status', 'Đổi trạng thái đơn ' . $order['order_code'] . ': ' . $order['status'] . ' → ' . $new_status);

    // Telegram notification
    require_once(__DIR__.'/../../libs/telegram.php');
    $bot = new TelegramBot();
    $bot->notifyStatusChange($order, $order['status'], $new_status);

    // Email notification to customer
    require_once(__DIR__.'/../../libs/email.php');
    $customer = $ToryHub->get_row_safe("SELECT `email` FROM `customers` WHERE `id` = ?", [$order['customer_id']]);
    if (!empty($customer['email'])) {
        email_notify('notifyStatusChange', $order, $customer['email'], $order['status'], $new_status);
    }

    echo json_encode(['status' => 'success', 'msg' => __('Cập nhật trạng thái thành công')]);
} else {
    echo json_encode(['status' => 'error', 'msg' => __('Lỗi cập nhật trạng thái')]);
}
