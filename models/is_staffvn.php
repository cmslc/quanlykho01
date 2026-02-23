<?php

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

$ToryHub = new DB();

if (isset($_COOKIE["token"])) {
    $getUser = $ToryHub->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `role` = 'staffvn'", [$_COOKIE['token']]);
    if (!$getUser) {
        header("location: " . base_url('staffvn/login'));
        exit();
    }
    $_SESSION['staffvn_login'] = $getUser['token'];
}

if (!isset($_SESSION['staffvn_login'])) {
    redirect(base_url('staffvn/login'));
} else {
    $getUser = $ToryHub->get_row_safe("SELECT * FROM `users` WHERE `role` = 'staffvn' AND `token` = ?", [$_SESSION['staffvn_login']]);

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
