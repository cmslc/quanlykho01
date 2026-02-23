<?php
define('IN_SITE', true);
require_once(__DIR__.'/../libs/db.php');
require_once(__DIR__.'/../config.php');
require_once(__DIR__.'/../libs/helper.php');

// Missing translations found from display_order_status() and other places
$missing = [
    'Shop Trung Quốc đã gửi' => ['zh' => '店铺已发货', 'en' => 'CN Shop Shipped'],
    'Đã về kho Trung Quốc' => ['zh' => '已到中国仓', 'en' => 'At CN Warehouse'],
    'Đã về kho Việt Nam' => ['zh' => '已到越南仓', 'en' => 'At VN Warehouse'],
    'Đã mua hàng' => ['zh' => '已采购', 'en' => 'Purchased'],
    'Đã giao hàng' => ['zh' => '已交货', 'en' => 'Delivered'],
    // From orders-list filter labels
    'Đang xử lý' => ['zh' => '处理中', 'en' => 'Processing'],
    // Staff CN home
    'Shop đã gửi' => ['zh' => '店铺已发货', 'en' => 'Shop Shipped'],
    // From telegram.php
    'Đang về TQ' => ['zh' => '运往中国仓', 'en' => 'Shipping to CN'],
    // Export labels
    'Chờ xử lý' => ['zh' => '待处理', 'en' => 'Pending'],  // may already exist
];

$inserted = 0;
foreach ($missing as $name => $langs) {
    foreach ($langs as $code => $value) {
        $langId = ($code === 'zh') ? 2 : 3;
        $existing = $CMSNT->get_row_safe("SELECT id FROM translate WHERE lang_id = ? AND name = ?", [$langId, $name]);
        if (!$existing) {
            $CMSNT->insert_safe('translate', ['lang_id' => $langId, 'name' => $name, 'value' => $value]);
            echo "INSERT: [{$code}] {$name} => {$value}" . PHP_EOL;
            $inserted++;
        } else {
            echo "EXISTS: [{$code}] {$name}" . PHP_EOL;
        }
    }
}
echo PHP_EOL . "Inserted: {$inserted}" . PHP_EOL;

// Verify
echo PHP_EOL . "=== Verify status labels ===" . PHP_EOL;
$labels = ['Chờ xử lý','Đã mua hàng','Shop Trung Quốc đã gửi','Đã về kho Trung Quốc','Đang vận chuyển','Đã về kho Việt Nam','Đã giao hàng','Đã hủy'];
foreach ($labels as $l) {
    $zh = $CMSNT->get_row_safe("SELECT value FROM translate WHERE lang_id = 2 AND name = ?", [$l]);
    $en = $CMSNT->get_row_safe("SELECT value FROM translate WHERE lang_id = 3 AND name = ?", [$l]);
    echo $l . " => zh: " . ($zh['value'] ?? 'MISSING!') . " | en: " . ($en['value'] ?? 'MISSING!') . PHP_EOL;
}
