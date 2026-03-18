<?php

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once(__DIR__.'/../vendor/autoload.php');

class EmailService
{
    private $mail;
    private $enabled = false;

    public function __construct()
    {
        global $ToryHub;

        // Check if email is enabled in settings
        $this->enabled = ($ToryHub->site('email_enabled') == '1');

        $host = $ToryHub->site('mail_host') ?: ($_ENV['MAIL_HOST'] ?? '');
        $username = $ToryHub->site('mail_username') ?: ($_ENV['MAIL_USERNAME'] ?? '');
        $password = $ToryHub->site('mail_password') ?: ($_ENV['MAIL_PASSWORD'] ?? '');
        $port = intval($ToryHub->site('mail_port') ?: ($_ENV['MAIL_PORT'] ?? 587));
        $from_name = $ToryHub->site('site_name') ?: ($_ENV['MAIL_FROM_NAME'] ?? 'ToryHub');
        $encryption = $ToryHub->site('mail_encryption') ?: ($_ENV['MAIL_ENCRYPTION'] ?? 'tls');

        if (empty($host) || empty($username) || empty($password)) {
            $this->enabled = false;
            return;
        }

        $this->mail = new PHPMailer(true);
        $this->mail->isSMTP();
        $this->mail->Host = $host;
        $this->mail->SMTPAuth = true;
        $this->mail->Username = $username;
        $this->mail->Password = $password;
        $this->mail->SMTPSecure = $encryption;
        $this->mail->Port = $port;
        $this->mail->setFrom($username, $from_name);
        $this->mail->isHTML(true);
        $this->mail->CharSet = 'UTF-8';
    }

    public function isEnabled()
    {
        return $this->enabled && $this->mail !== null;
    }

