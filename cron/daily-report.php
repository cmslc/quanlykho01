<?php
/**
 * Cron Job: Báo cáo tổng hợp hàng ngày qua Telegram
 *
 * Chạy: php cron/daily-report.php
 * Hoặc cấu hình crontab: 0 8 * * * php /path/to/ToryHub/cron/daily-report.php
 * (Chạy lúc 8:00 sáng mỗi ngày)
 */

define('IN_SITE', true);
require_once(__DIR__.'/../libs/db.php');
require_once(__DIR__.'/../config.php');
require_once(__DIR__.'/../libs/lang.php');
require_once(__DIR__.'/../libs/helper.php');
require_once(__DIR__.'/../libs/telegram.php');

$ToryHub = new DB();

$today = date('Y/m/d');
$yesterday = date('Y/m/d', strtotime('-1 day'));

// === Thống kê đơn hàng hôm nay ===
$orders_today = $ToryHub->get_row_safe(
    "SELECT COUNT(*) as total FROM `orders` WHERE DATE(`create_date`) = CURDATE()", []
);

$orders_by_status = $ToryHub->get_list_safe(
    "SELECT `status`, COUNT(*) as total FROM `orders` GROUP BY `status`", []
);

$status_summary = [];
foreach ($orders_by_status as $s) {
    $status_summary[$s['status']] = $s['total'];
}

// Đơn hàng mới hôm nay
$new_orders = $ToryHub->get_row_safe(
    "SELECT COUNT(*) as total, COALESCE(SUM(`grand_total`), 0) as revenue
     FROM `orders` WHERE DATE(`create_date`) = CURDATE()", []
);

// Đơn giao hôm nay
$delivered_today = $ToryHub->get_row_safe(
    "SELECT COUNT(*) as total FROM `order_status_history`
     WHERE `new_status` = 'delivered' AND DATE(`create_date`) = CURDATE()", []
);

// === Thống kê tài chính ===
$deposits_today = $ToryHub->get_row_safe(
    "SELECT COUNT(*) as total, COALESCE(SUM(`amount`), 0) as sum_amount
     FROM `transactions` WHERE `type` = 'deposit' AND DATE(`create_date`) = CURDATE()", []
);

$payments_today = $ToryHub->get_row_safe(
    "SELECT COUNT(*) as total, COALESCE(SUM(ABS(`amount`)), 0) as sum_amount
     FROM `transactions` WHERE `type` = 'payment' AND DATE(`create_date`) = CURDATE()", []
);

// === Đơn hàng cần xử lý ===
$cn_warehouse_count = $status_summary['cn_warehouse'] ?? 0;
$shipping_count = $status_summary['shipping'] ?? 0;
$vn_warehouse_count = $status_summary['vn_warehouse'] ?? 0;

// === Tổng khách hàng ===
$total_customers = $ToryHub->get_row_safe("SELECT COUNT(*) as total FROM `customers`", []);

// === Build message ===
$message = "📊 <b>BÁO CÁO NGÀY " . date('d/m/Y') . "</b>\n";
$message .= "━━━━━━━━━━━━━━━━━━━━\n\n";

$message .= "📦 <b>ĐƠN HÀNG</b>\n";
$message .= "• Đơn mới hôm nay: <b>" . intval($new_orders['total']) . "</b>\n";
$message .= "• Doanh thu đơn mới: <b>" . format_vnd(intval($new_orders['revenue'])) . "</b>\n";
$message .= "• Đã giao hôm nay: <b>" . intval($delivered_today['total']) . "</b>\n\n";

$message .= "⚠️ <b>CẦN XỬ LÝ</b>\n";
$message .= "• Tại kho Trung Quốc: <b>" . $cn_warehouse_count . "</b>\n";
$message .= "• Đang vận chuyển: <b>" . $shipping_count . "</b>\n";
$message .= "• Đã về kho Việt Nam: <b>" . $vn_warehouse_count . "</b>\n\n";

$message .= "💰 <b>TÀI CHÍNH</b>\n";
$message .= "• Nạp tiền: <b>" . intval($deposits_today['total']) . "</b> lệnh - " . format_vnd(intval($deposits_today['sum_amount'])) . "\n";
$message .= "• Thanh toán: <b>" . intval($payments_today['total']) . "</b> lệnh - " . format_vnd(intval($payments_today['sum_amount'])) . "\n\n";

$message .= "👥 Tổng khách hàng: <b>" . intval($total_customers['total']) . "</b>\n\n";
$message .= "━━━━━━━━━━━━━━━━━━━━\n";
$message .= "🕐 " . date('H:i:s d/m/Y');

$bot = new TelegramBot();
$result = $bot->sendMessage($message);

if ($result) {
    echo "[" . date('Y-m-d H:i:s') . "] Daily report sent successfully.\n";
} else {
    echo "[" . date('Y-m-d H:i:s') . "] Failed to send daily report.\n";
}
