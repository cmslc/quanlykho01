<?php
/**
 * Customer Notification AJAX handler - Thông báo khách hàng
 * Actions: send_notification, bulk_notify, get_notifications
 */
define('IN_SITE', true);
require_once(__DIR__.'/../../libs/db.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../libs/lang.php');
require_once(__DIR__.'/../../libs/helper.php');
require_once(__DIR__.'/../../libs/session.php');
require_once(__DIR__.'/../../libs/role.php');
require_once(__DIR__.'/../../libs/csrf.php');
require_once(__DIR__.'/../../libs/email.php');
require_once(__DIR__.'/../../libs/telegram.php');
require_once(__DIR__.'/../../models/is_staffvn.php');

header('Content-Type: application/json; charset=utf-8');

$csrf = new Csrf();
if (!$csrf->validate()) {
    echo json_encode(['status' => 'error', 'msg' => __('Token không hợp lệ')]);
    exit;
}

$request = input_post('request_name');
$user_id = $_SESSION['user']['id'];

/**
 * Get message template by type
 */
function getNotificationTemplate($type, $order = null) {
    $orderCode = $order ? $order['order_code'] : '';
    $productName = $order ? ($order['product_name'] ?? '') : '';

    $templates = [
        'arrived_vn' => __('Hàng đã về kho Việt Nam') . ($orderCode ? '. ' . __('Mã đơn') . ': ' . $orderCode : '') . '. ' . __('Vui lòng liên hệ để nhận hàng.'),
        'ready_delivery' => __('Đơn hàng') . ' ' . $orderCode . ' ' . __('sẵn sàng giao') . '. ' . __('Vui lòng xác nhận địa chỉ nhận hàng.'),
        'delivered' => __('Đơn hàng') . ' ' . $orderCode . ' ' . __('đã được giao thành công') . '. ' . __('Cảm ơn quý khách!'),
    ];

    return $templates[$type] ?? '';
}

/**
 * Send notification to a customer via available channels
 */
function sendCustomerNotification($ToryHub, $customer, $message, $order = null, $package_id = null, $type = 'custom', $user_id = null) {
    $channels = [];

    // Email
    if (!empty($customer['email'])) {
        try {
            $emailSvc = new EmailService();
            if ($emailSvc->isEnabled()) {
                $siteName = $ToryHub->site('site_name') ?: 'ToryHub';
                $subject = $siteName . ' - ' . __('Thông báo');
                if ($order) {
                    $subject .= ': ' . $order['order_code'];
                }
                $emailBody = '<h2 style="color:#405189;margin:0 0 15px;">' . __('Thông báo') . '</h2>';
                $emailBody .= '<p style="font-size:15px;line-height:1.6;">' . nl2br(htmlspecialchars($message)) . '</p>';
                if ($order) {
                    $emailBody .= '<table style="width:100%;border-collapse:collapse;margin-top:15px;">';
                    $emailBody .= '<tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666;">' . __('Mã đơn hàng') . '</td>';
                    $emailBody .= '<td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;">' . htmlspecialchars($order['order_code']) . '</td></tr>';
                    if (!empty($order['product_name'])) {
                        $emailBody .= '<tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666;">' . __('Sản phẩm') . '</td>';
                        $emailBody .= '<td style="padding:8px;border-bottom:1px solid #eee;">' . htmlspecialchars($order['product_name']) . '</td></tr>';
                    }
                    $emailBody .= '</table>';
                }
                if ($emailSvc->send($customer['email'], $subject, $emailBody)) {
                    $channels[] = 'email';
                }
            }
        } catch (Exception $e) {
            // Silent fail for email
        }
    }

    // Telegram
    if (!empty($customer['telegram_chat_id'])) {
        try {
            $tg = new TelegramBot();
            $tgMsg = $message;
            if ($order) {
                $tgMsg .= "\n" . __('Mã đơn') . ': ' . $order['order_code'];
            }
            if ($tg->sendTo($customer['telegram_chat_id'], $tgMsg)) {
                $channels[] = 'telegram';
            }
        } catch (Exception $e) {
            // Silent fail for telegram
        }
    }

    // Log notification
    if (!empty($channels)) {
        $channel = count($channels) > 1 ? 'both' : $channels[0];
        $ToryHub->insert_safe('customer_notifications', [
            'customer_id' => $customer['id'],
            'order_id' => $order ? $order['id'] : null,
            'package_id' => $package_id,
            'type' => in_array($type, ['arrived_vn', 'ready_delivery', 'delivered', 'custom']) ? $type : 'custom',
            'channel' => $channel,
            'message' => mb_substr($message, 0, 1000),
            'sent_by' => $user_id,
            'sent_at' => gettime()
        ]);
    }

    return $channels;
}

