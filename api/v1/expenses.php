<?php
/**
 * API Expenses endpoints
 */

require_once(__DIR__.'/bootstrap.php');
require_once(__DIR__.'/jwt.php');

$user = api_auth();
$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

// ===== GET =====
if ($method === 'GET') {
    $pg = api_pagination();
    $category = $_GET['category'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';

    $where = "1=1"; $params = [];
    if ($category) { $where .= " AND `category` = ?"; $params[] = $category; }
    if ($date_from) { $where .= " AND `expense_date` >= ?"; $params[] = $date_from; }
    if ($date_to) { $where .= " AND `expense_date` <= ?"; $params[] = $date_to; }

    $total = $ToryHub->get_row_safe("SELECT COUNT(*) as cnt FROM `expenses` WHERE $where", $params);
    $listParams = array_merge($params, [$pg['per_page'], $pg['offset']]);
    $expenses = $ToryHub->get_list_safe(
        "SELECT * FROM `expenses` WHERE $where ORDER BY `expense_date` DESC, `id` DESC LIMIT ? OFFSET ?", $listParams
    );

    api_success([
        'expenses' => $expenses,
        'total' => (int)($total['cnt'] ?? 0),
        'page' => $pg['page'],
        'per_page' => $pg['per_page']
    ]);
}

// ===== POST =====
if ($method === 'POST') {
    $input = api_input();
    $category = trim($input['category'] ?? '');
    $amount = floatval($input['amount'] ?? 0);
    $description = trim($input['description'] ?? '');
    $expense_date = $input['expense_date'] ?? date('Y-m-d');

    if (empty($category)) api_error('Vui lòng nhập danh mục');
    if ($amount <= 0) api_error('Số tiền phải lớn hơn 0');

    $ToryHub->insert_safe('expenses', [
        'category' => $category, 'amount' => $amount,
        'description' => $description, 'expense_date' => $expense_date,
        'created_by' => $user['id'], 'create_date' => gettime()
    ]);

    api_success(['expense_id' => $ToryHub->insert_id()], 'Thêm chi phí thành công');
}

// ===== PUT =====
if ($method === 'PUT' && $id) {
    $input = api_input();
    $ToryHub->update_safe('expenses', [
        'category' => trim($input['category'] ?? ''),
        'amount' => floatval($input['amount'] ?? 0),
        'description' => trim($input['description'] ?? ''),
        'expense_date' => $input['expense_date'] ?? date('Y-m-d'),
    ], "`id` = ?", [$id]);
    api_success([], 'Cập nhật chi phí thành công');
}

// ===== DELETE =====
if ($method === 'DELETE' && $id) {
    $ToryHub->remove_safe('expenses', "`id` = ?", [$id]);
    api_success([], 'Đã xóa chi phí');
}

api_error('Method not allowed', 405);
