<?php
/**
 * API Users endpoints
 */

require_once(__DIR__.'/bootstrap.php');
require_once(__DIR__.'/jwt.php');

$user = api_auth();
$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

// ===== GET =====
if ($method === 'GET') {
    if ($id) {
        $u = $ToryHub->get_row_safe(
            "SELECT `id`, `username`, `fullname`, `email`, `phone`, `role`, `active`, `banned`, `language`, `create_date`
             FROM `users` WHERE `id` = ?", [$id]
        );
        if (!$u) api_error('Nhân viên không tồn tại', 404);
        api_success(['user' => $u]);
    }

    $pg = api_pagination();
    $role = $_GET['role'] ?? '';
    $search = $_GET['search'] ?? '';

    $where = "1=1"; $params = [];
    if ($role) { $where .= " AND `role` = ?"; $params[] = $role; }
    if ($search) { $where .= " AND (`fullname` LIKE ? OR `username` LIKE ?)"; $s = "%$search%"; $params[] = $s; $params[] = $s; }

    $total = $ToryHub->get_row_safe("SELECT COUNT(*) as cnt FROM `users` WHERE $where", $params);
    $listParams = array_merge($params, [$pg['per_page'], $pg['offset']]);
    $users = $ToryHub->get_list_safe(
        "SELECT `id`, `username`, `fullname`, `email`, `phone`, `role`, `active`, `banned`, `create_date`
         FROM `users` WHERE $where ORDER BY `id` DESC LIMIT ? OFFSET ?", $listParams
    );

    api_success([
        'users' => $users,
        'total' => (int)($total['cnt'] ?? 0),
        'page' => $pg['page'],
        'per_page' => $pg['per_page']
    ]);
}

// ===== POST: Create User =====
if ($method === 'POST') {
    if ($user['role'] !== 'admin') api_error('Không có quyền', 403);

    $input = api_input();
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $fullname = trim($input['fullname'] ?? '');
    $role = $input['role'] ?? '';

    if (empty($username) || empty($password)) api_error('Username và mật khẩu là bắt buộc');

    $allowed_roles = ['admin', 'staffcn', 'finance_cn', 'staffvn', 'finance_vn', 'customer'];
    if (!in_array($role, $allowed_roles)) api_error('Vai trò không hợp lệ');

    $exists = $ToryHub->get_row_safe("SELECT * FROM `users` WHERE `username` = ?", [$username]);
    if ($exists) api_error('Username đã tồn tại');

    $ToryHub->insert_safe('users', [
        'username' => $username,
        'password' => password_hash($password, PASSWORD_BCRYPT),
        'fullname' => $fullname,
        'email' => trim($input['email'] ?? ''),
        'phone' => trim($input['phone'] ?? ''),
        'role' => $role,
        'active' => 1,
        'create_date' => gettime()
    ]);

    api_success(['user_id' => $ToryHub->insert_id()], 'Tạo nhân viên thành công');
}

// ===== PUT: Edit User =====
if ($method === 'PUT' && $id) {
    if ($user['role'] !== 'admin') api_error('Không có quyền', 403);

    $input = api_input();
    $data = [
        'fullname' => trim($input['fullname'] ?? ''),
        'email' => trim($input['email'] ?? ''),
        'phone' => trim($input['phone'] ?? ''),
        'role' => $input['role'] ?? '',
        'banned' => intval($input['banned'] ?? 0),
        'update_date' => gettime()
    ];

    if (!empty($input['password'])) {
        $data['password'] = password_hash($input['password'], PASSWORD_BCRYPT);
    }

    $ToryHub->update_safe('users', $data, "`id` = ?", [$id]);
    api_success([], 'Cập nhật nhân viên thành công');
}

// ===== DELETE =====
if ($method === 'DELETE' && $id) {
    if ($user['role'] !== 'admin') api_error('Không có quyền', 403);
    if ($id == $user['id']) api_error('Không thể xóa chính mình');

    $ToryHub->remove_safe('users', "`id` = ?", [$id]);
    api_success([], 'Đã xóa nhân viên');
}

api_error('Method not allowed', 405);
