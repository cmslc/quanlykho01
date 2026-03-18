<?php
define('IN_SITE', true);
require_once(__DIR__.'/../../libs/db.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../libs/lang.php');
require_once(__DIR__.'/../../libs/helper.php');
require_once(__DIR__.'/../../libs/session.php');

$lang = $_GET['lang'] ?? 'vi';
$allowed = ['vi', 'zh', 'en'];

if (in_array($lang, $allowed)) {
    setcookie('language', $lang, time() + (31536000 * 30), '/');
}

// Redirect back to previous page
$referer = $_SERVER['HTTP_REFERER'] ?? base_url('admin/home');
header('Location: ' . $referer);
exit;
