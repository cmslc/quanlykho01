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
require_once(__DIR__.'/../../libs/email.php');
require_once(__DIR__.'/../../models/is_staffvn.php');

header('Content-Type: application/json; charset=utf-8');

$csrf = new Csrf();
if (!$csrf->validate()) {
    echo json_encode(['status' => 'error', 'msg' => __('Token không hợp lệ')]);
    exit;
}

$request = input_post('request_name');

// ===== GET PHOTOS =====
if ($request === 'get_photos') {
    $package_id = intval(input_post('package_id'));
    $photos = $ToryHub->get_list_safe("SELECT * FROM `package_photos` WHERE `package_id` = ? ORDER BY `create_date` ASC", [$package_id]);
    $result = [];
    foreach ($photos as $p) {
        $result[] = ['id' => $p['id'], 'url' => get_upload_url($p['photo_path']), 'type' => $p['photo_type'], 'date' => $p['create_date']];
    }
    // Fallback: check package receive_photo if no photos in table
    if (empty($result)) {
        $pkg = $ToryHub->get_row_safe("SELECT `receive_photo` FROM `packages` WHERE `id` = ?", [$package_id]);
        if ($pkg && !empty($pkg['receive_photo'])) {
            $result[] = ['id' => 0, 'url' => get_upload_url($pkg['receive_photo']), 'type' => 'receive', 'date' => ''];
        }
    }
    $newCsrf = new Csrf();
    echo json_encode(['status' => 'success', 'photos' => $result, 'csrf_token' => $newCsrf->get_token_value()]);
    exit;
}