// ===== SEND NOTIFICATION =====
if ($request === 'send_notification') {
    $customer_id = intval(input_post('customer_id'));
    $order_id = intval(input_post('order_id'));
    $type = input_post('type') ?: 'custom';
    $message = trim(input_post('message'));

    if (!$customer_id) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Thiếu thông tin khách hàng'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    $customer = $ToryHub->get_row_safe("SELECT * FROM `customers` WHERE `id` = ?", [$customer_id]);
    if (!$customer) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Khách hàng không tồn tại'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    // Get order info if provided
    $order = null;
    if ($order_id) {
        $order = $ToryHub->get_row_safe("SELECT * FROM `orders` WHERE `id` = ?", [$order_id]);
    }

    // Build message from template if not custom
    if (empty($message) && $type !== 'custom') {
        $message = getNotificationTemplate($type, $order);
    }

    if (empty($message)) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập nội dung thông báo'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    // Check if customer has any contact method
    if (empty($customer['email']) && empty($customer['telegram_chat_id'])) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Khách hàng không có email hoặc Telegram'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    $channels = sendCustomerNotification($ToryHub, $customer, $message, $order, null, $type, $user_id);

    if (empty($channels)) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Không gửi được thông báo'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    add_log($user_id, 'NOTIFY_CUSTOMER', 'Gửi thông báo cho ' . $customer['fullname'] . ' (' . implode('+', $channels) . '): ' . mb_substr($message, 0, 100));

    $newCsrf = new Csrf();
    echo json_encode([
        'status' => 'success',
        'msg' => __('Đã gửi thông báo qua') . ': ' . implode(', ', $channels),
        'channels' => $channels,
        'csrf_token' => $newCsrf->get_token_value()
    ]);
    exit;
}

// ===== BULK NOTIFY =====
if ($request === 'bulk_notify') {
    $order_ids_raw = input_post('order_ids');
    $type = input_post('type') ?: 'arrived_vn';
    $message = trim(input_post('message'));

    if (empty($order_ids_raw)) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Không có đơn nào được chọn'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    $order_ids = json_decode($order_ids_raw, true);
    if (!$order_ids) {
        $order_ids = array_filter(array_map('intval', explode(',', $order_ids_raw)));
    }

    $sent = 0;
    $failed = 0;
    $notifiedCustomers = []; // Avoid duplicate notifications to same customer

    foreach ($order_ids as $oid) {
        $order = $ToryHub->get_row_safe("SELECT o.*, c.id as cid, c.fullname, c.email, c.telegram_chat_id
            FROM `orders` o JOIN `customers` c ON o.customer_id = c.id WHERE o.id = ?", [$oid]);

        if (!$order || in_array($order['cid'], $notifiedCustomers)) continue;

        $customer = [
            'id' => $order['cid'],
            'fullname' => $order['fullname'],
            'email' => $order['email'],
            'telegram_chat_id' => $order['telegram_chat_id']
        ];

        $msg = !empty($message) ? $message : getNotificationTemplate($type, $order);
        $channels = sendCustomerNotification($ToryHub, $customer, $msg, $order, null, $type, $user_id);

        if (!empty($channels)) {
            $sent++;
            $notifiedCustomers[] = $order['cid'];
        } else {
            $failed++;
        }
    }

    $newCsrf = new Csrf();
    echo json_encode([
        'status' => 'success',
        'msg' => __('Đã gửi') . ': ' . $sent . ' | ' . __('Thất bại') . ': ' . $failed,
        'sent' => $sent,
        'failed' => $failed,
        'csrf_token' => $newCsrf->get_token_value()
    ]);
    exit;
}

// ===== GET NOTIFICATIONS =====
if ($request === 'get_notifications') {
    $customer_id = intval(input_post('customer_id'));
    $order_id = intval(input_post('order_id'));

    $where = "1=1";
    $params = [];

    if ($customer_id) {
        $where .= " AND cn.customer_id = ?";
        $params[] = $customer_id;
    }
    if ($order_id) {
        $where .= " AND cn.order_id = ?";
        $params[] = $order_id;
    }

    $notifications = $ToryHub->get_list_safe(
        "SELECT cn.*, c.fullname as customer_name, u.fullname as sent_by_name
         FROM `customer_notifications` cn
         LEFT JOIN `customers` c ON cn.customer_id = c.id
         LEFT JOIN `users` u ON cn.sent_by = u.id
         WHERE $where ORDER BY cn.sent_at DESC LIMIT 50", $params
    );

    $newCsrf = new Csrf();
    echo json_encode([
        'status' => 'success',
        'notifications' => $notifications,
        'csrf_token' => $newCsrf->get_token_value()
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'msg' => __('Yêu cầu không hợp lệ')]);
