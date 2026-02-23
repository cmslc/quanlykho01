<?php
$CMSNT = new DB();

if (isset($_SESSION['customer_login'])) {
    $CMSNT->update_safe('users', ['token' => ''], "`token` = ?", [$_SESSION['customer_login']]);
}

session_destroy();
setcookie('customer_token', '', time() - 3600, '/');
setcookie('token', '', time() - 3600, '/');

redirect(base_url('customer/login'));
