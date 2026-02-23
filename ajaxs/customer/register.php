<?php
define("IN_SITE", true);
require_once(__DIR__.'/../../libs/db.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../libs/lang.php');
require_once(__DIR__.'/../../libs/helper.php');
require_once(__DIR__.'/../../libs/session.php');
require_once(__DIR__.'/../../libs/role.php');
require_once(__DIR__.'/../../libs/csrf.php');

header('Content-Type: application/json');

$ToryHub = new DB();
$csrf = new Csrf(true, true, false);

if (!is_submit('register')) {
    echo json_encode(['status' => 'error', 'msg' => 'Invalid request']);
    exit();
}

// Check block IP
$blockResult = checkBlockIP('REGISTER', 15);
if ($blockResult) {
    echo $blockResult;
    exit();
}

$username         = check_string(input_post('username'));
$fullname         = check_string(input_post('fullname'));
$email            = trim(input_post('email') ?: '');
$phone            = check_string(input_post('phone') ?: '');
$password         = input_post('password');
$confirm_password = input_post('confirm_password');

// Validate username
if (empty($username) || empty($fullname) || empty($password)) {
    echo json_encode(['status' => 'error', 'msg' => __('Vui lòng điền đầy đủ thông tin bắt buộc')]);
    exit();
}

if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
    echo json_encode(['status' => 'error', 'msg' => __('Tên đăng nhập phải từ 3-50 ký tự, chỉ gồm chữ cái, số và dấu gạch dưới')]);
    exit();
}

// Validate password
if (strlen($password) < 6) {
    echo json_encode(['status' => 'error', 'msg' => __('Mật khẩu phải có ít nhất 6 ký tự')]);
    exit();
}

if ($password !== $confirm_password) {
    echo json_encode(['status' => 'error', 'msg' => __('Mật khẩu xác nhận không khớp')]);
    exit();
}

// Validate email
if (!empty($email) && !check_email($email)) {
    echo json_encode(['status' => 'error', 'msg' => __('Email không hợp lệ')]);
    exit();
}

// Validate phone
if (!empty($phone) && !check_phone($phone)) {
    echo json_encode(['status' => 'error', 'msg' => __('Số điện thoại không hợp lệ')]);
    exit();
}

// Check duplicate username
$existUser = $ToryHub->get_row_safe("SELECT `id` FROM `users` WHERE `username` = ?", [$username]);
if ($existUser) {
    echo json_encode(['status' => 'error', 'msg' => __('Tên đăng nhập đã tồn tại')]);
    exit();
}

// Check duplicate email
if (!empty($email)) {
    $existEmail = $ToryHub->get_row_safe("SELECT `id` FROM `users` WHERE `email` = ? AND `email` != ''", [$email]);
    if ($existEmail) {
        echo json_encode(['status' => 'error', 'msg' => __('Email đã được sử dụng')]);
        exit();
    }
}

// Insert new customer
$token = generateUltraSecureToken();

$ToryHub->insert_safe('users', [
    'username'    => $username,
    'password'    => TypePassword($password),
    'fullname'    => $fullname,
    'email'       => $email,
    'phone'       => $phone,
    'role'        => 'customer',
    'active'      => 1,
    'token'       => $token,
    'ip'          => myip(),
    'device'      => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'time_session'=> time(),
    'create_date' => gettime()
]);

$newUser = $ToryHub->get_row_safe("SELECT `id` FROM `users` WHERE `username` = ?", [$username]);
$newUserId = $newUser ? $newUser['id'] : 0;

// Auto login
$_SESSION['customer_login'] = $token;
setSecureCookie('token', $token);
setSecureCookie('customer_token', $token);
set_logged($username, 'customer');

// Log
add_log($newUserId, 'REGISTER', 'Customer registration successful');

// Clear failed attempts
$ToryHub->remove_safe('failed_attempts', "`ip_address` = ? AND `type` = 'REGISTER'", [myip()]);

echo json_encode([
    'status'   => 'success',
    'msg'      => __('Đăng ký thành công'),
    'redirect' => base_url('customer/home')
]);
