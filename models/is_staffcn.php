<?php

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

$ToryHub = new DB();

if (isset($_COOKIE["token"])) {
    $getUser = $ToryHub->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `role` = 'staffcn'", [$_COOKIE['token']]);
    if (!$getUser) {
        header("location: " . base_url('staffcn/login'));
        exit();
    }
    $_SESSION['staffcn_login'] = $getUser['token'];
}

if (!isset($_SESSION['staffcn_login'])) {
    redirect(base_url('staffcn/login'));
} else {
    $getUser = $ToryHub->get_row_safe("SELECT * FROM `users` WHERE `role` = 'staffcn' AND `token` = ?", [$_SESSION['staffcn_login']]);

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
