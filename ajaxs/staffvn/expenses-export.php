<?php
/**
 * Export expenses to XLSX (PhpSpreadsheet)
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
use PhpOffice\PhpSpreadsheet\Style\Border;

$filterMonth = intval(input_get('month')) ?: date('n');
$filterYear = intval(input_get('year')) ?: date('Y');
$filterCat = input_get('category') ?: '';

$monthStart = sprintf('%04d-%02d-01', $filterYear, $filterMonth);
$monthEnd = date('Y-m-t', strtotime($monthStart));

$where = "e.expense_date BETWEEN ? AND ? AND e.warehouse = 'vn'";
$params = [$monthStart, $monthEnd];
if ($filterCat) {
    $where .= " AND e.category = ?";
    $params[] = $filterCat;
}

$expenses = $ToryHub->get_list_safe("SELECT e.*, u.username as created_by_name
    FROM `expenses` e
    LEFT JOIN `users` u ON e.created_by = u.id
    WHERE $where ORDER BY e.expense_date DESC, e.id DESC", $params);

// Summary by category
$catSums = $ToryHub->get_list_safe("SELECT category, COALESCE(SUM(amount),0) as total, COUNT(*) as cnt
    FROM `expenses` WHERE `expense_date` BETWEEN ? AND ? AND `warehouse` = 'vn'
    GROUP BY category ORDER BY total DESC", [$monthStart, $monthEnd]);

$totalMonth = 0;
foreach ($catSums as $cs) { $totalMonth += floatval($cs['total']); }

// Build XLSX
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle(__('Chi phí vận hành'));

function style_header_row($sheet, $range) {
    $sheet->getStyle($range)->getFont()->setBold(true)->setSize(11);
    $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
    $sheet->getStyle($range)->getFont()->getColor()->setRGB('FFFFFF');
    $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

$row = 1;

// Title
$title = __('CHI PHÍ VẬN HÀNH KHO') . " — " . __('Tháng') . " {$filterMonth}/{$filterYear}";
if ($filterCat) $title .= " — " . $filterCat;
$sheet->setCellValue("A{$row}", $title);
$sheet->mergeCells("A{$row}:F{$row}");
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
$sheet->setCellValue("C{$row}", __('Tổng tiền'));
style_header_row($sheet, "A{$row}:C{$row}");
$row++;

$sumDataStart = $row;
foreach ($catSums as $cs) {
    $sheet->setCellValue("A{$row}", $cs['category']);
    $sheet->setCellValue("B{$row}", intval($cs['cnt']));
    $sheet->setCellValue("C{$row}", round(floatval($cs['total'])));
    $row++;
}

// Total row
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
$sheet->mergeCells("A{$row}:F{$row}");
$sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
$row++;

$detailHeaderRow = $row;
$headers = ['#', __('Danh mục'), __('Số tiền'), __('Mô tả'), __('Ngày chi'), __('Người tạo')];
$col = 'A';
foreach ($headers as $h) { $sheet->setCellValue($col++ . $row, $h); }
style_header_row($sheet, "A{$row}:F{$row}");
$row++;

$stt = 0;
$detailDataStart = $row;
foreach ($expenses as $exp) {
    $stt++;
    $sheet->setCellValue("A{$row}", $stt);
    $sheet->setCellValue("B{$row}", $exp['category']);
    $sheet->setCellValue("C{$row}", round(floatval($exp['amount'])));
    $sheet->setCellValue("D{$row}", $exp['description'] ?? '');
    $sheet->setCellValue("E{$row}", $exp['expense_date']);
    $sheet->setCellValue("F{$row}", $exp['created_by_name'] ?? '');
    $row++;
}

// Detail total
$sheet->setCellValue("B{$row}", __('TỔNG CỘNG'));
$sheet->setCellValue("C{$row}", round($totalMonth));
$sheet->getStyle("B{$row}:C{$row}")->getFont()->setBold(true);
$detailDataEnd = $row;

if ($detailDataStart <= $detailDataEnd) {
    $sheet->getStyle("C{$detailDataStart}:C{$detailDataEnd}")->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle("C{$detailDataStart}:C{$detailDataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle("A{$detailHeaderRow}:F{$detailDataEnd}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}

// Auto column width
foreach (range('A', 'F') as $c) { $sheet->getColumnDimension($c)->setAutoSize(true); }

// Output
$filename = "ChiPhi_KhoVN_T{$filterMonth}_{$filterYear}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
