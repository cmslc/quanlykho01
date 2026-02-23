<?php

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

$ToryHub = new DB();

if (isset($_COOKIE["token"])) {
    $getUser = $ToryHub->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `role` = 'staff_cn'", [$_COOKIE['token']]);
    if (!$getUser) {
        header("location: " . base_url('staff_cn/login'));
        exit();
    }
    $_SESSION['staff_cn_login'] = $getUser['token'];
}

if (!isset($_SESSION['staff_cn_login'])) {
    redirect(base_url('staff_cn/login'));
} else {
    $getUser = $ToryHub->get_row_safe("SELECT * FROM `users` WHERE `role` = 'staff_cn' AND `token` = ?", [$_SESSION['staff_cn_login']]);

    if (!$getUser) {
        redirect(base_url('staff_cn/login'));
    }

    if ($getUser['banned'] != 0) {
        redirect(base_url('common/banned'));
    }

    $ToryHub->update_safe("users", [
        'time_session' => time()
    ], "`id` = ?", [$getUser['id']]);
}
