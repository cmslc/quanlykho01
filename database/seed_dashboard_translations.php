<?php
define('IN_SITE', true);
require_once(__DIR__.'/../libs/db.php');
require_once(__DIR__.'/../config.php');
require_once(__DIR__.'/../libs/helper.php');

// Dashboard-related translations
$translations = [
    // KPI cards
    'Doanh thu tháng' => ['zh' => '本月收入', 'en' => 'Monthly Revenue'],
    'Đơn tháng này' => ['zh' => '本月订单', 'en' => 'Orders This Month'],
    'Kiện đang chuyển' => ['zh' => '在途包裹', 'en' => 'In Transit'],

    // Chart titles
    'Doanh thu' => ['zh' => '收入', 'en' => 'Revenue'],
    'Số đơn' => ['zh' => '订单数', 'en' => 'Orders Count'],
    'Trạng thái đơn hàng' => ['zh' => '订单状态', 'en' => 'Order Status'],
    'Tình trạng kiện hàng' => ['zh' => '包裹状态', 'en' => 'Package Status'],
    'Top khách hàng' => ['zh' => '顶级客户', 'en' => 'Top Customers'],
    'Tổng chi tiêu' => ['zh' => '总消费', 'en' => 'Total Spent'],
    'Tổng đơn' => ['zh' => '总订单', 'en' => 'Total Orders'],

    // Package pipeline labels
    'Kho Trung Quốc' => ['zh' => '中国仓', 'en' => 'CN Warehouse'],
    'Vận chuyển' => ['zh' => '运输中', 'en' => 'Shipping'],
    'Kho Việt Nam' => ['zh' => '越南仓', 'en' => 'VN Warehouse'],
    'kiện' => ['zh' => '件', 'en' => 'packages'],

    // Quick links
    'Truy cập nhanh' => ['zh' => '快捷链接', 'en' => 'Quick Links'],
    'Tạo đơn mới' => ['zh' => '创建新订单', 'en' => 'Create New Order'],
    'Quản lý khách hàng' => ['zh' => '客户管理', 'en' => 'Customer Management'],
];

$inserted = 0;
foreach ($translations as $name => $langs) {
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
echo PHP_EOL . "Inserted: {$inserted} dashboard translations" . PHP_EOL;
