<?php
/**
 * Warehouse Receive AJAX handler - Nhập kho tổng hợp tại kho VN
 * Actions: scan_barcode, scan_bag_package, upload_photo
 */
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
$user_id = $getUser['id'];

/**
 * Auto-update shipment to 'arrived' if all packages in shipment have been received
 */
function checkShipmentAutoArrival($ToryHub, $packageId, $userId) {
    $sp = $ToryHub->get_row_safe(
        "SELECT sp.shipment_id FROM `shipment_packages` sp
         JOIN `shipments` s ON sp.shipment_id = s.id
         WHERE sp.package_id = ? AND s.status IN ('preparing', 'in_transit')
         LIMIT 1",
        [$packageId]
    );
    if (!$sp) return;

    $shipmentId = $sp['shipment_id'];

    // Count packages not yet received
    $remaining = $ToryHub->get_row_safe(
        "SELECT COUNT(*) as cnt FROM `shipment_packages` sp
         JOIN `packages` p ON sp.package_id = p.id
         WHERE sp.shipment_id = ? AND p.status NOT IN ('vn_warehouse', 'delivered', 'returned', 'damaged')",
        [$shipmentId]
    );

    if (intval($remaining['cnt']) === 0) {
        $shipment = $ToryHub->get_row_safe("SELECT * FROM `shipments` WHERE `id` = ?", [$shipmentId]);
        if ($shipment && !in_array($shipment['status'], ['arrived', 'completed'])) {
            $ToryHub->update_safe('shipments', [
                'status'       => 'arrived',
                'arrived_date' => gettime(),
                'update_date'  => gettime()
            ], "`id` = ?", [$shipmentId]);
            add_log($userId, 'SHIPMENT_AUTO_ARRIVED',
                'Chuyến ' . $shipment['shipment_code'] . ': tự động → Đã đến (tất cả kiện đã nhập kho VN)');
        }
    }
}

function checkBagAutoComplete($ToryHub, $packageId, $userId) {
    $bp = $ToryHub->get_row_safe(
        "SELECT bp.bag_id FROM `bag_packages` bp
         JOIN `bags` b ON bp.bag_id = b.id
         WHERE bp.package_id = ? AND b.status NOT IN ('completed')
         LIMIT 1",
        [$packageId]
    );
    if (!$bp) return;

    $bagId = $bp['bag_id'];

    $remaining = $ToryHub->get_row_safe(
        "SELECT COUNT(*) as cnt FROM `bag_packages` bp
         JOIN `packages` p ON bp.package_id = p.id
         WHERE bp.bag_id = ? AND p.status NOT IN ('vn_warehouse', 'delivered')",
        [$bagId]
    );

    if (intval($remaining['cnt']) === 0) {
        $bag = $ToryHub->get_row_safe("SELECT * FROM `bags` WHERE `id` = ?", [$bagId]);
        if ($bag && $bag['status'] !== 'completed') {
            $ToryHub->update_safe('bags', [
                'status'      => 'completed',
                'update_date' => gettime()
            ], "`id` = ?", [$bagId]);
            add_log($userId, 'BAG_AUTO_COMPLETED',
                'Bao ' . $bag['bag_code'] . ': tự động → Hoàn tất (tất cả kiện đã nhận đủ)');
        }
    }
}

