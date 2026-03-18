<?php

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

$_is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
    || !empty($_POST['request_name']);

$ToryHub = new DB();

// Check cookie token
if (isset($_COOKIE["token"])) {
    $getUser = $ToryHub->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `role` = 'admin'", [$_COOKIE['token']]);
    if (!$getUser) {
        if ($_is_ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'msg' => 'Phiên đăng nhập hết hạn', 'redirect' => base_url('admin/login')]);
            exit();
        }
        header("location: " . base_url('admin/login'));
        exit();
    }
    $_SESSION['admin_login'] = $getUser['token'];
}

// Verify session
if (!isset($_SESSION['admin_login'])) {
    if ($_is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'msg' => 'Phiên đăng nhập hết hạn', 'redirect' => base_url('admin/login')]);
        exit();
    }
    redirect(base_url('admin/login'));
} else {
    $getUser = $ToryHub->get_row_safe("SELECT * FROM `users` WHERE `role` = 'admin' AND `token` = ?", [$_SESSION['admin_login']]);

    if (!$getUser) {
        redirect(base_url('admin/login'));
    }

    if ($getUser['banned'] != 0) {
        redirect(base_url('common/banned'));
    }

    // Update session time
    $ToryHub->update_safe("users", [
        'time_session' => time()
    ], "`id` = ?", [$getUser['id']]);
}
