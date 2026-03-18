<?php
/**
 * Export transactions to XLSX (PhpSpreadsheet) - CN staff
 */
define('IN_SITE', true);
require_once(__DIR__.'/../../libs/db.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../libs/lang.php');
require_once(__DIR__.'/../../libs/helper.php');
require_once(__DIR__.'/../../libs/session.php');
require_once(__DIR__.'/../../libs/role.php');
require_once(__DIR__.'/../../models/is_staffcn.php');
require_once(__DIR__.'/../../vendor/autoload.php');

if (get_user_role() !== 'finance_cn') { exit; }

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

$filterType = input_get('type') ?: '';
$filterCustomer = input_get('customer_id') ?: '';
$filterDateFrom = input_get('date_from') ?: '';
$filterDateTo = input_get('date_to') ?: '';

$where = "1=1";
$params = [];

if ($filterType) {
    $where .= " AND t.type = ?";
    $params[] = $filterType;
}
if ($filterCustomer) {
    $where .= " AND t.customer_id = ?";
    $params[] = intval($filterCustomer);
}
if ($filterDateFrom) {
    $where .= " AND DATE(t.create_date) >= ?";
    $params[] = $filterDateFrom;
}
if ($filterDateTo) {
    $where .= " AND DATE(t.create_date) <= ?";
    $params[] = $filterDateTo;
}

$transactions = $ToryHub->get_list_safe("SELECT t.*, c.fullname as customer_name, c.customer_code, u.username as created_by_name,
    (SELECT o.order_code FROM orders o WHERE o.id = t.order_id LIMIT 1) as order_code
    FROM `transactions` t
    LEFT JOIN `customers` c ON t.customer_id = c.id
    LEFT JOIN `users` u ON t.created_by = u.id
    WHERE $where ORDER BY t.create_date DESC", $params);

$typeLabel = ['deposit' => __('Nạp tiền'), 'payment' => __('Thanh toán'), 'refund' => __('Hoàn tiền'), 'adjustment' => __('Điều chỉnh')];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle(__('Giao dịch'));

function style_header_row($sheet, $range) {
    $sheet->getStyle($range)->getFont()->setBold(true)->setSize(11);
    $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
    $sheet->getStyle($range)->getFont()->getColor()->setRGB('FFFFFF');
    $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

$row = 1;
$title = __('DANH SÁCH GIAO DỊCH');
if ($filterDateFrom || $filterDateTo) {
    $title .= " — " . ($filterDateFrom ?: '...') . " → " . ($filterDateTo ?: '...');
}
$sheet->setCellValue("A{$row}", $title);
$sheet->mergeCells("A{$row}:I{$row}");
$sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
$row += 2;

$headerRow = $row;
$headers = ['#', __('Khách hàng'), __('Mã KH'), __('Loại'), __('Số tiền'), __('Mã đơn'), __('Mô tả'), __('Người tạo'), __('Ngày')];
$col = 'A';
foreach ($headers as $h) { $sheet->setCellValue($col++ . $row, $h); }
style_header_row($sheet, "A{$row}:I{$row}");
$row++;

$stt = 0;
$dataStart = $row;
$totalAmount = 0;

foreach ($transactions as $txn) {
    $stt++;
    $amount = floatval($txn['amount']);
    $totalAmount += $amount;

    $sheet->setCellValue("A{$row}", $stt);
    $sheet->setCellValue("B{$row}", $txn['customer_name'] ?? '');
    $sheet->setCellValue("C{$row}", $txn['customer_code'] ?? '');
    $sheet->setCellValue("D{$row}", $typeLabel[$txn['type']] ?? $txn['type']);
    $sheet->setCellValue("E{$row}", round($amount));
    $sheet->setCellValue("F{$row}", $txn['order_code'] ?? '');
    $sheet->setCellValue("G{$row}", $txn['description'] ?? '');
    $sheet->setCellValue("H{$row}", $txn['created_by_name'] ?? '');
    $sheet->setCellValue("I{$row}", $txn['create_date']);
    $row++;
}

$sheet->setCellValue("D{$row}", __('TỔNG'));
$sheet->setCellValue("E{$row}", round($totalAmount));
$sheet->getStyle("D{$row}:E{$row}")->getFont()->setBold(true);
$dataEnd = $row;

if ($dataStart <= $dataEnd) {
    $sheet->getStyle("E{$dataStart}:E{$dataEnd}")->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle("E{$dataStart}:E{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle("A{$headerRow}:I{$dataEnd}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}

foreach (range('A', 'I') as $c) { $sheet->getColumnDimension($c)->setAutoSize(true); }

$datePart = ($filterDateFrom ?: date('Y-m-d')) . '_' . ($filterDateTo ?: date('Y-m-d'));
$filename = "GiaoDich_KhoTQ_{$datePart}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
