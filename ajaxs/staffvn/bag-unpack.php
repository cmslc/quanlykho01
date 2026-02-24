<?php
/**
 * Bag Unpack AJAX handler - Tách bao tại kho VN
 * Actions: load_bag, unpack_packages, unpack_all
 */
define('IN_SITE', true);
require_once(__DIR__.'/../../libs/db.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../libs/lang.php');
require_once(__DIR__.'/../../libs/helper.php');
require_once(__DIR__.'/../../libs/session.php');
require_once(__DIR__.'/../../libs/role.php');
require_once(__DIR__.'/../../libs/csrf.php');
require_once(__DIR__.'/../../models/is_staffvn.php');

header('Content-Type: application/json; charset=utf-8');

$csrf = new Csrf();
if (!$csrf->validate()) {
    echo json_encode(['status' => 'error', 'msg' => __('Token không hợp lệ')]);
    exit;
}

$request = input_post('request_name');
$user_id = $getUser['id'];

// ===== LOAD BAG: quét mã bao → hiển thị thông tin + danh sách kiện =====
if ($request === 'load_bag') {
    $bag_code = strtoupper(trim(input_post('bag_code')));

    if (empty($bag_code)) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập mã bao'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    $bag = $ToryHub->get_row_safe("SELECT * FROM `bags` WHERE `bag_code` = ?", [$bag_code]);
    if (!$bag) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Không tìm thấy bao hàng') . ': ' . htmlspecialchars($bag_code), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    // Get packages in this bag
    $packages = $ToryHub->get_list_safe(
        "SELECT p.id, p.package_code, p.tracking_cn, p.tracking_intl, p.tracking_vn,
                p.weight_actual, p.weight_charged, p.status,
                GROUP_CONCAT(DISTINCT o.order_code SEPARATOR ', ') as order_codes,
                GROUP_CONCAT(DISTINCT c.fullname SEPARATOR ', ') as customer_names,
                GROUP_CONCAT(DISTINCT o.product_name SEPARATOR ', ') as product_names
         FROM `bag_packages` bp
         JOIN `packages` p ON bp.package_id = p.id
         LEFT JOIN `package_orders` po ON p.id = po.package_id
         LEFT JOIN `orders` o ON po.order_id = o.id
         LEFT JOIN `customers` c ON o.customer_id = c.id
         WHERE bp.bag_id = ?
         GROUP BY p.id
         ORDER BY p.package_code ASC",
        [$bag['id']]
    );

    if (empty($packages)) {
        $newCsrf = new Csrf();
        echo json_encode([
            'status' => 'warning',
            'msg' => __('Bao hàng trống, không có kiện nào'),
            'bag' => [
                'id' => $bag['id'],
                'bag_code' => $bag['bag_code'],
                'status' => $bag['status'],
                'total_packages' => 0
            ],
            'packages' => [],
            'csrf_token' => $newCsrf->get_token_value()
        ]);
        exit;
    }

    // Format packages for response
    $pkgList = [];
    foreach ($packages as $p) {
        $pkgList[] = [
            'id' => $p['id'],
            'package_code' => $p['package_code'],
            'tracking_cn' => $p['tracking_cn'] ?? '',
            'tracking_intl' => $p['tracking_intl'] ?? '',
            'weight_charged' => floatval($p['weight_charged']),
            'status' => $p['status'],
            'status_html' => display_package_status($p['status']),
            'order_codes' => $p['order_codes'] ?? '',
            'customer_names' => $p['customer_names'] ?? '',
            'product_names' => $p['product_names'] ?? ''
        ];
    }

    $newCsrf = new Csrf();
    echo json_encode([
        'status' => 'success',
        'msg' => __('Đã tải bao hàng') . ': ' . $bag['bag_code'],
        'bag' => [
            'id' => $bag['id'],
            'bag_code' => $bag['bag_code'],
            'status' => $bag['status'],
            'total_packages' => intval($bag['total_packages']),
            'total_weight' => floatval($bag['total_weight']),
            'note' => $bag['note'] ?? '',
            'create_date' => $bag['create_date'] ?? ''
        ],
        'packages' => $pkgList,
        'csrf_token' => $newCsrf->get_token_value()
    ]);
    exit;
}

