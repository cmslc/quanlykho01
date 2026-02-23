<?php
define('IN_SITE', true);
require_once(__DIR__.'/../../libs/db.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../libs/lang.php');
require_once(__DIR__.'/../../libs/helper.php');
require_once(__DIR__.'/../../libs/session.php');
require_once(__DIR__.'/../../libs/role.php');
require_once(__DIR__.'/../../libs/csrf.php');

header('Content-Type: application/json; charset=utf-8');

$CMSNT = new DB();

$csrf = new Csrf();
if (!$csrf->validate()) {
    echo json_encode(['status' => 'error', 'msg' => __('Token không hợp lệ')]);
    exit;
}

// Check customer login
if (!isset($_SESSION['customer_login'])) {
    echo json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]);
    exit;
}

$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `role` = 'customer' AND `token` = ?", [$_SESSION['customer_login']]);
if (!$getUser) {
    echo json_encode(['status' => 'error', 'msg' => __('Phiên đăng nhập hết hạn')]);
    exit;
}

$request = input_post('request_name');

if ($request === 'change_password') {
    $current_password = input_post('current_password');
    $new_password = input_post('new_password');
    $confirm_password = input_post('confirm_password');

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        echo json_encode(['status' => 'error', 'msg' => __('Vui lòng điền đầy đủ thông tin')]);
        exit;
    }

    // Verify current password
    if (!VerifyPassword($current_password, $getUser['password'])) {
        echo json_encode(['status' => 'error', 'msg' => __('Mật khẩu hiện tại không đúng')]);
        exit;
    }

    // Check new password length
    if (strlen($new_password) < 6) {
        echo json_encode(['status' => 'error', 'msg' => __('Mật khẩu mới phải có ít nhất 6 ký tự')]);
        exit;
    }

    // Check confirm match
    if ($new_password !== $confirm_password) {
        echo json_encode(['status' => 'error', 'msg' => __('Mật khẩu xác nhận không khớp')]);
        exit;
    }

    // Check new password != old password
    if ($current_password === $new_password) {
        echo json_encode(['status' => 'error', 'msg' => __('Mật khẩu mới phải khác mật khẩu cũ')]);
        exit;
    }

    // Update password
    $hashed = TypePassword($new_password);
    $CMSNT->update_safe('users', [
        'password' => $hashed,
        'update_date' => gettime()
    ], "`id` = ?", [$getUser['id']]);

    add_log($getUser['id'], 'change_password', 'Customer changed password');

    echo json_encode(['status' => 'success', 'msg' => __('Đổi mật khẩu thành công')]);
    exit;
}

echo json_encode(['status' => 'error', 'msg' => 'Invalid request']);
