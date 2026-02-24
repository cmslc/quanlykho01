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
$user_id = $_SESSION['user']['id'];

// ===== SCAN: quét mã kiện/bao khi kiểm đếm =====
if ($request === 'scan') {
    $shipment_id = intval(input_post('shipment_id'));
    $barcode = trim(input_post('barcode'));

    if (!$shipment_id || !$barcode) {
        echo json_encode(['status' => 'error', 'msg' => __('Thiếu thông tin')]);
        exit;
    }

    $shipment = $ToryHub->get_row_safe("SELECT * FROM `shipments` WHERE `id` = ?", [$shipment_id]);
    if (!$shipment) {
        echo json_encode(['status' => 'error', 'msg' => __('Chuyến xe không tồn tại')]);
        exit;
    }

    // Find package by barcode (package_code, tracking_code, bag_code)
    $pkg = $ToryHub->get_row_safe(
        "SELECT p.id, p.package_code FROM `packages` p WHERE p.package_code = ? OR p.tracking_code = ?",
        [$barcode, $barcode]
    );

    // Also check if it's a bag_code
    $bag = $ToryHub->get_row_safe("SELECT * FROM `bags` WHERE `bag_code` = ?", [$barcode]);

    if ($bag) {
        // It's a bag code - find all packages in this bag that are in this shipment
        $bagPkgs = $ToryHub->get_list_safe(
            "SELECT sp.id as sp_id, sp.package_id, sp.check_status, p.package_code
             FROM `shipment_packages` sp
             JOIN `packages` p ON sp.package_id = p.id
             JOIN `bag_packages` bp ON p.id = bp.package_id
             WHERE sp.shipment_id = ? AND bp.bag_id = ?",
            [$shipment_id, $bag['id']]
        );

        if (empty($bagPkgs)) {
            // Bag not in this shipment → extra
            $exists = $ToryHub->get_row_safe(
                "SELECT id FROM `shipment_check_extras` WHERE `shipment_id` = ? AND `barcode` = ?",
                [$shipment_id, $barcode]
            );
            if (!$exists) {
                $ToryHub->insert_safe('shipment_check_extras', [
                    'shipment_id' => $shipment_id,
                    'barcode' => $barcode,
                    'scanned_by' => $user_id,
                    'scanned_at' => gettime()
                ]);
            }
            echo json_encode([
                'status' => 'extra',
                'msg' => __('Mã bao') . ' ' . $barcode . ' ' . __('không có trong chuyến này'),
                'barcode' => $barcode
            ]);
            exit;
        }

        // Mark all packages in this bag as matched
        $matched = 0;
        $already = 0;
        foreach ($bagPkgs as $bp) {
            if ($bp['check_status'] === 'matched') {
                $already++;
            } else {
                $ToryHub->update_safe('shipment_packages', ['check_status' => 'matched'], "`id` = ?", [$bp['sp_id']]);
                $matched++;
            }
        }

        echo json_encode([
            'status' => 'matched',
            'type' => 'bag',
            'msg' => __('Mã bao') . ' ' . $barcode . ': ' . $matched . ' ' . __('kiện đã xác nhận'),
            'barcode' => $barcode,
            'bag_code' => $bag['bag_code'],
            'matched_count' => $matched,
            'already_count' => $already,
            'package_codes' => array_column($bagPkgs, 'package_code')
        ]);
        exit;
    }

    if ($pkg) {
        // Check if package is in this shipment
        $sp = $ToryHub->get_row_safe(
            "SELECT sp.id, sp.check_status FROM `shipment_packages` sp WHERE sp.shipment_id = ? AND sp.package_id = ?",
            [$shipment_id, $pkg['id']]
        );

        if ($sp) {
            if ($sp['check_status'] === 'matched') {
                echo json_encode([
                    'status' => 'duplicate',
                    'msg' => __('Kiện') . ' ' . $pkg['package_code'] . ' ' . __('đã kiểm rồi'),
                    'barcode' => $barcode,
                    'package_code' => $pkg['package_code']
                ]);
                exit;
            }

            $ToryHub->update_safe('shipment_packages', ['check_status' => 'matched'], "`id` = ?", [$sp['id']]);

            echo json_encode([
                'status' => 'matched',
                'type' => 'package',
                'msg' => __('Kiện') . ' ' . $pkg['package_code'] . ' ✓',
                'barcode' => $barcode,
                'package_code' => $pkg['package_code']
            ]);
            exit;
        }

        // Package exists but not in this shipment → extra
        $exists = $ToryHub->get_row_safe(
            "SELECT id FROM `shipment_check_extras` WHERE `shipment_id` = ? AND `barcode` = ?",
            [$shipment_id, $barcode]
        );
        if (!$exists) {
            $ToryHub->insert_safe('shipment_check_extras', [
                'shipment_id' => $shipment_id,
                'package_id' => $pkg['id'],
                'barcode' => $barcode,
                'scanned_by' => $user_id,
                'scanned_at' => gettime()
            ]);
        }

        echo json_encode([
            'status' => 'extra',
            'msg' => __('Kiện') . ' ' . $pkg['package_code'] . ' ' . __('không có trong chuyến này'),
            'barcode' => $barcode,
            'package_code' => $pkg['package_code']
        ]);
        exit;
    }

    // Not found at all
    $exists = $ToryHub->get_row_safe(
        "SELECT id FROM `shipment_check_extras` WHERE `shipment_id` = ? AND `barcode` = ?",
        [$shipment_id, $barcode]
    );
    if (!$exists) {
        $ToryHub->insert_safe('shipment_check_extras', [
            'shipment_id' => $shipment_id,
            'barcode' => $barcode,
            'scanned_by' => $user_id,
            'scanned_at' => gettime()
        ]);
    }

    echo json_encode([
        'status' => 'not_found',
        'msg' => __('Không tìm thấy mã') . ' ' . $barcode,
        'barcode' => $barcode
    ]);
    exit;
}

