<?php
define('IN_SITE', true);
require_once(__DIR__.'/../../libs/db.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../libs/lang.php');
require_once(__DIR__.'/../../libs/helper.php');
require_once(__DIR__.'/../../libs/session.php');
require_once(__DIR__.'/../../libs/role.php');
require_once(__DIR__.'/../../libs/csrf.php');
require_once(__DIR__.'/../../models/is_admin.php');

header('Content-Type: application/json; charset=utf-8');

$csrf = new Csrf();
if (!$csrf->validate()) {
    echo json_encode(['status' => 'error', 'msg' => 'Token không hợp lệ']);
    exit;
}

$request = input_post('request_name');

// ======== ADD TRANSACTION ========
if ($request === 'add') {
    $customer_id = intval(input_post('customer_id'));
    $type = input_post('type');
    $rawAmount = floatval(input_post('amount'));
    $currency = input_post('currency') ?: 'VND';
    $description = trim(input_post('description'));
    $order_code = trim(input_post('order_code'));

    // Convert CNY to VND if needed
    if ($currency === 'CNY') {
        $exchangeRate = get_exchange_rate();
        $amount = round($rawAmount * $exchangeRate);
    } else {
        $amount = $rawAmount;
    }

    if (!$customer_id) {
        echo json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn khách hàng')]);
        exit;
    }

    $validTypes = ['deposit', 'payment', 'refund', 'adjustment'];
    if (!in_array($type, $validTypes)) {
        echo json_encode(['status' => 'error', 'msg' => __('Loại giao dịch không hợp lệ')]);
        exit;
    }

    if ($amount <= 0) {
        echo json_encode(['status' => 'error', 'msg' => __('Số tiền phải lớn hơn 0')]);
        exit;
    }

    $customer = $ToryHub->get_row_safe("SELECT * FROM `customers` WHERE `id` = ?", [$customer_id]);
    if (!$customer) {
        echo json_encode(['status' => 'error', 'msg' => __('Khách hàng không tồn tại')]);
        exit;
    }

    // Find order if order_code provided
    $order_id = null;
    if ($order_code) {
        $order = $ToryHub->get_row_safe("SELECT `id` FROM `orders` WHERE `order_code` = ?", [$order_code]);
        if ($order) {
            $order_id = $order['id'];
        }
    }

    // Calculate balance change
    $balance_before = floatval($customer['balance']);
    $actual_amount = $amount;

    if ($type === 'deposit' || $type === 'refund') {
        // Increase balance
        $balance_after = $balance_before + $amount;
    } elseif ($type === 'payment') {
        // Decrease balance
        $actual_amount = -$amount;
        $balance_after = $balance_before - $amount;
    } else {
        // Adjustment - can be positive or negative, treat input as-is (positive = add)
        $balance_after = $balance_before + $amount;
    }

    // Use transaction for atomicity
    $ToryHub->beginTransaction();

    try {
        // Insert transaction record
        $ToryHub->insert_safe("transactions", [
            'customer_id' => $customer_id,
            'order_id' => $order_id,
            'type' => $type,
            'amount' => $actual_amount,
            'balance_before' => $balance_before,
            'balance_after' => $balance_after,
            'description' => $description,
            'created_by' => $getUser['id'],
            'create_date' => gettime()
        ]);

        // Update customer balance
        $ToryHub->update_safe("customers", [
            'balance' => $balance_after,
            'update_date' => gettime()
        ], "id = ?", [$customer_id]);

        // Update total_spent (Đã thanh toán)
        if ($type === 'deposit' || $type === 'payment') {
            $ToryHub->cong_safe("customers", "total_spent", $amount, "id = ?", [$customer_id]);
        } elseif ($type === 'refund') {
            $newTotalSpent = max(0, floatval($customer['total_spent']) - $amount);
            $ToryHub->update_safe("customers", ['total_spent' => $newTotalSpent], "id = ?", [$customer_id]);
        } elseif ($type === 'adjustment') {
            $ToryHub->cong_safe("customers", "total_spent", $amount, "id = ?", [$customer_id]);
        }

        $ToryHub->commit();

        $typeLabel = ['deposit' => 'Nạp tiền', 'payment' => 'Thanh toán', 'refund' => 'Hoàn tiền', 'adjustment' => 'Điều chỉnh'];
        add_log($getUser['id'], 'add_transaction', $typeLabel[$type] . ' ' . format_vnd($amount) . ' cho ' . $customer['customer_code']);

        // Telegram notification for deposits
        if ($type === 'deposit') {
            require_once(__DIR__.'/../../libs/telegram.php');
            $bot = new TelegramBot();
            $bot->notifyDeposit($customer['customer_code'], $customer['fullname'], $amount, $balance_after);
        }

        // Email notification for deposits
        if ($type === 'deposit' && !empty($customer['email'])) {
            require_once(__DIR__.'/../../libs/email.php');
            email_notify('notifyDeposit', $customer['email'], $amount, $balance_after, $description);
        }

        echo json_encode(['status' => 'success', 'msg' => __('Tạo giao dịch thành công')]);
    } catch (Exception $e) {
        $ToryHub->rollBack();
        echo json_encode(['status' => 'error', 'msg' => __('Lỗi hệ thống')]);
    }
    exit;
}

