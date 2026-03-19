<?php
/**
 * JWT Authentication helper
 * Uses firebase/php-jwt library
 */

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// JWT secret key - lấy từ .env hoặc dùng mặc định
define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? 'toryhub-api-secret-key-change-me');
define('JWT_ALGO', 'HS256');
define('JWT_EXPIRE', 86400 * 7); // 7 ngày

/**
 * Tạo JWT token cho user
 */
function jwt_encode($user) {
    $now = time();
    $payload = [
        'iss' => 'toryhub-api',
        'iat' => $now,
        'exp' => $now + JWT_EXPIRE,
        'sub' => $user['id'],
        'role' => $user['role'],
        'username' => $user['username'],
        'fullname' => $user['fullname'] ?? ''
    ];
    return JWT::encode($payload, JWT_SECRET, JWT_ALGO);
}

/**
 * Decode JWT token
 * Returns payload array hoặc null nếu invalid
 */
function jwt_decode_token($token) {
    try {
        $decoded = JWT::decode($token, new Key(JWT_SECRET, JWT_ALGO));
        return (array) $decoded;
    } catch (\Exception $e) {
        return null;
    }
}

/**
 * Lấy token từ Authorization header
 */
function get_bearer_token() {
    $headers = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $headers = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        $all = apache_request_headers();
        $headers = $all['Authorization'] ?? $all['authorization'] ?? '';
    }

    if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
        return $matches[1];
    }
    return null;
}

/**
 * Middleware: Yêu cầu đăng nhập
 * Returns user data từ JWT payload
 */
function api_auth() {
    global $ToryHub;

    $token = get_bearer_token();
    if (!$token) {
        api_error('Token không hợp lệ', 401);
    }

    $payload = jwt_decode_token($token);
    if (!$payload) {
        api_error('Token hết hạn hoặc không hợp lệ', 401);
    }

    // Verify user vẫn tồn tại và chưa bị ban
    $user = $ToryHub->get_row_safe(
        "SELECT `id`, `username`, `fullname`, `role`, `banned` FROM `users` WHERE `id` = ?",
        [$payload['sub']]
    );

    if (!$user || $user['banned'] != 0) {
        api_error('Tài khoản không tồn tại hoặc đã bị khóa', 401);
    }

    return $user;
}

/**
 * Middleware: Yêu cầu role cụ thể
 */
function api_require_role($roles) {
    $user = api_auth();
    if (is_string($roles)) $roles = [$roles];

    if (!in_array($user['role'], $roles)) {
        api_error('Bạn không có quyền truy cập', 403);
    }

    return $user;
}