// ===== COMPLETE: hoàn thành kiểm đếm =====
if ($request === 'complete') {
    $shipment_id = intval(input_post('shipment_id'));
    $note = trim(input_post('note'));

    $shipment = $ToryHub->get_row_safe("SELECT * FROM `shipments` WHERE `id` = ?", [$shipment_id]);
    if (!$shipment) {
        echo json_encode(['status' => 'error', 'msg' => __('Chuyến xe không tồn tại')]);
        exit;
    }

    // Mark unchecked as missing
    $ToryHub->update_safe('shipment_packages',
        ['check_status' => 'missing'],
        "`shipment_id` = ? AND `check_status` = 'unchecked'",
        [$shipment_id]
    );

    // Count results
    $matched = $ToryHub->num_rows_safe(
        "SELECT id FROM `shipment_packages` WHERE `shipment_id` = ? AND `check_status` = 'matched'", [$shipment_id]
    );
    $missing = $ToryHub->num_rows_safe(
        "SELECT id FROM `shipment_packages` WHERE `shipment_id` = ? AND `check_status` = 'missing'", [$shipment_id]
    );
    $extras = $ToryHub->num_rows_safe(
        "SELECT id FROM `shipment_check_extras` WHERE `shipment_id` = ?", [$shipment_id]
    );

    // Get missing package details
    $missingPkgs = $ToryHub->get_list_safe(
        "SELECT p.package_code, COALESCE(b.bag_code, o.product_code, o.order_code, '') as ma_hang
         FROM `shipment_packages` sp
         JOIN `packages` p ON sp.package_id = p.id
         LEFT JOIN `bag_packages` bp ON p.id = bp.package_id
         LEFT JOIN `bags` b ON bp.bag_id = b.id
         LEFT JOIN `package_orders` po ON p.id = po.package_id
         LEFT JOIN `orders` o ON po.order_id = o.id
         WHERE sp.shipment_id = ? AND sp.check_status = 'missing'
         GROUP BY p.id", [$shipment_id]
    );

    // Get extra items
    $extraItems = $ToryHub->get_list_safe(
        "SELECT * FROM `shipment_check_extras` WHERE `shipment_id` = ?", [$shipment_id]
    );

    // Update shipment
    $ToryHub->update_safe('shipments', [
        'checked_by' => $user_id,
        'checked_date' => gettime(),
        'check_matched' => $matched,
        'check_missing' => $missing,
        'check_extra' => $extras,
        'check_notes' => $note ?: null,
        'update_date' => gettime()
    ], "`id` = ?", [$shipment_id]);

    // Send Telegram notification if missing or extra
    if ($missing > 0 || $extras > 0) {
        $tg = new TelegramBot();
        $staffName = $_SESSION['user']['fullname'] ?? 'Staff VN';
        $msg = "⚠️ *Kiểm đếm chuyến xe " . $shipment['shipment_code'] . "*\n";
        $msg .= "👤 " . $staffName . "\n";
        $msg .= "✅ Khớp: " . $matched . " kiện\n";
        if ($missing > 0) {
            $msg .= "❌ Thiếu: " . $missing . " kiện\n";
            foreach ($missingPkgs as $mp) {
                $msg .= "  - " . $mp['package_code'] . ($mp['ma_hang'] ? ' (' . $mp['ma_hang'] . ')' : '') . "\n";
            }
        }
        if ($extras > 0) {
            $msg .= "⚠️ Thừa: " . $extras . " mã\n";
            foreach ($extraItems as $ei) {
                $msg .= "  - " . $ei['barcode'] . "\n";
            }
        }
        if ($note) $msg .= "📝 " . $note;
        $tg->send($msg);
    }

    echo json_encode([
        'status' => 'success',
        'msg' => __('Hoàn thành kiểm đếm'),
        'matched' => $matched,
        'missing' => $missing,
        'extra' => $extras,
        'missing_items' => $missingPkgs,
        'extra_items' => $extraItems
    ]);
    exit;
}

