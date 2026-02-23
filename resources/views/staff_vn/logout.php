<?php
$CMSNT = new DB();

if (isset($_SESSION['staff_vn_login'])) {
    $CMSNT->update_safe('users', ['token' => ''], "`token` = ?", [$_SESSION['staff_vn_login']]);
}

session_destroy();
setcookie('staff_vn_token', '', time() - 3600, '/');
setcookie('token', '', time() - 3600, '/');

redirect(base_url('staff_vn/login'));
