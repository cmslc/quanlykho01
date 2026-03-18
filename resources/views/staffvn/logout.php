<?php
$ToryHub = new DB();

if (isset($_SESSION['staffvn_login'])) {
    $ToryHub->update_safe('users', ['token' => ''], "`token` = ?", [$_SESSION['staffvn_login']]);
}

session_destroy();
setcookie('staffvn_token', '', time() - 3600, '/');
setcookie('token', '', time() - 3600, '/');

redirect(base_url('staffvn/login'));
