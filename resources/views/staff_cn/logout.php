<?php
$ToryHub = new DB();

if (isset($_SESSION['staff_cn_login'])) {
    $ToryHub->update_safe('users', ['token' => ''], "`token` = ? AND `role` = 'staff_cn'", [$_SESSION['staff_cn_login']]);
}

session_destroy();
setcookie('token', '', time() - 3600, '/');
setcookie('staff_cn_token', '', time() - 3600, '/');

redirect(base_url('staff_cn/login'));
