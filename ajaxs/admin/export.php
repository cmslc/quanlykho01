<?php
/**
 * Admin Export to XLSX (PhpSpreadsheet)
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
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Border;

$type = input_get('type');
$dateFrom = input_get('date_from') ?: date('Y-m-01');
$dateTo = input_get('date_to') ?: date('Y-m-d');

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

/**
 * Helper: style header row
 */
function style_header($sheet, $range) {
    $sheet->getStyle($range)->getFont()->setBold(true)->setSize(11);
    $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
    $sheet->getStyle($range)->getFont()->getColor()->setRGB('FFFFFF');
    $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

// ======== REVENUE EXPORT ========
if ($type === 'revenue') {
    $sheet->setTitle(__('Doanh thu'));
    $headers = [__('Kỳ'), __('Số đơn'), __('Tiền hàng CNY'), __('Tiền hàng VND'), __('Phí vận chuyển'), __('Phí khác'), __('Tổng phí'), __('Tổng cộng')];
    $col = 'A';
    foreach ($headers as $h) { $sheet->setCellValue($col++ . '1', $h); }
    style_header($sheet, 'A1:H1');

    $data = $ToryHub->get_list_safe("SELECT DATE_FORMAT(create_date, '%Y-%m-%d') as period,
        COUNT(*) as order_count,
        COALESCE(SUM(total_cny),0) as total_cny,
        COALESCE(SUM(total_vnd),0) as total_vnd,
        COALESCE(SUM(shipping_fee_cn + shipping_fee_intl),0) as shipping_fee,
        COALESCE(SUM(packing_fee + insurance_fee + other_fee),0) as other_fees,
        COALESCE(SUM(total_fee),0) as total_fee,
        COALESCE(SUM(grand_total),0) as grand_total
        FROM `orders` WHERE `status` != 'cancelled'
        AND DATE(create_date) >= ? AND DATE(create_date) <= ?
        GROUP BY DATE(create_date) ORDER BY period ASC", [$dateFrom, $dateTo]);

    $row = 2;
    foreach ($data as $r) {
        $sheet->setCellValue('A' . $row, $r['period']);
        $sheet->setCellValue('B' . $row, intval($r['order_count']));
        $sheet->setCellValue('C' . $row, round(floatval($r['total_cny']), 2));
        $sheet->setCellValue('D' . $row, round(floatval($r['total_vnd'])));
        $sheet->setCellValue('E' . $row, round(floatval($r['shipping_fee'])));
        $sheet->setCellValue('F' . $row, round(floatval($r['other_fees'])));
        $sheet->setCellValue('G' . $row, round(floatval($r['total_fee'])));
        $sheet->setCellValue('H' . $row, round(floatval($r['grand_total'])));
        $row++;
    }

    $lastRow = $row - 1;
    if ($lastRow >= 2) {
        $sheet->getStyle("C2:C{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("D2:H{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("B2:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }
    foreach (range('A', 'I') as $c) { $sheet->getColumnDimension($c)->setAutoSize(true); }
    if ($lastRow >= 1) {
        $sheet->getStyle("A1:I{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    add_log($getUser['id'], 'export', 'Xuất báo cáo doanh thu: ' . $dateFrom . ' → ' . $dateTo);
}

// ======== ORDERS EXPORT ========
elseif ($type === 'orders') {
    $sheet->setTitle(__('Đơn hàng'));
    $headers = [__('Mã đơn'), __('Khách hàng'), __('Mã khách hàng'), __('Nền tảng'), __('Sản phẩm'), __('Số lượng'), __('Đơn giá CNY'), __('Tổng CNY'), __('Tỷ giá'), __('Tiền hàng VND'), __('Ship nội TQ'), __('Ship quốc tế'), __('Đóng gỗ'), __('Bảo hiểm'), __('Phí khác'), __('Tổng cộng'), __('Trạng thái'), __('Mã vận đơn TQ'), __('Mã quốc tế'), __('Mã Việt Nam'), __('Cân tính phí'), __('Ngày tạo')];
    $col = 'A';
    foreach ($headers as $h) { $sheet->setCellValue($col++ . '1', $h); }
    style_header($sheet, 'A1:V1');

    $data = $ToryHub->get_list_safe("SELECT o.*, c.fullname as customer_name, c.customer_code
        FROM `orders` o LEFT JOIN `customers` c ON o.customer_id = c.id
        WHERE DATE(o.create_date) >= ? AND DATE(o.create_date) <= ?
        ORDER BY o.create_date ASC", [$dateFrom, $dateTo]);

    $statusLabel = [
        'cn_warehouse' => __('Tại kho Trung Quốc'), 'packed' => __('Đã đóng bao'),
        'shipping' => __('Vận chuyển'), 'vn_warehouse' => __('Kho VN'),
        'delivered' => __('Đã giao'), 'cancelled' => __('Đã hủy')
    ];

    $row = 2;
    foreach ($data as $r) {
        $sheet->setCellValue('A' . $row, $r['order_code']);
        $sheet->setCellValue('B' . $row, $r['customer_name'] ?? '');
        $sheet->setCellValue('C' . $row, $r['customer_code'] ?? '');
        $sheet->setCellValue('D' . $row, $r['platform']);
        $sheet->setCellValue('E' . $row, $r['product_name']);
        $sheet->setCellValue('F' . $row, intval($r['quantity']));
        $sheet->setCellValue('G' . $row, round(floatval($r['unit_price_cny']), 2));
        $sheet->setCellValue('H' . $row, round(floatval($r['total_cny']), 2));
        $sheet->setCellValue('I' . $row, round(floatval($r['exchange_rate'])));
        $sheet->setCellValue('J' . $row, round(floatval($r['total_vnd'])));
        $sheet->setCellValue('K' . $row, round(floatval($r['shipping_fee_cn'])));
        $sheet->setCellValue('L' . $row, round(floatval($r['shipping_fee_intl'])));
        $sheet->setCellValue('M' . $row, round(floatval($r['packing_fee'])));
        $sheet->setCellValue('N' . $row, round(floatval($r['insurance_fee'])));
        $sheet->setCellValue('O' . $row, round(floatval($r['other_fee'])));
        $sheet->setCellValue('P' . $row, round(floatval($r['grand_total'])));
        $sheet->setCellValue('Q' . $row, $statusLabel[$r['status']] ?? $r['status']);
        $sheet->setCellValue('R' . $row, $r['cn_tracking']);
        $sheet->setCellValue('S' . $row, $r['intl_tracking']);
        $sheet->setCellValue('T' . $row, $r['vn_tracking']);
        $sheet->setCellValue('U' . $row, floatval($r['weight_charged']));
        $sheet->setCellValue('V' . $row, $r['create_date']);
        $row++;
    }

    $lastRow = $row - 1;
    if ($lastRow >= 2) {
        $sheet->getStyle("G2:H{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("I2:P{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("U2:U{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
    }
    foreach (range('A', 'V') as $c) { $sheet->getColumnDimension($c)->setAutoSize(true); }
    if ($lastRow >= 1) {
        $sheet->getStyle("A1:V{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    add_log($getUser['id'], 'export', 'Xuất danh sách đơn hàng: ' . $dateFrom . ' → ' . $dateTo);
}

// ======== CUSTOMERS EXPORT ========
elseif ($type === 'customers') {
    $sheet->setTitle(__('Khách hàng'));
    $headers = [__('Mã khách hàng'), __('Họ tên'), __('Điện thoại'), __('Email'), __('Loại khách hàng'), __('Tổng đơn'), __('Tổng chi tiêu'), __('Số dư'), __('Zalo'), __('WeChat'), __('Địa chỉ VN'), __('Ngày tạo')];
    $col = 'A';
    foreach ($headers as $h) { $sheet->setCellValue($col++ . '1', $h); }
    style_header($sheet, 'A1:L1');

    $data = $ToryHub->get_list_safe("SELECT * FROM `customers` ORDER BY `id` ASC", []);

    $typeLabel = ['normal' => __('Thường'), 'vip' => 'VIP', 'agent' => __('Đại lý')];

    $row = 2;
    foreach ($data as $r) {
        $sheet->setCellValue('A' . $row, $r['customer_code']);
        $sheet->setCellValue('B' . $row, $r['fullname']);
        $sheet->setCellValue('C' . $row, $r['phone'] ?? '');
        $sheet->setCellValue('D' . $row, $r['email'] ?? '');
        $sheet->setCellValue('E' . $row, $typeLabel[$r['customer_type']] ?? $r['customer_type']);
        $sheet->setCellValue('F' . $row, intval($r['total_orders']));
        $sheet->setCellValue('G' . $row, round(floatval($r['total_spent'])));
        $sheet->setCellValue('H' . $row, round(floatval($r['balance'])));
        $sheet->setCellValue('I' . $row, $r['zalo'] ?? '');
        $sheet->setCellValue('J' . $row, $r['wechat'] ?? '');
        $sheet->setCellValue('K' . $row, $r['address_vn'] ?? '');
        $sheet->setCellValue('L' . $row, $r['create_date']);
        $row++;
    }

    $lastRow = $row - 1;
    if ($lastRow >= 2) {
        $sheet->getStyle("G2:H{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("G2:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }
    foreach (range('A', 'L') as $c) { $sheet->getColumnDimension($c)->setAutoSize(true); }
    if ($lastRow >= 1) {
        $sheet->getStyle("A1:L{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    add_log($getUser['id'], 'export', 'Xuất danh sách khách hàng');
}

// ======== TRANSACTIONS EXPORT ========
elseif ($type === 'transactions') {
    $sheet->setTitle(__('Giao dịch'));
    $headers = ['ID', __('Khách hàng'), __('Mã khách hàng'), __('Loại'), __('Số tiền'), __('Số dư trước'), __('Số dư sau'), __('Mô tả'), __('Ngày')];
    $col = 'A';
    foreach ($headers as $h) { $sheet->setCellValue($col++ . '1', $h); }
    style_header($sheet, 'A1:I1');

    $data = $ToryHub->get_list_safe("SELECT t.*, c.fullname as customer_name, c.customer_code
        FROM `transactions` t LEFT JOIN `customers` c ON t.customer_id = c.id
        WHERE DATE(t.create_date) >= ? AND DATE(t.create_date) <= ?
        ORDER BY t.create_date ASC", [$dateFrom, $dateTo]);

    $typeLabel = ['deposit' => __('Nạp tiền'), 'payment' => __('Thanh toán'), 'refund' => __('Hoàn tiền'), 'adjustment' => __('Điều chỉnh')];

    $row = 2;
    foreach ($data as $r) {
        $sheet->setCellValue('A' . $row, intval($r['id']));
        $sheet->setCellValue('B' . $row, $r['customer_name'] ?? '');
        $sheet->setCellValue('C' . $row, $r['customer_code'] ?? '');
        $sheet->setCellValue('D' . $row, $typeLabel[$r['type']] ?? $r['type']);
        $sheet->setCellValue('E' . $row, round(floatval($r['amount'])));
        $sheet->setCellValue('F' . $row, round(floatval($r['balance_before'])));
        $sheet->setCellValue('G' . $row, round(floatval($r['balance_after'])));
        $sheet->setCellValue('H' . $row, $r['description'] ?? '');
        $sheet->setCellValue('I' . $row, $r['create_date']);
        $row++;
    }

    $lastRow = $row - 1;
    if ($lastRow >= 2) {
        $sheet->getStyle("E2:G{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("E2:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }
    foreach (range('A', 'I') as $c) { $sheet->getColumnDimension($c)->setAutoSize(true); }
    if ($lastRow >= 1) {
        $sheet->getStyle("A1:I{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    add_log($getUser['id'], 'export', 'Xuất giao dịch: ' . $dateFrom . ' → ' . $dateTo);
}

// Output XLSX
$filename = "ToryHub_{$type}_{$dateFrom}_{$dateTo}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
