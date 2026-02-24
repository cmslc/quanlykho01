<?php
define('IN_SITE', true);
require_once(__DIR__.'/../../libs/db.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../libs/lang.php');
require_once(__DIR__.'/../../libs/helper.php');
require_once(__DIR__.'/../../libs/session.php');
require_once(__DIR__.'/../../libs/role.php');
require_once(__DIR__.'/../../libs/csrf.php');
require_once(__DIR__.'/../../libs/telegram.php');
require_once(__DIR__.'/../../models/is_staffvn.php');

header('Content-Type: application/json; charset=utf-8');

$csrf = new Csrf();
if (!$csrf->validate()) {
    echo json_encode(['status' => 'error', 'msg' => __('Token không hợp lệ')]);
    exit;
}

$request = input_post('request_name');

// ===== START CHECK =====
if ($request === 'start_check') {
    $checkCode = 'KK-' . date('YmdHis');

    // Count expected packages at VN warehouse
    $expected = $ToryHub->num_rows_safe("SELECT * FROM `packages` WHERE `status` = 'vn_warehouse'", []) ?: 0;

    $ToryHub->insert_safe('inventory_checks', [
        'check_code'     => $checkCode,
        'staff_id'       => $getUser['id'],
        'status'         => 'in_progress',
        'total_expected'  => $expected,
        'create_date'    => gettime()
    ]);

    $checkId = $ToryHub->insert_id();
    add_log($getUser['id'], 'START_INVENTORY', 'Bắt đầu kiểm kê ' . $checkCode . ' | Expected: ' . $expected);

    $newCsrf = new Csrf();
    echo json_encode([
        'status'     => 'success',
        'check_id'   => $checkId,
        'check_code' => $checkCode,
        'expected'   => $expected,
        'csrf_token' => $newCsrf->get_token_value()
    ]);
    exit;
}