    public function send($to, $subject, $body)
    {
        if (!$this->isEnabled()) return false;

        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($to);
            $this->mail->Subject = $subject;
            $this->mail->Body = $this->wrapTemplate($subject, $body);
            $this->mail->AltBody = strip_tags($body);
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log('EmailService error: ' . $e->getMessage());
            return false;
        }
    }

    private function wrapTemplate($title, $content)
    {
        global $ToryHub;
        $siteName = htmlspecialchars($ToryHub->site('site_name') ?: 'ToryHub');

        return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
    <tr><td style="background:#405189;color:#fff;padding:20px 30px;font-size:18px;font-weight:bold;">' . $siteName . '</td></tr>
    <tr><td style="padding:30px;">' . $content . '</td></tr>
    <tr><td style="background:#f8f9fa;padding:15px 30px;color:#888;font-size:12px;text-align:center;">&copy; ' . date('Y') . ' ' . $siteName . '. All rights reserved.</td></tr>
</table>
</td></tr>
</table>
</body>
</html>';
    }

    // ===== NOTIFICATION TEMPLATES =====

    public function notifyNewOrder($order, $customer_email)
    {
        if (empty($customer_email)) return false;

        $subject = __('Đơn hàng mới') . ': ' . $order['order_code'];
        $body = '<h2 style="color:#405189;margin:0 0 15px;">' . __('Đơn hàng mới đã được tạo') . '</h2>
        <table style="width:100%;border-collapse:collapse;">
            <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666;">' . __('Mã đơn hàng') . '</td>
                <td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;">' . htmlspecialchars($order['order_code']) . '</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666;">' . __('Sản phẩm') . '</td>
                <td style="padding:8px;border-bottom:1px solid #eee;">' . htmlspecialchars($order['product_name']) . '</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666;">' . __('Số lượng') . '</td>
                <td style="padding:8px;border-bottom:1px solid #eee;">' . $order['quantity'] . '</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666;">' . __('Tổng tiền') . '</td>
                <td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;color:#e74c3c;">' . format_vnd($order['grand_total']) . '</td></tr>
        </table>
        <p style="margin-top:20px;color:#666;">' . __('Cảm ơn bạn đã sử dụng dịch vụ của chúng tôi.') . '</p>';

        return $this->send($customer_email, $subject, $body);
    }

    public function notifyStatusChange($order, $customer_email, $old_status, $new_status)
    {
        if (empty($customer_email)) return false;

        $status_labels = [
            'cn_warehouse' => 'Đã về kho Trung Quốc',
            'shipping' => 'Đang vận chuyển', 'vn_warehouse' => 'Đã về kho Việt Nam',
            'delivered' => 'Đã giao hàng', 'cancelled' => 'Đã hủy'
        ];

        $subject = __('Cập nhật đơn hàng') . ': ' . $order['order_code'];
        $new_label = __($status_labels[$new_status] ?? $new_status);

        $body = '<h2 style="color:#405189;margin:0 0 15px;">' . __('Cập nhật trạng thái đơn hàng') . '</h2>
        <table style="width:100%;border-collapse:collapse;">
            <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666;">' . __('Mã đơn hàng') . '</td>
                <td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;">' . htmlspecialchars($order['order_code']) . '</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666;">' . __('Sản phẩm') . '</td>
                <td style="padding:8px;border-bottom:1px solid #eee;">' . htmlspecialchars($order['product_name']) . '</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666;">' . __('Trạng thái mới') . '</td>
                <td style="padding:8px;border-bottom:1px solid #eee;"><span style="background:#405189;color:#fff;padding:4px 12px;border-radius:4px;font-size:13px;">' . $new_label . '</span></td></tr>
        </table>';

        return $this->send($customer_email, $subject, $body);
    }

    public function notifyArrivedVN($order, $customer_email)
    {
        if (empty($customer_email)) return false;

        $subject = __('Hàng đã về kho Việt Nam') . ': ' . $order['order_code'];
        $body = '<h2 style="color:#27ae60;margin:0 0 15px;">' . __('Hàng đã về kho Việt Nam') . '</h2>
        <p style="font-size:15px;">' . __('Đơn hàng của bạn đã về đến kho Việt Nam. Vui lòng liên hệ để nhận hàng.') . '</p>
        <table style="width:100%;border-collapse:collapse;">
            <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666;">' . __('Mã đơn hàng') . '</td>
                <td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;">' . htmlspecialchars($order['order_code']) . '</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666;">' . __('Sản phẩm') . '</td>
                <td style="padding:8px;border-bottom:1px solid #eee;">' . htmlspecialchars($order['product_name'] ?? '') . '</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666;">' . __('Trạng thái') . '</td>
                <td style="padding:8px;border-bottom:1px solid #eee;"><span style="background:#27ae60;color:#fff;padding:4px 12px;border-radius:4px;font-size:13px;">' . __('Đã về kho Việt Nam') . '</span></td></tr>
        </table>
        <p style="margin-top:20px;color:#666;">' . __('Cảm ơn bạn đã sử dụng dịch vụ của chúng tôi.') . '</p>';

        return $this->send($customer_email, $subject, $body);
    }

    public function notifyReadyDelivery($order, $customer_email)
    {
        if (empty($customer_email)) return false;

        $subject = __('Đơn hàng sẵn sàng giao') . ': ' . $order['order_code'];
        $body = '<h2 style="color:#405189;margin:0 0 15px;">' . __('Đơn hàng sẵn sàng giao') . '</h2>
        <p style="font-size:15px;">' . __('Đơn hàng của bạn đã sẵn sàng để giao. Vui lòng xác nhận địa chỉ nhận hàng.') . '</p>
        <table style="width:100%;border-collapse:collapse;">
            <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666;">' . __('Mã đơn hàng') . '</td>
                <td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;">' . htmlspecialchars($order['order_code']) . '</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666;">' . __('Sản phẩm') . '</td>
                <td style="padding:8px;border-bottom:1px solid #eee;">' . htmlspecialchars($order['product_name'] ?? '') . '</td></tr>
        </table>
        <p style="margin-top:20px;color:#666;">' . __('Cảm ơn bạn đã sử dụng dịch vụ của chúng tôi.') . '</p>';

        return $this->send($customer_email, $subject, $body);
    }

    public function notifyDeposit($customer_email, $amount, $balance_after, $description = '')
    {
        if (empty($customer_email)) return false;

        $subject = __('Nạp tiền thành công');
        $body = '<h2 style="color:#27ae60;margin:0 0 15px;">' . __('Nạp tiền thành công') . '</h2>
        <table style="width:100%;border-collapse:collapse;">
            <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666;">' . __('Số tiền') . '</td>
                <td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;color:#27ae60;">+' . format_vnd($amount) . '</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666;">' . __('Số dư hiện tại') . '</td>
                <td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;">' . format_vnd($balance_after) . '</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666;">' . __('Mô tả') . '</td>
                <td style="padding:8px;border-bottom:1px solid #eee;">' . htmlspecialchars($description) . '</td></tr>
        </table>';

        return $this->send($customer_email, $subject, $body);
    }
}

function email_notify($method, ...$args)
{
    try {
        $email = new EmailService();
        if (!$email->isEnabled()) return false;
        return call_user_func_array([$email, $method], $args);
    } catch (Exception $e) {
        error_log('email_notify error: ' . $e->getMessage());
        return false;
    }
}
