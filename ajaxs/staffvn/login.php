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

header('Content-Type: application/json');

$ToryHub = new DB();
$csrf = new Csrf(true, true, false);

if (!is_submit('login')) {
    echo json_encode(['status' => 'error', 'msg' => 'Invalid request']);
    exit();
}

$username = check_string(input_post('username'));
$password = input_post('password');

if (empty($username) || empty($password)) {
    echo json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập tên đăng nhập và mật khẩu')]);
    exit();
}

// Check block IP
$blockResult = checkBlockIP('STAFFVN', 15);
if ($blockResult) {
    echo $blockResult;
    exit();
}

// Find user - specifically check for staffvn role
$getUser = $ToryHub->get_row_safe("SELECT * FROM `users` WHERE `username` = ? AND `role` IN ('staffvn', 'finance_vn')", [$username]);

if (!$getUser) {
    echo json_encode(['status' => 'error', 'msg' => __('Tên đăng nhập hoặc mật khẩu không đúng')]);
    exit();
}

// Verify password
if (!VerifyPassword($password, $getUser['password'])) {
    echo json_encode(['status' => 'error', 'msg' => __('Tên đăng nhập hoặc mật khẩu không đúng')]);
    exit();
}

// Check banned
if ($getUser['banned'] != 0) {
    echo json_encode(['status' => 'error', 'msg' => __('Tài khoản đã bị khóa')]);
    exit();
}

// Generate token and login
$token = generateUltraSecureToken();

$ToryHub->update_safe('users', [
    'token'        => $token,
    'ip'           => myip(),
    'device'       => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'time_session' => time(),
    'update_date'  => gettime()
], "`id` = ?", [$getUser['id']]);

// Set session and cookie
$_SESSION['staffvn_login'] = $token;
setSecureCookie('token', $token);

// Log
add_log($getUser['id'], 'LOGIN', 'Staff VN login successful');

// Clear failed attempts for this IP
$ToryHub->remove_safe('failed_attempts', "`ip_address` = ? AND `type` = 'STAFFVN'", [myip()]);

echo json_encode([
    'status'   => 'success',
    'msg'      => __('Đăng nhập thành công'),
    'redirect' => base_url('staffvn/home')
]);
