<?php
/**
 * API Bootstrap - Khởi tạo môi trường cho API
 */
define("IN_SITE", true);
define("IN_API", true);

require_once(__DIR__.'/../../libs/db.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../libs/lang.php');
require_once(__DIR__.'/../../libs/helper.php');
require_once(__DIR__.'/../../libs/session.php');
require_once(__DIR__.'/../../libs/role.php');

// CORS headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$ToryHub = new DB();

/**
 * JSON response helper
 */
function api_response($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function api_error($msg, $code = 400) {
    api_response(['status' => 'error', 'msg' => $msg], $code);
}

function api_success($data = [], $msg = 'OK') {
    api_response(array_merge(['status' => 'success', 'msg' => $msg], $data));
}

/**
 * Get JSON body from request
 */
function api_input() {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

/**
 * Get single input value
 */
function api_get($key, $default = null) {
    $input = api_input();
    return isset($input[$key]) ? trim($input[$key]) : $default;
}

/**
 * Require specific HTTP method
 */
function api_method($method) {
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        api_error('Method not allowed', 405);
    }
}

/**
 * Pagination helper
 */
function api_pagination($page = 1, $per_page = 20) {
    $page = max(1, intval($_GET['page'] ?? $page));
    $per_page = max(1, min(100, intval($_GET['per_page'] ?? $per_page)));
    $offset = ($page - 1) * $per_page;
    return ['page' => $page, 'per_page' => $per_page, 'offset' => $offset];
}
