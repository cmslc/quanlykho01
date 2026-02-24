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

// ===== CREATE BATCH =====
if ($request === 'create_batch') {
    $note = trim(input_post('note') ?: '');

    // Generate batch code: GH-YYYYMMDD-NNN
    $today = date('Ymd');
    $lastBatch = $ToryHub->get_row_safe("SELECT `batch_code` FROM `delivery_batches` WHERE `batch_code` LIKE ? ORDER BY `id` DESC LIMIT 1", ["GH-{$today}-%"]);
    if ($lastBatch) {
        $lastNum = intval(substr($lastBatch['batch_code'], -3));
        $nextNum = str_pad($lastNum + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $nextNum = '001';
    }
    $batchCode = "GH-{$today}-{$nextNum}";

    $ToryHub->insert_safe('delivery_batches', [
        'batch_code'  => $batchCode,
        'staff_id'    => $getUser['id'],
        'status'      => 'preparing',
        'note'        => $note,
        'create_date' => gettime(),
        'update_date' => gettime()
    ]);

    $batchId = $ToryHub->insert_id();
    add_log($getUser['id'], 'CREATE_BATCH', 'Tạo chuyến giao ' . $batchCode);

    $newCsrf = new Csrf();
    echo json_encode([
        'status'     => 'success',
        'msg'        => __('Đã tạo chuyến giao') . ' ' . $batchCode,
        'batch_id'   => $batchId,
        'batch_code' => $batchCode,
        'csrf_token' => $newCsrf->get_token_value()
    ]);
    exit;
}

// ===== ADD ORDERS TO BATCH =====
if ($request === 'add_orders') {
    $batch_id = intval(input_post('batch_id'));
    $order_ids = input_post('order_ids'); // comma-separated or JSON array

    $batch = $ToryHub->get_row_safe("SELECT * FROM `delivery_batches` WHERE `id` = ? AND `status` = 'preparing'", [$batch_id]);
    if (!$batch) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Chuyến giao không tồn tại hoặc đã bắt đầu'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    // Parse order IDs
    if (is_string($order_ids)) {
        $ids = array_filter(array_map('intval', explode(',', $order_ids)));
    } else {
        $ids = [];
    }

    if (empty($ids)) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Không có đơn hàng được chọn'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    $added = 0;
    $skipped = 0;
    foreach ($ids as $oid) {
        // Check order exists and is vn_warehouse
        $order = $ToryHub->get_row_safe("SELECT `id`, `grand_total` FROM `orders` WHERE `id` = ? AND `status` = 'vn_warehouse'", [$oid]);
        if (!$order) { $skipped++; continue; }

        // Check not already in a batch
        $exists = $ToryHub->get_row_safe("SELECT `id` FROM `delivery_batch_orders` WHERE `batch_id` = ? AND `order_id` = ?", [$batch_id, $oid]);
        if ($exists) { $skipped++; continue; }

        $ToryHub->insert_safe('delivery_batch_orders', [
            'batch_id'  => $batch_id,
            'order_id'  => $oid,
            'cod_amount' => $order['grand_total']
        ]);
        $added++;
    }

    // Update batch totals
    $totals = $ToryHub->get_row_safe("SELECT COUNT(*) as cnt, COALESCE(SUM(cod_amount), 0) as total_amount FROM `delivery_batch_orders` WHERE `batch_id` = ?", [$batch_id]);
    $ToryHub->update_safe('delivery_batches', [
        'total_orders' => $totals['cnt'],
        'total_amount' => $totals['total_amount'],
        'update_date'  => gettime()
    ], "`id` = ?", [$batch_id]);

    $newCsrf = new Csrf();
    echo json_encode([
        'status'     => 'success',
        'msg'        => __('Đã thêm') . ' ' . $added . ' ' . __('đơn') . ($skipped ? ' (' . $skipped . ' ' . __('bỏ qua') . ')' : ''),
        'csrf_token' => $newCsrf->get_token_value()
    ]);
    exit;
}

// ===== REMOVE ORDER FROM BATCH =====
if ($request === 'remove_order') {
    $batch_id = intval(input_post('batch_id'));
    $order_id = intval(input_post('order_id'));

    $batch = $ToryHub->get_row_safe("SELECT * FROM `delivery_batches` WHERE `id` = ? AND `status` = 'preparing'", [$batch_id]);
    if (!$batch) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Chuyến giao không tồn tại hoặc đã bắt đầu'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    $ToryHub->remove_safe('delivery_batch_orders', "`batch_id` = ? AND `order_id` = ?", [$batch_id, $order_id]);

    // Update totals
    $totals = $ToryHub->get_row_safe("SELECT COUNT(*) as cnt, COALESCE(SUM(cod_amount), 0) as total_amount FROM `delivery_batch_orders` WHERE `batch_id` = ?", [$batch_id]);
    $ToryHub->update_safe('delivery_batches', [
        'total_orders' => $totals['cnt'],
        'total_amount' => $totals['total_amount'],
        'update_date'  => gettime()
    ], "`id` = ?", [$batch_id]);

    $newCsrf = new Csrf();
    echo json_encode(['status' => 'success', 'msg' => __('Đã xóa đơn khỏi chuyến'), 'csrf_token' => $newCsrf->get_token_value()]);
    exit;
}

// ===== START BATCH =====
if ($request === 'start_batch') {
    $batch_id = intval(input_post('batch_id'));

    $batch = $ToryHub->get_row_safe("SELECT * FROM `delivery_batches` WHERE `id` = ? AND `status` = 'preparing'", [$batch_id]);
    if (!$batch) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Chuyến giao không hợp lệ'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    $orderCount = $ToryHub->num_rows_safe("SELECT * FROM `delivery_batch_orders` WHERE `batch_id` = ?", [$batch_id]);
    if (!$orderCount) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Chuyến giao chưa có đơn hàng'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    $ToryHub->update_safe('delivery_batches', [
        'status'       => 'delivering',
        'started_date' => gettime(),
        'update_date'  => gettime()
    ], "`id` = ?", [$batch_id]);

    add_log($getUser['id'], 'START_BATCH', 'Bắt đầu chuyến giao ' . $batch['batch_code'] . ' (' . $orderCount . ' đơn)');

    $newCsrf = new Csrf();
    echo json_encode(['status' => 'success', 'msg' => __('Đã bắt đầu chuyến giao'), 'csrf_token' => $newCsrf->get_token_value()]);
    exit;
}

