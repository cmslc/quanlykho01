<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

define("IN_SITE", true);
require_once(__DIR__.'/libs/db.php');
require_once(__DIR__.'/config.php');
require_once(__DIR__.'/libs/lang.php');
require_once(__DIR__.'/libs/helper.php');
require_once(__DIR__.'/libs/session.php');
require_once(__DIR__.'/libs/role.php');
require_once(__DIR__.'/libs/csrf.php');

$ToryHub = new DB();

echo "Cookie token: " . ($_COOKIE['token'] ?? 'NOT SET') . "<br>";

$getUser = $ToryHub->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `role` IN ('staffcn','finance_cn')", [$_COOKIE['token'] ?? '']);
echo "DB user found: " . ($getUser ? $getUser['username'] : 'NOT FOUND') . "<br>";
echo "Session staffcn_login: " . ($_SESSION['staffcn_login'] ?? 'NOT SET') . "<br>";

echo "<br>--- Now testing is_staffcn.php ---<br>";
require_once(__DIR__.'/models/is_staffcn.php');
echo "Auth OK! User: " . $getUser['username'] . "<br>";
