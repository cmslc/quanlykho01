<?php
define("IN_SITE", true);

require_once(__DIR__.'/libs/db.php');
require_once(__DIR__.'/config.php');
require_once(__DIR__.'/libs/lang.php');
require_once(__DIR__.'/libs/helper.php');
require_once(__DIR__.'/libs/session.php');
require_once(__DIR__.'/libs/role.php');
require_once(__DIR__.'/libs/csrf.php');

$ToryHub = new DB();

$module = !empty($_GET['module']) ? check_path($_GET['module']) : 'admin';
$home = 'home';
$action = !empty($_GET['action']) ? check_path($_GET['action']) : $home;

// Block access to layout files
$blocked_actions = ['footer', 'header', 'sidebar', 'nav'];
if (in_array($action, $blocked_actions)) {
    require_once(__DIR__.'/resources/views/common/404.php');
    exit();
}

// Allowed modules
$allowed_modules = ['admin', 'staffcn', 'staffvn', 'common'];
if (!in_array($module, $allowed_modules)) {
    require_once(__DIR__.'/resources/views/common/404.php');
    exit();
}

// Load view
$path = "resources/views/$module/$action.php";
if (file_exists(__DIR__.'/'.$path)) {
    require_once(__DIR__.'/'.$path);
    exit();
} else {
    require_once(__DIR__.'/resources/views/common/404.php');
    exit();
}
