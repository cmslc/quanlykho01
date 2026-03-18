<?php
/**
 * Export bags list to XLSX with embedded images (PhpSpreadsheet)
 */
define('IN_SITE', true);
require_once(__DIR__.'/../../libs/db.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../libs/lang.php');
require_once(__DIR__.'/../../libs/helper.php');
require_once(__DIR__.'/../../libs/session.php');
require_once(__DIR__.'/../../libs/role.php');
require_once(__DIR__.'/../../models/is_admin.php');
require_once(__DIR__.'/../../vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

// Filters (same as bags-list)
$filterStatus = input_get('status') ?: '';
$filterSearch = trim(input_get('search') ?? '');

$where = "1=1";
$params = [];

if ($filterStatus && in_array($filterStatus, ['open', 'sealed', 'loading', 'shipping', 'arrived'])) {
    $where .= " AND b.status = ?";
    $params[] = $filterStatus;
}
if ($filterSearch) {
    $where .= " AND b.bag_code LIKE ?";
    $params[] = '%' . $filterSearch . '%';
}

$bags = $ToryHub->get_list_safe("SELECT b.*, u.fullname as creator_name
    FROM `bags` b LEFT JOIN `users` u ON b.created_by = u.id
    WHERE $where ORDER BY b.create_date DESC", $params);

$bagStatusLabels = [
    'open'     => 'Đang mở',
    'sealed'   => 'Chờ vận chuyển',
    'loading'  => 'Đang xếp xe',
    'shipping' => 'Đang vận chuyển',
    'arrived'  => 'Đã đến kho VN',
];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Danh sách bao');

// Column widths
$sheet->getColumnDimension('A')->setWidth(6);
$sheet->getColumnDimension('B')->setWidth(16);
$sheet->getColumnDimension('C')->setWidth(20);
$sheet->getColumnDimension('D')->setWidth(10);
$sheet->getColumnDimension('E')->setWidth(14);
$sheet->getColumnDimension('F')->setWidth(14);
$sheet->getColumnDimension('G')->setWidth(22);
$sheet->getColumnDimension('H')->setWidth(18);
$sheet->getColumnDimension('I')->setWidth(16);

// Header row
$headers = ['STT', 'Mã bao', 'Trạng thái', 'Số kiện', 'Tổng cân (kg)', 'Số khối (m³)', 'Người tạo', 'Ngày tạo', 'Ảnh'];
$sheet->fromArray($headers, null, 'A1');

$headerStyle = [
    'font'      => ['bold' => true, 'color' => ['rgb' => '1F3864']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
];
$sheet->getStyle('A1:I1')->applyFromArray($headerStyle);
$sheet->getRowDimension(1)->setRowHeight(22);

// Image thumbnail size in pixels (for Drawing)
$imgPx = 80;
// Convert px to Excel row height points (approx 0.75)
$rowHeightPt = intval($imgPx / 0.75) * 0.75 + 10; // ~70pt

$projectRoot = realpath(__DIR__ . '/../../');

$row = 2;
foreach ($bags as $stt => $bag) {
    $sheet->setCellValue('A' . $row, $stt + 1);
    $sheet->setCellValue('B' . $row, $bag['bag_code']);
    $sheet->setCellValue('C' . $row, $bagStatusLabels[$bag['status']] ?? $bag['status']);
    $sheet->setCellValue('D' . $row, intval($bag['total_packages']));
    $sheet->setCellValue('E' . $row, floatval($bag['total_weight']));
    $sheet->setCellValue('F' . $row, floatval($bag['weight_volume']));
    $sheet->setCellValue('G' . $row, $bag['creator_name'] ?? '');
    $sheet->setCellValue('H' . $row, date('d/m/Y H:i', strtotime($bag['create_date'])));

    // Vertical align text cells
    $sheet->getStyle('A' . $row . ':H' . $row)->getAlignment()
        ->setVertical(Alignment::VERTICAL_CENTER);

    // Embed images
    if (!empty($bag['images'])) {
        $imgPaths = array_values(array_filter(array_map('trim', explode(',', $bag['images']))));
        $imgCount = count($imgPaths);
        $imgEmbedded = 0;

        // Set taller row height for image rows
        $sheet->getRowDimension($row)->setRowHeight($rowHeightPt);

        // Place each image in its own column starting from I
        $imgCols = ['I', 'J', 'K', 'L', 'M'];
        foreach ($imgPaths as $idx => $imgRelPath) {
            if ($idx >= count($imgCols)) break;
            $imgFile = $projectRoot . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $imgRelPath), DIRECTORY_SEPARATOR);
            if (!file_exists($imgFile)) continue;

            $col = $imgCols[$idx];
            // Ensure column width for extra image columns
            if ($idx > 0) {
                $sheet->getColumnDimension($col)->setWidth(16);
            }

            $drawing = new Drawing();
            $drawing->setPath($imgFile);
            $drawing->setCoordinates($col . $row);
            $drawing->setHeight($imgPx);
            $drawing->setOffsetX(4);
            $drawing->setOffsetY(4);
            $drawing->setWorksheet($sheet);
            $imgEmbedded++;
        }

        if ($imgEmbedded === 0) {
            // File(s) not accessible — fall back to URL text
            $sheet->setCellValue('I' . $row, get_upload_url($imgPaths[0]) . ($imgCount > 1 ? ' (+' . ($imgCount - 1) . ')' : ''));
        }
    }

    $row++;
}

// Header row for extra image columns (if any bags have >1 image)
// Add headers I, J, K... dynamically if needed
$maxImgCols = 5;
$imgColHeaders = ['Ảnh 1', 'Ảnh 2', 'Ảnh 3', 'Ảnh 4', 'Ảnh 5'];
$imgCols = ['I', 'J', 'K', 'L', 'M'];
for ($i = 0; $i < $maxImgCols; $i++) {
    $sheet->setCellValue($imgCols[$i] . '1', $imgColHeaders[$i]);
    $sheet->getStyle($imgCols[$i] . '1')->applyFromArray($headerStyle);
}

// Freeze top row
$sheet->freezePane('A2');

$filename = 'DanhSachBao_' . date('Y-m-d_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

add_log($getUser['id'], 'export', 'Xuất danh sách bao kèm ảnh (' . count($bags) . ' bao)');
