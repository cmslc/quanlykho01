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
require_once(__DIR__.'/../../libs/database/packages.php');
require_once(__DIR__.'/../../libs/telegram.php');
require_once(__DIR__.'/../../models/is_staffvn.php');

header('Content-Type: application/json; charset=utf-8');

$csrf = new Csrf();
if (!$csrf->validate()) {
    echo json_encode(['status' => 'error', 'msg' => __('Token không hợp lệ')]);
    exit;
}

$request = input_post('request_name');

// ===== END SESSION - Telegram notification =====
if ($request === 'end_session') {
    $total     = (int) input_post('total');
    $success   = (int) input_post('success');
    $error     = (int) input_post('error');
    $duplicate = (int) input_post('duplicate');
    $mode      = input_post('mode') === 'delivered' ? __('Giao hàng') : __('Nhập kho Việt Nam');

    $staffName = $getUser['fullname'] ?? $getUser['username'];

    $message = "<b>📦 " . __('Kết thúc phiên quét') . " - " . __('Kho Việt Nam') . "</b>\n\n";
    $message .= "<b>" . __('Nhân viên') . ":</b> " . htmlspecialchars($staffName) . "\n";
    $message .= "<b>" . __('Chế độ') . ":</b> " . $mode . "\n";
    $message .= "<b>" . __('Thời gian') . ":</b> " . date('d/m/Y H:i:s') . "\n\n";
    $message .= "<b>" . __('Tổng quét') . ":</b> " . $total . "\n";
    $message .= "✅ " . __('Thành công') . ": " . $success . "\n";
    $message .= "❌ " . __('Lỗi') . ": " . $error . "\n";
    $message .= "🔁 " . __('Trùng') . ": " . $duplicate . "\n";

    try {
        $telegram = new TelegramBot();
        $telegram->sendMessage($message);
    } catch (Exception $e) {
        // Silent fail
    }

    add_log($getUser['id'], 'BATCH_SCAN_END', "Kho Việt Nam - Mode: {$mode} | Total: {$total} | Success: {$success} | Error: {$error} | Dup: {$duplicate}");

    $newCsrf = new Csrf();
    echo json_encode([
        'status'     => 'success',
        'msg'        => __('Đã gửi báo cáo phiên quét'),
        'csrf_token' => $newCsrf->get_token_value()
    ]);
    exit;
}

// ===== SCAN ORDER =====
if ($request !== 'scan_order') {
    echo json_encode(['status' => 'error', 'msg' => __('Yêu cầu không hợp lệ')]);
    exit;
}

$barcode = trim(input_post('barcode'));
$targetStatus = input_post('target_status');

if (empty($barcode)) {
    echo json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập mã vận đơn hoặc mã đơn hàng')]);
    exit;
}

// Validate target status
$validTargets = ['vn_warehouse', 'delivered'];
if (!in_array($targetStatus, $validTargets)) {
    $targetStatus = 'vn_warehouse';
}

// ===== PHASE 1: Package-first search =====
$package = $ToryHub->get_row_safe(
    "SELECT * FROM `packages` WHERE `tracking_intl` = ? OR `tracking_vn` = ? OR `package_code` = ? LIMIT 1",
    [$barcode, $barcode, $barcode]
);

if ($package) {
    $pkgModel = new Packages();

    // Get linked orders for response info
    $linkedOrders = $pkgModel->getOrdersByPackage($package['id']);
    $orderCodes = array_map(function($o) { return $o['order_code']; }, $linkedOrders);

    $orderInfo = [
        'order_code'    => $package['package_code'],
        'customer_name' => !empty($linkedOrders) ? htmlspecialchars($linkedOrders[0]['customer_name'] ?? 'N/A') : 'N/A',
        'intl_tracking' => htmlspecialchars($package['tracking_intl'] ?? '-'),
        'vn_tracking'   => htmlspecialchars($package['tracking_vn'] ?? '-'),
        'product_name'  => __('Kiện hàng') . ' (' . count($linkedOrders) . ' ' . __('đơn') . ')',
    ];

    // Check duplicate
    if ($package['status'] === $targetStatus) {
        $newCsrf = new Csrf();
        echo json_encode([
            'status'     => 'error',
            'error_type' => 'duplicate',
            'msg'        => __('Kiện hàng đã ở trạng thái') . ': ' . strip_tags(display_package_status($package['status'])) . ' (' . $package['package_code'] . ')',
            'order'      => $orderInfo,
            'csrf_token' => $newCsrf->get_token_value()
        ]);
        exit;
    }

    // Validate transition
    if ($targetStatus === 'vn_warehouse') {
        $allowedFrom = ['shipping'];
        $logAction = 'SCAN_PKG_VN_WAREHOUSE';
        $successMsg = __('Kiện hàng đã nhập kho Việt Nam');
    } else { // delivered
        $allowedFrom = ['vn_warehouse'];
        $logAction = 'SCAN_PKG_DELIVERED';
        $successMsg = __('Kiện hàng đã giao thành công');
    }

    if (!in_array($package['status'], $allowedFrom)) {
        $newCsrf = new Csrf();
        echo json_encode([
            'status'     => 'error',
            'error_type' => 'invalid_status',
            'msg'        => __('Trạng thái không hợp lệ') . '. ' . __('Hiện tại') . ': ' . strip_tags(display_package_status($package['status'])) . ' (' . $package['package_code'] . ')',
            'order'      => $orderInfo,
            'csrf_token' => $newCsrf->get_token_value()
        ]);
        exit;
    }

    // Update package status (auto-syncs linked orders)
    $result = $pkgModel->updateStatus($package['id'], $targetStatus, $getUser['id'],
        __('Batch scan bởi') . ' ' . ($getUser['fullname'] ?? $getUser['username']));

    if ($result === true) {
        add_log($getUser['id'], $logAction,
            'Quét kiện ' . $package['package_code'] . ': ' . $package['status'] . ' -> ' . $targetStatus
            . ' | Đơn: ' . implode(',', $orderCodes) . ' (barcode: ' . $barcode . ')');

        $newCsrf = new Csrf();
        echo json_encode([
            'status'     => 'success',
            'msg'        => $successMsg . ' | ' . $package['package_code']
                          . (!empty($orderCodes) ? ' → ' . implode(', ', $orderCodes) : ''),
            'order_code' => $package['package_code'],
            'order'      => $orderInfo,
            'csrf_token' => $newCsrf->get_token_value()
        ]);
    } else {
        $newCsrf = new Csrf();
        echo json_encode([
            'status'     => 'error',
            'msg'        => __('Lỗi cập nhật kiện hàng. Vui lòng thử lại.'),
            'csrf_token' => $newCsrf->get_token_value()
        ]);
    }
    exit;
}

