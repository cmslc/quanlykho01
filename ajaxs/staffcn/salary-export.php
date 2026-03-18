<?php
/**
 * Export salary list to XLSX (PhpSpreadsheet) - CN staff
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

$filterMonth = intval(input_get('month')) ?: date('n');
$filterYear = intval(input_get('year')) ?: date('Y');
$filterStatus = input_get('status') ?: '';
$filterStaff = input_get('user_id') ?: '';

$where = "s.month = ? AND s.year = ? AND u.role IN ('staffcn', 'finance_cn')";
$params = [intval($filterMonth), intval($filterYear)];

if ($filterStatus) {
    $where .= " AND s.status = ?";
    $params[] = $filterStatus;
}
if ($filterStaff) {
    $where .= " AND s.user_id = ?";
    $params[] = intval($filterStaff);
}

$salaries = $ToryHub->get_list_safe(
    "SELECT s.*, u.fullname, u.username, u.role, u.phone
     FROM `salaries` s
     JOIN `users` u ON s.user_id = u.id
     WHERE $where
     ORDER BY u.role ASC, u.fullname ASC",
    $params
);

$exchangeRate = get_exchange_rate();
$statusLabel = ['draft' => __('Nháp'), 'confirmed' => __('Đã xác nhận'), 'paid' => __('Đã trả')];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle(__('Bảng lương'));

function style_header_row($sheet, $range) {
    $sheet->getStyle($range)->getFont()->setBold(true)->setSize(11);
    $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
    $sheet->getStyle($range)->getFont()->getColor()->setRGB('FFFFFF');
    $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

$row = 1;
$sheet->setCellValue("A{$row}", __('BẢNG LƯƠNG NHÂN VIÊN KHO TQ') . " — " . __('Tháng') . " {$filterMonth}/{$filterYear}");
$sheet->mergeCells("A{$row}:N{$row}");
$sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
$row += 2;

$headerRow = $row;
$headers = ['#', __('Nhân viên'), __('Điện thoại'), __('Tiền tệ'), __('Tỉ giá'), __('Lương CB'), __('Phụ cấp'), __('Thưởng'), __('Khấu trừ'), __('Thực nhận'), __('Thực nhận (VNĐ)'), __('Ngày công'), __('Trạng thái'), __('Ghi chú')];
$col = 'A';
foreach ($headers as $h) { $sheet->setCellValue($col++ . $row, $h); }
style_header_row($sheet, "A{$row}:N{$row}");
$row++;

$stt = 0;
$dataStart = $row;
$totalNetVnd = 0;

foreach ($salaries as $s) {
    $stt++;
    $isCNY = $s['currency'] === 'CNY';
    $sRate = floatval($s['exchange_rate'] ?? 0) ?: $exchangeRate;
    $net = floatval($s['net_salary']);
    $netVnd = $isCNY ? $net * $sRate : $net;
    $totalNetVnd += $netVnd;

    $sheet->setCellValue("A{$row}", $stt);
    $sheet->setCellValue("B{$row}", $s['fullname'] ?: $s['username']);
    $sheet->setCellValue("C{$row}", $s['phone'] ?? '');
    $sheet->setCellValue("D{$row}", $s['currency']);
    $sheet->setCellValue("E{$row}", $isCNY ? round($sRate) : '');
    $sheet->setCellValue("F{$row}", round(floatval($s['base_salary'])));
    $sheet->setCellValue("G{$row}", round(floatval($s['allowance'])));
    $sheet->setCellValue("H{$row}", round(floatval($s['bonus'])));
    $sheet->setCellValue("I{$row}", round(floatval($s['deduction'])));
    $sheet->setCellValue("J{$row}", round($net));
    $sheet->setCellValue("K{$row}", round($netVnd));
    $sheet->setCellValue("L{$row}", $s['work_days'] !== null ? floatval($s['work_days']) : '');
    $sheet->setCellValue("M{$row}", $statusLabel[$s['status']] ?? $s['status']);
    $sheet->setCellValue("N{$row}", $s['note'] ?? '');
    $row++;
}

$sheet->setCellValue("I{$row}", __('TỔNG'));
$sheet->setCellValue("K{$row}", round($totalNetVnd));
$sheet->getStyle("I{$row}:K{$row}")->getFont()->setBold(true);
$dataEnd = $row;

if ($dataStart <= $dataEnd) {
    $sheet->getStyle("E{$dataStart}:K{$dataEnd}")->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle("E{$dataStart}:K{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle("A{$headerRow}:N{$dataEnd}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}

foreach (range('A', 'N') as $c) { $sheet->getColumnDimension($c)->setAutoSize(true); }

$filename = "Luong_KhoTQ_T{$filterMonth}_{$filterYear}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
