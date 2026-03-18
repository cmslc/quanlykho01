<?php

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

$_is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
    || !empty($_POST['request_name']);

$ToryHub = new DB();

if (isset($_COOKIE["token"])) {
    $getUser = $ToryHub->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `role` IN ('staffcn','finance_cn')", [$_COOKIE['token']]);
    if (!$getUser) {
        if ($_is_ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'msg' => 'Phiên đăng nhập hết hạn', 'redirect' => base_url('staffcn/login')]);
            exit();
        }
        header("location: " . base_url('staffcn/login'));
        exit();
    }
    $_SESSION['staffcn_login'] = $getUser['token'];
}

if (!isset($_SESSION['staffcn_login'])) {
    if ($_is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'msg' => 'Phiên đăng nhập hết hạn', 'redirect' => base_url('staffcn/login')]);
        exit();
    }
    redirect(base_url('staffcn/login'));
} else {
    $getUser = $ToryHub->get_row_safe("SELECT * FROM `users` WHERE `role` IN ('staffcn','finance_cn') AND `token` = ?", [$_SESSION['staffcn_login']]);

    if (!$getUser) {
        redirect(base_url('staffcn/login'));
    }

    if ($getUser['banned'] != 0) {
        redirect(base_url('common/banned'));
    }

    $ToryHub->update_safe("users", [
        'time_session' => time()
    ], "`id` = ?", [$getUser['id']]);
}