// ===== PHASE 2: Fallback - search orders (backward compatible) =====
$order = $ToryHub->get_row_safe("SELECT o.*, c.fullname as customer_name, c.customer_code
    FROM `orders` o LEFT JOIN `customers` c ON o.customer_id = c.id
    WHERE o.intl_tracking = ? OR o.vn_tracking = ? OR o.order_code = ?
    LIMIT 1", [$barcode, $barcode, $barcode]);

if (!$order) {
    $newCsrf = new Csrf();
    echo json_encode([
        'status'     => 'error',
        'msg'        => __('Không tìm thấy kiện hàng hoặc đơn hàng với mã') . ': ' . htmlspecialchars($barcode),
        'csrf_token' => $newCsrf->get_token_value()
    ]);
    exit;
}

// Build order info
$orderInfo = [
    'order_code'    => $order['order_code'],
    'customer_name' => htmlspecialchars($order['customer_name'] ?? 'N/A'),
    'intl_tracking' => htmlspecialchars($order['intl_tracking'] ?? '-'),
    'vn_tracking'   => htmlspecialchars($order['vn_tracking'] ?? '-'),
    'product_name'  => htmlspecialchars(mb_strimwidth($order['product_name'] ?? '', 0, 30, '...')),
];

// Define valid transitions
if ($targetStatus === 'vn_warehouse') {
    $allowedFrom = ['shipping'];
    $alreadyStatus = 'vn_warehouse';
    $logAction = 'SCAN_VN_WAREHOUSE';
    $successMsg = __('Đã nhập kho Việt Nam thành công');
    $dateField = 'vn_warehouse_date';
} else { // delivered
    $allowedFrom = ['vn_warehouse'];
    $alreadyStatus = 'delivered';
    $logAction = 'SCAN_DELIVERED';
    $successMsg = __('Đã xác nhận giao hàng thành công');
    $dateField = 'delivered_date';
}

// Check duplicate
if ($order['status'] === $alreadyStatus) {
    $newCsrf = new Csrf();
    echo json_encode([
        'status'     => 'error',
        'error_type' => 'duplicate',
        'msg'        => __('Đơn hàng đã ở trạng thái') . ': ' . strip_tags(display_order_status($order['status'])) . ' (' . $order['order_code'] . ')',
        'order'      => $orderInfo,
        'csrf_token' => $newCsrf->get_token_value()
    ]);
    exit;
}

// Check valid transition
if (!in_array($order['status'], $allowedFrom)) {
    $newCsrf = new Csrf();
    echo json_encode([
        'status'     => 'error',
        'error_type' => 'invalid_status',
        'msg'        => __('Trạng thái không hợp lệ') . '. ' . __('Hiện tại') . ': ' . strip_tags(display_order_status($order['status'])) . ' (' . $order['order_code'] . ')',
        'order'      => $orderInfo,
        'csrf_token' => $newCsrf->get_token_value()
    ]);
    exit;
}

// Update order status
$oldStatus = $order['status'];
$updateData = [
    'status'      => $targetStatus,
    'update_date' => gettime(),
    $dateField    => gettime()
];

$result = $ToryHub->update_safe('orders', $updateData, "`id` = ?", [$order['id']]);

if ($result) {
    add_log($getUser['id'], $logAction, 'Quét ' . $order['order_code'] . ': ' . $oldStatus . ' -> ' . $targetStatus . ' (barcode: ' . $barcode . ')');

    $ToryHub->insert_safe('order_status_history', [
        'order_id'    => $order['id'],
        'old_status'  => $oldStatus,
        'new_status'  => $targetStatus,
        'changed_by'  => $getUser['id'],
        'note'        => __('Batch scan bởi') . ' ' . ($getUser['fullname'] ?? $getUser['username']),
        'create_date' => gettime()
    ]);

    $newCsrf = new Csrf();
    echo json_encode([
        'status'     => 'success',
        'msg'        => $successMsg,
        'order_code' => $order['order_code'],
        'order'      => $orderInfo,
        'csrf_token' => $newCsrf->get_token_value()
    ]);
} else {
    $newCsrf = new Csrf();
    echo json_encode([
        'status'     => 'error',
        'msg'        => __('Lỗi cập nhật. Vui lòng thử lại.'),
        'csrf_token' => $newCsrf->get_token_value()
    ]);
}