// ===== UPLOAD PHOTO =====
if ($request === 'upload_photo') {
    $entity_type = input_post('entity_type'); // package or order
    $entity_id = intval(input_post('entity_id'));
    $photo_type = input_post('photo_type') ?: 'receive';

    if (!in_array($entity_type, ['package', 'order']) || $entity_id <= 0) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Dữ liệu không hợp lệ'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    // Handle base64 upload
    $photo_base64 = input_post('photo_base64');
    if (!empty($photo_base64)) {
        // Remove data URI prefix if present
        if (preg_match('/^data:image\/(jpeg|png|webp);base64,/', $photo_base64, $matches)) {
            $ext = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
            $photo_base64 = preg_replace('/^data:image\/\w+;base64,/', '', $photo_base64);
        } else {
            $ext = 'jpg';
        }

        $imageData = base64_decode($photo_base64);
        if ($imageData === false || strlen($imageData) < 100) {
            $newCsrf = new Csrf();
            echo json_encode(['status' => 'error', 'msg' => __('Dữ liệu ảnh không hợp lệ'), 'csrf_token' => $newCsrf->get_token_value()]);
            exit;
        }

        // Max 5MB
        if (strlen($imageData) > 5 * 1024 * 1024) {
            $newCsrf = new Csrf();
            echo json_encode(['status' => 'error', 'msg' => __('Ảnh quá lớn (tối đa 5MB)'), 'csrf_token' => $newCsrf->get_token_value()]);
            exit;
        }

        $folder = 'packages';
        $upload_dir = __DIR__ . '/../../uploads/' . $folder . '/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $filepath = $upload_dir . $filename;

        if (file_put_contents($filepath, $imageData) === false) {
            $newCsrf = new Csrf();
            echo json_encode(['status' => 'error', 'msg' => __('Không thể lưu ảnh'), 'csrf_token' => $newCsrf->get_token_value()]);
            exit;
        }

        $photo_path = 'uploads/' . $folder . '/' . $filename;

    } elseif (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        // Handle file upload
        $result = upload_image($_FILES['photo'], 'packages');
        if ($result['status'] !== 'success') {
            $newCsrf = new Csrf();
            echo json_encode(['status' => 'error', 'msg' => $result['msg'], 'csrf_token' => $newCsrf->get_token_value()]);
            exit;
        }
        $photo_path = $result['path'];
    } else {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Không có ảnh được gửi'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    // Save to package_photos table
    $photoData = [
        'photo_path'  => $photo_path,
        'photo_type'  => in_array($photo_type, ['receive', 'delivery', 'damage', 'other']) ? $photo_type : 'receive',
        'uploaded_by'  => $getUser['id'],
        'create_date' => gettime()
    ];

    if ($entity_type === 'package') {
        $photoData['package_id'] = $entity_id;
        // Also update packages.receive_photo if it's a receive photo
        if ($photo_type === 'receive') {
            $ToryHub->update_safe('packages', [
                'receive_photo' => $photo_path,
                'received_by'   => $getUser['id']
            ], "`id` = ?", [$entity_id]);
        }
    } else {
        $photoData['order_id'] = $entity_id;
    }

    $ToryHub->insert_safe('package_photos', $photoData);

    add_log($getUser['id'], 'UPLOAD_PHOTO', "Upload {$photo_type} photo for {$entity_type} #{$entity_id}: {$photo_path}");

    $newCsrf = new Csrf();
    echo json_encode([
        'status'     => 'success',
        'msg'        => __('Đã lưu ảnh thành công'),
        'photo_url'  => get_upload_url($photo_path),
        'csrf_token' => $newCsrf->get_token_value()
    ]);
    exit;
}

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

        // Auto-notify customers when status changes to vn_warehouse
        $notified = false;
        if ($targetStatus === 'vn_warehouse' && !empty($linkedOrders)) {
            $notifiedCustomerIds = [];
            foreach ($linkedOrders as $lo) {
                if (empty($lo['customer_id']) || in_array($lo['customer_id'], $notifiedCustomerIds)) continue;
                $customer = $ToryHub->get_row_safe("SELECT * FROM `customers` WHERE `id` = ?", [$lo['customer_id']]);
                if (!$customer) continue;
                $hasContact = !empty($customer['email']) || !empty($customer['telegram_chat_id']);
                if (!$hasContact) continue;
                // Check if already notified for this order
                $alreadyNotified = $ToryHub->get_row_safe(
                    "SELECT id FROM `customer_notifications` WHERE `customer_id` = ? AND `order_id` = ? AND `type` = 'arrived_vn'",
                    [$customer['id'], $lo['id']]
                );
                if ($alreadyNotified) continue;
                // Send notification
                $channels = [];
                if (!empty($customer['email'])) {
                    try {
                        $emailSvc = new EmailService();
                        if ($emailSvc->isEnabled() && $emailSvc->notifyArrivedVN($lo, $customer['email'])) {
                            $channels[] = 'email';
                        }
                    } catch (Exception $e) {}
                }
                if (!empty($customer['telegram_chat_id'])) {
                    try {
                        $tgBot = new TelegramBot();
                        if ($tgBot->notifyCustomerArrived($customer['telegram_chat_id'], $lo)) {
                            $channels[] = 'telegram';
                        }
                    } catch (Exception $e) {}
                }
                if (!empty($channels)) {
                    $ToryHub->insert_safe('customer_notifications', [
                        'customer_id' => $customer['id'],
                        'order_id' => $lo['id'],
                        'package_id' => $package['id'],
                        'type' => 'arrived_vn',
                        'channel' => count($channels) > 1 ? 'both' : $channels[0],
                        'message' => 'Hàng đã về kho VN: ' . $lo['order_code'],
                        'sent_by' => $getUser['id'],
                        'sent_at' => gettime()
                    ]);
                    $notified = true;
                }
                $notifiedCustomerIds[] = $lo['customer_id'];
            }
        }

        $newCsrf = new Csrf();
        echo json_encode([
            'status'      => 'success',
            'msg'         => $successMsg . ' | ' . $package['package_code']
                           . (!empty($orderCodes) ? ' → ' . implode(', ', $orderCodes) : '')
                           . ($notified ? ' 📨' : ''),
            'order_code'  => $package['package_code'],
            'order'       => $orderInfo,
            'entity_type' => 'package',
            'entity_id'   => $package['id'],
            'notified'    => $notified,
            'csrf_token'  => $newCsrf->get_token_value()
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

    // Auto-notify customer when order arrives at VN warehouse
    $notified = false;
    if ($targetStatus === 'vn_warehouse' && !empty($order['customer_id'])) {
        $customer = $ToryHub->get_row_safe("SELECT * FROM `customers` WHERE `id` = ?", [$order['customer_id']]);
        if ($customer && (!empty($customer['email']) || !empty($customer['telegram_chat_id']))) {
            $alreadyNotified = $ToryHub->get_row_safe(
                "SELECT id FROM `customer_notifications` WHERE `customer_id` = ? AND `order_id` = ? AND `type` = 'arrived_vn'",
                [$customer['id'], $order['id']]
            );
            if (!$alreadyNotified) {
                $channels = [];
                if (!empty($customer['email'])) {
                    try {
                        $emailSvc = new EmailService();
                        if ($emailSvc->isEnabled() && $emailSvc->notifyArrivedVN($order, $customer['email'])) {
                            $channels[] = 'email';
                        }
                    } catch (Exception $e) {}
                }
                if (!empty($customer['telegram_chat_id'])) {
                    try {
                        $tgBot = new TelegramBot();
                        if ($tgBot->notifyCustomerArrived($customer['telegram_chat_id'], $order)) {
                            $channels[] = 'telegram';
                        }
                    } catch (Exception $e) {}
                }
                if (!empty($channels)) {
                    $ToryHub->insert_safe('customer_notifications', [
                        'customer_id' => $customer['id'],
                        'order_id' => $order['id'],
                        'type' => 'arrived_vn',
                        'channel' => count($channels) > 1 ? 'both' : $channels[0],
                        'message' => 'Hàng đã về kho VN: ' . $order['order_code'],
                        'sent_by' => $getUser['id'],
                        'sent_at' => gettime()
                    ]);
                    $notified = true;
                }
            }
        }
    }

    $newCsrf = new Csrf();
    echo json_encode([
        'status'      => 'success',
        'msg'         => $successMsg . ($notified ? ' 📨' : ''),
        'order_code'  => $order['order_code'],
        'order'       => $orderInfo,
        'entity_type' => 'order',
        'entity_id'   => $order['id'],
        'notified'    => $notified,
        'csrf_token'  => $newCsrf->get_token_value()
    ]);
} else {
    $newCsrf = new Csrf();
    echo json_encode([
        'status'     => 'error',
        'msg'        => __('Lỗi cập nhật. Vui lòng thử lại.'),
        'csrf_token' => $newCsrf->get_token_value()
    ]);
}
