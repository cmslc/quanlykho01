<?php
/**
 * API Translations - lấy tất cả bản dịch theo ngôn ngữ
 * GET /translations.php?lang=zh
 */

require_once(__DIR__.'/bootstrap.php');

api_method('GET');

$lang = $_GET['lang'] ?? 'vi';
$allowed = ['vi', 'zh', 'en'];
if (!in_array($lang, $allowed)) $lang = 'vi';

$langRow = $ToryHub->get_row_safe("SELECT * FROM `languages` WHERE `lang` = ? AND `status` = 1", [$lang]);
if (!$langRow) {
    api_success(['translations' => [], 'lang' => $lang]);
}

$rows = $ToryHub->get_list_safe(
    "SELECT `name`, `value` FROM `translate` WHERE `lang_id` = ?",
    [$langRow['id']]
);

$translations = [];
foreach ($rows as $row) {
    $translations[$row['name']] = $row['value'];
}

api_success(['translations' => $translations, 'lang' => $lang]);
