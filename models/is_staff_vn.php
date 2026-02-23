<?php

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

$CMSNT = new DB();

if (isset($_COOKIE["token"])) {
    $getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `role` = 'staff_vn'", [$_COOKIE['token']]);
    if (!$getUser) {
        header("location: " . base_url('staff_vn/login'));
        exit();
    }
    $_SESSION['staff_vn_login'] = $getUser['token'];
}

if (!isset($_SESSION['staff_vn_login'])) {
    redirect(base_url('staff_vn/login'));
} else {
    $getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `role` = 'staff_vn' AND `token` = ?", [$_SESSION['staff_vn_login']]);

    if (!$getUser) {
        redirect(base_url('staff_vn/login'));
    }

    if ($getUser['banned'] != 0) {
        redirect(base_url('common/banned'));
    }

    $CMSNT->update_safe("users", [
        'time_session' => time()
    ], "`id` = ?", [$getUser['id']]);
}
