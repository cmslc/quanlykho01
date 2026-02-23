<?php
define('IN_SITE', true);
require_once(__DIR__.'/../../libs/db.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../libs/lang.php');
require_once(__DIR__.'/../../libs/helper.php');
require_once(__DIR__.'/../../libs/session.php');
require_once(__DIR__.'/../../libs/role.php');
require_once(__DIR__.'/../../libs/csrf.php');
require_once(__DIR__.'/../../models/is_admin.php');

header('Content-Type: application/json; charset=utf-8');

$csrf = new Csrf();
if (!$csrf->validate()) {
    echo json_encode(['status' => 'error', 'msg' => 'Token không hợp lệ']);
    exit;
}

$request = input_post('request_name');

// ======== UPDATE SETTINGS ========
if ($request === 'update_settings') {
    // Each form only sends its own fields — only update what was actually submitted
    // Checkbox → if its sibling field is present but checkbox is missing, set to '0'
    $checkbox_groups = [
        'telegram_enabled' => 'telegram_bot_token',
        'email_enabled'    => 'mail_host',
    ];

    $fields = [
        'site_name', 'exchange_rate_cny_vnd', 'service_fee_percent',
        'shipping_rate_road', 'shipping_rate_sea', 'shipping_rate_air', 'site_status',
        'shipping_road_easy_per_kg', 'shipping_road_easy_per_cbm',
        'shipping_road_difficult_per_kg', 'shipping_road_difficult_per_cbm',
        'telegram_bot_token', 'telegram_chat_id', 'telegram_enabled',
        'mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_encryption', 'email_enabled'
    ];

    foreach ($fields as $field) {
        // input_post() returns false when field is not in POST
        if (!isset($_POST[$field])) {
            // Handle checkbox: set '0' only if its form was actually submitted
            if (isset($checkbox_groups[$field])) {
                $siblingField = $checkbox_groups[$field];
                if (isset($_POST[$siblingField])) {
                    $value = '0';
                } else {
                    continue; // Form not submitted, skip
                }
            } else {
                continue; // Field not in this form, skip
            }
        } else {
            $value = trim($_POST[$field]);
        }

        $existing = $ToryHub->get_row_safe("SELECT `id` FROM `settings` WHERE `name` = ?", [$field]);
        if ($existing) {
            $ToryHub->update_safe("settings", ['value' => $value], "id = ?", [$existing['id']]);
        } else {
            $ToryHub->insert_safe("settings", ['name' => $field, 'value' => $value]);
        }
    }

    add_log('update_settings', 'Cập nhật cài đặt hệ thống');
    echo json_encode(['status' => 'success', 'msg' => __('Cập nhật thành công')]);
    exit;
}

// ======== TEST TELEGRAM ========
if ($request === 'test_telegram') {
    require_once(__DIR__.'/../../libs/telegram.php');

    // Use settings from DB if available, fallback to .env
    $botToken = $ToryHub->site('telegram_bot_token') ?: ($_ENV['TELEGRAM_BOT_TOKEN'] ?? '');
    $chatId = $ToryHub->site('telegram_chat_id') ?: ($_ENV['TELEGRAM_CHAT_ID'] ?? '');

    if (empty($botToken) || empty($chatId)) {
        echo json_encode(['status' => 'error', 'msg' => __('Chưa cấu hình Bot Token hoặc Chat ID')]);
        exit;
    }

    $bot = new TelegramBot();
    $result = $bot->sendMessage("✅ <b>ToryHub Test</b>\n\nKết nối Telegram thành công!\n📅 " . date('d/m/Y H:i:s'));

    if ($result) {
        echo json_encode(['status' => 'success', 'msg' => __('Gửi thành công')]);
    } else {
        echo json_encode(['status' => 'error', 'msg' => __('Gửi thất bại. Kiểm tra Bot Token và Chat ID.')]);
    }
    exit;
}

// ======== TEST EMAIL ========
if ($request === 'test_email') {
    require_once(__DIR__.'/../../libs/email.php');

    $test_email = trim(input_post('test_email'));
    if (empty($test_email) || !check_email($test_email)) {
        echo json_encode(['status' => 'error', 'msg' => __('Email không hợp lệ')]);
        exit;
    }

    $email = new EmailService();
    $body = '<h2 style="color:#405189;">Test Email</h2>
             <p>' . __('Kết nối email thành công!') . '</p>
             <p style="color:#888;">' . date('d/m/Y H:i:s') . '</p>';

    $result = $email->send($test_email, 'ToryHub - Test Email', $body);

    if ($result) {
        echo json_encode(['status' => 'success', 'msg' => __('Gửi email thành công')]);
    } else {
        echo json_encode(['status' => 'error', 'msg' => __('Gửi thất bại. Kiểm tra cấu hình SMTP.')]);
    }
    exit;
}

// ======== UPLOAD LOGO ========
if ($request === 'upload_logo') {
    $brandName = trim(input_post('site_brand_name') ?? 'ToryHub');

    // Save brand name
    $existing = $ToryHub->get_row_safe("SELECT `id` FROM `settings` WHERE `name` = 'site_brand_name'", []);
    if ($existing) {
        $ToryHub->update_safe("settings", ['value' => $brandName], "id = ?", [$existing['id']]);
    } else {
        $ToryHub->insert_safe("settings", ['name' => 'site_brand_name', 'value' => $brandName]);
    }

    // Handle logo upload if file provided
    if (!empty($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        // Delete old logo
        $oldLogo = $ToryHub->site('site_logo');
        if ($oldLogo) {
            delete_uploaded_file($oldLogo);
        }

        $result = upload_image($_FILES['logo'], 'logo');
        if ($result['status'] === 'success') {
            $existing = $ToryHub->get_row_safe("SELECT `id` FROM `settings` WHERE `name` = 'site_logo'", []);
            if ($existing) {
                $ToryHub->update_safe("settings", ['value' => $result['path']], "id = ?", [$existing['id']]);
            } else {
                $ToryHub->insert_safe("settings", ['name' => 'site_logo', 'value' => $result['path']]);
            }
        } else {
            echo json_encode(['status' => 'error', 'msg' => $result['msg']]);
            exit;
        }
    }

    add_log('update_brand', 'Cập nhật thương hiệu');
    echo json_encode(['status' => 'success', 'msg' => __('Cập nhật thành công')]);
    exit;
}

// ======== DELETE LOGO ========
if ($request === 'delete_logo') {
    $oldLogo = $ToryHub->site('site_logo');
    if ($oldLogo) {
        delete_uploaded_file($oldLogo);
        $ToryHub->update_safe("settings", ['value' => ''], "name = ?", ['site_logo']);
    }

    add_log('delete_logo', 'Xóa logo');
    echo json_encode(['status' => 'success', 'msg' => __('Đã xóa logo')]);
    exit;
}

echo json_encode(['status' => 'error', 'msg' => 'Invalid request']);
