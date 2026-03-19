<?php
/**
 * API Auth endpoints
 * POST /api/v1/auth.php?action=login
 * GET  /api/v1/auth.php?action=me
 */

require_once(__DIR__.'/bootstrap.php');
require_once(__DIR__.'/jwt.php');

$action = $_GET['action'] ?? '';

switch ($action) {

    // ===== LOGIN =====
    case 'login':
        api_method('POST');
        $input = api_input();
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($username) || empty($password)) {
            api_error('Vui lòng nhập tên đăng nhập và mật khẩu');
        }

        // Rate limit
        $blockResult = checkBlockIP('API', 15);
        if ($blockResult) {
            api_error('Quá nhiều lần thử. Vui lòng đợi 15 phút.', 429);
        }

        // Find user (any role)
        $user = $ToryHub->get_row_safe(
            "SELECT * FROM `users` WHERE `username` = ?",
            [$username]
        );

        if (!$user || !VerifyPassword($password, $user['password'])) {
            api_error('Sai tên đăng nhập hoặc mật khẩu');
        }

        if ($user['banned'] != 0) {
            api_error('Tài khoản đã bị khóa');
        }

        // Generate JWT
        $token = jwt_encode($user);

        // Update last login
        $ToryHub->update_safe('users', [
            'ip'           => myip(),
            'device'       => $_SERVER['HTTP_USER_AGENT'] ?? 'mobile-app',
            'time_session' => time(),
            'update_date'  => gettime()
        ], "`id` = ?", [$user['id']]);

        // Clear failed attempts
        $ToryHub->remove_safe('failed_attempts', "`ip_address` = ? AND `type` = 'API'", [myip()]);

        api_success([
            'token' => $token,
            'user' => [
                'id'       => $user['id'],
                'username' => $user['username'],
                'fullname' => $user['fullname'],
                'role'     => $user['role'],
                'language' => $user['language'] ?? 'vi'
            ]
        ], 'Đăng nhập thành công');
        break;

    // ===== GET CURRENT USER =====
    case 'me':
        api_method('GET');
        $user = api_auth();

        api_success([
            'user' => [
                'id'       => $user['id'],
                'username' => $user['username'],
                'fullname' => $user['fullname'],
                'role'     => $user['role']
            ]
        ]);
        break;

    // ===== REFRESH TOKEN =====
    case 'refresh':
        api_method('POST');
        $user = api_auth();

        // Lấy full user info để tạo token mới
        $fullUser = $ToryHub->get_row_safe("SELECT * FROM `users` WHERE `id` = ?", [$user['id']]);
        $token = jwt_encode($fullUser);

        api_success(['token' => $token], 'Token đã được làm mới');
        break;

    default:
        api_error('Action không hợp lệ', 404);
}
