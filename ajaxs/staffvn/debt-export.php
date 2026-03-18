<?php
/**
 * Export debt list to XLSX (PhpSpreadsheet)
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

if ($getUser['role'] !== 'finance_vn') { exit; }

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Same logic as debt-list.php
$shippingRates = [
    'easy'      => ['per_kg' => floatval($ToryHub->site('shipping_road_easy_per_kg') ?: 25000), 'per_cbm' => floatval($ToryHub->site('shipping_road_easy_per_cbm') ?: 6000000)],
    'difficult' => ['per_kg' => floatval($ToryHub->site('shipping_road_difficult_per_kg') ?: 35000), 'per_cbm' => floatval($ToryHub->site('shipping_road_difficult_per_cbm') ?: 8000000)],
];
$exchangeRate = get_exchange_rate();

$orderShipData = $ToryHub->get_list_safe(
    "SELECT o.id, o.customer_id, o.cargo_type,
        o.weight_charged as order_weight_charged,
        o.weight_actual as order_weight_actual,
        o.custom_rate_kg, o.custom_rate_cbm,
        o.domestic_cost, o.exchange_rate as order_exchange_rate,
        SUM(p.weight_charged) as pkg_weight_charged,
        SUM(p.weight_actual) as pkg_weight_actual,
        SUM(p.length_cm * p.width_cm * p.height_cm / 1000000) as total_cbm
     FROM `orders` o
     LEFT JOIN `package_orders` po ON o.id = po.order_id
     LEFT JOIN `packages` p ON po.package_id = p.id
     WHERE o.status != 'cancelled'
     GROUP BY o.id", []
);

$totalShipMap = [];
foreach ($orderShipData as $od) {
    $cid = $od['customer_id'];
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
    $orderRate = floatval($od['order_exchange_rate'] ?? 0) ?: $exchangeRate;
    $domesticVnd = floatval($od['domestic_cost'] ?? 0) * $orderRate;
    $cost = max($w * $rkg, $cbm * $rcbm) + $domesticVnd;
    if (!isset($totalShipMap[$cid])) $totalShipMap[$cid] = 0;
    $totalShipMap[$cid] += $cost;
}

$customers = $ToryHub->get_list_safe("SELECT * FROM `customers` ORDER BY `id` DESC", []);
$debtCustomers = [];
foreach ($customers as $c) {
    $cid = $c['id'];
    $ship = $totalShipMap[$cid] ?? 0;
    $paid = floatval($c['total_spent'] ?? 0);
    $debt = max(0, $ship - $paid);
    if ($debt > 0) {
        $debtCustomers[] = array_merge($c, ['ship' => $ship, 'paid' => $paid, 'debt' => $debt]);
    }
}
usort($debtCustomers, function($a, $b) { return $b['debt'] <=> $a['debt']; });

// Build XLSX
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle(__('Công nợ'));

// Header row
$headers = ['STT', __('Họ tên'), __('Điện thoại'), __('Loại'), __('Tổng cước'), __('Đã thanh toán'), __('Đang nợ')];
$col = 'A';
foreach ($headers as $h) {
    $sheet->setCellValue($col . '1', $h);
    $col++;
}

// Header style
$headerRange = 'A1:G1';
$sheet->getStyle($headerRange)->getFont()->setBold(true)->setSize(11);
$sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
$sheet->getStyle($headerRange)->getFont()->getColor()->setRGB('FFFFFF');
$sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Data rows
$row = 2;
$stt = 0;
foreach ($debtCustomers as $dc) {
    $stt++;
    $customerType = '';
    if (($dc['customer_type'] ?? '') === 'wholesale') $customerType = __('Khách lô');
    elseif (($dc['customer_type'] ?? '') === 'retail') $customerType = __('Khách lẻ');
    else $customerType = $dc['customer_type'] ?? '';

    $sheet->setCellValue('A' . $row, $stt);
    $sheet->setCellValue('B' . $row, $dc['fullname']);
    $sheet->setCellValue('C' . $row, $dc['phone'] ?? '');
    $sheet->setCellValue('D' . $row, $customerType);
    $sheet->setCellValue('E' . $row, round($dc['ship']));
    $sheet->setCellValue('F' . $row, round($dc['paid']));
    $sheet->setCellValue('G' . $row, round($dc['debt']));
    $row++;
}

// Number format for money columns
$lastRow = $row - 1;
if ($lastRow >= 2) {
    $sheet->getStyle("E2:G{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle("E2:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
}

// Auto column width
foreach (range('A', 'G') as $c) {
    $sheet->getColumnDimension($c)->setAutoSize(true);
}

// Border
$allRange = "A1:G{$lastRow}";
$sheet->getStyle($allRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Output
$filename = 'cong-no-khach-hang-' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
