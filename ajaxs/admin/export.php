<?php
/**
 * Export to CSV (Excel-compatible)
 * Uses plain CSV with BOM for Excel UTF-8 compatibility
 */
define('IN_SITE', true);
require_once(__DIR__.'/../../libs/db.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../libs/lang.php');
require_once(__DIR__.'/../../libs/helper.php');
require_once(__DIR__.'/../../libs/session.php');
require_once(__DIR__.'/../../libs/role.php');
require_once(__DIR__.'/../../models/is_admin.php');

$type = input_get('type');
$dateFrom = input_get('date_from') ?: date('Y-m-01');
$dateTo = input_get('date_to') ?: date('Y-m-d');

$filename = "ToryHub_{$type}_{$dateFrom}_{$dateTo}.csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM for UTF-8 Excel
$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// ======== REVENUE EXPORT ========
if ($type === 'revenue') {
    fputcsv($output, ['Kỳ', 'Số đơn', 'Tiền hàng CNY', 'Tiền hàng VND', 'Phí dịch vụ', 'Phí vận chuyển', 'Phí khác', 'Tổng phí', 'Tổng cộng']);

    $data = $ToryHub->get_list_safe("SELECT DATE_FORMAT(create_date, '%Y-%m-%d') as period,
        COUNT(*) as order_count,
        COALESCE(SUM(total_cny),0) as total_cny,
        COALESCE(SUM(total_vnd),0) as total_vnd,
        COALESCE(SUM(service_fee),0) as service_fee,
        COALESCE(SUM(shipping_fee_cn + shipping_fee_intl),0) as shipping_fee,
        COALESCE(SUM(packing_fee + insurance_fee + other_fee),0) as other_fees,
        COALESCE(SUM(total_fee),0) as total_fee,
        COALESCE(SUM(grand_total),0) as grand_total
        FROM `orders` WHERE `status` != 'cancelled'
        AND DATE(create_date) >= ? AND DATE(create_date) <= ?
        GROUP BY DATE(create_date) ORDER BY period ASC", [$dateFrom, $dateTo]);

    foreach ($data as $row) {
        fputcsv($output, [
            $row['period'], $row['order_count'], $row['total_cny'],
            $row['total_vnd'], $row['service_fee'], $row['shipping_fee'],
            $row['other_fees'], $row['total_fee'], $row['grand_total']
        ]);
    }

    add_log('export', 'Xuất báo cáo doanh thu: ' . $dateFrom . ' → ' . $dateTo);
}

// ======== ORDERS EXPORT ========
elseif ($type === 'orders') {
    fputcsv($output, ['Mã đơn', 'Khách hàng', 'Mã khách hàng', 'Nền tảng', 'Sản phẩm', 'Số lượng', 'Đơn giá CNY', 'Tổng CNY', 'Tỷ giá', 'Tiền hàng VND', 'Phí dịch vụ', 'Ship nội Trung Quốc', 'Ship quốc tế', 'Đóng gỗ', 'Bảo hiểm', 'Phí khác', 'Tổng cộng', 'Trạng thái', 'Mã vận đơn Trung Quốc', 'Mã quốc tế', 'Mã Việt Nam', 'Cân tính phí', 'Ngày tạo']);

    $data = $ToryHub->get_list_safe("SELECT o.*, c.fullname as customer_name, c.customer_code
        FROM `orders` o LEFT JOIN `customers` c ON o.customer_id = c.id
        WHERE DATE(o.create_date) >= ? AND DATE(o.create_date) <= ?
        ORDER BY o.create_date ASC", [$dateFrom, $dateTo]);

    $statusLabel = [
        'cn_warehouse' => 'Tại kho Trung Quốc', 'packed' => 'Đã đóng bao',
        'shipping' => 'Đang vận chuyển', 'vn_warehouse' => 'Tại kho Việt Nam',
        'delivered' => 'Đã giao', 'cancelled' => 'Đã hủy'
    ];

    foreach ($data as $row) {
        fputcsv($output, [
            $row['order_code'], $row['customer_name'] ?? '', $row['customer_code'] ?? '',
            $row['platform'], $row['product_name'], $row['quantity'],
            $row['unit_price_cny'], $row['total_cny'], $row['exchange_rate'],
            $row['total_vnd'], $row['service_fee'], $row['shipping_fee_cn'],
            $row['shipping_fee_intl'], $row['packing_fee'], $row['insurance_fee'],
            $row['other_fee'], $row['grand_total'],
            $statusLabel[$row['status']] ?? $row['status'],
            $row['cn_tracking'], $row['intl_tracking'], $row['vn_tracking'],
            $row['weight_charged'], $row['create_date']
        ]);
    }

    add_log('export', 'Xuất danh sách đơn hàng: ' . $dateFrom . ' → ' . $dateTo);
}

// ======== CUSTOMERS EXPORT ========
elseif ($type === 'customers') {
    fputcsv($output, ['Mã khách hàng', 'Họ tên', 'Điện thoại', 'Email', 'Loại khách hàng', 'Tổng đơn', 'Tổng chi tiêu', 'Số dư', 'Zalo', 'WeChat', 'Địa chỉ Việt Nam', 'Ngày tạo']);

    $data = $ToryHub->get_list_safe("SELECT * FROM `customers` ORDER BY `id` ASC", []);

    $typeLabel = ['normal' => 'Thường', 'vip' => 'VIP', 'agent' => 'Đại lý'];

    foreach ($data as $row) {
        fputcsv($output, [
            $row['customer_code'], $row['fullname'], $row['phone'] ?? '',
            $row['email'] ?? '', $typeLabel[$row['customer_type']] ?? $row['customer_type'],
            $row['total_orders'], $row['total_spent'], $row['balance'],
            $row['zalo'] ?? '', $row['wechat'] ?? '', $row['address_vn'] ?? '',
            $row['create_date']
        ]);
    }

    add_log('export', 'Xuất danh sách khách hàng');
}

// ======== TRANSACTIONS EXPORT ========
elseif ($type === 'transactions') {
    fputcsv($output, ['ID', 'Khách hàng', 'Mã khách hàng', 'Loại', 'Số tiền', 'Số dư trước', 'Số dư sau', 'Mô tả', 'Ngày']);

    $data = $ToryHub->get_list_safe("SELECT t.*, c.fullname as customer_name, c.customer_code
        FROM `transactions` t LEFT JOIN `customers` c ON t.customer_id = c.id
        WHERE DATE(t.create_date) >= ? AND DATE(t.create_date) <= ?
        ORDER BY t.create_date ASC", [$dateFrom, $dateTo]);

    $typeLabel = ['deposit' => 'Nạp tiền', 'payment' => 'Thanh toán', 'refund' => 'Hoàn tiền', 'adjustment' => 'Điều chỉnh'];

    foreach ($data as $row) {
        fputcsv($output, [
            $row['id'], $row['customer_name'] ?? '', $row['customer_code'] ?? '',
            $typeLabel[$row['type']] ?? $row['type'], $row['amount'],
            $row['balance_before'], $row['balance_after'],
            $row['description'] ?? '', $row['create_date']
        ]);
    }

    add_log('export', 'Xuất giao dịch: ' . $dateFrom . ' → ' . $dateTo);
}

fclose($output);
