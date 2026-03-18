<?php
/**
 * StaffVN Export to XLSX (PhpSpreadsheet)
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

$type = input_get('type');
$dateFrom = input_get('date_from') ?: date('Y-m-d');
$dateTo = input_get('date_to') ?: date('Y-m-d');

if ($type === 'daily') {
    $spreadsheet = new Spreadsheet();

    // ========== Sheet 1: Packages received ==========
    $sheet1 = $spreadsheet->getActiveSheet();
    $sheet1->setTitle(__('Kiện hàng đã nhận'));

    $headers1 = [__('Mã kiện'), __('Tracking QT'), __('Tracking VN'), __('Cân nặng (kg)'), __('Kích thước'), __('Đơn hàng'), __('Khách hàng'), __('Giờ nhận')];
    $col = 'A';
    foreach ($headers1 as $h) {
        $sheet1->setCellValue($col . '1', $h);
        $col++;
    }
    $headerRange1 = 'A1:H1';
    $sheet1->getStyle($headerRange1)->getFont()->setBold(true)->setSize(11);
    $sheet1->getStyle($headerRange1)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
    $sheet1->getStyle($headerRange1)->getFont()->getColor()->setRGB('FFFFFF');
    $sheet1->getStyle($headerRange1)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

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

    $row = 2;
    foreach ($packages as $pkg) {
        $dim = '';
        if ($pkg['length_cm'] > 0 || $pkg['width_cm'] > 0 || $pkg['height_cm'] > 0) {
            $dim = floatval($pkg['length_cm']) . 'x' . floatval($pkg['width_cm']) . 'x' . floatval($pkg['height_cm']);
        }
        $sheet1->setCellValue('A' . $row, $pkg['package_code']);
        $sheet1->setCellValue('B' . $row, $pkg['tracking_intl'] ?: '');
        $sheet1->setCellValue('C' . $row, $pkg['tracking_vn'] ?: '');
        $sheet1->setCellValue('D' . $row, floatval($pkg['weight_charged'] ?: $pkg['weight_actual']));
        $sheet1->setCellValue('E' . $row, $dim);
        $sheet1->setCellValue('F' . $row, $pkg['order_codes'] ?: '');
        $sheet1->setCellValue('G' . $row, $pkg['customer_names'] ?: '');
        $sheet1->setCellValue('H' . $row, $pkg['vn_warehouse_date'] ? date('H:i', strtotime($pkg['vn_warehouse_date'])) : '');
        $row++;
    }

    // Summary row
    $sheet1->setCellValue('A' . $row, __('Tổng kiện') . ': ' . count($packages));
    $sheet1->getStyle('A' . $row)->getFont()->setBold(true);

    $lastRow1 = $row - 1;
    if ($lastRow1 >= 2) {
        $sheet1->getStyle("D2:D{$lastRow1}")->getNumberFormat()->setFormatCode('#,##0.00');
    }
    foreach (range('A', 'H') as $c) {
        $sheet1->getColumnDimension($c)->setAutoSize(true);
    }
    if ($lastRow1 >= 1) {
        $sheet1->getStyle("A1:H{$lastRow1}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    // ========== Sheet 2: Orders delivered ==========
    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle(__('Đơn hàng đã giao'));

    $headers2 = [__('Mã đơn'), __('Mã KH'), __('Khách hàng'), __('Sản phẩm'), __('Tổng VND'), __('COD thu'), __('PT thanh toán'), __('Giờ giao')];
    $col = 'A';
    foreach ($headers2 as $h) {
        $sheet2->setCellValue($col . '1', $h);
        $col++;
    }
    $headerRange2 = 'A1:H1';
    $sheet2->getStyle($headerRange2)->getFont()->setBold(true)->setSize(11);
    $sheet2->getStyle($headerRange2)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
    $sheet2->getStyle($headerRange2)->getFont()->getColor()->setRGB('FFFFFF');
    $sheet2->getStyle($headerRange2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

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
    $row = 2;

    foreach ($orders as $order) {
        $totalValue += floatval($order['grand_total']);
        $totalCod += floatval($order['cod_amount']);
        $sheet2->setCellValue('A' . $row, $order['order_code']);
        $sheet2->setCellValue('B' . $row, $order['customer_code'] ?: '');
        $sheet2->setCellValue('C' . $row, $order['customer_name'] ?: '');
        $sheet2->setCellValue('D' . $row, $order['product_name'] ?: '');
        $sheet2->setCellValue('E' . $row, round(floatval($order['grand_total'])));
        $sheet2->setCellValue('F' . $row, round(floatval($order['cod_amount'])));
        $sheet2->setCellValue('G' . $row, $methodLabels[$order['cod_method']] ?? '');
        $sheet2->setCellValue('H' . $row, $order['delivered_date'] ? date('H:i', strtotime($order['delivered_date'])) : '');
        $row++;
    }

    // Summary row
    $sheet2->setCellValue('A' . $row, __('Tổng đơn giao') . ': ' . count($orders));
    $sheet2->setCellValue('E' . $row, round($totalValue));
    $sheet2->setCellValue('F' . $row, round($totalCod));
    $sheet2->getStyle("A{$row}:H{$row}")->getFont()->setBold(true);

    $lastRow2 = $row - 1;
    if ($lastRow2 >= 2) {
        $sheet2->getStyle("E2:F{$lastRow2}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet2->getStyle("E2:F{$lastRow2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }
    $sheet2->getStyle("E{$row}:F{$row}")->getNumberFormat()->setFormatCode('#,##0');
    foreach (range('A', 'H') as $c) {
        $sheet2->getColumnDimension($c)->setAutoSize(true);
    }
    if ($lastRow2 >= 1) {
        $sheet2->getStyle("A1:H{$lastRow2}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    // Output
    $spreadsheet->setActiveSheetIndex(0);
    $filename = "KhoVN_{$type}_{$dateFrom}_{$dateTo}.xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
