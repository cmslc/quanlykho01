<?php
/**
 * Telegram Bot Notification
 * Gửi thông báo qua Telegram Bot API
 */

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

class TelegramBot
{
    private $botToken;
    private $chatId;
    private $apiUrl = 'https://api.telegram.org/bot';

    public function __construct()
    {
        // Priority: DB settings > .env
        global $ToryHub;
        $enabled = '1';
        if ($ToryHub) {
            $this->botToken = $ToryHub->site('telegram_bot_token') ?: ($_ENV['TELEGRAM_BOT_TOKEN'] ?? '');
            $this->chatId = $ToryHub->site('telegram_chat_id') ?: ($_ENV['TELEGRAM_CHAT_ID'] ?? '');
            $enabled = $ToryHub->site('telegram_enabled') ?: '1';
        } else {
            $this->botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
            $this->chatId = $_ENV['TELEGRAM_CHAT_ID'] ?? '';
        }
        // Disable if telegram_enabled is explicitly set to 0
        if ($enabled === '0') {
            $this->botToken = '';
            $this->chatId = '';
        }
    }

    /**
     * Check if Telegram is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->botToken) && !empty($this->chatId);
    }

    /**
     * Send text message
     */
    public function sendMessage(string $text, string $parseMode = 'HTML'): bool
    {
        if (!$this->isConfigured()) return false;

        $params = [
            'chat_id' => $this->chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => true
        ];

        return $this->request('sendMessage', $params);
    }

    /**
     * Send to specific chat
     */
    public function sendTo(string $chatId, string $text, string $parseMode = 'HTML'): bool
    {
        if (empty($this->botToken)) return false;

        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => true
        ];

        return $this->request('sendMessage', $params);
    }

    /**
     * Notify new order
     */
    public function notifyNewOrder(array $order, string $customerName): bool
    {
        $text = "🛒 <b>Đơn hàng mới</b>\n\n";
        $text .= "📋 Mã đơn: <code>{$order['order_code']}</code>\n";
        $text .= "👤 Khách hàng: {$customerName}\n";
        $text .= "📦 Sản phẩm: {$order['product_name']}\n";
        $text .= "🔢 Số lượng: {$order['quantity']}\n";
        $text .= "💰 Tổng tiền: ¥" . number_format($order['total_cny'], 2) . "\n";
        $text .= "💵 VND: " . number_format($order['grand_total']) . "đ\n";
        $text .= "🏷 Nền tảng: " . ucfirst($order['platform']) . "\n";
        $text .= "📅 Ngày: " . date('d/m/Y H:i');

        return $this->sendMessage($text);
    }

    /**
     * Notify order status change
     */
    public function notifyStatusChange(array $order, string $oldStatus, string $newStatus): bool
    {
        $statusEmoji = [
            'cn_warehouse' => '🏭', 'shipping' => '✈️', 'vn_warehouse' => '🏬',
            'delivered' => '✅', 'cancelled' => '❌'
        ];

        $statusLabel = [
            'cn_warehouse' => 'Tại kho Trung Quốc', 'shipping' => 'Đang vận chuyển',
            'vn_warehouse' => 'Tại kho Việt Nam', 'delivered' => 'Đã giao', 'cancelled' => 'Đã hủy'
        ];

        $emoji = $statusEmoji[$newStatus] ?? '📌';
        $text = "{$emoji} <b>Cập nhật đơn hàng</b>\n\n";
        $text .= "📋 Mã đơn: <code>{$order['order_code']}</code>\n";
        $text .= "📦 Sản phẩm: {$order['product_name']}\n";
        $text .= "🔄 Trạng thái: {$statusLabel[$oldStatus]} → <b>{$statusLabel[$newStatus]}</b>\n";
        $text .= "📅 " . date('d/m/Y H:i');

        return $this->sendMessage($text);
    }

    /**
     * Notify new deposit
     */
    public function notifyDeposit(string $customerCode, string $customerName, float $amount, float $balanceAfter): bool
    {
        $text = "💰 <b>Nạp tiền</b>\n\n";
        $text .= "👤 Khách hàng: {$customerCode} - {$customerName}\n";
        $text .= "💵 Số tiền: " . number_format($amount) . "đ\n";
        $text .= "💳 Số dư mới: " . number_format($balanceAfter) . "đ\n";
        $text .= "📅 " . date('d/m/Y H:i');

        return $this->sendMessage($text);
    }

    /**
     * Notify customer: goods arrived at VN warehouse
     */
    public function notifyCustomerArrived(string $chatId, array $order): bool
    {
        $text = "📦 Hàng đã về kho Việt Nam\n\n";
        $text .= "Mã đơn: " . ($order['order_code'] ?? '') . "\n";
        if (!empty($order['product_name'])) {
            $text .= "Sản phẩm: " . $order['product_name'] . "\n";
        }
        $text .= "\nVui lòng liên hệ để nhận hàng.";

        return $this->sendTo($chatId, $text);
    }

    /**
     * Notify customer: ready for delivery
     */
    public function notifyCustomerDelivery(string $chatId, array $order): bool
    {
        $text = "🚛 Đơn hàng sẵn sàng giao\n\n";
        $text .= "Mã đơn: " . ($order['order_code'] ?? '') . "\n";
        if (!empty($order['product_name'])) {
            $text .= "Sản phẩm: " . $order['product_name'] . "\n";
        }
        $text .= "\nVui lòng xác nhận địa chỉ nhận hàng.";

        return $this->sendTo($chatId, $text);
    }

    /**
     * Daily summary
     */
    public function notifyDailySummary(int $newOrders, float $revenue, int $delivered, int $pending): bool
    {
        $text = "📊 <b>Báo cáo ngày " . date('d/m/Y') . "</b>\n\n";
        $text .= "🛒 Đơn mới: {$newOrders}\n";
        $text .= "💰 Doanh thu: " . number_format($revenue) . "đ\n";
        $text .= "✅ Đã giao: {$delivered}\n";
        $text .= "🏭 Tại kho Trung Quốc: {$pending}";

        return $this->sendMessage($text);
    }

    /**
     * Make API request
     */
    private function request(string $method, array $params): bool
    {
        $url = $this->apiUrl . $this->botToken . '/' . $method;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }
}

/**
 * Helper function to send Telegram notification
 */
function telegram_notify(string $text): bool
{
    $bot = new TelegramBot();
    return $bot->sendMessage($text);
}
