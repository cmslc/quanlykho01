<?php
/**
 * Export customer detail to XLSX (PhpSpreadsheet) - Single sheet
 */
define('IN_SITE', true);
require_once(__DIR__.'/../../libs/db.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../libs/lang.php');
require_once(__DIR__.'/../../libs/helper.php');
require_once(__DIR__.'/../../libs/session.php');
require_once(__DIR__.'/../../libs/role.php');
require_once(__DIR__.'/../../models/is_staffvn.php');
require_once(__DIR__.'/../../vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Border;

$id = intval(input_get('id'));
$customer = $ToryHub->get_row_safe("SELECT * FROM `customers` WHERE `id` = ?", [$id]);
if (!$customer) { exit; }

// Shipping rates + exchange rate
$shippingRates = [
    'easy'      => ['per_kg' => floatval($ToryHub->site('shipping_road_easy_per_kg') ?: 25000), 'per_cbm' => floatval($ToryHub->site('shipping_road_easy_per_cbm') ?: 6000000)],
    'difficult' => ['per_kg' => floatval($ToryHub->site('shipping_road_difficult_per_kg') ?: 35000), 'per_cbm' => floatval($ToryHub->site('shipping_road_difficult_per_cbm') ?: 8000000)],
];
$exchangeRate = get_exchange_rate();

// Orders
$orders = $ToryHub->get_list_safe(
    "SELECT o.*,
        o.weight_charged as order_weight_charged,
        o.weight_actual as order_weight_actual,
        o.custom_rate_kg, o.custom_rate_cbm,
        SUM(p.weight_charged) as pkg_weight_charged,
        SUM(p.weight_actual) as pkg_weight_actual,
        SUM(p.length_cm * p.width_cm * p.height_cm / 1000000) as total_cbm
     FROM `orders` o
     LEFT JOIN `package_orders` po ON o.id = po.order_id
     LEFT JOIN `packages` p ON po.package_id = p.id
     WHERE o.customer_id = ?
     GROUP BY o.id
     ORDER BY o.create_date DESC", [$id]
);

$totalShipCost = 0;
foreach ($orders as &$od) {
    $wC = floatval($od['order_weight_charged'] ?? 0);
    $wA = floatval($od['order_weight_actual'] ?? 0);
    $pkgWC = floatval($od['pkg_weight_charged'] ?? 0);
    $pkgWA = floatval($od['pkg_weight_actual'] ?? 0);
    $w = $pkgWA > 0 ? $pkgWA : ($wA > 0 ? $wA : ($pkgWC > 0 ? $pkgWC : $wC));
    $cbm = floatval($od['total_cbm'] ?? 0);
    $cargo = $od['cargo_type'] ?? 'easy';
    $rate = $shippingRates[$cargo] ?? $shippingRates['easy'];
    $rkg = $od['custom_rate_kg'] !== null ? floatval($od['custom_rate_kg']) : $rate['per_kg'];
    $rcbm = $od['custom_rate_cbm'] !== null ? floatval($od['custom_rate_cbm']) : $rate['per_cbm'];
    $orderRate = floatval($od['exchange_rate'] ?? 0) ?: $exchangeRate;
    $domesticVnd = floatval($od['domestic_cost'] ?? 0) * $orderRate;
    $od['ship_cost'] = ($od['status'] !== 'cancelled') ? max($w * $rkg, $cbm * $rcbm) + $domesticVnd : 0;
    $totalShipCost += $od['ship_cost'];
}
unset($od);

$totalPaid = floatval($customer['total_spent'] ?? 0);
$totalDebt = max(0, $totalShipCost - $totalPaid);

// Labels

$statusLabel = [
    'cn_warehouse' => __('Tại kho TQ'), 'packed' => __('Đã đóng bao'),
    'shipping' => __('Vận chuyển'), 'vn_warehouse' => __('Kho VN'),
    'delivered' => __('Đã giao'), 'cancelled' => __('Đã hủy')
];
$typeLabel = ['wholesale' => __('Hàng lô'), 'retail' => __('Hàng lẻ')];
// Helper: style header row
function style_header_row($sheet, $range) {
    $sheet->getStyle($range)->getFont()->setBold(true)->setSize(11);
    $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
    $sheet->getStyle($range)->getFont()->getColor()->setRGB('FFFFFF');
    $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

// Build XLSX - Single sheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle(__('Chi tiết khách hàng'));

$row = 1;

// ========== Section 1: Thông tin khách hàng ==========
$sheet->setCellValue("A{$row}", __('THÔNG TIN KHÁCH HÀNG'));
$sheet->mergeCells("A{$row}:H{$row}");
$sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
$row += 2;

$infoData = [
    [__('Mã KH'), $customer['customer_code'] ?? '-', '', '', __('Điện thoại'), $customer['phone'] ?? '-'],
    [__('Họ tên'), $customer['fullname'], '', '', 'Email', $customer['email'] ?? '-'],
    [__('Địa chỉ VN'), $customer['address_vn'] ?? '-', '', '', 'Zalo', $customer['zalo'] ?? '-'],
    ['WeChat', $customer['wechat'] ?? '-', '', '', __('Ghi chú'), $customer['note'] ?? '-'],
];
foreach ($infoData as $info) {
    $sheet->setCellValue("A{$row}", $info[0]);
    $sheet->setCellValue("B{$row}", $info[1]);
    $sheet->setCellValue("E{$row}", $info[4]);
    $sheet->setCellValue("F{$row}", $info[5]);
    $sheet->getStyle("A{$row}")->getFont()->setBold(true);
    $sheet->getStyle("E{$row}")->getFont()->setBold(true);
    $row++;
}

$row++;
$sheet->setCellValue("A{$row}", __('Tổng cước vận chuyển'));
$sheet->setCellValue("B{$row}", round($totalShipCost));
$sheet->setCellValue("D{$row}", __('Đã thanh toán'));
$sheet->setCellValue("E{$row}", round($totalPaid));
$sheet->setCellValue("G{$row}", __('Đang nợ'));
$sheet->setCellValue("H{$row}", round($totalDebt));
$sheet->getStyle("A{$row}")->getFont()->setBold(true);
$sheet->getStyle("D{$row}")->getFont()->setBold(true);
$sheet->getStyle("G{$row}")->getFont()->setBold(true);
$sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('#,##0');
$sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode('#,##0');
$sheet->getStyle("H{$row}")->getNumberFormat()->setFormatCode('#,##0');
$sheet->getStyle("H{$row}")->getFont()->setBold(true)->getColor()->setRGB('DC3545');

// ========== Section 2: Đơn hàng ==========
$row += 2;
$sheet->setCellValue("A{$row}", __('ĐƠN HÀNG') . ' (' . count($orders) . ')');
$sheet->mergeCells("A{$row}:H{$row}");
$sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(13);
$row++;

$orderHeaderRow = $row;
$headers2 = ['#', __('Mã hàng'), __('Loại'), __('Cân nặng (kg)'), __('Số khối (m³)'), __('Cước vận chuyển'), __('Trạng thái'), __('Ngày tạo')];
$col = 'A';
foreach ($headers2 as $h) { $sheet->setCellValue($col++ . $row, $h); }
style_header_row($sheet, "A{$row}:H{$row}");
$row++;

$stt = 0;
$orderDataStart = $row;
foreach ($orders as $order) {
    $stt++;
    $weightKg = floatval($order['pkg_weight_actual'] ?? 0) ?: floatval($order['pkg_weight_charged'] ?? 0) ?: floatval($order['order_weight_actual'] ?? 0) ?: floatval($order['order_weight_charged'] ?? 0);
    $cbm = floatval($order['total_cbm'] ?? 0);
    $wVal = $weightKg > 0 ? round($weightKg, 2) : '';
    $cVal = $cbm > 0 ? round($cbm, 4) : '';
    $sheet->setCellValue("A{$row}", $stt);
    $sheet->setCellValue("B{$row}", $order['product_code'] ?? $order['order_code'] ?? '');
    $sheet->setCellValue("C{$row}", $typeLabel[$order['product_type']] ?? ($order['product_type'] ?? ''));
    $sheet->setCellValue("D{$row}", $wVal !== '' && $wVal == intval($wVal) ? intval($wVal) : $wVal);
    $sheet->setCellValue("E{$row}", $cVal !== '' && $cVal == intval($cVal) ? intval($cVal) : $cVal);
    $sheet->setCellValue("F{$row}", round($order['ship_cost']));
    $sheet->setCellValue("G{$row}", $statusLabel[$order['status']] ?? $order['status']);
    $sheet->setCellValue("H{$row}", $order['create_date']);
    $row++;
}

// Orders summary
$sheet->setCellValue("E{$row}", __('Tổng cước'));
$sheet->setCellValue("F{$row}", round($totalShipCost));
$sheet->getStyle("E{$row}:F{$row}")->getFont()->setBold(true);
$sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0');

$orderDataEnd = $row - 1;
if ($orderDataEnd >= $orderDataStart) {
    $sheet->getStyle("D{$orderDataStart}:D{$orderDataEnd}")->getNumberFormat()->setFormatCode('General');
    $sheet->getStyle("E{$orderDataStart}:E{$orderDataEnd}")->getNumberFormat()->setFormatCode('General');
    $sheet->getStyle("F{$orderDataStart}:F{$orderDataEnd}")->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle("D{$orderDataStart}:F{$orderDataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle("A{$orderHeaderRow}:H{$orderDataEnd}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}


// Auto column width
foreach (range('A', 'H') as $c) { $sheet->getColumnDimension($c)->setAutoSize(true); }

// Output
$safeName = preg_replace('/[^a-zA-Z0-9_-]/', '', $customer['customer_code'] ?? $customer['fullname']);
$filename = 'khach-hang-' . ($safeName ?: $id) . '-' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
