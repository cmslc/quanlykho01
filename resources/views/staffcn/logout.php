<?php
$ToryHub = new DB();

if (isset($_SESSION['staffcn_login'])) {
    $ToryHub->update_safe('users', ['token' => ''], "`token` = ? AND `role` = 'staffcn'", [$_SESSION['staffcn_login']]);
}

session_destroy();
setcookie('token', '', time() - 3600, '/');
setcookie('staffcn_token', '', time() - 3600, '/');

redirect(base_url('staffcn/login'));
