<?php
/**
 * Export shipments LIST to XLSX (PhpSpreadsheet)
 * Supports same filters as shipments-list page
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

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Filters (same as shipments-list)
$filterStatus = input_get('status') ?: '';
$filterSearch = trim(input_get('search') ?? '');

$where  = "1=1";
$params = [];

if ($filterStatus && in_array($filterStatus, ['preparing', 'in_transit', 'arrived', 'completed'])) {
    $where .= " AND s.status = ?";
    $params[] = $filterStatus;
}
if ($filterSearch) {
    $where .= " AND (s.shipment_code LIKE ? OR s.truck_plate LIKE ? OR s.driver_name LIKE ?)";
    $searchLike = '%' . $filterSearch . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

$shipments = $ToryHub->get_list_safe("SELECT s.*, u.fullname as creator_name
    FROM `shipments` s LEFT JOIN `users` u ON s.created_by = u.id
    WHERE $where ORDER BY s.create_date DESC", $params);

$statusLabel = [
    'preparing'  => 'Đang chuẩn bị',
    'in_transit' => 'Đang vận chuyển',
    'arrived'    => 'Đã đến',
    'completed'  => 'Hoàn thành',
];

// Build spreadsheet
$spreadsheet = new Spreadsheet();
$sheet       = $spreadsheet->getActiveSheet();
$sheet->setTitle('Danh sách chuyến xe');

$cols = [
    'A' => 6,   // STT
    'B' => 18,  // Mã chuyến
    'C' => 14,  // Biển số
    'D' => 20,  // Tài xế
    'E' => 16,  // SĐT tài xế
    'F' => 28,  // Tuyến đường
    'G' => 10,  // Số kiện
    'H' => 14,  // Tổng cân (kg)
    'I' => 14,  // Tổng khối (m³)
    'J' => 20,  // Trạng thái
    'K' => 18,  // Ngày tạo
    'L' => 16,  // Ngày xuất phát
    'M' => 16,  // Ngày đến
    'N' => 20,  // Người tạo
    'O' => 28,  // Ghi chú
];
foreach ($cols as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

$headers = ['STT', 'Mã chuyến', 'Biển số', 'Tài xế', 'SĐT tài xế', 'Tuyến đường', 'Số kiện', 'Tổng cân (kg)', 'Tổng khối (m³)', 'Trạng thái', 'Ngày tạo', 'Ngày xuất phát', 'Ngày đến', 'Người tạo', 'Ghi chú'];
$sheet->fromArray($headers, null, 'A1');

$headerStyle = [
    'font'      => ['bold' => true, 'color' => ['rgb' => '1F3864']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'B8CCE4']]],
];
$sheet->getStyle('A1:O1')->applyFromArray($headerStyle);
$sheet->getRowDimension(1)->setRowHeight(22);

$row = 2;
foreach ($shipments as $stt => $s) {
    $sheet->setCellValue('A' . $row, $stt + 1);
    $sheet->setCellValue('B' . $row, $s['shipment_code']);
    $sheet->setCellValue('C' . $row, $s['truck_plate'] ?? '');
    $sheet->setCellValue('D' . $row, $s['driver_name'] ?? '');
    $sheet->setCellValue('E' . $row, $s['driver_phone'] ?? '');
    $sheet->setCellValue('F' . $row, $s['route'] ?? '');
    $sheet->setCellValue('G' . $row, intval($s['total_packages']));
    $sheet->setCellValue('H' . $row, floatval($s['total_weight']));
    $sheet->setCellValue('I' . $row, floatval($s['total_cbm']));
    $sheet->setCellValue('J' . $row, $statusLabel[$s['status']] ?? $s['status']);
    $sheet->setCellValue('K' . $row, date('d/m/Y H:i', strtotime($s['create_date'])));
    $sheet->setCellValue('L' . $row, $s['departed_date'] ? date('d/m/Y', strtotime($s['departed_date'])) : '');
    $sheet->setCellValue('M' . $row, $s['arrived_date'] ? date('d/m/Y', strtotime($s['arrived_date'])) : '');
    $sheet->setCellValue('N' . $row, $s['creator_name'] ?? '');
    $sheet->setCellValue('O' . $row, $s['note'] ?? '');

    $sheet->getStyle('A' . $row . ':O' . $row)->getAlignment()
        ->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(false);

    $row++;
}

$sheet->freezePane('A2');

$filename = 'DanhSachChuyenXe_' . date('Y-m-d_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

add_log($getUser['id'], 'export', 'Xuất danh sách chuyến xe (' . count($shipments) . ' chuyến)');
