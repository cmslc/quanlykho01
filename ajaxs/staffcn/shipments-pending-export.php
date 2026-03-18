<?php
/**
 * Export "hàng chờ xếp xe" to XLSX with embedded images (PhpSpreadsheet)
 * Supports same filters as shipments-pending page
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
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

// Filters
$filterSearch   = trim(input_get('search') ?? '');
$filterCustomer = input_get('customer_id') ?: '';
$filterType     = input_get('type') ?: '';

$notInShipment = "p.id NOT IN (SELECT sp.package_id FROM `shipment_packages` sp JOIN `shipments` s ON sp.shipment_id = s.id WHERE s.status IN ('preparing','in_transit'))";
$projectRoot   = realpath(__DIR__ . '/../../');

// === Sealed bags (retail) ===
$sealedBags    = [];
$bagCustomerMap = [];

if ($filterType !== 'wholesale') {
    $bagWhere  = "b.status = 'sealed' AND p.status = 'packed' AND $notInShipment";
    $bagParams = [];
    if ($filterSearch) {
        $bagWhere .= " AND b.bag_code LIKE ?";
        $bagParams[] = '%' . $filterSearch . '%';
    }
    if ($filterCustomer) {
        $bagWhere .= " AND p.id IN (SELECT po2.package_id FROM `package_orders` po2 JOIN `orders` o2 ON po2.order_id = o2.id WHERE o2.customer_id = ?)";
        $bagParams[] = intval($filterCustomer);
    }

    $sealedBags = $ToryHub->get_list_safe(
        "SELECT b.id as bag_id, b.bag_code, b.images as bag_images,
            COUNT(p.id) as pkg_count,
            b.total_weight as bag_weight,
            COALESCE(b.weight_volume, 0) as bag_cbm,
            SUM(COALESCE(p.weight_charged, 0)) as pkg_weight_charged,
            SUM(COALESCE(p.weight_actual, 0)) as pkg_weight_actual,
            SUM(COALESCE(p.length_cm,0) * COALESCE(p.width_cm,0) * COALESCE(p.height_cm,0) / 1000000) as pkg_cbm,
            b.create_date
        FROM `bags` b
        JOIN `bag_packages` bp ON b.id = bp.bag_id
        JOIN `packages` p ON bp.package_id = p.id
        WHERE $bagWhere
        GROUP BY b.id
        ORDER BY b.create_date DESC",
        $bagParams
    );

    if (!empty($sealedBags)) {
        $bagIds = array_column($sealedBags, 'bag_id');
        $ph     = implode(',', array_fill(0, count($bagIds), '?'));
        $bagCusts = $ToryHub->get_list_safe(
            "SELECT DISTINCT bp.bag_id, c.id as cid, c.fullname
             FROM `bag_packages` bp
             JOIN `packages` p ON bp.package_id = p.id
             JOIN `package_orders` po ON p.id = po.package_id
             JOIN `orders` o ON po.order_id = o.id
             LEFT JOIN `customers` c ON o.customer_id = c.id
             WHERE bp.bag_id IN ($ph) AND c.id IS NOT NULL",
            $bagIds
        );
        foreach ($bagCusts as $bc) {
            $bagCustomerMap[$bc['bag_id']][$bc['cid']] = $bc['fullname'];
        }
    }
}

// === Wholesale orders ===
$wholesaleOrders = [];

if ($filterType !== 'retail') {
    $orderWhere  = "o.product_type = 'wholesale' AND p.status = 'cn_warehouse' AND $notInShipment";
    $orderParams = [];
    if ($filterSearch) {
        $orderWhere .= " AND (o.product_code LIKE ? OR o.order_code LIKE ?)";
        $searchLike   = '%' . $filterSearch . '%';
        $orderParams[] = $searchLike;
        $orderParams[] = $searchLike;
    }
    if ($filterCustomer) {
        $orderWhere .= " AND o.customer_id = ?";
        $orderParams[] = intval($filterCustomer);
    }

    $wholesaleOrders = $ToryHub->get_list_safe(
        "SELECT o.id, o.product_code, o.product_name, o.product_image, o.customer_id,
            c.fullname as customer_name, c.customer_code,
            COUNT(p.id) as pkg_count,
            SUM(COALESCE(p.weight_charged, 0)) as total_weight_charged,
            SUM(COALESCE(p.weight_actual, 0)) as total_weight_actual,
            SUM(p.length_cm * p.width_cm * p.height_cm / 1000000) as total_cbm,
            o.create_date
        FROM `packages` p
        JOIN `package_orders` po ON p.id = po.package_id
        JOIN `orders` o ON po.order_id = o.id
        LEFT JOIN `customers` c ON o.customer_id = c.id
        WHERE $orderWhere
        GROUP BY o.id
        ORDER BY o.create_date DESC",
        $orderParams
    );
}

// Build spreadsheet
$spreadsheet = new Spreadsheet();
$sheet       = $spreadsheet->getActiveSheet();
$sheet->setTitle('Hàng chờ xếp xe');

$cols = [
    'A' => 6,   // STT
    'B' => 18,  // Mã hàng
    'C' => 12,  // Loại
    'D' => 28,  // Sản phẩm
    'E' => 22,  // Khách hàng
    'F' => 8,   // Số kiện
    'G' => 14,  // Cân nặng (kg)
    'H' => 14,  // Số khối (m³)
    'I' => 18,  // Trạng thái
    'J' => 16,  // Ngày tạo
    'K' => 16,  // Ảnh 1
];
foreach ($cols as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

// Header row (image columns added after data loop)
$headers = ['STT', 'Mã hàng', 'Loại', 'Sản phẩm', 'Khách hàng', 'Số kiện', 'Cân nặng (kg)', 'Số khối (m³)', 'Trạng thái', 'Ngày tạo'];
$sheet->fromArray($headers, null, 'A1');

$headerStyle = [
    'font'      => ['bold' => true, 'color' => ['rgb' => '1F3864']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'B8CCE4']]],
];
$sheet->getStyle('A1:J1')->applyFromArray($headerStyle);
$sheet->getRowDimension(1)->setRowHeight(22);

$imgPx       = 80;
$rowHeightPt = $imgPx + 10;
$imgCols     = ['K', 'L', 'M', 'N', 'O'];

$row = 2;
$stt = 0;

// Helper: embed images into row
function embedImages($sheet, $imgPaths, $imgCols, $row, $imgPx, $rowHeightPt, $projectRoot) {
    if (empty($imgPaths)) return;
    $sheet->getRowDimension($row)->setRowHeight($rowHeightPt);
    foreach ($imgPaths as $idx => $imgRelPath) {
        if ($idx >= count($imgCols)) break;
        $imgFile = $projectRoot . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $imgRelPath), DIRECTORY_SEPARATOR);
        if (!file_exists($imgFile)) continue;
        $col = $imgCols[$idx];
        if ($idx > 0) {
            $sheet->getColumnDimension($col)->setWidth(16);
        }
        try {
            $drawing = new Drawing();
            $drawing->setPath($imgFile);
            $drawing->setCoordinates($col . $row);
            $drawing->setHeight($imgPx);
            $drawing->setOffsetX(4);
            $drawing->setOffsetY(4);
            $drawing->setWorksheet($sheet);
        } catch (\Exception $e) {
            // skip unreadable
        }
    }
}

// Sealed bags
foreach ($sealedBags as $bag) {
    $stt++;
    $bagW   = floatval($bag['bag_weight'] ?? 0);
    $pkgWC  = floatval($bag['pkg_weight_charged'] ?? 0);
    $pkgWA  = floatval($bag['pkg_weight_actual'] ?? 0);
    $weight = $bagW > 0 ? $bagW : ($pkgWC > 0 ? $pkgWC : $pkgWA);
    $bagCbm = floatval($bag['bag_cbm'] ?? 0);
    $pkgCbm = floatval($bag['pkg_cbm'] ?? 0);
    $cbm    = $bagCbm > 0 ? $bagCbm : $pkgCbm;

    $custs    = $bagCustomerMap[$bag['bag_id']] ?? [];
    $custName = count($custs) == 1 ? array_values($custs)[0] : (count($custs) > 1 ? count($custs) . ' khách' : '');
    $dateStr  = $bag['create_date'] ? date('d/m/Y', strtotime($bag['create_date'])) : '';

    $sheet->setCellValue('A' . $row, $stt);
    $sheet->setCellValue('B' . $row, $bag['bag_code']);
    $sheet->setCellValue('C' . $row, 'Bao hàng lẻ');
    $sheet->setCellValue('D' . $row, '');
    $sheet->setCellValue('E' . $row, $custName);
    $sheet->setCellValue('F' . $row, intval($bag['pkg_count']));
    $sheet->setCellValue('G' . $row, $weight > 0 ? round($weight, 2) : '');
    $sheet->setCellValue('H' . $row, $cbm > 0 ? round($cbm, 4) : '');
    $sheet->setCellValue('I' . $row, 'Đã đóng bao');
    $sheet->setCellValue('J' . $row, $dateStr);

    $sheet->getStyle('A' . $row . ':J' . $row)->getAlignment()
        ->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(false);

    if (!empty($bag['bag_images'])) {
        $imgPaths = array_values(array_filter(array_map('trim', explode(',', $bag['bag_images']))));
        embedImages($sheet, $imgPaths, $imgCols, $row, $imgPx, $rowHeightPt, $projectRoot);
    }

    $row++;
}

// Wholesale orders
foreach ($wholesaleOrders as $order) {
    $stt++;
    $wC     = floatval($order['total_weight_charged'] ?? 0);
    $wA     = floatval($order['total_weight_actual'] ?? 0);
    $weight = $wC > 0 ? $wC : $wA;
    $cbm    = floatval($order['total_cbm'] ?? 0);
    $dateStr = $order['create_date'] ? date('d/m/Y', strtotime($order['create_date'])) : '';

    $sheet->setCellValue('A' . $row, $stt);
    $sheet->setCellValue('B' . $row, $order['product_code'] ?? '');
    $sheet->setCellValue('C' . $row, 'Hàng lô');
    $sheet->setCellValue('D' . $row, $order['product_name'] ?? '');
    $sheet->setCellValue('E' . $row, $order['customer_name'] ?? '');
    $sheet->setCellValue('F' . $row, intval($order['pkg_count']));
    $sheet->setCellValue('G' . $row, $weight > 0 ? round($weight, 2) : '');
    $sheet->setCellValue('H' . $row, $cbm > 0 ? round($cbm, 4) : '');
    $sheet->setCellValue('I' . $row, 'Đã về kho Trung Quốc');
    $sheet->setCellValue('J' . $row, $dateStr);

    $sheet->getStyle('A' . $row . ':J' . $row)->getAlignment()
        ->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(false);

    if (!empty($order['product_image'])) {
        $imgPaths = array_values(array_filter(array_map('trim', explode(',', $order['product_image']))));
        embedImages($sheet, $imgPaths, $imgCols, $row, $imgPx, $rowHeightPt, $projectRoot);
    }

    $row++;
}

// Add headers for image columns
$imgColHeaders = ['Ảnh 1', 'Ảnh 2', 'Ảnh 3', 'Ảnh 4', 'Ảnh 5'];
for ($i = 0; $i < count($imgCols); $i++) {
    $sheet->setCellValue($imgCols[$i] . '1', $imgColHeaders[$i]);
    $sheet->getStyle($imgCols[$i] . '1')->applyFromArray($headerStyle);
}

$sheet->freezePane('A2');

$filename = 'HangChoXepXe_' . date('Y-m-d_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

add_log($getUser['id'], 'export', 'Xuất danh sách hàng chờ xếp xe (' . ($stt) . ' dòng)');