// ===== UNPACK PACKAGES: tách kiện đã chọn khỏi bao =====
if ($request === 'unpack_packages') {
    $bag_id = intval(input_post('bag_id'));
    $package_ids_raw = input_post('package_ids');

    if (!$bag_id || empty($package_ids_raw)) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Thiếu thông tin'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    // Parse package_ids (can be JSON array or comma-separated)
    if (is_string($package_ids_raw)) {
        $package_ids = json_decode($package_ids_raw, true);
        if (!$package_ids) {
            $package_ids = array_filter(array_map('intval', explode(',', $package_ids_raw)));
        }
    } else {
        $package_ids = array_map('intval', (array) $package_ids_raw);
    }

    if (empty($package_ids)) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Không có kiện nào được chọn'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    $bag = $ToryHub->get_row_safe("SELECT * FROM `bags` WHERE `id` = ?", [$bag_id]);
    if (!$bag) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Bao hàng không tồn tại'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    $ToryHub->beginTransaction();
    try {
        $unpacked = 0;
        $unpackedCodes = [];

        foreach ($package_ids as $pkg_id) {
            // Verify package belongs to this bag
            $bp = $ToryHub->get_row_safe(
                "SELECT bp.id, p.package_code FROM `bag_packages` bp JOIN `packages` p ON bp.package_id = p.id WHERE bp.bag_id = ? AND bp.package_id = ?",
                [$bag_id, $pkg_id]
            );
            if ($bp) {
                $ToryHub->remove_safe('bag_packages', "`bag_id` = ? AND `package_id` = ?", [$bag_id, $pkg_id]);
                $unpacked++;
                $unpackedCodes[] = $bp['package_code'];
            }
        }

        // Recalculate bag totals
        $totals = $ToryHub->get_row_safe(
            "SELECT COUNT(*) as cnt, COALESCE(SUM(p.weight_charged), 0) as total_w
             FROM `bag_packages` bp JOIN `packages` p ON bp.package_id = p.id
             WHERE bp.bag_id = ?", [$bag_id]
        );

        $ToryHub->update_safe('bags', [
            'total_packages' => intval($totals['cnt']),
            'total_weight' => floatval($totals['total_w']),
            'update_date' => gettime()
        ], "`id` = ?", [$bag_id]);

        $ToryHub->commit();

        add_log($user_id, 'BAG_UNPACK', 'Tách ' . $unpacked . ' kiện từ bao ' . $bag['bag_code'] . ': ' . implode(', ', $unpackedCodes));

        $newCsrf = new Csrf();
        echo json_encode([
            'status' => 'success',
            'msg' => __('Đã tách') . ' ' . $unpacked . ' ' . __('kiện khỏi bao') . ' ' . $bag['bag_code'],
            'unpacked_count' => $unpacked,
            'unpacked_codes' => $unpackedCodes,
            'bag_remaining' => intval($totals['cnt']),
            'bag_remaining_weight' => floatval($totals['total_w']),
            'csrf_token' => $newCsrf->get_token_value()
        ]);
    } catch (Exception $e) {
        $ToryHub->rollBack();
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Lỗi hệ thống'), 'csrf_token' => $newCsrf->get_token_value()]);
    }
    exit;
}

// ===== UNPACK ALL: tách toàn bộ kiện khỏi bao =====
if ($request === 'unpack_all') {
    $bag_id = intval(input_post('bag_id'));

    if (!$bag_id) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Thiếu thông tin'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    $bag = $ToryHub->get_row_safe("SELECT * FROM `bags` WHERE `id` = ?", [$bag_id]);
    if (!$bag) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Bao hàng không tồn tại'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    // Count before delete
    $count = $ToryHub->num_rows_safe("SELECT id FROM `bag_packages` WHERE `bag_id` = ?", [$bag_id]);

    $ToryHub->beginTransaction();
    try {
        $ToryHub->remove_safe('bag_packages', "`bag_id` = ?", [$bag_id]);

        $ToryHub->update_safe('bags', [
            'total_packages' => 0,
            'total_weight' => 0,
            'update_date' => gettime()
        ], "`id` = ?", [$bag_id]);

        $ToryHub->commit();

        add_log($user_id, 'BAG_UNPACK_ALL', 'Tách toàn bộ ' . $count . ' kiện từ bao ' . $bag['bag_code']);

        $newCsrf = new Csrf();
        echo json_encode([
            'status' => 'success',
            'msg' => __('Đã tách toàn bộ') . ' ' . $count . ' ' . __('kiện khỏi bao') . ' ' . $bag['bag_code'],
            'unpacked_count' => $count,
            'csrf_token' => $newCsrf->get_token_value()
        ]);
    } catch (Exception $e) {
        $ToryHub->rollBack();
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Lỗi hệ thống'), 'csrf_token' => $newCsrf->get_token_value()]);
    }
    exit;
}

