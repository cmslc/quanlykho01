<?php
/**
 * Export shipment detail to XLSX with product images
 * Uses PhpSpreadsheet (embedded images)
 */
define('IN_SITE', true);
require_once(__DIR__.'/../../libs/db.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../libs/lang.php');
require_once(__DIR__.'/../../libs/helper.php');
require_once(__DIR__.'/../../libs/session.php');
require_once(__DIR__.'/../../libs/role.php');
require_once(__DIR__.'/../../models/is_admin.php');
require_once(__DIR__.'/../../libs/database/shipments.php');

$id = intval(input_get('id'));
$shipment = $ToryHub->get_row_safe(
    "SELECT * FROM `shipments` WHERE `id` = ?", [$id]
);
if (!$shipment) {
    http_response_code(404);
    die('Chuyến xe không tồn tại');
}

$ShipmentsDB = new Shipments();
$packages = $ShipmentsDB->getPackages($id);

// Group packages (same logic as shipments-detail.php)
$grouped = [];
foreach ($packages as $pkg) {
    $key = $pkg['bag_code'] ?: ($pkg['product_code'] ?: ($pkg['order_code'] ?: 'Không xác định'));
    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'pkgs'          => [],
            'product_name'  => $pkg['product_name'] ?? '',
            'product_image' => $pkg['product_image'] ?? '',
            'create_date'   => $pkg['create_date'] ?? '',
            'is_bag'        => !empty($pkg['bag_code']),
            'bag_weight'    => $pkg['bag_weight'] ?? 0,
            'bag_cbm'       => (($pkg['bag_length'] ?? 0) * ($pkg['bag_width'] ?? 0) * ($pkg['bag_height'] ?? 0)) / 1000000,
        ];
    }
    $grouped[$key]['pkgs'][] = $pkg;
}

// Build spreadsheet
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('ChuyenXe');

// ── Column widths ──────────────────────────────────────────────
$sheet->getColumnDimension('A')->setWidth(14);  // Ngày tạo
$sheet->getColumnDimension('B')->setWidth(20);  // Mã hàng
$sheet->getColumnDimension('C')->setWidth(30);  // Sản phẩm
$sheet->getColumnDimension('D')->setWidth(10);  // Số kiện
$sheet->getColumnDimension('E')->setWidth(14);  // Tổng cân
$sheet->getColumnDimension('F')->setWidth(14);  // Tổng khối
$sheet->getColumnDimension('G')->setWidth(14);  // Ảnh

// ── Header row ─────────────────────────────────────────────────
$headers = ['Ngày tạo', 'Mã hàng', 'Sản phẩm', 'Số kiện', 'Tổng cân (kg)', 'Tổng khối (m³)', 'Ảnh'];
foreach ($headers as $ci => $h) {
    $col = chr(65 + $ci); // A, B, C...
    $sheet->setCellValue($col . '1', $h);
}

$headerStyle = [
    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2d6a4f']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'AAAAAA']]],
];
$sheet->getStyle('A1:G1')->applyFromArray($headerStyle);
$sheet->getRowDimension(1)->setRowHeight(22);

// ── Data rows ──────────────────────────────────────────────────
$rowNum = 2;
$rootDir = realpath(__DIR__ . '/../../');

foreach ($grouped as $maHang => $group) {
    $pkgList = $group['pkgs'];
    if ($group['is_bag']) {
        $w   = round(floatval($group['bag_weight']), 2);
        $cbm = round(floatval($group['bag_cbm']), 4);
    } else {
        $w   = round(array_sum(array_column($pkgList, 'weight_charged')), 2);
        $cbm = 0;
        foreach ($pkgList as $p) {
            $cbm += ($p['length_cm'] * $p['width_cm'] * $p['height_cm']) / 1000000;
        }
        $cbm = round($cbm, 4);
    }

    $dateStr = $group['create_date'] ? date('d/m/Y', strtotime($group['create_date'])) : '';

    $sheet->setCellValue('A' . $rowNum, $dateStr);
    $sheet->setCellValue('B' . $rowNum, $maHang);
    $sheet->setCellValue('C' . $rowNum, $group['product_name']);
    $sheet->setCellValue('D' . $rowNum, count($pkgList));
    $sheet->setCellValue('E' . $rowNum, $w > 0 ? $w : '');
    $sheet->setCellValue('F' . $rowNum, $cbm > 0 ? $cbm : '');
    // Column G = image (handled below)

    // Row height for image
    $rowHeight = 60;
    $sheet->getRowDimension($rowNum)->setRowHeight($rowHeight);

    // Embed first product image
    $imgEmbedded = false;
    if (!empty($group['product_image'])) {
        $imgPaths = array_filter(array_map('trim', explode(',', $group['product_image'])));
        foreach ($imgPaths as $imgPath) {
            $imgPath = ltrim($imgPath, '/\\');
            $fullPath = $rootDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $imgPath);
            if (file_exists($fullPath)) {
                try {
                    $drawing = new Drawing();
                    $drawing->setPath($fullPath);
                    $drawing->setCoordinates('G' . $rowNum);
                    $drawing->setHeight(55);
                    $drawing->setOffsetX(2);
                    $drawing->setOffsetY(2);
                    $drawing->setWorksheet($sheet);
                    $imgEmbedded = true;
                } catch (\Exception $e) {
                    // skip if image can't be loaded
                }
                break;
            }
        }
    }
    if (!$imgEmbedded) {
        $sheet->setCellValue('G' . $rowNum, '');
    }

    // Row style
    $rowStyle = [
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
    ];
    if ($rowNum % 2 === 0) {
        $rowStyle['fill'] = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8F9FA']];
    }
    $sheet->getStyle('A' . $rowNum . ':G' . $rowNum)->applyFromArray($rowStyle);
    $sheet->getStyle('D' . $rowNum . ':F' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $rowNum++;
}

// ── Output ─────────────────────────────────────────────────────
$filename = 'ChuyenXe_' . $shipment['shipment_code'] . '_' . date('Y-m-d') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
