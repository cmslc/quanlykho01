<?php

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

$_is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
    || !empty($_POST['request_name']);

$ToryHub = new DB();

if (isset($_COOKIE["token"])) {
    $getUser = $ToryHub->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `role` IN ('staffvn', 'finance_vn')", [$_COOKIE['token']]);
    if (!$getUser) {
        if ($_is_ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'msg' => 'Phiên đăng nhập hết hạn', 'redirect' => base_url('staffvn/login')]);
            exit();
        }
        header("location: " . base_url('staffvn/login'));
        exit();
    }
    $_SESSION['staffvn_login'] = $getUser['token'];
}

if (!isset($_SESSION['staffvn_login'])) {
    if ($_is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'msg' => 'Phiên đăng nhập hết hạn', 'redirect' => base_url('staffvn/login')]);
        exit();
    }
    redirect(base_url('staffvn/login'));
} else {
    $getUser = $ToryHub->get_row_safe("SELECT * FROM `users` WHERE `role` IN ('staffvn', 'finance_vn') AND `token` = ?", [$_SESSION['staffvn_login']]);

    if (!$getUser) {
        redirect(base_url('staffvn/login'));
    }

    if ($getUser['banned'] != 0) {
        redirect(base_url('common/banned'));
    }

    $ToryHub->update_safe("users", [
        'time_session' => time()
    ], "`id` = ?", [$getUser['id']]);
}
