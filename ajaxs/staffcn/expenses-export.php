<?php
/**
 * Export expenses to XLSX (PhpSpreadsheet) - CN warehouse
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

$exchangeRate = get_exchange_rate();
$filterMonth = intval(input_get('month')) ?: date('n');
$filterYear = intval(input_get('year')) ?: date('Y');
$filterCat = input_get('category') ?: '';

$monthStart = sprintf('%04d-%02d-01', $filterYear, $filterMonth);
$monthEnd = date('Y-m-t', strtotime($monthStart));

$where = "e.expense_date BETWEEN ? AND ? AND e.warehouse = 'cn'";
$params = [$monthStart, $monthEnd];
if ($filterCat) {
    $where .= " AND e.category = ?";
    $params[] = $filterCat;
}

$expenses = $ToryHub->get_list_safe("SELECT e.*, u.username as created_by_name
    FROM `expenses` e
    LEFT JOIN `users` u ON e.created_by = u.id
    WHERE $where ORDER BY e.expense_date DESC, e.id DESC", $params);

$catSums = $ToryHub->get_list_safe("SELECT category, COALESCE(SUM(amount),0) as total, COUNT(*) as cnt
    FROM `expenses` WHERE `expense_date` BETWEEN ? AND ? AND `warehouse` = 'cn'
    GROUP BY category ORDER BY total DESC", [$monthStart, $monthEnd]);

$totalMonth = 0;
foreach ($catSums as $cs) { $totalMonth += floatval($cs['total']); }

// Build XLSX
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle(__('Chi phí kho TQ'));

function style_header_row($sheet, $range) {
    $sheet->getStyle($range)->getFont()->setBold(true)->setSize(11);
    $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
    $sheet->getStyle($range)->getFont()->getColor()->setRGB('FFFFFF');
    $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

$row = 1;

$title = __('CHI PHÍ VẬN HÀNH KHO TQ') . " — " . __('Tháng') . " {$filterMonth}/{$filterYear}";
if ($filterCat) $title .= " — " . $filterCat;
$sheet->setCellValue("A{$row}", $title);
$sheet->mergeCells("A{$row}:H{$row}");
$sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
$row += 2;

// Summary by category
$sheet->setCellValue("A{$row}", __('TỔNG HỢP THEO DANH MỤC'));
$sheet->mergeCells("A{$row}:C{$row}");
$sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
$row++;

$sumHeaderRow = $row;
$sheet->setCellValue("A{$row}", __('Danh mục'));
$sheet->setCellValue("B{$row}", __('Số khoản'));
$sheet->setCellValue("C{$row}", __('Tổng tiền (VNĐ)'));
style_header_row($sheet, "A{$row}:C{$row}");
$row++;

$sumDataStart = $row;
foreach ($catSums as $cs) {
    $sheet->setCellValue("A{$row}", $cs['category']);
    $sheet->setCellValue("B{$row}", intval($cs['cnt']));
    $sheet->setCellValue("C{$row}", round(floatval($cs['total'])));
    $row++;
}

$sheet->setCellValue("A{$row}", __('TỔNG CỘNG'));
$sheet->setCellValue("C{$row}", round($totalMonth));
$sheet->getStyle("A{$row}:C{$row}")->getFont()->setBold(true);
$sumDataEnd = $row;

if ($sumDataStart <= $sumDataEnd) {
    $sheet->getStyle("C{$sumDataStart}:C{$sumDataEnd}")->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle("B{$sumDataStart}:C{$sumDataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle("A{$sumHeaderRow}:C{$sumDataEnd}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}

// Detail table
$row += 2;
$sheet->setCellValue("A{$row}", __('CHI TIẾT CHI PHÍ'));
$sheet->mergeCells("A{$row}:H{$row}");
$sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
$row++;

$detailHeaderRow = $row;
$headers = ['#', __('Danh mục'), __('Số tiền (VNĐ)'), __('Số tiền (¥)'), __('Tỉ giá'), __('Mô tả'), __('Ngày chi'), __('Người tạo')];
$col = 'A';
foreach ($headers as $h) { $sheet->setCellValue($col++ . $row, $h); }
style_header_row($sheet, "A{$row}:H{$row}");
$row++;

$stt = 0;
$detailDataStart = $row;
foreach ($expenses as $exp) {
    $stt++;
    $amountVnd = floatval($exp['amount']);
    $expRate = floatval($exp['exchange_rate'] ?? 0) ?: $exchangeRate;
    $amountCny = $expRate > 0 ? round($amountVnd / $expRate) : 0;

    $sheet->setCellValue("A{$row}", $stt);
    $sheet->setCellValue("B{$row}", $exp['category']);
    $sheet->setCellValue("C{$row}", round($amountVnd));
    $sheet->setCellValue("D{$row}", $amountCny);
    $sheet->setCellValue("E{$row}", $exp['exchange_rate'] ? round(floatval($exp['exchange_rate'])) : '');
    $sheet->setCellValue("F{$row}", $exp['description'] ?? '');
    $sheet->setCellValue("G{$row}", $exp['expense_date']);
    $sheet->setCellValue("H{$row}", $exp['created_by_name'] ?? '');
    $row++;
}

$sheet->setCellValue("B{$row}", __('TỔNG CỘNG'));
$sheet->setCellValue("C{$row}", round($totalMonth));
$sheet->getStyle("B{$row}:C{$row}")->getFont()->setBold(true);
$detailDataEnd = $row;

if ($detailDataStart <= $detailDataEnd) {
    $sheet->getStyle("C{$detailDataStart}:C{$detailDataEnd}")->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle("D{$detailDataStart}:E{$detailDataEnd}")->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle("C{$detailDataStart}:E{$detailDataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle("A{$detailHeaderRow}:H{$detailDataEnd}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}

foreach (range('A', 'H') as $c) { $sheet->getColumnDimension($c)->setAutoSize(true); }

$filename = "ChiPhi_KhoTQ_T{$filterMonth}_{$filterYear}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
