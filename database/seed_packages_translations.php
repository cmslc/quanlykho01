<?php
define('IN_SITE', true);
require_once(__DIR__.'/../libs/db.php');
require_once(__DIR__.'/../config.php');
require_once(__DIR__.'/../libs/helper.php');

// Package-related translations
$translations = [
    // Package status labels (from display_package_status)
    'Chờ nhập kho' => ['zh' => '待入库', 'en' => 'Pending'],
    'Đang vận chuyển' => ['zh' => '运输中', 'en' => 'Shipping'],

    // Package management UI
    'Kiện hàng' => ['zh' => '包裹', 'en' => 'Packages'],
    'Danh sách kiện' => ['zh' => '包裹列表', 'en' => 'Package List'],
    'Chi tiết kiện hàng' => ['zh' => '包裹详情', 'en' => 'Package Detail'],
    'Mã kiện' => ['zh' => '包裹编号', 'en' => 'Package Code'],
    'Tạo kiện hàng' => ['zh' => '创建包裹', 'en' => 'Create Package'],
    'Sửa kiện hàng' => ['zh' => '编辑包裹', 'en' => 'Edit Package'],
    'Xóa kiện hàng' => ['zh' => '删除包裹', 'en' => 'Delete Package'],
    'Thêm kiện mới' => ['zh' => '添加新包裹', 'en' => 'Add New Package'],

    // Package tracking
    'Tracking TQ' => ['zh' => '中国快递单号', 'en' => 'CN Tracking'],
    'Tracking QT' => ['zh' => '国际运单号', 'en' => 'Intl Tracking'],
    'Tracking VN' => ['zh' => '越南快递单号', 'en' => 'VN Tracking'],

    // Package weight/dimensions
    'Cân thực' => ['zh' => '实重', 'en' => 'Actual Weight'],
    'Cân quy đổi' => ['zh' => '体积重', 'en' => 'Volume Weight'],
    'Cân tính phí' => ['zh' => '计费重', 'en' => 'Charged Weight'],
    'Tổng cân' => ['zh' => '总重量', 'en' => 'Total Weight'],
    'Kích thước' => ['zh' => '尺寸', 'en' => 'Dimensions'],
    'Dài' => ['zh' => '长', 'en' => 'Length'],
    'Rộng' => ['zh' => '宽', 'en' => 'Width'],
    'Cao' => ['zh' => '高', 'en' => 'Height'],

    // Package status updates
    'Cập nhật trạng thái kiện hàng' => ['zh' => '更新包裹状态', 'en' => 'Update Package Status'],
    'Kiện hàng đã nhập kho Trung Quốc' => ['zh' => '包裹已入中国仓', 'en' => 'Package arrived at CN warehouse'],
    'Kiện hàng đã xuất kho Trung Quốc' => ['zh' => '包裹已从中国仓发出', 'en' => 'Package shipped from CN'],
    'Kiện hàng đã nhập kho Việt Nam' => ['zh' => '包裹已入越南仓', 'en' => 'Package arrived at VN warehouse'],
    'Kiện hàng đã giao thành công' => ['zh' => '包裹已送达', 'en' => 'Package delivered'],
    'Kiện hàng đã ở trạng thái' => ['zh' => '包裹已处于状态', 'en' => 'Package is already at status'],
    'Lỗi cập nhật kiện hàng. Vui lòng thử lại.' => ['zh' => '更新包裹失败，请重试', 'en' => 'Package update failed. Please try again.'],

    // Package-order linking
    'Đơn hàng liên kết' => ['zh' => '关联订单', 'en' => 'Linked Orders'],
    'Liên kết đơn hàng' => ['zh' => '关联订单', 'en' => 'Link Order'],
    'Gỡ liên kết' => ['zh' => '取消关联', 'en' => 'Unlink'],
    'Tìm đơn hàng' => ['zh' => '搜索订单', 'en' => 'Search Orders'],
    'đơn' => ['zh' => '单', 'en' => 'orders'],

    // Merge/Split
    'Gộp kiện' => ['zh' => '合并包裹', 'en' => 'Merge Packages'],
    'Tách kiện' => ['zh' => '拆分包裹', 'en' => 'Split Package'],
    'Chọn kiện để gộp' => ['zh' => '选择要合并的包裹', 'en' => 'Select packages to merge'],
    'Tách thành kiện riêng' => ['zh' => '拆分为独立包裹', 'en' => 'Split into separate packages'],

    // Shipping method
    'Đường bộ' => ['zh' => '陆运', 'en' => 'Road'],
    'Phương thức vận chuyển' => ['zh' => '运输方式', 'en' => 'Shipping Method'],

    // Scan hints
    'Tìm theo: Mã kiện hàng (PKG), Tracking TQ, Mã đơn hàng. Auto-submit sau 500ms.' =>
        ['zh' => '搜索：包裹编号(PKG)、中国快递单号、订单编号。500ms后自动提交。', 'en' => 'Search by: Package code (PKG), CN tracking, Order code. Auto-submit after 500ms.'],
    'Tìm theo: Mã kiện hàng (PKG), Tracking QT, Tracking VN, Mã đơn hàng. Auto-submit sau 500ms.' =>
        ['zh' => '搜索：包裹编号(PKG)、国际运单号、越南快递单号、订单编号。500ms后自动提交。', 'en' => 'Search by: Package code (PKG), Intl tracking, VN tracking, Order code. Auto-submit after 500ms.'],

    // Error/status messages
    'Không tìm thấy kiện hàng hoặc đơn hàng với mã' => ['zh' => '未找到包裹或订单，编号', 'en' => 'No package or order found with code'],
    'Tự động cập nhật từ kiện hàng' => ['zh' => '从包裹自动更新', 'en' => 'Auto-updated from package'],

    // Package list/detail UI
    'Lịch sử trạng thái' => ['zh' => '状态历史', 'en' => 'Status History'],
    'Thông tin kiện hàng' => ['zh' => '包裹信息', 'en' => 'Package Info'],
    'Timeline kiện hàng' => ['zh' => '包裹时间线', 'en' => 'Package Timeline'],
    'Tổng kiện' => ['zh' => '总包裹数', 'en' => 'Total Packages'],
    'Tại kho Trung Quốc' => ['zh' => '在中国仓', 'en' => 'At CN Warehouse'],
    'Đang ship' => ['zh' => '运输中', 'en' => 'Shipping'],
    'Tại kho Việt Nam' => ['zh' => '在越南仓', 'en' => 'At VN Warehouse'],
    'Đã giao' => ['zh' => '已送达', 'en' => 'Delivered'],
    'Xem chi tiết' => ['zh' => '查看详情', 'en' => 'View Detail'],
];

$inserted = 0;
foreach ($translations as $name => $langs) {
    foreach ($langs as $code => $value) {
        $langId = ($code === 'zh') ? 2 : 3;
        $existing = $ToryHub->get_row_safe("SELECT id FROM translate WHERE lang_id = ? AND name = ?", [$langId, $name]);
        if (!$existing) {
            $ToryHub->insert_safe('translate', ['lang_id' => $langId, 'name' => $name, 'value' => $value]);
            echo "INSERT: [{$code}] {$name} => {$value}" . PHP_EOL;
            $inserted++;
        } else {
            echo "EXISTS: [{$code}] {$name}" . PHP_EOL;
        }
    }
}
echo PHP_EOL . "Inserted: {$inserted} package translations" . PHP_EOL;
