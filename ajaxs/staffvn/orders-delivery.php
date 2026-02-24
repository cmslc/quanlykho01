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
require_once(__DIR__.'/../../models/is_staffvn.php');

header('Content-Type: application/json; charset=utf-8');

$csrf = new Csrf();
if (!$csrf->validate()) {
    echo json_encode(['status' => 'error', 'msg' => __('Token không hợp lệ')]);
    exit;
}

$request = input_post('request_name');

// ===== GET CUSTOMER BALANCE =====
if ($request === 'get_customer_balance') {
    $customer_id = intval(input_post('customer_id'));
    $customer = $ToryHub->get_row_safe("SELECT `balance` FROM `customers` WHERE `id` = ?", [$customer_id]);
    $newCsrf = new Csrf();
    echo json_encode([
        'status'     => 'success',
        'balance'    => $customer ? floatval($customer['balance']) : 0,
        'csrf_token' => $newCsrf->get_token_value()
    ]);
    exit;
}

// ===== MARK DELIVERED =====
if ($request !== 'mark_delivered') {
    echo json_encode(['status' => 'error', 'msg' => __('Yêu cầu không hợp lệ')]);
    exit;
}

$order_id = intval(input_post('order_id'));
$note = trim(input_post('note') ?: '');

// COD params
$collect_cod = input_post('collect_cod') === '1';
$cod_amount = floatval(input_post('cod_amount'));
$payment_method = input_post('payment_method') ?: 'cash';

if (!$order_id) {
    echo json_encode(['status' => 'error', 'msg' => __('Thiếu thông tin đơn hàng')]);
    exit;
}

// Get order with customer info
$order = $ToryHub->get_row_safe("SELECT o.*, c.balance as customer_balance, c.customer_code
    FROM `orders` o LEFT JOIN `customers` c ON o.customer_id = c.id
    WHERE o.id = ?", [$order_id]);
if (!$order) {
    echo json_encode(['status' => 'error', 'msg' => __('Đơn hàng không tồn tại')]);
    exit;
}

// Only allow marking as delivered from vn_warehouse status
if ($order['status'] !== 'vn_warehouse') {
    echo json_encode(['status' => 'error', 'msg' => __('Chỉ có thể giao đơn hàng đang ở kho Việt Nam')]);
    exit;
}

// Validate COD
if ($collect_cod) {
    if ($cod_amount <= 0) {
        echo json_encode(['status' => 'error', 'msg' => __('Số tiền COD phải lớn hơn 0')]);
        exit;
    }
    if (!in_array($payment_method, ['cash', 'transfer', 'balance'])) {
        $payment_method = 'cash';
    }
    if ($payment_method === 'balance') {
        $customer_balance = floatval($order['customer_balance']);
        if ($customer_balance < $cod_amount) {
            echo json_encode(['status' => 'error', 'msg' => __('Số dư khách hàng không đủ') . '. ' . __('Số dư') . ': ' . format_vnd($customer_balance)]);
            exit;
        }
    }
}

$ToryHub->beginTransaction();

try {
    // 1. Update order status to delivered
    $Orders = new Orders();
    $result = $Orders->updateStatus($order_id, 'delivered', $getUser['id'], $note);

    if (!$result) {
        throw new Exception(__('Lỗi cập nhật trạng thái đơn hàng'));
    }

    // Update delivery date
    $orderUpdate = ['delivered_date' => gettime()];

    // 2. Process COD if enabled
    $transaction_id = null;
    if ($collect_cod && $cod_amount > 0) {
        // Mark as paid
        $orderUpdate['is_paid'] = 1;
        $orderUpdate['paid_date'] = gettime();

        // Insert COD collection record
        $ToryHub->insert_safe('cod_collections', [
            'order_id'       => $order_id,
            'customer_id'    => $order['customer_id'],
            'amount'         => $cod_amount,
            'payment_method' => $payment_method,
            'note'           => $note ?: __('Thu COD khi giao hàng'),
            'collected_by'   => $getUser['id'],
            'create_date'    => gettime()
        ]);

        // If payment by balance, create transaction record
        if ($payment_method === 'balance') {
            $balance_before = floatval($order['customer_balance']);
            $balance_after = $balance_before - $cod_amount;

            $ToryHub->insert_safe('transactions', [
                'customer_id'    => $order['customer_id'],
                'order_id'       => $order_id,
                'type'           => 'payment',
                'amount'         => -$cod_amount,
                'balance_before' => $balance_before,
                'balance_after'  => $balance_after,
                'description'    => __('Thanh toán COD đơn') . ' ' . $order['order_code'],
                'created_by'     => $getUser['id'],
                'create_date'    => gettime()
            ]);

            // Update customer balance
            $ToryHub->update_safe('customers', [
                'balance'     => $balance_after,
                'update_date' => gettime()
            ], "`id` = ?", [$order['customer_id']]);

            // Update total_spent
            $ToryHub->cong_safe('customers', 'total_spent', $cod_amount, "`id` = ?", [$order['customer_id']]);

            $transaction_id = $ToryHub->insert_id();

            // Update COD collection with transaction_id
            if ($transaction_id) {
                $ToryHub->update_safe('cod_collections', [
                    'transaction_id' => $transaction_id
                ], "`order_id` = ? AND `collected_by` = ? ORDER BY `id` DESC LIMIT 1", [$order_id, $getUser['id']]);
            }
        }
    }

    $ToryHub->update_safe('orders', $orderUpdate, "`id` = ?", [$order_id]);

    $ToryHub->commit();

    // Log
    $logMsg = 'Giao hàng đơn ' . $order['order_code'];
    if ($collect_cod) {
        $methodLabels = ['cash' => 'Tiền mặt', 'transfer' => 'Chuyển khoản', 'balance' => 'Trừ số dư'];
        $logMsg .= ' | COD: ' . format_vnd($cod_amount) . ' (' . ($methodLabels[$payment_method] ?? $payment_method) . ')';
    }
    if ($note) $logMsg .= ' | Note: ' . $note;
    add_log($getUser['id'], 'DELIVERY', $logMsg);

    $newCsrf = new Csrf();
    $msg = __('Đã xác nhận giao hàng thành công');
    if ($collect_cod) {
        $msg .= ' | COD: ' . format_vnd($cod_amount);
    }
    echo json_encode(['status' => 'success', 'msg' => $msg, 'csrf_token' => $newCsrf->get_token_value()]);

} catch (Exception $e) {
    $ToryHub->rollBack();
    $newCsrf = new Csrf();
    echo json_encode(['status' => 'error', 'msg' => $e->getMessage(), 'csrf_token' => $newCsrf->get_token_value()]);
}
