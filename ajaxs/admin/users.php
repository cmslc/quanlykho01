<?php
define("IN_SITE", true);
require_once(__DIR__.'/../../libs/db.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../libs/lang.php');
require_once(__DIR__.'/../../libs/helper.php');
require_once(__DIR__.'/../../libs/session.php');
require_once(__DIR__.'/../../libs/role.php');
require_once(__DIR__.'/../../libs/csrf.php');
require_once(__DIR__.'/../../libs/database/users.php');
require_once(__DIR__.'/../../models/is_admin.php');

header('Content-Type: application/json');
$ToryHub = new DB();
$csrf = new Csrf(true, false, false);

// Validate CSRF manually to return JSON error instead of die()
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!$csrf->validate()) {
        echo json_encode(['status' => 'error', 'msg' => __('Phiên làm việc hết hạn, vui lòng tải lại trang')]);
        exit();
    }
}

// ADD USER
if (is_submit('add')) {
    $username = check_string(input_post('username'));
    $password = input_post('password');
    $fullname = check_string(input_post('fullname'));
    $email = check_string(input_post('email'));
    $phone = check_string(input_post('phone'));
    $role = check_string(input_post('role'));

    if (empty($username) || empty($password)) {
        echo json_encode(['status' => 'error', 'msg' => __('Username and password are required')]);
        exit();
    }

    $allowed_roles = ['admin', 'staffcn', 'finance_cn', 'staffvn', 'finance_vn', 'customer'];
    if (empty($role) || !in_array($role, $allowed_roles)) {
        echo json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn vai trò')]);
        exit();
    }

    if ($ToryHub->get_row_safe("SELECT * FROM `users` WHERE `username` = ?", [$username])) {
        echo json_encode(['status' => 'error', 'msg' => __('Username already exists')]);
        exit();
    }

    $result = $ToryHub->insert_safe('users', [
        'username'    => $username,
        'password'    => TypePassword($password),
        'fullname'    => $fullname,
        'email'       => $email,
        'phone'       => $phone,
        'role'        => $role,
        'active'      => 1,
        'create_date' => gettime()
    ]);

    if (!$result) {
        echo json_encode(['status' => 'error', 'msg' => __('Lỗi lưu dữ liệu. Vui lòng kiểm tra lại thông tin')]);
        exit();
    }

    add_log($getUser['id'], 'ADD_USER', "Added user: $username ($role)");
    echo json_encode(['status' => 'success', 'msg' => __('User added successfully')]);
    exit();
}

// EDIT USER
if (is_submit('edit')) {
    $id = intval(input_post('id'));
    $fullname = check_string(input_post('fullname'));
    $email = check_string(input_post('email'));
    $phone = check_string(input_post('phone'));
    $role = check_string(input_post('role'));
    $banned = intval(input_post('banned'));
    $password = input_post('password');

    $allowed_roles = ['admin', 'staffcn', 'finance_cn', 'staffvn', 'finance_vn', 'customer'];
    if (empty($role) || !in_array($role, $allowed_roles)) {
        echo json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn vai trò')]);
        exit();
    }

    $data = [
        'fullname'    => $fullname,
        'email'       => $email,
        'phone'       => $phone,
        'role'        => $role,
        'banned'      => $banned,
        'update_date' => gettime()
    ];

    if (!empty($password)) {
        $data['password'] = TypePassword($password);
    }

    $result = $ToryHub->update_safe('users', $data, "`id` = ?", [$id]);
    if (!$result) {
        echo json_encode(['status' => 'error', 'msg' => __('Lỗi cập nhật dữ liệu. Vui lòng kiểm tra lại')]);
        exit();
    }

    add_log($getUser['id'], 'EDIT_USER', "Edited user ID: $id");
    echo json_encode(['status' => 'success', 'msg' => __('User updated successfully')]);
    exit();
}

// DELETE USER
if (is_submit('delete')) {
    $id = intval(input_post('id'));

    if ($id == $getUser['id']) {
        echo json_encode(['status' => 'error', 'msg' => __('Cannot delete yourself')]);
        exit();
    }

    $ToryHub->remove_safe('users', "`id` = ?", [$id]);
    add_log($getUser['id'], 'DELETE_USER', "Deleted user ID: $id");
    echo json_encode(['status' => 'success', 'msg' => __('User deleted successfully')]);
    exit();
}

echo json_encode(['status' => 'error', 'msg' => 'Invalid request']);