// ===== SCAN ITEM =====
if ($request === 'scan_item') {
    $check_id = intval(input_post('check_id'));
    $barcode = trim(input_post('barcode'));

    $check = $ToryHub->get_row_safe("SELECT * FROM `inventory_checks` WHERE `id` = ? AND `status` = 'in_progress'", [$check_id]);
    if (!$check) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Phiên kiểm kê không hợp lệ'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    if (empty($barcode)) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập mã'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    // Check duplicate scan in this session
    $alreadyScanned = $ToryHub->get_row_safe("SELECT * FROM `inventory_check_items` WHERE `check_id` = ? AND `barcode_scanned` = ?", [$check_id, $barcode]);
    if ($alreadyScanned) {
        $newCsrf = new Csrf();
        echo json_encode([
            'status'     => 'error',
            'error_type' => 'duplicate',
            'msg'        => __('Mã này đã được quét trong phiên kiểm kê'),
            'csrf_token' => $newCsrf->get_token_value()
        ]);
        exit;
    }

    // Search package
    $package = $ToryHub->get_row_safe("SELECT * FROM `packages` WHERE `package_code` = ? OR `tracking_intl` = ? OR `tracking_vn` = ? LIMIT 1", [$barcode, $barcode, $barcode]);

    if ($package) {
        if ($package['status'] === 'vn_warehouse') {
            $result = 'matched';
            $msg = __('Khớp') . ' - ' . $package['package_code'];
        } else {
            $result = 'extra';
            $msg = __('Kiện tồn tại nhưng trạng thái khác') . ': ' . strip_tags(display_package_status($package['status'])) . ' - ' . $package['package_code'];
        }

        $ToryHub->insert_safe('inventory_check_items', [
            'check_id'        => $check_id,
            'package_id'      => $package['id'],
            'barcode_scanned' => $barcode,
            'result'          => $result,
            'scan_date'       => gettime()
        ]);
    } else {
        $result = 'not_found';
        $msg = __('Không tìm thấy kiện hàng');

        $ToryHub->insert_safe('inventory_check_items', [
            'check_id'        => $check_id,
            'barcode_scanned' => $barcode,
            'result'          => $result,
            'scan_date'       => gettime()
        ]);
    }

    // Update counts
    $counts = $ToryHub->get_row_safe("
        SELECT COUNT(*) as total,
               SUM(CASE WHEN result = 'matched' THEN 1 ELSE 0 END) as matched,
               SUM(CASE WHEN result = 'extra' THEN 1 ELSE 0 END) as extra,
               SUM(CASE WHEN result = 'not_found' THEN 1 ELSE 0 END) as not_found
        FROM `inventory_check_items` WHERE `check_id` = ?
    ", [$check_id]);

    $ToryHub->update_safe('inventory_checks', [
        'total_scanned' => $counts['total'],
        'total_matched' => $counts['matched'],
        'total_extra'   => $counts['extra']
    ], "`id` = ?", [$check_id]);

    $newCsrf = new Csrf();
    echo json_encode([
        'status'      => $result === 'matched' ? 'success' : 'error',
        'result_type' => $result,
        'msg'         => $msg,
        'counts'      => [
            'scanned'  => intval($counts['total']),
            'matched'  => intval($counts['matched']),
            'extra'    => intval($counts['extra']),
            'not_found' => intval($counts['not_found'])
        ],
        'csrf_token'  => $newCsrf->get_token_value()
    ]);
    exit;
}

// ===== COMPLETE CHECK =====
if ($request === 'complete_check') {
    $check_id = intval(input_post('check_id'));

    $check = $ToryHub->get_row_safe("SELECT * FROM `inventory_checks` WHERE `id` = ? AND `status` = 'in_progress'", [$check_id]);
    if (!$check) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Phiên kiểm kê không hợp lệ'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    // Find missing packages (vn_warehouse but not scanned)
    $scannedPkgIds = $ToryHub->get_list_safe("SELECT `package_id` FROM `inventory_check_items` WHERE `check_id` = ? AND `package_id` IS NOT NULL", [$check_id]);
    $scannedIds = array_column($scannedPkgIds, 'package_id');

    if (!empty($scannedIds)) {
        $placeholders = implode(',', array_fill(0, count($scannedIds), '?'));
        $missing = $ToryHub->get_list_safe("SELECT `id`, `package_code`, `tracking_intl` FROM `packages` WHERE `status` = 'vn_warehouse' AND `id` NOT IN ({$placeholders})", $scannedIds);
    } else {
        $missing = $ToryHub->get_list_safe("SELECT `id`, `package_code`, `tracking_intl` FROM `packages` WHERE `status` = 'vn_warehouse'", []);
    }

    $totalMissing = count($missing);

    // Update check
    $ToryHub->update_safe('inventory_checks', [
        'status'         => 'completed',
        'total_missing'  => $totalMissing,
        'completed_date' => gettime()
    ], "`id` = ?", [$check_id]);

    // Build missing list for response
    $missingList = [];
    foreach ($missing as $m) {
        $missingList[] = [
            'package_code' => $m['package_code'],
            'tracking'     => $m['tracking_intl'] ?: '-'
        ];
    }

    $counts = $ToryHub->get_row_safe("
        SELECT COUNT(*) as total,
               SUM(CASE WHEN result = 'matched' THEN 1 ELSE 0 END) as matched,
               SUM(CASE WHEN result = 'extra' THEN 1 ELSE 0 END) as extra,
               SUM(CASE WHEN result = 'not_found' THEN 1 ELSE 0 END) as not_found
        FROM `inventory_check_items` WHERE `check_id` = ?
    ", [$check_id]);

    add_log($getUser['id'], 'COMPLETE_INVENTORY', 'Hoàn thành kiểm kê ' . $check['check_code'] .
        ' | Matched: ' . $counts['matched'] . ' | Missing: ' . $totalMissing . ' | Extra: ' . $counts['extra']);

    // Telegram
    try {
        $telegram = new TelegramBot();
        $staffName = $getUser['fullname'] ?? $getUser['username'];
        $message = "<b>📋 " . __('Hoàn thành kiểm kê kho') . "</b>\n\n";
        $message .= "<b>" . __('Mã phiên') . ":</b> " . $check['check_code'] . "\n";
        $message .= "<b>" . __('Nhân viên') . ":</b> " . htmlspecialchars($staffName) . "\n";
        $message .= "<b>" . __('Thời gian') . ":</b> " . date('d/m/Y H:i:s') . "\n\n";
        $message .= "<b>" . __('Dự kiến') . ":</b> " . $check['total_expected'] . "\n";
        $message .= "✅ " . __('Khớp') . ": " . $counts['matched'] . "\n";
        $message .= "❌ " . __('Thiếu') . ": " . $totalMissing . "\n";
        $message .= "⚠️ " . __('Thừa/Lạ') . ": " . $counts['extra'] . "\n";
        $message .= "🔍 " . __('Không tìm thấy') . ": " . $counts['not_found'] . "\n";

        if ($totalMissing > 0 && $totalMissing <= 10) {
            $message .= "\n<b>" . __('Kiện thiếu') . ":</b>\n";
            foreach ($missingList as $m) {
                $message .= "- " . $m['package_code'] . " (" . $m['tracking'] . ")\n";
            }
        }
        $telegram->sendMessage($message);
    } catch (Exception $e) {}

    $newCsrf = new Csrf();
    echo json_encode([
        'status'       => 'success',
        'msg'          => __('Đã hoàn thành kiểm kê'),
        'missing_count' => $totalMissing,
        'missing_list' => $missingList,
        'counts'       => [
            'expected'  => intval($check['total_expected']),
            'scanned'   => intval($counts['total']),
            'matched'   => intval($counts['matched']),
            'extra'     => intval($counts['extra']),
            'not_found' => intval($counts['not_found']),
            'missing'   => $totalMissing
        ],
        'csrf_token'   => $newCsrf->get_token_value()
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'msg' => __('Yêu cầu không hợp lệ')]);
