<?php
/**
 * API Settings endpoints
 */

require_once(__DIR__.'/bootstrap.php');
require_once(__DIR__.'/jwt.php');

$user = api_auth();
$method = $_SERVER['REQUEST_METHOD'];

// ===== GET =====
if ($method === 'GET') {
    $rows = $ToryHub->get_list_safe("SELECT `name`, `value` FROM `settings`", []);
    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['name']] = $row['value'];
    }
    api_success(['settings' => $settings]);
}

// ===== PUT =====
if ($method === 'PUT') {
    if ($user['role'] !== 'admin') api_error('Không có quyền', 403);

    $input = api_input();
    foreach ($input as $key => $value) {
        $existing = $ToryHub->get_row_safe("SELECT `id` FROM `settings` WHERE `name` = ?", [$key]);
        if ($existing) {
            $ToryHub->update_safe('settings', ['value' => $value], "`id` = ?", [$existing['id']]);
        } else {
            $ToryHub->insert_safe('settings', ['name' => $key, 'value' => $value]);
        }
    }
    api_success([], 'Cập nhật cài đặt thành công');
}

api_error('Method not allowed', 405);
