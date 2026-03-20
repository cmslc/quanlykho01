<?php
define('IN_SITE', true);
require_once(__DIR__.'/../libs/db.php');
require_once(__DIR__.'/../config.php');

$ToryHub = new DB();
$zhId = 2;

$translations = [
    'Trang chủ' => '首页',
    'Đang xếp hàng' => '正在装车',
    'Tài khoản' => '账户',
    'Nhân viên' => '员工',
    'Admin' => '管理员',
    'NV Kho TQ' => '中国仓员工',
    'Tài chính TQ' => '中国财务',
    'NV Kho VN' => '越南仓员工',
    'Tài chính VN' => '越南财务',
    'Login Admin' => '管理员登录',
    'Dashboard' => '控制面板',
    'Đơn hàng' => '订单',
    'Lương nhân viên' => '员工工资',
    'Nhập kho VN' => '越南仓入库',
];

$inserted = 0;
foreach ($translations as $name => $value) {
    $exists = $ToryHub->get_row_safe("SELECT `id` FROM `translate` WHERE `lang_id` = ? AND `name` = ?", [$zhId, $name]);
    if ($exists) continue;
    $ToryHub->insert_safe('translate', ['lang_id' => $zhId, 'name' => $name, 'value' => $value]);
    $inserted++;
}
echo "Done! Inserted: $inserted\n";
