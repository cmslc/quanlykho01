<?php
/**
 * Export orders list to CSV (Excel-compatible)
 * Supports same filters as orders-list page
 */
define('IN_SITE', true);
require_once(__DIR__.'/../../libs/db.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../libs/lang.php');
require_once(__DIR__.'/../../libs/helper.php');
require_once(__DIR__.'/../../libs/session.php');
require_once(__DIR__.'/../../libs/role.php');
require_once(__DIR__.'/../../models/is_admin.php');

// Filters (same as orders-list)
$filterStatus = input_get('status') ?: '';
$filterCustomer = input_get('customer_id') ?: '';
$filterProductType = input_get('product_type') ?: '';
$filterSearch = trim(input_get('search') ?? '');
$filterDateFrom = input_get('date_from') ?: '';
$filterDateTo = input_get('date_to') ?: '';

$where = "1=1";
$params = [];

if ($filterStatus) {
    $where .= " AND o.status = ?";
    $params[] = $filterStatus;
}
if ($filterCustomer) {
    $where .= " AND o.customer_id = ?";
    $params[] = intval($filterCustomer);
}
if ($filterProductType && in_array($filterProductType, ['retail', 'wholesale'])) {
    $where .= " AND o.product_type = ?";
    $params[] = $filterProductType;
}
if ($filterDateFrom) {
    $where .= " AND DATE(o.create_date) >= ?";
    $params[] = $filterDateFrom;
}
if ($filterDateTo) {
    $where .= " AND DATE(o.create_date) <= ?";
    $params[] = $filterDateTo;
}
if ($filterSearch) {
    $where .= " AND (o.order_code LIKE ? OR o.product_name LIKE ? OR o.product_code LIKE ? OR o.id IN (SELECT po.order_id FROM `package_orders` po JOIN `packages` p ON po.package_id = p.id WHERE p.tracking_cn LIKE ?))";
    $searchLike = '%' . $filterSearch . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

$orders = $ToryHub->get_list_safe("SELECT o.*, c.fullname as customer_name, c.customer_code
    FROM `orders` o LEFT JOIN `customers` c ON o.customer_id = c.id
    WHERE $where ORDER BY o.create_date DESC", $params);

// Get tracking codes for orders (from packages)
$orderIds = array_column($orders, 'id');
$trackingMap = [];
$weightMap = [];
if (!empty($orderIds)) {
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $packages = $ToryHub->get_list_safe(
        "SELECT po.order_id, p.tracking_cn, p.weight_charged FROM `package_orders` po
         JOIN `packages` p ON po.package_id = p.id
         WHERE po.order_id IN ($placeholders)",
        $orderIds
    );
    foreach ($packages as $pkg) {
        if ($pkg['tracking_cn']) {
            $trackingMap[$pkg['order_id']][] = $pkg['tracking_cn'];
        }
        $weightMap[$pkg['order_id']] = ($weightMap[$pkg['order_id']] ?? 0) + floatval($pkg['weight_charged']);
    }
}

$statusLabel = [
    'cn_warehouse' => 'Đã về kho Trung Quốc',
    'packed' => 'Đã đóng bao',
    'shipping' => 'Đang vận chuyển',
    'vn_warehouse' => 'Đã về kho Việt Nam',
    'delivered' => 'Đã giao hàng',
    'cancelled' => 'Đã hủy'
];

$filename = "DanhSachHang_" . date('Y-m-d_His') . ".xls";

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

function xls_esc($v) {
    return htmlspecialchars((string)$v, ENT_XML1, 'UTF-8');
}
function xls_cell($v, $bold = false) {
    $t = is_numeric($v) && $v !== '' ? 'Number' : 'String';
    $s = $bold ? ' ss:StyleID="H"' : '';
    return '<Cell' . $s . '><Data ss:Type="' . $t . '">' . xls_esc($v) . '</Data></Cell>';
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
echo '<Styles><Style ss:ID="H"><Font ss:Bold="1"/></Style></Styles>' . "\n";
echo '<Worksheet ss:Name="Sheet1"><Table>' . "\n";

// Header row
$headers = ['STT','Loại hàng','Mã hàng','Mã vận đơn TQ','Khách hàng','Mã khách hàng','Sản phẩm','Cân tính phí (kg)','Trạng thái','Ghi chú khách hàng','Ghi chú nội bộ','Ngày nhập','Cập nhật'];
echo '<Row>';
foreach ($headers as $h) echo xls_cell($h, true);
echo '</Row>' . "\n";

// Data rows
$stt = 0;
foreach ($orders as $row) {
    $stt++;
    $productType = ($row['product_type'] ?? 'retail') === 'retail' ? 'Hàng lẻ' : 'Hàng lô';
    $trackings = isset($trackingMap[$row['id']]) ? implode(', ', $trackingMap[$row['id']]) : '';
    $totalWeight = isset($weightMap[$row['id']]) ? floatval($weightMap[$row['id']]) : '';

    $cells = [
        $stt,
        $productType,
        $row['product_code'] ?? '',
        $trackings,
        $row['customer_name'] ?? '',
        $row['customer_code'] ?? '',
        $row['product_name'] ?? '',
        $totalWeight,
        $statusLabel[$row['status']] ?? $row['status'],
        $row['note'] ?? '',
        $row['note_internal'] ?? '',
        $row['create_date'],
        $row['update_date']
    ];
    echo '<Row>';
    foreach ($cells as $c) echo xls_cell($c);
    echo '</Row>' . "\n";
}

echo '</Table></Worksheet></Workbook>';

add_log('export', 'Xuất danh sách hàng (' . count($orders) . ' đơn)');