// ===== SCAN BAG PACKAGE: quét kiện trong bao =====
if ($request === 'scan_bag_package') {
    $barcode = trim(input_post('barcode'));
    $bag_id = intval(input_post('bag_id'));

    if (empty($barcode) || !$bag_id) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Thiếu thông tin'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    // Find package in this bag
    $package = $ToryHub->get_row_safe(
        "SELECT p.* FROM `bag_packages` bp
         JOIN `packages` p ON bp.package_id = p.id
         WHERE bp.bag_id = ? AND (p.tracking_intl = ? OR p.tracking_vn = ? OR p.package_code = ? OR p.tracking_cn = ?)
         LIMIT 1",
        [$bag_id, $barcode, $barcode, $barcode, $barcode]
    );

    if (!$package) {
        $newCsrf = new Csrf();
        echo json_encode([
            'status'     => 'error',
            'error_type' => 'not_in_bag',
            'msg'        => __('Kiện hàng không thuộc bao này') . ': ' . htmlspecialchars($barcode),
            'csrf_token' => $newCsrf->get_token_value()
        ]);
        exit;
    }

    $pkgModel = new Packages();
    $linkedOrders = $pkgModel->getOrdersByPackage($package['id']);
    $orderCodes = array_map(function($o) { return $o['order_code']; }, $linkedOrders);

    $orderInfo = [
        'order_code'    => $package['package_code'],
        'customer_name' => !empty($linkedOrders) ? htmlspecialchars($linkedOrders[0]['customer_name'] ?? 'N/A') : 'N/A',
        'product_name'  => !empty($linkedOrders) ? htmlspecialchars(mb_strimwidth($linkedOrders[0]['product_name'] ?? '', 0, 30, '...')) : '-',
    ];

    // Check duplicate
    if (in_array($package['status'], ['vn_warehouse', 'delivered'])) {
        $newCsrf = new Csrf();
        echo json_encode([
            'status'     => 'error',
            'error_type' => 'duplicate',
            'msg'        => __('Kiện hàng đã nhập kho') . ': ' . $package['package_code'],
            'order'      => $orderInfo,
            'csrf_token' => $newCsrf->get_token_value()
        ]);
        exit;
    }

    // Validate transition
    if (!in_array($package['status'], ['packed', 'loading', 'shipping'])) {
        $newCsrf = new Csrf();
        echo json_encode([
            'status'     => 'error',
            'error_type' => 'invalid_status',
            'msg'        => __('Trạng thái không hợp lệ') . '. ' . __('Hiện tại') . ': ' . strip_tags(display_package_status($package['status'])),
            'order'      => $orderInfo,
            'csrf_token' => $newCsrf->get_token_value()
        ]);
        exit;
    }

    // Kiện trong bao đã có cân nặng → cập nhật trực tiếp, không cần modal
    $staffName = $getUser['fullname'] ?? $getUser['username'];
    $bagCode = $ToryHub->get_row_safe("SELECT bag_code FROM bags WHERE id = ?", [$bag_id])['bag_code'] ?? '';
    $result = $pkgModel->updateStatus($package['id'], 'vn_warehouse', $user_id,
        __('Nhập kho từ bao') . ' ' . $bagCode . ' ' . __('bởi') . ' ' . $staffName);

    if ($result === true) {
        add_log($user_id, 'WAREHOUSE_RECEIVE_BAG_PKG',
            'Quét kiện ' . $package['package_code'] . ' trong bao ' . $bagCode . ': '
            . strip_tags(display_package_status($package['status'])) . ' → Kho VN'
            . ' | Đơn: ' . implode(',', $orderCodes));

        // Auto-notify customers
        $notified = false;
        if (!empty($linkedOrders)) {
            $notifiedCustomerIds = [];
            foreach ($linkedOrders as $lo) {
                if (empty($lo['customer_id']) || in_array($lo['customer_id'], $notifiedCustomerIds)) continue;
                $customer = $ToryHub->get_row_safe("SELECT * FROM `customers` WHERE `id` = ?", [$lo['customer_id']]);
                if (!$customer) continue;
                $hasContact = !empty($customer['email']) || !empty($customer['telegram_chat_id']);
                if (!$hasContact) continue;
                $alreadyNotified = $ToryHub->get_row_safe(
                    "SELECT id FROM `customer_notifications` WHERE `customer_id` = ? AND `order_id` = ? AND `type` = 'arrived_vn'",
                    [$customer['id'], $lo['id']]
                );
                if ($alreadyNotified) continue;
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
                        'sent_by' => $user_id,
                        'sent_at' => gettime()
                    ]);
                    $notified = true;
                }
                $notifiedCustomerIds[] = $lo['customer_id'];
            }
        }

        checkShipmentAutoArrival($ToryHub, $package['id'], $user_id);
        checkBagAutoComplete($ToryHub, $package['id'], $user_id);

        // Count remaining
        $remaining = $ToryHub->get_row_safe(
            "SELECT COUNT(*) as cnt FROM `bag_packages` bp
             JOIN `packages` p ON bp.package_id = p.id
             WHERE bp.bag_id = ? AND p.status NOT IN ('vn_warehouse', 'delivered')",
            [$bag_id]
        );
        $totalInBag = $ToryHub->get_row_safe(
            "SELECT COUNT(*) as cnt FROM `bag_packages` WHERE `bag_id` = ?",
            [$bag_id]
        );

        $newCsrf = new Csrf();
        echo json_encode([
            'status'       => 'success',
            'type'         => 'bag_package',
            'msg'          => __('Kiện hàng đã nhập kho Việt Nam') . ' | ' . $package['package_code']
                             . (!empty($orderCodes) ? ' → ' . implode(', ', $orderCodes) : '')
                             . ($notified ? ' 📨' : ''),
            'package_id'   => $package['id'],
            'package_code' => $package['package_code'],
            'order'        => $orderInfo,
            'remaining'    => intval($remaining['cnt']),
            'total'        => intval($totalInBag['cnt']),
            'all_done'     => (intval($remaining['cnt']) === 0),
            'csrf_token'   => $newCsrf->get_token_value()
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

// ===================================================================
// REQUEST: confirm_retail_pkg - Xác nhận kiện hàng lẻ (package) + cập nhật cân nặng
// ===================================================================
if ($request === 'confirm_retail_pkg') {
    $package_id = intval(input_post('package_id'));
    $weight = floatval(input_post('weight'));

    if (!$package_id || $weight <= 0) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập cân nặng hợp lệ'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    $package = $ToryHub->get_row_safe("SELECT * FROM `packages` WHERE `id` = ?", [$package_id]);
    if (!$package) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Kiện hàng không tồn tại'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    if (!in_array($package['status'], ['packed', 'loading', 'shipping'])) {
        $newCsrf = new Csrf();
        echo json_encode([
            'status' => 'error',
            'msg' => __('Trạng thái không hợp lệ') . '. ' . __('Hiện tại') . ': ' . strip_tags(display_package_status($package['status'])),
            'csrf_token' => $newCsrf->get_token_value()
        ]);
        exit;
    }

    // Cập nhật cân nặng vào package
    $ToryHub->update_safe('packages', [
        'weight_actual' => $weight,
        'update_date'   => gettime()
    ], "`id` = ?", [$package_id]);

    // Cập nhật cân nặng vào orders liên kết
    $pkgModel = new Packages();
    $linkedOrders = $pkgModel->getOrdersByPackage($package['id']);
    $orderCodes = array_map(function($o) { return $o['order_code']; }, $linkedOrders);
    foreach ($linkedOrders as $lo) {
        $ToryHub->update_safe('orders', [
            'weight_actual' => $weight,
            'update_date'   => gettime()
        ], "`id` = ?", [$lo['id']]);
    }

    $orderInfo = [
        'order_code'    => $package['package_code'],
        'customer_name' => !empty($linkedOrders) ? htmlspecialchars($linkedOrders[0]['customer_name'] ?? 'N/A') : 'N/A',
        'product_name'  => !empty($linkedOrders) ? htmlspecialchars(mb_strimwidth($linkedOrders[0]['product_name'] ?? '', 0, 30, '...')) : '-',
    ];

    // Cập nhật trạng thái package
    $staffName = $getUser['fullname'] ?? $getUser['username'];
    $result = $pkgModel->updateStatus($package['id'], 'vn_warehouse', $user_id,
        __('Nhập kho bởi') . ' ' . $staffName . ' | ' . $weight . ' kg');

    if ($result === true) {
        add_log($user_id, 'WAREHOUSE_RECEIVE_PKG',
            'Quét kiện ' . $package['package_code'] . ': '
            . strip_tags(display_package_status($package['status'])) . ' → Kho VN'
            . ' | Cân nặng: ' . $weight . ' kg'
            . ' | Đơn: ' . implode(',', $orderCodes));

        // Auto-notify customers
        $notified = false;
        if (!empty($linkedOrders)) {
            $notifiedCustomerIds = [];
            foreach ($linkedOrders as $lo) {
                if (empty($lo['customer_id']) || in_array($lo['customer_id'], $notifiedCustomerIds)) continue;
                $customer = $ToryHub->get_row_safe("SELECT * FROM `customers` WHERE `id` = ?", [$lo['customer_id']]);
                if (!$customer) continue;
                if (empty($customer['email']) && empty($customer['telegram_chat_id'])) continue;
                $alreadyNotified = $ToryHub->get_row_safe(
                    "SELECT id FROM `customer_notifications` WHERE `customer_id` = ? AND `order_id` = ? AND `type` = 'arrived_vn'",
                    [$customer['id'], $lo['id']]
                );
                if ($alreadyNotified) continue;
                $channels = [];
                if (!empty($customer['email'])) {
                    try {
                        $emailSvc = new EmailService();
                        if ($emailSvc->isEnabled() && $emailSvc->notifyArrivedVN($lo, $customer['email'])) $channels[] = 'email';
                    } catch (Exception $e) {}
                }
                if (!empty($customer['telegram_chat_id'])) {
                    try {
                        $tgBot = new TelegramBot();
                        if ($tgBot->notifyCustomerArrived($customer['telegram_chat_id'], $lo)) $channels[] = 'telegram';
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
                        'sent_by' => $user_id,
                        'sent_at' => gettime()
                    ]);
                    $notified = true;
                }
                $notifiedCustomerIds[] = $lo['customer_id'];
            }
        }

        checkShipmentAutoArrival($ToryHub, $package['id'], $user_id);
        checkBagAutoComplete($ToryHub, $package['id'], $user_id);

        $newCsrf = new Csrf();
        echo json_encode([
            'status'      => 'success',
            'type'        => 'package',
            'msg'         => __('Kiện hàng đã nhập kho Việt Nam') . ' | ' . $package['package_code'] . ' (' . $weight . ' kg)'
                            . (!empty($orderCodes) ? ' → ' . implode(', ', $orderCodes) : '')
                            . ($notified ? ' 📨' : ''),
            'order'       => $orderInfo,
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

// ===================================================================
// REQUEST: confirm_retail - Xác nhận hàng lẻ + cập nhật cân nặng
// ===================================================================
if ($request === 'confirm_retail') {
    $order_id = intval(input_post('order_id'));
    $weight = floatval(input_post('weight'));

    if (!$order_id || $weight <= 0) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập cân nặng hợp lệ'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    $order = $ToryHub->get_row_safe(
        "SELECT o.*, c.fullname as customer_name FROM `orders` o LEFT JOIN `customers` c ON o.customer_id = c.id WHERE o.id = ? AND o.product_type = 'retail'",
        [$order_id]
    );
    if (!$order) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Đơn hàng không tồn tại'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    if (!in_array($order['status'], ['packed', 'loading', 'shipping'])) {
        $newCsrf = new Csrf();
        echo json_encode([
            'status' => 'error',
            'msg' => __('Trạng thái không hợp lệ') . '. ' . __('Hiện tại') . ': ' . strip_tags(display_order_status($order['status'])),
            'csrf_token' => $newCsrf->get_token_value()
        ]);
        exit;
    }

    $oldStatus = $order['status'];
    $updateData = [
        'status'           => 'vn_warehouse',
        'weight_actual'    => $weight,
        'vn_warehouse_date' => gettime(),
        'update_date'      => gettime()
    ];

    $result = $ToryHub->update_safe('orders', $updateData, "`id` = ?", [$order_id]);

    if ($result) {
        $staffName = $getUser['fullname'] ?? $getUser['username'];
        add_log($user_id, 'WAREHOUSE_RECEIVE_ORDER',
            'Quét ' . $order['order_code'] . ': ' . strip_tags(display_order_status($oldStatus)) . ' → Kho VN'
            . ' | Cân nặng: ' . $weight . ' kg (' . $staffName . ')');

        $ToryHub->insert_safe('order_status_history', [
            'order_id'    => $order['id'],
            'old_status'  => $oldStatus,
            'new_status'  => 'vn_warehouse',
            'changed_by'  => $user_id,
            'note'        => __('Nhập kho bởi') . ' ' . $staffName . ' | ' . $weight . ' kg',
            'create_date' => gettime()
        ]);

        // Auto-notify customer
        $notified = false;
        if (!empty($order['customer_id'])) {
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
                            'sent_by' => $user_id,
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
            'msg'         => __('Đã nhập kho Việt Nam thành công') . ' | ' . $order['order_code'] . ' (' . $weight . ' kg)' . ($notified ? ' 📨' : ''),
            'order_code'  => $order['order_code'],
            'order'       => [
                'order_code'    => $order['order_code'],
                'customer_name' => htmlspecialchars($order['customer_name'] ?? 'N/A'),
                'product_name'  => htmlspecialchars(mb_strimwidth($order['product_name'] ?? '', 0, 30, '...')),
            ],
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
    exit;
}

// ===================================================================
// REQUEST: confirm_wholesale - Xác nhận kiện hàng lô đủ / thiếu
// ===================================================================
if ($request === 'confirm_wholesale') {

    $order_id = intval(input_post('order_id'));
    $received_count = input_post('received_count');
    $action = input_post('action'); // 'full' or 'partial' (legacy from orders-manage)
    $note = trim(input_post('note') ?? '');

    // Validate: cần order_id + (received_count hoặc action)
    $hasReceivedCount = ($received_count !== null && $received_count !== '');
    if (!$order_id || (!$hasReceivedCount && !in_array($action, ['full', 'partial']))) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Dữ liệu không hợp lệ'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    $order = $ToryHub->get_row_safe(
        "SELECT * FROM `orders` WHERE `id` = ? AND `product_type` = 'wholesale'", [$order_id]
    );
    if (!$order) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Đơn hàng không tồn tại'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    // Lấy kiện đã gửi (shipped)
    $pkgModel = new Packages();
    $shippedPkgs = $ToryHub->get_list_safe(
        "SELECT p.id, p.package_code, p.status FROM `packages` p
         JOIN `package_orders` po ON p.id = po.package_id
         WHERE po.order_id = ? AND p.status IN ('packed', 'loading', 'shipping')",
        [$order_id]
    );
    $totalShipped = count($shippedPkgs);

    // Xác định số kiện cần cập nhật
    if ($hasReceivedCount) {
        $toUpdate = max(0, min(intval($received_count), $totalShipped));
        $missing = $totalShipped - $toUpdate;
    } elseif ($action === 'full') {
        $toUpdate = $totalShipped;
        $missing = 0;
    } else { // partial
        $missing_count = intval(input_post('missing_count'));
        $toUpdate = max(0, $totalShipped - $missing_count);
        $missing = $missing_count;
    }

    // Cập nhật kiện
    $updated = 0;
    $orderLabel = $order['product_code'] ?: $order['order_code'];
    foreach ($shippedPkgs as $lp) {
        if ($updated >= $toUpdate) break;
        $pkgModel->updateStatus($lp['id'], 'vn_warehouse', $user_id,
            __('Nhập kho từ đơn lô') . ' ' . $orderLabel . ' (' . ($getUser['fullname'] ?? $getUser['username']) . ')');
        checkShipmentAutoArrival($ToryHub, $lp['id'], $user_id);
        $updated++;
    }

    // Ghi note
    $existingNote = $order['note'] ?? '';
    if ($missing > 0) {
        $noteText = __('Thiếu kiện') . ': ' . $missing . ' ' . __('kiện')
            . ($note ? ' - ' . $note : '') . ' (' . ($getUser['fullname'] ?? $getUser['username']) . ')';
    } else {
        $noteText = __('Xác nhận đủ kiện') . ' (' . ($getUser['fullname'] ?? $getUser['username']) . ')';
    }
    $newNote = ($existingNote ? $existingNote . "\n" : '') . '[' . date('d/m/Y H:i') . '] ' . $noteText;

    $orderUpdate = ['note' => $newNote, 'update_date' => gettime()];
    if ($missing > 0) {
        $orderUpdate['missing_count'] = $missing;
    } else {
        $orderUpdate['missing_count'] = 0;
    }
    $ToryHub->update_safe('orders', $orderUpdate, "`id` = ?", [$order_id]);

    // Đếm kiện thực tế đã nhận (sau cập nhật)
    $pkgStats = $ToryHub->get_row_safe(
        "SELECT COUNT(p.id) as total,
                SUM(CASE WHEN p.status IN ('vn_warehouse', 'delivered') THEN 1 ELSE 0 END) as received
         FROM `packages` p JOIN `package_orders` po ON p.id = po.package_id
         WHERE po.order_id = ?",
        [$order_id]
    );
    $pkgTotal = max(intval($order['total_packages'] ?? 0), intval($pkgStats['total'] ?? 0));
    $pkgReceived = intval($pkgStats['received'] ?? 0);

    $logAction = $missing > 0 ? 'WHOLESALE_CONFIRM_PARTIAL' : 'WHOLESALE_CONFIRM_FULL';
    $logDesc = 'Đơn ' . $orderLabel . ': nhận ' . $updated . '/' . $totalShipped . ' kiện'
        . ($missing > 0 ? ', thiếu ' . $missing : ', đủ')
        . ' (' . $pkgReceived . '/' . $pkgTotal . ' tổng)';
    add_log($user_id, $logAction, $logDesc);

    $msg = $missing > 0
        ? __('Đã nhận') . ' ' . $updated . ' ' . __('kiện') . ', ' . __('thiếu') . ' ' . $missing
        : __('Đã xác nhận đủ kiện') . ' (' . $pkgReceived . '/' . $pkgTotal . ')';

    $newCsrf = new Csrf();
    echo json_encode([
        'status'       => 'success',
        'msg'          => $msg,
        'pkg_received' => $pkgReceived,
        'pkg_total'    => $pkgTotal,
        'missing'      => $missing,
        'note_line'    => '[' . date('d/m/Y H:i') . '] ' . $noteText,
        'csrf_token'   => $newCsrf->get_token_value()
    ]);
    exit;
}

// ===== SCAN BARCODE: quét chính (auto-detect bao vs kiện) =====
if ($request !== 'scan_barcode') {
    echo json_encode(['status' => 'error', 'msg' => __('Yêu cầu không hợp lệ')]);
    exit;
}

$barcode = trim(input_post('barcode'));
$targetStatus = input_post('target_status');

// Validate target status
$validTargets = ['vn_warehouse', 'delivered'];
if (!in_array($targetStatus, $validTargets)) {
    $targetStatus = 'vn_warehouse';
}

if (empty($barcode)) {
    $newCsrf = new Csrf();
    echo json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập mã quét'), 'csrf_token' => $newCsrf->get_token_value()]);
    exit;
}

// Reject date-like strings (dd/mm/yyyy, mm/dd/yyyy, yyyy/mm/dd, etc.)
if (preg_match('/^\d{1,4}[\/\-\.]\d{1,2}[\/\-\.]\d{1,4}(\s+\d{1,2}:\d{2}(:\d{2})?)?$/', $barcode)) {
    $newCsrf = new Csrf();
    echo json_encode(['status' => 'error', 'msg' => __('Mã quét không hợp lệ'), 'csrf_token' => $newCsrf->get_token_value()]);
    exit;
}

// Define transition rules based on target
if ($targetStatus === 'vn_warehouse') {
    $allowedFromPkg = ['packed', 'loading', 'shipping'];
    $allowedFromOrder = ['packed', 'loading', 'shipping'];
    $logActionPkg = 'WAREHOUSE_RECEIVE_PKG';
    $logActionOrder = 'WAREHOUSE_RECEIVE_ORDER';
    $successMsgPkg = __('Kiện hàng đã nhập kho Việt Nam');
    $successMsgOrder = __('Đã nhập kho Việt Nam thành công');
    $dateField = 'vn_warehouse_date';
    $noteText = __('Nhập kho bởi');
} else { // delivered
    $allowedFromPkg = ['vn_warehouse'];
    $allowedFromOrder = ['vn_warehouse'];
    $logActionPkg = 'SCAN_PKG_DELIVERED';
    $logActionOrder = 'SCAN_DELIVERED';
    $successMsgPkg = __('Kiện hàng đã giao thành công');
    $successMsgOrder = __('Đã xác nhận giao hàng thành công');
    $dateField = 'delivered_date';
    $noteText = __('Giao hàng bởi');
}

// ===== BAG DETECTION (only for vn_warehouse mode) =====
if ($targetStatus === 'vn_warehouse' && stripos($barcode, 'BAO-') === 0) {
    $bag_code = strtoupper($barcode);
    $bag = $ToryHub->get_row_safe("SELECT * FROM `bags` WHERE `bag_code` = ?", [$bag_code]);

    if (!$bag) {
        $newCsrf = new Csrf();
        echo json_encode([
            'status'     => 'error',
            'msg'        => __('Không tìm thấy bao hàng') . ': ' . htmlspecialchars($bag_code),
            'csrf_token' => $newCsrf->get_token_value()
        ]);
        exit;
    }

    // Update bag status to arrived
    if (in_array($bag['status'], ['sealed', 'loading', 'shipping'])) {
        $oldBagStatus = $bag['status'];
        $ToryHub->update_safe('bags', [
            'status'      => 'arrived',
            'update_date' => gettime()
        ], "`id` = ?", [$bag['id']]);
        $bag['status'] = 'arrived';
        add_log($user_id, 'BAG_ARRIVED', 'Bao ' . $bag_code . ': → Đã đến (quét nhập kho)');
    }

    // Get packages in this bag
    $packages = $ToryHub->get_list_safe(
        "SELECT p.id, p.package_code, p.tracking_cn, p.tracking_intl, p.tracking_vn,
                p.weight_actual, p.weight_charged, p.status,
                GROUP_CONCAT(DISTINCT o.order_code SEPARATOR ', ') as order_codes,
                GROUP_CONCAT(DISTINCT c.fullname SEPARATOR ', ') as customer_names,
                GROUP_CONCAT(DISTINCT o.product_name SEPARATOR ', ') as product_names
         FROM `bag_packages` bp
         JOIN `packages` p ON bp.package_id = p.id
         LEFT JOIN `package_orders` po ON p.id = po.package_id
         LEFT JOIN `orders` o ON po.order_id = o.id
         LEFT JOIN `customers` c ON o.customer_id = c.id
         WHERE bp.bag_id = ?
         GROUP BY p.id
         ORDER BY p.package_code ASC",
        [$bag['id']]
    );

    $pkgList = [];
    $receivedCount = 0;
    foreach ($packages as $p) {
        $isReceived = in_array($p['status'], ['vn_warehouse', 'delivered']);
        if ($isReceived) $receivedCount++;
        $pkgList[] = [
            'id'            => $p['id'],
            'package_code'  => $p['package_code'],
            'tracking_cn'   => $p['tracking_cn'] ?? '',
            'tracking_intl' => $p['tracking_intl'] ?? '',
            'weight_charged' => floatval($p['weight_charged']),
            'status'        => $p['status'],
            'status_html'   => display_package_status($p['status']),
            'is_received'   => $isReceived,
            'order_codes'   => $p['order_codes'] ?? '',
            'customer_names' => $p['customer_names'] ?? '',
            'product_names' => $p['product_names'] ?? ''
        ];
    }

    $newCsrf = new Csrf();
    echo json_encode([
        'status'   => 'success',
        'type'     => 'bag',
        'msg'      => __('Đã tải bao hàng') . ': ' . $bag_code . ' (' . count($pkgList) . ' ' . __('kiện') . ')',
        'bag'      => [
            'id'             => $bag['id'],
            'bag_code'       => $bag['bag_code'],
            'status'         => $bag['status'],
            'total_packages' => intval($bag['total_packages']),
            'total_weight'   => floatval($bag['total_weight']),
            'note'           => $bag['note'] ?? '',
            'create_date'    => $bag['create_date'] ?? ''
        ],
        'packages'       => $pkgList,
        'received_count' => $receivedCount,
        'csrf_token'     => $newCsrf->get_token_value()
    ]);
    exit;
}

// ===== PACKAGE SCAN =====
// Phase 1: Package-first search
$package = $ToryHub->get_row_safe(
    "SELECT * FROM `packages` WHERE `tracking_cn` = ? OR `tracking_intl` = ? OR `tracking_vn` = ? OR `package_code` = ? LIMIT 1",
    [$barcode, $barcode, $barcode, $barcode]
);

if ($package) {
    $pkgModel = new Packages();
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
    if (!in_array($package['status'], $allowedFromPkg)) {
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

    // Hàng lẻ nhập kho → trả thông tin để nhân viên nhập cân nặng
    if ($targetStatus === 'vn_warehouse') {
        $isRetailPkg = false;
        if (!empty($linkedOrders)) {
            foreach ($linkedOrders as $lo) {
                if (($lo['product_type'] ?? '') === 'retail') { $isRetailPkg = true; break; }
            }
        }
        if ($isRetailPkg) {
            $bagInfo = $ToryHub->get_row_safe(
                "SELECT b.bag_code FROM bag_packages bp JOIN bags b ON bp.bag_id = b.id WHERE bp.package_id = ? LIMIT 1",
                [$package['id']]
            );
            $newCsrf = new Csrf();
            echo json_encode([
                'status'         => 'success',
                'type'           => 'retail_pkg',
                'package_id'     => $package['id'],
                'package_code'   => $package['package_code'],
                'tracking_cn'    => $package['tracking_cn'] ?? '',
                'order'          => $orderInfo,
                'current_weight' => floatval($package['weight_actual'] ?? 0),
                'bag_code'       => $bagInfo['bag_code'] ?? '',
                'csrf_token'     => $newCsrf->get_token_value()
            ]);
            exit;
        }
    }

    // Update package status
    $result = $pkgModel->updateStatus($package['id'], $targetStatus, $user_id,
        $noteText . ' ' . ($getUser['fullname'] ?? $getUser['username']));

    if ($result === true) {
        add_log($user_id, $logActionPkg,
            'Quét kiện ' . $package['package_code'] . ': ' . strip_tags(display_package_status($package['status'])) . ' → ' . strip_tags(display_package_status($targetStatus)) . ' (barcode: ' . $barcode . ')');

        // Auto-notify customers (only for vn_warehouse)
        $notified = false;
        if ($targetStatus === 'vn_warehouse' && !empty($linkedOrders)) {
            $notifiedCustomerIds = [];
            foreach ($linkedOrders as $lo) {
                if (empty($lo['customer_id']) || in_array($lo['customer_id'], $notifiedCustomerIds)) continue;
                $customer = $ToryHub->get_row_safe("SELECT * FROM `customers` WHERE `id` = ?", [$lo['customer_id']]);
                if (!$customer) continue;
                $hasContact = !empty($customer['email']) || !empty($customer['telegram_chat_id']);
                if (!$hasContact) continue;
                $alreadyNotified = $ToryHub->get_row_safe(
                    "SELECT id FROM `customer_notifications` WHERE `customer_id` = ? AND `order_id` = ? AND `type` = 'arrived_vn'",
                    [$customer['id'], $lo['id']]
                );
                if ($alreadyNotified) continue;
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
                        'sent_by' => $user_id,
                        'sent_at' => gettime()
                    ]);
                    $notified = true;
                }
                $notifiedCustomerIds[] = $lo['customer_id'];
            }
        }

        // Auto-update shipment and bag if all packages received
        if ($targetStatus === 'vn_warehouse') {
            checkShipmentAutoArrival($ToryHub, $package['id'], $user_id);
            checkBagAutoComplete($ToryHub, $package['id'], $user_id);
        }

        $newCsrf = new Csrf();
        echo json_encode([
            'status'      => 'success',
            'type'        => 'package',
            'msg'         => $successMsgPkg . ' | ' . $package['package_code']
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

// Phase 2: Fallback - search orders
$order = $ToryHub->get_row_safe("SELECT o.*, c.fullname as customer_name, c.customer_code
    FROM `orders` o LEFT JOIN `customers` c ON o.customer_id = c.id
    WHERE o.cn_tracking = ? OR o.intl_tracking = ? OR o.vn_tracking = ? OR o.order_code = ? OR o.product_code = ?
    LIMIT 1", [$barcode, $barcode, $barcode, $barcode, $barcode]);

if (!$order) {
    $newCsrf = new Csrf();
    echo json_encode([
        'status'     => 'error',
        'msg'        => __('Không tìm thấy kiện hàng hoặc đơn hàng với mã') . ': ' . htmlspecialchars($barcode),
        'csrf_token' => $newCsrf->get_token_value()
    ]);
    exit;
}

$orderInfo = [
    'order_code'    => $order['order_code'],
    'customer_name' => htmlspecialchars($order['customer_name'] ?? 'N/A'),
    'intl_tracking' => htmlspecialchars($order['intl_tracking'] ?? '-'),
    'vn_tracking'   => htmlspecialchars($order['vn_tracking'] ?? '-'),
    'product_name'  => htmlspecialchars(mb_strimwidth($order['product_name'] ?? '', 0, 30, '...')),
];

// Check duplicate
if ($order['status'] === $targetStatus) {
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

// Retail → trả thông tin để nhân viên nhập cân nặng, KHÔNG tự cập nhật
if ($order['product_type'] === 'retail' && $targetStatus === 'vn_warehouse') {
    $bagInfo = $ToryHub->get_row_safe(
        "SELECT b.bag_code FROM package_orders po JOIN bag_packages bp ON po.package_id = bp.package_id JOIN bags b ON bp.bag_id = b.id WHERE po.order_id = ? LIMIT 1",
        [$order['id']]
    );
    $newCsrf = new Csrf();
    echo json_encode([
        'status'         => 'success',
        'type'           => 'retail',
        'order_id'       => $order['id'],
        'order_code'     => $order['order_code'],
        'tracking_cn'    => $order['cn_tracking'] ?? '',
        'order'          => $orderInfo,
        'current_status' => strip_tags(display_order_status($order['status'])),
        'current_weight' => floatval($order['weight_actual'] ?? 0),
        'bag_code'       => $bagInfo['bag_code'] ?? '',
        'csrf_token'     => $newCsrf->get_token_value()
    ]);
    exit;
}

// Wholesale → trả thông tin để nhân viên nhập số kiện, KHÔNG tự cập nhật
if ($order['product_type'] === 'wholesale' && $targetStatus === 'vn_warehouse') {
    $pkgStats = $ToryHub->get_row_safe(
        "SELECT COUNT(p.id) as total,
                SUM(CASE WHEN p.status IN ('vn_warehouse', 'delivered') THEN 1 ELSE 0 END) as received,
                SUM(CASE WHEN p.status IN ('packed', 'loading', 'shipping') THEN 1 ELSE 0 END) as shipped
         FROM `packages` p JOIN `package_orders` po ON p.id = po.package_id
         WHERE po.order_id = ?",
        [$order['id']]
    );
    $newCsrf = new Csrf();
    echo json_encode([
        'status'         => 'success',
        'type'           => 'wholesale',
        'order_id'       => $order['id'],
        'order_code'     => $order['order_code'],
        'product_code'   => $order['product_code'] ?? '',
        'order'          => $orderInfo,
        'current_status' => strip_tags(display_order_status($order['status'])),
        'pkg_total'      => intval($pkgStats['total']),
        'pkg_received'   => intval($pkgStats['received']),
        'pkg_shipped'    => intval($pkgStats['shipped']),
        'csrf_token'     => $newCsrf->get_token_value()
    ]);
    exit;
}

// Validate transition
$allowedFrom = $allowedFromOrder;
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
    add_log($user_id, $logActionOrder, 'Quét ' . $order['order_code'] . ': ' . strip_tags(display_order_status($oldStatus)) . ' → ' . strip_tags(display_order_status($targetStatus)) . ' (barcode: ' . $barcode . ')');

    $ToryHub->insert_safe('order_status_history', [
        'order_id'    => $order['id'],
        'old_status'  => $oldStatus,
        'new_status'  => $targetStatus,
        'changed_by'  => $user_id,
        'note'        => $noteText . ' ' . ($getUser['fullname'] ?? $getUser['username']),
        'create_date' => gettime()
    ]);

    // Auto-notify customer (only for vn_warehouse)
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
                        'sent_by' => $user_id,
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
        'type'        => 'package',
        'msg'         => $successMsgOrder . ($notified ? ' 📨' : ''),
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