// ===== DELIVER ORDER (in batch) =====
if ($request === 'deliver_order') {
    $batch_id = intval(input_post('batch_id'));
    $order_id = intval(input_post('order_id'));
    $note = trim(input_post('note') ?: '');
    $collect_cod = input_post('collect_cod') === '1';
    $cod_amount = floatval(input_post('cod_amount'));
    $payment_method = input_post('payment_method') ?: 'cash';

    $batch = $ToryHub->get_row_safe("SELECT * FROM `delivery_batches` WHERE `id` = ? AND `status` = 'delivering'", [$batch_id]);
    if (!$batch) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Chuyến giao không hợp lệ'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    $order = $ToryHub->get_row_safe("SELECT o.*, c.balance as customer_balance FROM `orders` o LEFT JOIN `customers` c ON o.customer_id = c.id WHERE o.id = ?", [$order_id]);
    if (!$order || $order['status'] !== 'vn_warehouse') {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Đơn hàng không hợp lệ'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    // Validate balance if needed
    if ($collect_cod && $payment_method === 'balance') {
        if (floatval($order['customer_balance']) < $cod_amount) {
            $newCsrf = new Csrf();
            echo json_encode(['status' => 'error', 'msg' => __('Số dư khách hàng không đủ'), 'csrf_token' => $newCsrf->get_token_value()]);
            exit;
        }
    }

    $ToryHub->beginTransaction();

    try {
        // Update order status
        $Orders = new Orders();
        $Orders->updateStatus($order_id, 'delivered', $getUser['id'], $note);

        $orderUpdate = ['delivered_date' => gettime()];

        // Process COD
        if ($collect_cod && $cod_amount > 0) {
            $orderUpdate['is_paid'] = 1;
            $orderUpdate['paid_date'] = gettime();

            $ToryHub->insert_safe('cod_collections', [
                'order_id'       => $order_id,
                'customer_id'    => $order['customer_id'],
                'amount'         => $cod_amount,
                'payment_method' => in_array($payment_method, ['cash', 'transfer', 'balance']) ? $payment_method : 'cash',
                'note'           => __('Thu COD chuyến') . ' ' . $batch['batch_code'],
                'collected_by'   => $getUser['id'],
                'create_date'    => gettime()
            ]);

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
                    'description'    => __('Thanh toán COD đơn') . ' ' . $order['order_code'] . ' (' . $batch['batch_code'] . ')',
                    'created_by'     => $getUser['id'],
                    'create_date'    => gettime()
                ]);

                $ToryHub->update_safe('customers', [
                    'balance'     => $balance_after,
                    'update_date' => gettime()
                ], "`id` = ?", [$order['customer_id']]);

                $ToryHub->cong_safe('customers', 'total_spent', $cod_amount, "`id` = ?", [$order['customer_id']]);
            }
        }

        $ToryHub->update_safe('orders', $orderUpdate, "`id` = ?", [$order_id]);

        // Update batch order record
        $ToryHub->update_safe('delivery_batch_orders', [
            'delivery_status' => 'delivered',
            'cod_collected'   => $collect_cod ? 1 : 0,
            'cod_amount'      => $collect_cod ? $cod_amount : 0,
            'delivery_note'   => $note,
            'delivered_date'  => gettime()
        ], "`batch_id` = ? AND `order_id` = ?", [$batch_id, $order_id]);

        // Update batch collected total
        $collected = $ToryHub->get_row_safe("SELECT COALESCE(SUM(cod_amount), 0) as total FROM `delivery_batch_orders` WHERE `batch_id` = ? AND `cod_collected` = 1", [$batch_id]);
        $ToryHub->update_safe('delivery_batches', [
            'total_collected' => $collected['total'],
            'update_date'     => gettime()
        ], "`id` = ?", [$batch_id]);

        $ToryHub->commit();

        add_log($getUser['id'], 'BATCH_DELIVER', 'Giao đơn ' . $order['order_code'] . ' trong chuyến ' . $batch['batch_code']);

        $newCsrf = new Csrf();
        echo json_encode(['status' => 'success', 'msg' => __('Đã giao thành công'), 'csrf_token' => $newCsrf->get_token_value()]);

    } catch (Exception $e) {
        $ToryHub->rollBack();
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Lỗi hệ thống'), 'csrf_token' => $newCsrf->get_token_value()]);
    }
    exit;
}

