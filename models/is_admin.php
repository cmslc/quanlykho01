<?php

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

$ToryHub = new DB();

// Check cookie token
if (isset($_COOKIE["token"])) {
    $getUser = $ToryHub->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `role` = 'admin'", [$_COOKIE['token']]);
    if (!$getUser) {
        header("location: " . base_url('admin/login'));
        exit();
    }
    $_SESSION['admin_login'] = $getUser['token'];
}

// Verify session
if (!isset($_SESSION['admin_login'])) {
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