// ===== LOAD BAG DETAIL: xem kiện trong bao (dùng cho trang quản lý mã hàng) =====
if ($request === 'load_bag_detail') {
    $bag_id = intval(input_post('bag_id'));

    if (!$bag_id) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Thiếu thông tin'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    $bag = $ToryHub->get_row_safe("SELECT * FROM `bags` WHERE `id` = ?", [$bag_id]);
    if (!$bag) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Bao hàng không tồn tại'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    // Check if zone columns exist via INFORMATION_SCHEMA (safe, no fatal error)
    $hasZone = false;
    $zoneCheck = $ToryHub->get_row_safe(
        "SELECT COUNT(*) as cnt FROM `INFORMATION_SCHEMA`.`COLUMNS`
         WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'packages' AND `COLUMN_NAME` = 'zone_id'", []
    );
    $zoneTableCheck = $ToryHub->get_row_safe(
        "SELECT COUNT(*) as cnt FROM `INFORMATION_SCHEMA`.`TABLES`
         WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'warehouse_zones'", []
    );
    if ($zoneCheck && intval($zoneCheck['cnt']) > 0 && $zoneTableCheck && intval($zoneTableCheck['cnt']) > 0) {
        $hasZone = true;
    }

    if ($hasZone) {
        $packages = $ToryHub->get_list_safe(
            "SELECT p.id, p.package_code, p.tracking_cn, p.tracking_intl, p.tracking_vn,
                    p.weight_actual, p.weight_charged, p.length_cm, p.width_cm, p.height_cm,
                    p.status, p.zone_id, p.shelf_position,
                    wz.zone_code,
                    GROUP_CONCAT(DISTINCT o.order_code SEPARATOR ', ') as order_codes,
                    GROUP_CONCAT(DISTINCT o.product_name SEPARATOR ', ') as product_names,
                    GROUP_CONCAT(DISTINCT c.fullname SEPARATOR ', ') as customer_name
             FROM `bag_packages` bp
             JOIN `packages` p ON bp.package_id = p.id
             LEFT JOIN `warehouse_zones` wz ON p.zone_id = wz.id
             LEFT JOIN `package_orders` po ON p.id = po.package_id
             LEFT JOIN `orders` o ON po.order_id = o.id
             LEFT JOIN `customers` c ON o.customer_id = c.id
             WHERE bp.bag_id = ?
             GROUP BY p.id
             ORDER BY p.package_code ASC",
            [$bag_id]
        );
    } else {
        $packages = $ToryHub->get_list_safe(
            "SELECT p.id, p.package_code, p.tracking_cn, p.tracking_intl, p.tracking_vn,
                    p.weight_actual, p.weight_charged, p.length_cm, p.width_cm, p.height_cm,
                    p.status,
                    GROUP_CONCAT(DISTINCT o.order_code SEPARATOR ', ') as order_codes,
                    GROUP_CONCAT(DISTINCT o.product_name SEPARATOR ', ') as product_names,
                    GROUP_CONCAT(DISTINCT c.fullname SEPARATOR ', ') as customer_name
             FROM `bag_packages` bp
             JOIN `packages` p ON bp.package_id = p.id
             LEFT JOIN `package_orders` po ON p.id = po.package_id
             LEFT JOIN `orders` o ON po.order_id = o.id
             LEFT JOIN `customers` c ON o.customer_id = c.id
             WHERE bp.bag_id = ?
             GROUP BY p.id
             ORDER BY p.package_code ASC",
            [$bag_id]
        );
    }

    $pkgList = [];
    foreach ($packages as $p) {
        $pkgList[] = [
            'id' => $p['id'],
            'package_code' => $p['package_code'],
            'tracking_cn' => $p['tracking_cn'] ?? '',
            'weight_charged' => floatval($p['weight_charged']),
            'length_cm' => floatval($p['length_cm'] ?? 0),
            'width_cm' => floatval($p['width_cm'] ?? 0),
            'height_cm' => floatval($p['height_cm'] ?? 0),
            'status' => $p['status'],
            'status_html' => display_package_status($p['status']),
            'zone_code' => $p['zone_code'] ?? '',
            'shelf_position' => $p['shelf_position'] ?? '',
            'product_name' => $p['product_names'] ?? '',
            'customer_name' => $p['customer_name'] ?? ''
        ];
    }

    $newCsrf = new Csrf();
    echo json_encode([
        'status' => 'success',
        'bag' => [
            'id' => $bag['id'],
            'bag_code' => $bag['bag_code'],
            'images' => $bag['images'] ?? ''
        ],
        'packages' => $pkgList,
        'csrf_token' => $newCsrf->get_token_value()
    ]);
    exit;
}

// ===== LOAD ORDER DETAIL: xem kiện trong đơn hàng lô (dùng cho trang quản lý mã hàng) =====
if ($request === 'load_order_detail') {
    $order_id = intval(input_post('order_id'));

    if (!$order_id) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Thiếu thông tin'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    $packages = $ToryHub->get_list_safe(
        "SELECT p.id, p.package_code, p.tracking_cn, p.weight_actual, p.weight_charged,
                p.length_cm, p.width_cm, p.height_cm, p.status
         FROM `packages` p
         JOIN `package_orders` po ON p.id = po.package_id
         WHERE po.order_id = ?
         ORDER BY p.id ASC",
        [$order_id]
    );

    $pkgList = [];
    foreach ($packages as $p) {
        $cbm = (floatval($p['length_cm']) * floatval($p['width_cm']) * floatval($p['height_cm'])) / 1000000;
        $pkgList[] = [
            'id' => $p['id'],
            'package_code' => $p['package_code'],
            'tracking_cn' => $p['tracking_cn'] ?? '',
            'weight_charged' => floatval($p['weight_charged']),
            'weight_actual' => floatval($p['weight_actual']),
            'cbm' => round($cbm, 4),
            'status' => $p['status'],
            'status_html' => display_package_status($p['status'])
        ];
    }

    $newCsrf = new Csrf();
    echo json_encode([
        'status' => 'success',
        'packages' => $pkgList,
        'csrf_token' => $newCsrf->get_token_value()
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'msg' => __('Yêu cầu không hợp lệ')]);
