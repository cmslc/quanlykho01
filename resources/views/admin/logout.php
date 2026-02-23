<?php
$CMSNT = new DB();

if (isset($_SESSION['admin_login'])) {
    $CMSNT->update_safe('users', ['token' => ''], "`token` = ?", [$_SESSION['admin_login']]);
}

session_destroy();
setcookie('token', '', time() - 3600, '/');

redirect(base_url('admin/login'));
