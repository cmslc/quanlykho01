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

$ToryHub = new DB();

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

$getUser = $ToryHub->get_row_safe("SELECT * FROM `users` WHERE `role` = 'customer' AND `token` = ?", [$_SESSION['customer_login']]);
if (!$getUser) {
    echo json_encode(['status' => 'error', 'msg' => __('Phiên đăng nhập hết hạn')]);
    exit;
}

$request = input_post('request_name');

if ($request === 'update_profile') {
    $fullname = trim(input_post('fullname'));
    $email = trim(input_post('email'));
    $phone = trim(input_post('phone'));
    $address_vn = trim(input_post('address_vn'));
    $zalo = trim(input_post('zalo'));
    $wechat = trim(input_post('wechat'));

    // Validate fullname
    if (empty($fullname) || mb_strlen($fullname) < 2 || mb_strlen($fullname) > 255) {
        echo json_encode(['status' => 'error', 'msg' => __('Họ và tên phải có từ 2 đến 255 ký tự')]);
        exit;
    }

    // Validate email if provided
    if (!empty($email)) {
        if (!check_email($email)) {
            echo json_encode(['status' => 'error', 'msg' => __('Email không hợp lệ')]);
            exit;
        }
        // Check duplicate email (exclude current user)
        $existEmail = $ToryHub->get_row_safe("SELECT `id` FROM `users` WHERE `email` = ? AND `id` != ?", [$email, $getUser['id']]);
        if ($existEmail) {
            echo json_encode(['status' => 'error', 'msg' => __('Email đã được sử dụng bởi tài khoản khác')]);
            exit;
        }
    }

    // Validate phone if provided
    if (!empty($phone) && !check_phone($phone)) {
        echo json_encode(['status' => 'error', 'msg' => __('Số điện thoại không hợp lệ')]);
        exit;
    }

    // Update users table
    $ToryHub->update_safe('users', [
        'fullname' => $fullname,
        'email' => $email,
        'phone' => $phone,
        'update_date' => gettime()
    ], "`id` = ?", [$getUser['id']]);

    // Update customers table
    $customer = $ToryHub->get_row_safe("SELECT `id` FROM `customers` WHERE `user_id` = ?", [$getUser['id']]);
    if ($customer) {
        $ToryHub->update_safe('customers', [
            'fullname' => $fullname,
            'email' => $email,
            'phone' => $phone,
            'address_vn' => $address_vn,
            'zalo' => $zalo,
            'wechat' => $wechat,
            'update_date' => gettime()
        ], "`id` = ?", [$customer['id']]);
    }

    add_log($getUser['id'], 'update_profile', 'Customer updated profile');

    echo json_encode(['status' => 'success', 'msg' => __('Cập nhật thông tin thành công')]);
    exit;
}

echo json_encode(['status' => 'error', 'msg' => 'Invalid request']);
