<?php
/**
 * Export orders list to XLSX with embedded product images (PhpSpreadsheet)
 * Supports same filters as orders-list page
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

// Filters (same as orders-list)
$filterStatus      = input_get('status') ?: '';
$filterCustomer    = input_get('customer_id') ?: '';
$filterProductType = input_get('product_type') ?: '';
$filterSearch      = trim(input_get('search') ?? '');
$filterDateFrom    = input_get('date_from') ?: '';
$filterDateTo      = input_get('date_to') ?: '';

$where  = "1=1";
$params = [];

if ($filterStatus) {
    $where .= " AND o.status = ?";
    $params[] = $filterStatus;
}
if ($filterCustomer) {
    $where .= " AND o.customer_id = ?";
    $params[] = intval($filterCustomer);
}
if ($filterProductType && in_array($filterProductType, ['retail', 'wholesale'])) {
    $where .= " AND o.product_type = ?";
    $params[] = $filterProductType;
}
if ($filterDateFrom) {
    $where .= " AND DATE(o.create_date) >= ?";
    $params[] = $filterDateFrom;
}
if ($filterDateTo) {
    $where .= " AND DATE(o.create_date) <= ?";
    $params[] = $filterDateTo;
}
if ($filterSearch) {
    $where .= " AND (o.order_code LIKE ? OR o.product_name LIKE ? OR o.product_code LIKE ? OR o.id IN (SELECT po.order_id FROM `package_orders` po JOIN `packages` p ON po.package_id = p.id WHERE p.tracking_cn LIKE ?))";
    $searchLike = '%' . $filterSearch . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

$orders = $ToryHub->get_list_safe("SELECT o.*, c.fullname as customer_name, c.customer_code
    FROM `orders` o LEFT JOIN `customers` c ON o.customer_id = c.id
    WHERE $where ORDER BY o.create_date DESC", $params);

// Get tracking codes and weight for all orders
$orderIds    = array_column($orders, 'id');
$trackingMap = [];
$weightMap   = [];
if (!empty($orderIds)) {
    $ph  = implode(',', array_fill(0, count($orderIds), '?'));
    $pkgs = $ToryHub->get_list_safe(
        "SELECT po.order_id, p.tracking_cn, p.weight_charged, p.weight_actual FROM `package_orders` po
         JOIN `packages` p ON po.package_id = p.id
         WHERE po.order_id IN ($ph)", $orderIds);
    foreach ($pkgs as $pkg) {
        if ($pkg['tracking_cn']) $trackingMap[$pkg['order_id']][] = $pkg['tracking_cn'];
        $weightMap[$pkg['order_id']]['charged'] = ($weightMap[$pkg['order_id']]['charged'] ?? 0) + floatval($pkg['weight_charged']);
        $weightMap[$pkg['order_id']]['actual']  = ($weightMap[$pkg['order_id']]['actual']  ?? 0) + floatval($pkg['weight_actual']);
    }
}

$statusLabel = [
    'cn_warehouse' => 'Đã về kho Trung Quốc',
    'packed'       => 'Đã đóng bao',
    'loading'      => 'Đang xếp xe',
    'shipping'     => 'Đang vận chuyển',
    'vn_warehouse' => 'Đã về kho Việt Nam',
    'delivered'    => 'Đã giao hàng',
    'cancelled'    => 'Đã hủy',
];

// Build spreadsheet
$spreadsheet = new Spreadsheet();
$sheet       = $spreadsheet->getActiveSheet();
$sheet->setTitle('Danh sách hàng');

$cols = [
    'A' => 6,   // STT
    'B' => 14,  // Loại hàng
    'C' => 14,  // Mã hàng
    'D' => 22,  // Mã vận đơn TQ
    'E' => 22,  // Khách hàng
    'F' => 14,  // Mã khách hàng
    'G' => 28,  // Sản phẩm
    'H' => 14,  // Cân tính phí
    'I' => 22,  // Trạng thái
    'J' => 28,  // Ghi chú khách hàng
    'K' => 28,  // Ghi chú nội bộ
    'L' => 18,  // Ngày nhập
    'M' => 18,  // Cập nhật
    'N' => 16,  // Ảnh 1
];
foreach ($cols as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

// Header row (image columns added after data loop)
$headers = ['STT', 'Loại hàng', 'Mã hàng', 'Mã vận đơn TQ', 'Khách hàng', 'Mã khách hàng', 'Sản phẩm', 'Cân tính phí (kg)', 'Trạng thái', 'Ghi chú khách hàng', 'Ghi chú nội bộ', 'Ngày nhập', 'Cập nhật'];
$sheet->fromArray($headers, null, 'A1');

$headerStyle = [
    'font'      => ['bold' => true, 'color' => ['rgb' => '1F3864']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'B8CCE4']]],
];
$sheet->getStyle('A1:M1')->applyFromArray($headerStyle);
$sheet->getRowDimension(1)->setRowHeight(22);

$imgPx       = 80;
$rowHeightPt = $imgPx + 10;
$projectRoot = realpath(__DIR__ . '/../../');
$imgCols     = ['N', 'O', 'P', 'Q', 'R'];

$row = 2;
foreach ($orders as $stt => $order) {
    $trackings = isset($trackingMap[$order['id']]) ? implode(', ', $trackingMap[$order['id']]) : '';
    $wCharged  = $weightMap[$order['id']]['charged'] ?? 0;
    $wActual   = $weightMap[$order['id']]['actual']  ?? 0;
    $isRetail  = ($order['product_type'] ?? 'retail') === 'retail';
    $productType = $isRetail ? 'Hàng lẻ' : 'Hàng lô';
    if ($isRetail) {
        $displayWeight = $wCharged > 0 ? $wCharged : ($wActual > 0 ? $wActual : '');
    } else {
        $oWC = floatval($order['weight_charged'] ?? 0);
        $oWA = floatval($order['weight_actual']  ?? 0);
        $displayWeight = $oWC > 0 ? $oWC : ($oWA > 0 ? $oWA : ($wCharged > 0 ? $wCharged : ($wActual > 0 ? $wActual : '')));
    }

    $sheet->setCellValue('A' . $row, $stt + 1);
    $sheet->setCellValue('B' . $row, $productType);
    $sheet->setCellValue('C' . $row, $order['product_code'] ?? '');
    $sheet->setCellValue('D' . $row, $trackings);
    $sheet->setCellValue('E' . $row, $order['customer_name'] ?? '');
    $sheet->setCellValue('F' . $row, $order['customer_code'] ?? '');
    $sheet->setCellValue('G' . $row, $order['product_name'] ?? '');
    $sheet->setCellValue('H' . $row, $displayWeight !== '' ? floatval($displayWeight) : '');
    $sheet->setCellValue('I' . $row, $statusLabel[$order['status']] ?? $order['status']);
    $sheet->setCellValue('J' . $row, $order['note'] ?? '');
    $sheet->setCellValue('K' . $row, $order['note_internal'] ?? '');
    $sheet->setCellValue('L' . $row, date('d/m/Y H:i', strtotime($order['create_date'])));
    $sheet->setCellValue('M' . $row, $order['update_date'] ? date('d/m/Y H:i', strtotime($order['update_date'])) : '');

    $sheet->getStyle('A' . $row . ':M' . $row)->getAlignment()
        ->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(false);

    // Embed all product images (up to 5 columns)
    if (!empty($order['product_image'])) {
        $imgPaths = array_values(array_filter(array_map('trim', explode(',', $order['product_image']))));
        if (!empty($imgPaths)) {
            $sheet->getRowDimension($row)->setRowHeight($rowHeightPt);
            foreach ($imgPaths as $idx => $imgRelPath) {
                if ($idx >= count($imgCols)) break;
                $imgFile = $projectRoot . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $imgRelPath), DIRECTORY_SEPARATOR);
                if (!file_exists($imgFile)) continue;
                $col = $imgCols[$idx];
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
            }
        }
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

$typeSuffix = $filterProductType === 'retail' ? '_HangLe' : ($filterProductType === 'wholesale' ? '_HangLo' : '');
$filename   = 'DanhSachHang' . $typeSuffix . '_' . date('Y-m-d_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

add_log($getUser['id'], 'export', 'Xuất danh sách hàng' . $typeSuffix . ' (' . count($orders) . ' đơn)');
