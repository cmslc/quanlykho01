<?php

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

$ToryHub = new DB();

if (isset($_COOKIE["token"])) {
    $getUser = $ToryHub->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `role` = 'customer'", [$_COOKIE['token']]);
    if (!$getUser) {
        header("location: " . base_url('customer/login'));
        exit();
    }
    $_SESSION['customer_login'] = $getUser['token'];
}

if (!isset($_SESSION['customer_login'])) {
    redirect(base_url('customer/login'));
} else {
    $getUser = $ToryHub->get_row_safe("SELECT * FROM `users` WHERE `role` = 'customer' AND `token` = ?", [$_SESSION['customer_login']]);

    if (!$getUser) {
        redirect(base_url('customer/login'));
    }

    if ($getUser['banned'] != 0) {
        redirect(base_url('common/banned'));
    }

    if ($getUser['active'] != 1) {
        redirect(base_url('common/not-active'));
    }

    $ToryHub->update_safe('users', [
        'update_date'  => gettime(),
        'time_session' => time()
    ], "`id` = ?", [$getUser['id']]);
}