// ===== RESET: reset kiểm đếm =====
if ($request === 'reset') {
    $shipment_id = intval(input_post('shipment_id'));

    $ToryHub->update_safe('shipment_packages',
        ['check_status' => 'unchecked', 'check_note' => null],
        "`shipment_id` = ?", [$shipment_id]
    );
    $ToryHub->remove_safe('shipment_check_extras', "`shipment_id` = ?", [$shipment_id]);
    $ToryHub->update_safe('shipments', [
        'checked_by' => null, 'checked_date' => null,
        'check_matched' => 0, 'check_missing' => 0, 'check_extra' => 0,
        'check_notes' => null, 'update_date' => gettime()
    ], "`id` = ?", [$shipment_id]);

    echo json_encode(['status' => 'success', 'msg' => __('Đã reset kiểm đếm')]);
    exit;
}

// ===== GET STATUS: lấy trạng thái kiểm đếm hiện tại =====
if ($request === 'get_status') {
    $shipment_id = intval(input_post('shipment_id'));

    $matched = $ToryHub->get_list_safe(
        "SELECT sp.package_id, p.package_code FROM `shipment_packages` sp
         JOIN `packages` p ON sp.package_id = p.id
         WHERE sp.shipment_id = ? AND sp.check_status = 'matched'", [$shipment_id]
    );
    $extras = $ToryHub->get_list_safe(
        "SELECT * FROM `shipment_check_extras` WHERE `shipment_id` = ?", [$shipment_id]
    );

    echo json_encode([
        'status' => 'success',
        'matched_package_ids' => array_column($matched, 'package_id'),
        'matched_package_codes' => array_column($matched, 'package_code'),
        'extras' => $extras
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'msg' => __('Yêu cầu không hợp lệ')]);
