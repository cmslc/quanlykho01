<?php
/**
 * API Transactions endpoints
 */

require_once(__DIR__.'/bootstrap.php');
require_once(__DIR__.'/jwt.php');

$user = api_auth();
$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

// ===== GET =====
if ($method === 'GET') {
    if ($id) {
        $txn = $ToryHub->get_row_safe(
            "SELECT t.*, c.fullname as customer_name, c.customer_code
             FROM `transactions` t LEFT JOIN `customers` c ON t.customer_id = c.id WHERE t.id = ?", [$id]
        );
        if (!$txn) api_error('Giao dịch không tồn tại', 404);
        api_success(['transaction' => $txn]);
    }

    $pg = api_pagination();
    $type = $_GET['type'] ?? '';
    $customer_id = $_GET['customer_id'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';

    $where = "1=1"; $params = [];
    if ($type) { $where .= " AND t.type = ?"; $params[] = $type; }
    if ($customer_id) { $where .= " AND t.customer_id = ?"; $params[] = $customer_id; }
    if ($date_from) { $where .= " AND DATE(t.create_date) >= ?"; $params[] = $date_from; }
    if ($date_to) { $where .= " AND DATE(t.create_date) <= ?"; $params[] = $date_to; }

    $total = $ToryHub->get_row_safe("SELECT COUNT(*) as cnt FROM `transactions` t WHERE $where", $params);
    $listParams = array_merge($params, [$pg['per_page'], $pg['offset']]);
    $transactions = $ToryHub->get_list_safe(
        "SELECT t.*, c.fullname as customer_name, c.customer_code
         FROM `transactions` t LEFT JOIN `customers` c ON t.customer_id = c.id
         WHERE $where ORDER BY t.id DESC LIMIT ? OFFSET ?", $listParams
    );

    api_success([
        'transactions' => $transactions,
        'total' => (int)($total['cnt'] ?? 0),
        'page' => $pg['page'],
        'per_page' => $pg['per_page']
    ]);
}

// ===== POST: Create Transaction =====
if ($method === 'POST') {
    $input = api_input();
    $customer_id = intval($input['customer_id'] ?? 0);
    $type = $input['type'] ?? '';
    $amount = floatval($input['amount'] ?? 0);
    $description = trim($input['description'] ?? '');
    $order_code = trim($input['order_code'] ?? '');

    if (!$customer_id) api_error('Vui lòng chọn khách hàng');
    if (!in_array($type, ['deposit', 'payment', 'refund', 'adjustment'])) api_error('Loại giao dịch không hợp lệ');
    if ($amount <= 0) api_error('Số tiền phải lớn hơn 0');

    $customer = $ToryHub->get_row_safe("SELECT * FROM `customers` WHERE `id` = ?", [$customer_id]);
    if (!$customer) api_error('Khách hàng không tồn tại');

    $order_id = null;
    if ($order_code) {
        $order = $ToryHub->get_row_safe("SELECT `id` FROM `orders` WHERE `order_code` = ?", [$order_code]);
        if ($order) $order_id = $order['id'];
    }

    $balance_before = floatval($customer['balance']);
    if ($type === 'deposit' || $type === 'refund') {
        $actual_amount = $amount;
        $balance_after = $balance_before + $amount;
    } elseif ($type === 'payment') {
        $actual_amount = -$amount;
        $balance_after = $balance_before - $amount;
    } else {
        $actual_amount = $amount;
        $balance_after = $balance_before + $amount;
    }

    $ToryHub->beginTransaction();
    try {
        $ToryHub->insert_safe('transactions', [
            'customer_id' => $customer_id, 'order_id' => $order_id, 'type' => $type,
            'amount' => $actual_amount, 'balance_before' => $balance_before,
            'balance_after' => $balance_after, 'description' => $description,
            'created_by' => $user['id'], 'create_date' => gettime()
        ]);
        $ToryHub->update_safe('customers', ['balance' => $balance_after, 'update_date' => gettime()], "`id` = ?", [$customer_id]);
        $ToryHub->commit();
        api_success(['transaction_id' => $ToryHub->insert_id()], 'Tạo giao dịch thành công');
    } catch (Exception $e) {
        $ToryHub->rollBack();
        api_error('Lỗi hệ thống', 500);
    }
}

// ===== PUT: Edit Transaction =====
if ($method === 'PUT' && $id) {
    $input = api_input();
    $amount = floatval($input['amount'] ?? 0);
    $description = trim($input['description'] ?? '');

    if ($amount <= 0) api_error('Số tiền phải lớn hơn 0');

    $txn = $ToryHub->get_row_safe("SELECT * FROM `transactions` WHERE `id` = ?", [$id]);
    if (!$txn) api_error('Giao dịch không tồn tại');

    $customer = $ToryHub->get_row_safe("SELECT * FROM `customers` WHERE `id` = ?", [$txn['customer_id']]);
    if (!$customer) api_error('Khách hàng không tồn tại');

    $oldAmount = floatval($txn['amount']);
    $newActual = ($txn['type'] === 'payment') ? -$amount : $amount;
    $currentBalance = floatval($customer['balance']);
    $newBalance = $currentBalance - $oldAmount + $newActual;

    $ToryHub->beginTransaction();
    try {
        $ToryHub->update_safe('transactions', [
            'amount' => $newActual, 'balance_after' => $newBalance, 'description' => $description
        ], "`id` = ?", [$id]);
        $ToryHub->update_safe('customers', ['balance' => $newBalance, 'update_date' => gettime()], "`id` = ?", [$txn['customer_id']]);
        $ToryHub->commit();
        api_success([], 'Cập nhật giao dịch thành công');
    } catch (Exception $e) {
        $ToryHub->rollBack();
        api_error('Lỗi hệ thống', 500);
    }
}

// ===== DELETE =====
if ($method === 'DELETE' && $id) {
    $txn = $ToryHub->get_row_safe("SELECT * FROM `transactions` WHERE `id` = ?", [$id]);
    if (!$txn) api_error('Giao dịch không tồn tại');

    $customer = $ToryHub->get_row_safe("SELECT * FROM `customers` WHERE `id` = ?", [$txn['customer_id']]);
    if (!$customer) api_error('Khách hàng không tồn tại');

    $ToryHub->beginTransaction();
    try {
        $newBalance = floatval($customer['balance']) - floatval($txn['amount']);
        $ToryHub->update_safe('customers', ['balance' => $newBalance, 'update_date' => gettime()], "`id` = ?", [$txn['customer_id']]);
        $ToryHub->remove_safe('transactions', "`id` = ?", [$id]);
        $ToryHub->commit();
        api_success([], 'Đã xóa giao dịch');
    } catch (Exception $e) {
        $ToryHub->rollBack();
        api_error('Lỗi hệ thống', 500);
    }
}

api_error('Method not allowed', 405);