// ===== FAIL ORDER (in batch) =====
if ($request === 'fail_order') {
    $batch_id = intval(input_post('batch_id'));
    $order_id = intval(input_post('order_id'));
    $note = trim(input_post('note') ?: '');

    $batch = $ToryHub->get_row_safe("SELECT * FROM `delivery_batches` WHERE `id` = ? AND `status` = 'delivering'", [$batch_id]);
    if (!$batch) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Chuyến giao không hợp lệ'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    $ToryHub->update_safe('delivery_batch_orders', [
        'delivery_status' => 'failed',
        'delivery_note'   => $note ?: __('Giao thất bại'),
        'delivered_date'  => gettime()
    ], "`batch_id` = ? AND `order_id` = ?", [$batch_id, $order_id]);

    add_log($getUser['id'], 'BATCH_FAIL', 'Giao thất bại đơn ' . $order_id . ' trong chuyến ' . $batch['batch_code'] . ': ' . $note);

    $newCsrf = new Csrf();
    echo json_encode(['status' => 'success', 'msg' => __('Đã đánh dấu giao thất bại'), 'csrf_token' => $newCsrf->get_token_value()]);
    exit;
}

// ===== COMPLETE BATCH =====
if ($request === 'complete_batch') {
    $batch_id = intval(input_post('batch_id'));

    $batch = $ToryHub->get_row_safe("SELECT * FROM `delivery_batches` WHERE `id` = ? AND `status` = 'delivering'", [$batch_id]);
    if (!$batch) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Chuyến giao không hợp lệ'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    $ToryHub->update_safe('delivery_batches', [
        'status'         => 'completed',
        'completed_date' => gettime(),
        'update_date'    => gettime()
    ], "`id` = ?", [$batch_id]);

    // Get summary
    $summary = $ToryHub->get_row_safe("
        SELECT COUNT(*) as total,
               SUM(CASE WHEN delivery_status = 'delivered' THEN 1 ELSE 0 END) as delivered,
               SUM(CASE WHEN delivery_status = 'failed' THEN 1 ELSE 0 END) as failed,
               SUM(CASE WHEN delivery_status = 'pending' THEN 1 ELSE 0 END) as pending
        FROM `delivery_batch_orders` WHERE `batch_id` = ?
    ", [$batch_id]);

    add_log($getUser['id'], 'COMPLETE_BATCH', 'Hoàn thành chuyến ' . $batch['batch_code'] .
        ' | Delivered: ' . $summary['delivered'] . ' | Failed: ' . $summary['failed'] . ' | Pending: ' . $summary['pending']);

    // Send Telegram
    try {
        require_once(__DIR__.'/../../libs/telegram.php');
        $telegram = new TelegramBot();
        $staffName = $getUser['fullname'] ?? $getUser['username'];
        $message = "<b>🚛 " . __('Hoàn thành chuyến giao') . "</b>\n\n";
        $message .= "<b>" . __('Mã chuyến') . ":</b> " . $batch['batch_code'] . "\n";
        $message .= "<b>" . __('Nhân viên') . ":</b> " . htmlspecialchars($staffName) . "\n";
        $message .= "<b>" . __('Thời gian') . ":</b> " . date('d/m/Y H:i:s') . "\n\n";
        $message .= "✅ " . __('Giao thành công') . ": " . $summary['delivered'] . "\n";
        $message .= "❌ " . __('Thất bại') . ": " . $summary['failed'] . "\n";
        if ($summary['pending'] > 0) {
            $message .= "⏳ " . __('Chưa xử lý') . ": " . $summary['pending'] . "\n";
        }
        $message .= "\n<b>" . __('COD đã thu') . ":</b> " . format_vnd($batch['total_collected']);
        $telegram->sendMessage($message);
    } catch (Exception $e) {}

    $newCsrf = new Csrf();
    echo json_encode([
        'status'     => 'success',
        'msg'        => __('Đã hoàn thành chuyến giao'),
        'summary'    => $summary,
        'csrf_token' => $newCsrf->get_token_value()
    ]);
    exit;
}

// ===== DELETE BATCH =====
if ($request === 'delete_batch') {
    $batch_id = intval(input_post('batch_id'));

    $batch = $ToryHub->get_row_safe("SELECT * FROM `delivery_batches` WHERE `id` = ? AND `status` = 'preparing'", [$batch_id]);
    if (!$batch) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Chỉ có thể xóa chuyến đang chuẩn bị'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    $ToryHub->remove_safe('delivery_batch_orders', "`batch_id` = ?", [$batch_id]);
    $ToryHub->remove_safe('delivery_batches', "`id` = ?", [$batch_id]);

    add_log($getUser['id'], 'DELETE_BATCH', 'Xóa chuyến giao ' . $batch['batch_code']);

    $newCsrf = new Csrf();
    echo json_encode(['status' => 'success', 'msg' => __('Đã xóa chuyến giao'), 'csrf_token' => $newCsrf->get_token_value()]);
    exit;
}

echo json_encode(['status' => 'error', 'msg' => __('Yêu cầu không hợp lệ')]);
