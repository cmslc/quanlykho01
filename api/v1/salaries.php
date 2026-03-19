<?php
/**
 * API Salaries endpoints
 */

require_once(__DIR__.'/bootstrap.php');
require_once(__DIR__.'/jwt.php');

$user = api_auth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

// ===== GENERATE MONTHLY =====
if ($action === 'generate' && $method === 'POST') {
    $input = api_input();
    $month = intval($input['month'] ?? 0);
    $year = intval($input['year'] ?? 0);

    if ($month < 1 || $month > 12 || $year < 2020) api_error('Tháng/năm không hợp lệ');

    $staffList = $ToryHub->get_list_safe(
        "SELECT id, role FROM `users` WHERE `role` IN ('staffcn','staffvn','finance_cn','finance_vn') AND `active` = 1 AND `banned` = 0", []
    );
    if (empty($staffList)) api_error('Không có nhân viên nào');

    $existing = $ToryHub->get_list_safe("SELECT `user_id` FROM `salaries` WHERE `month` = ? AND `year` = ?", [$month, $year]);
    $existingIds = array_column($existing, 'user_id');

    $prevMonth = $month == 1 ? 12 : $month - 1;
    $prevYear = $month == 1 ? $year - 1 : $year;
    $prevSalaries = $ToryHub->get_list_safe("SELECT `user_id`, `base_salary`, `allowance` FROM `salaries` WHERE `month` = ? AND `year` = ?", [$prevMonth, $prevYear]);
    $prevMap = [];
    foreach ($prevSalaries as $ps) $prevMap[$ps['user_id']] = $ps;

    $created = 0;
    foreach ($staffList as $staff) {
        if (in_array($staff['id'], $existingIds)) continue;
        $currency = in_array($staff['role'], ['staffcn', 'finance_cn']) ? 'CNY' : 'VND';
        $baseSalary = isset($prevMap[$staff['id']]) ? $prevMap[$staff['id']]['base_salary'] : 0;
        $allowance = isset($prevMap[$staff['id']]) ? $prevMap[$staff['id']]['allowance'] : 0;

        $ToryHub->insert_safe('salaries', [
            'user_id' => $staff['id'], 'month' => $month, 'year' => $year,
            'currency' => $currency, 'base_salary' => $baseSalary, 'allowance' => $allowance,
            'net_salary' => $baseSalary + $allowance, 'status' => 'draft',
            'created_by' => $user['id'], 'create_date' => gettime()
        ]);
        $created++;
    }

    if ($created === 0) api_success([], 'Tất cả nhân viên đã có bảng lương tháng này');
    api_success(['created' => $created], "Đã tạo bảng lương cho $created nhân viên");
}

// ===== CONFIRM =====
if ($action === 'confirm' && $method === 'POST') {
    $input = api_input();
    $sid = intval($input['id'] ?? 0);
    $salary = $ToryHub->get_row_safe("SELECT * FROM `salaries` WHERE `id` = ?", [$sid]);
    if (!$salary) api_error('Bản ghi không tồn tại');
    if ($salary['status'] !== 'draft') api_error('Chỉ có thể xác nhận bản ghi nháp');

    $ToryHub->update_safe('salaries', ['status' => 'confirmed'], "`id` = ?", [$sid]);
    api_success([], 'Đã xác nhận');
}

// ===== MARK PAID =====
if ($action === 'mark_paid' && $method === 'POST') {
    $input = api_input();
    $sid = intval($input['id'] ?? 0);
    $salary = $ToryHub->get_row_safe("SELECT * FROM `salaries` WHERE `id` = ?", [$sid]);
    if (!$salary) api_error('Bản ghi không tồn tại');
    if ($salary['status'] === 'paid') api_error('Đã thanh toán rồi');

    $ToryHub->update_safe('salaries', ['status' => 'paid', 'paid_date' => gettime()], "`id` = ?", [$sid]);
    api_success([], 'Đã đánh dấu thanh toán');
}

// ===== PUT: Update Salary =====
if ($method === 'PUT' && $id) {
    $input = api_input();
    $salary = $ToryHub->get_row_safe("SELECT * FROM `salaries` WHERE `id` = ?", [$id]);
    if (!$salary) api_error('Bản ghi không tồn tại');
    if ($salary['status'] === 'paid') api_error('Không thể sửa bản ghi đã thanh toán');

    $baseSalary = floatval($input['base_salary'] ?? $salary['base_salary']);
    $allowance = floatval($input['allowance'] ?? $salary['allowance']);
    $bonus = floatval($input['bonus'] ?? $salary['bonus']);
    $deduction = floatval($input['deduction'] ?? $salary['deduction']);

    $ToryHub->update_safe('salaries', [
        'base_salary' => $baseSalary, 'allowance' => $allowance,
        'bonus' => $bonus, 'deduction' => $deduction,
        'net_salary' => $baseSalary + $allowance + $bonus - $deduction,
        'work_days' => isset($input['work_days']) ? intval($input['work_days']) : $salary['work_days'],
        'note' => $input['note'] ?? $salary['note'],
        'update_date' => gettime()
    ], "`id` = ?", [$id]);
    api_success([], 'Đã cập nhật lương');
}

// ===== DELETE =====
if ($method === 'DELETE' && $id) {
    $salary = $ToryHub->get_row_safe("SELECT * FROM `salaries` WHERE `id` = ?", [$id]);
    if (!$salary) api_error('Bản ghi không tồn tại');
    if ($salary['status'] !== 'draft') api_error('Chỉ có thể xóa bản ghi nháp');
    $ToryHub->remove_safe('salaries', "`id` = ?", [$id]);
    api_success([], 'Đã xóa');
}

// ===== GET =====
if ($method === 'GET') {
    if ($id) {
        $salary = $ToryHub->get_row_safe(
            "SELECT s.*, u.fullname, u.role FROM `salaries` s LEFT JOIN `users` u ON s.user_id = u.id WHERE s.id = ?", [$id]
        );
        if (!$salary) api_error('Không tìm thấy', 404);
        api_success(['salary' => $salary]);
    }

    $pg = api_pagination();
    $month = $_GET['month'] ?? '';
    $year = $_GET['year'] ?? '';
    $status = $_GET['status'] ?? '';

    $where = "1=1"; $params = [];
    if ($month) { $where .= " AND s.month = ?"; $params[] = $month; }
    if ($year) { $where .= " AND s.year = ?"; $params[] = $year; }
    if ($status) { $where .= " AND s.status = ?"; $params[] = $status; }

    $total = $ToryHub->get_row_safe("SELECT COUNT(*) as cnt FROM `salaries` s WHERE $where", $params);
    $listParams = array_merge($params, [$pg['per_page'], $pg['offset']]);
    $salaries = $ToryHub->get_list_safe(
        "SELECT s.*, u.fullname, u.role FROM `salaries` s
         LEFT JOIN `users` u ON s.user_id = u.id WHERE $where ORDER BY s.id DESC LIMIT ? OFFSET ?", $listParams
    );

    api_success([
        'salaries' => $salaries,
        'total' => (int)($total['cnt'] ?? 0),
        'page' => $pg['page'],
        'per_page' => $pg['per_page']
    ]);
}

api_error('Method not allowed', 405);
