<?php
/**
 * StaffVN Export to CSV (Excel-compatible)
 */
define('IN_SITE', true);
require_once(__DIR__.'/../../libs/db.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../libs/lang.php');
require_once(__DIR__.'/../../libs/helper.php');
require_once(__DIR__.'/../../libs/session.php');
require_once(__DIR__.'/../../libs/role.php');
require_once(__DIR__.'/../../models/is_staffvn.php');

$type = input_get('type');
$dateFrom = input_get('date_from') ?: date('Y-m-d');
$dateTo = input_get('date_to') ?: date('Y-m-d');

$filename = "KhoVN_{$type}_{$dateFrom}_{$dateTo}.csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM for UTF-8 Excel
$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

if ($type === 'daily') {
    // Sheet 1: Packages received
    fputcsv($output, ['=== ' . __('KIỆN HÀNG ĐÃ NHẬN') . ' (' . $dateFrom . ' - ' . $dateTo . ') ===']);
    fputcsv($output, [__('Mã kiện'), __('Tracking QT'), __('Tracking VN'), __('Cân nặng (kg)'), __('Kích thước'), __('Đơn hàng'), __('Khách hàng'), __('Giờ nhận')]);

    $packages = $ToryHub->get_list_safe("
        SELECT p.*, GROUP_CONCAT(DISTINCT o.order_code SEPARATOR ', ') as order_codes,
               GROUP_CONCAT(DISTINCT c.fullname SEPARATOR ', ') as customer_names
        FROM `packages` p
        LEFT JOIN `package_orders` po ON p.id = po.package_id
        LEFT JOIN `orders` o ON po.order_id = o.id
        LEFT JOIN `customers` c ON o.customer_id = c.id
        WHERE DATE(p.vn_warehouse_date) >= ? AND DATE(p.vn_warehouse_date) <= ?
        GROUP BY p.id ORDER BY p.vn_warehouse_date ASC
    ", [$dateFrom, $dateTo]);

    foreach ($packages as $pkg) {
        $dim = '';
        if ($pkg['length_cm'] > 0 || $pkg['width_cm'] > 0 || $pkg['height_cm'] > 0) {
            $dim = floatval($pkg['length_cm']) . 'x' . floatval($pkg['width_cm']) . 'x' . floatval($pkg['height_cm']);
        }
        fputcsv($output, [
            $pkg['package_code'],
            $pkg['tracking_intl'] ?: '',
            $pkg['tracking_vn'] ?: '',
            floatval($pkg['weight_charged'] ?: $pkg['weight_actual']),
            $dim,
            $pkg['order_codes'] ?: '',
            $pkg['customer_names'] ?: '',
            $pkg['vn_warehouse_date'] ? date('H:i', strtotime($pkg['vn_warehouse_date'])) : ''
        ]);
    }

    fputcsv($output, []);
    fputcsv($output, [__('Tổng kiện') . ': ' . count($packages)]);
    fputcsv($output, []);

    // Sheet 2: Orders delivered
    fputcsv($output, ['=== ' . __('ĐƠN HÀNG ĐÃ GIAO') . ' (' . $dateFrom . ' - ' . $dateTo . ') ===']);
    fputcsv($output, [__('Mã đơn'), __('Mã KH'), __('Khách hàng'), __('Sản phẩm'), __('Tổng VND'), __('COD thu'), __('PT thanh toán'), __('Giờ giao')]);

    $orders = $ToryHub->get_list_safe("
        SELECT o.*, c.fullname as customer_name, c.customer_code,
               cod.amount as cod_amount, cod.payment_method as cod_method
        FROM `orders` o
        LEFT JOIN `customers` c ON o.customer_id = c.id
        LEFT JOIN `cod_collections` cod ON cod.order_id = o.id
        WHERE DATE(o.delivered_date) >= ? AND DATE(o.delivered_date) <= ?
        ORDER BY o.delivered_date ASC
    ", [$dateFrom, $dateTo]);

    $methodLabels = ['cash' => __('Tiền mặt'), 'transfer' => __('Chuyển khoản'), 'balance' => __('Trừ số dư')];
    $totalValue = 0;
    $totalCod = 0;

    foreach ($orders as $order) {
        $totalValue += floatval($order['grand_total']);
        $totalCod += floatval($order['cod_amount']);
        fputcsv($output, [
            $order['order_code'],
            $order['customer_code'] ?: '',
            $order['customer_name'] ?: '',
            $order['product_name'] ?: '',
            $order['grand_total'],
            $order['cod_amount'] ?: '',
            $methodLabels[$order['cod_method']] ?? '',
            $order['delivered_date'] ? date('H:i', strtotime($order['delivered_date'])) : ''
        ]);
    }

    fputcsv($output, []);
    fputcsv($output, [__('Tổng đơn giao') . ': ' . count($orders), '', '', '', $totalValue, $totalCod]);
}

fclose($output);