// ======== EDIT TRANSACTION ========
if ($request === 'edit') {
    $id = intval(input_post('id'));
    $amount = floatval(input_post('amount'));
    $description = trim(input_post('description'));

    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'msg' => __('ID không hợp lệ')]);
        exit;
    }
    if ($amount <= 0) {
        echo json_encode(['status' => 'error', 'msg' => __('Số tiền phải lớn hơn 0')]);
        exit;
    }

    $txn = $ToryHub->get_row_safe("SELECT * FROM `transactions` WHERE `id` = ?", [$id]);
    if (!$txn) {
        echo json_encode(['status' => 'error', 'msg' => __('Giao dịch không tồn tại')]);
        exit;
    }

    $customer = $ToryHub->get_row_safe("SELECT * FROM `customers` WHERE `id` = ?", [$txn['customer_id']]);
    if (!$customer) {
        echo json_encode(['status' => 'error', 'msg' => __('Khách hàng không tồn tại')]);
        exit;
    }

    $oldAmount = floatval($txn['amount']);
    $type = $txn['type'];
    $newActual = ($type === 'payment') ? -$amount : $amount;

    // Recalculate balance: reverse old, apply new
    $currentBalance = floatval($customer['balance']);
    $newBalance = $currentBalance - $oldAmount + $newActual;

    $ToryHub->beginTransaction();
    try {
        $ToryHub->update_safe("transactions", [
            'amount' => $newActual,
            'balance_after' => $newBalance,
            'description' => $description,
        ], "`id` = ?", [$id]);

        $ToryHub->update_safe("customers", [
            'balance' => $newBalance,
            'update_date' => gettime()
        ], "`id` = ?", [$txn['customer_id']]);

        // Adjust total_spent
        if ($type === 'deposit' || $type === 'payment' || $type === 'adjustment') {
            $oldSpent = abs($oldAmount);
            $newTotalSpent = max(0, floatval($customer['total_spent']) - $oldSpent + $amount);
            $ToryHub->update_safe("customers", ['total_spent' => $newTotalSpent], "`id` = ?", [$txn['customer_id']]);
        } elseif ($type === 'refund') {
            $oldRefund = abs($oldAmount);
            $newTotalSpent = max(0, floatval($customer['total_spent']) + $oldRefund - $amount);
            $ToryHub->update_safe("customers", ['total_spent' => $newTotalSpent], "`id` = ?", [$txn['customer_id']]);
        }

        $ToryHub->commit();
        add_log($getUser['id'], 'edit_transaction', "Sửa giao dịch #$id: " . format_vnd($amount));
        echo json_encode(['status' => 'success', 'msg' => __('Cập nhật giao dịch thành công')]);
    } catch (Exception $e) {
        $ToryHub->rollBack();
        echo json_encode(['status' => 'error', 'msg' => __('Lỗi hệ thống')]);
    }
    exit;
}

// ======== DELETE TRANSACTION ========
if ($request === 'delete') {
    $id = intval(input_post('id'));

    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'msg' => __('ID không hợp lệ')]);
        exit;
    }

    $txn = $ToryHub->get_row_safe("SELECT * FROM `transactions` WHERE `id` = ?", [$id]);
    if (!$txn) {
        echo json_encode(['status' => 'error', 'msg' => __('Giao dịch không tồn tại')]);
        exit;
    }

    $customer = $ToryHub->get_row_safe("SELECT * FROM `customers` WHERE `id` = ?", [$txn['customer_id']]);
    if (!$customer) {
        echo json_encode(['status' => 'error', 'msg' => __('Khách hàng không tồn tại')]);
        exit;
    }

    $ToryHub->beginTransaction();
    try {
        // Reverse balance
        $newBalance = floatval($customer['balance']) - floatval($txn['amount']);
        $ToryHub->update_safe("customers", [
            'balance' => $newBalance,
            'update_date' => gettime()
        ], "`id` = ?", [$txn['customer_id']]);

        // Reverse total_spent
        if ($txn['type'] === 'deposit' || $txn['type'] === 'payment' || $txn['type'] === 'adjustment') {
            $newTotalSpent = max(0, floatval($customer['total_spent']) - abs(floatval($txn['amount'])));
            $ToryHub->update_safe("customers", ['total_spent' => $newTotalSpent], "`id` = ?", [$txn['customer_id']]);
        } elseif ($txn['type'] === 'refund') {
            $newTotalSpent = floatval($customer['total_spent']) + abs(floatval($txn['amount']));
            $ToryHub->update_safe("customers", ['total_spent' => $newTotalSpent], "`id` = ?", [$txn['customer_id']]);
        }

        $ToryHub->remove_safe('transactions', "`id` = ?", [$id]);
        $ToryHub->commit();

        add_log($getUser['id'], 'delete_transaction', "Xóa giao dịch #$id");
        echo json_encode(['status' => 'success', 'msg' => __('Đã xóa giao dịch')]);
    } catch (Exception $e) {
        $ToryHub->rollBack();
        echo json_encode(['status' => 'error', 'msg' => __('Lỗi hệ thống')]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'msg' => 'Invalid request']);
