<?php
/**
 * Warehouse Zones AJAX handler - Phân vùng kho
 * Actions: list_zones, create_zone, assign_zone, bulk_assign, get_zone_packages
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
$user_id = $_SESSION['user']['id'];

// ===== LIST ZONES =====
if ($request === 'list_zones') {
    $zones = $ToryHub->get_list_safe(
        "SELECT z.*, (SELECT COUNT(*) FROM `packages` p WHERE p.zone_id = z.id AND p.status = 'vn_warehouse') as package_count
         FROM `warehouse_zones` z WHERE z.is_active = 1 ORDER BY z.zone_code ASC", []
    );

    $newCsrf = new Csrf();
    echo json_encode([
        'status' => 'success',
        'zones' => $zones,
        'csrf_token' => $newCsrf->get_token_value()
    ]);
    exit;
}

// ===== CREATE ZONE =====
if ($request === 'create_zone') {
    $zone_code = strtoupper(trim(input_post('zone_code')));
    $zone_name = trim(input_post('zone_name'));
    $description = trim(input_post('description'));

    if (empty($zone_code) || empty($zone_name)) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập mã vùng và tên vùng'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    // Check duplicate code
    $exists = $ToryHub->get_row_safe("SELECT id FROM `warehouse_zones` WHERE `zone_code` = ?", [$zone_code]);
    if ($exists) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Mã vùng đã tồn tại') . ': ' . $zone_code, 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    $ToryHub->insert_safe('warehouse_zones', [
        'zone_code' => $zone_code,
        'zone_name' => $zone_name,
        'description' => $description ?: null,
        'is_active' => 1,
        'create_date' => gettime()
    ]);

    $zone_id = $ToryHub->insert_id();
    add_log($user_id, 'CREATE_ZONE', 'Tạo vùng kho: ' . $zone_code . ' - ' . $zone_name);

    $newCsrf = new Csrf();
    echo json_encode([
        'status' => 'success',
        'msg' => __('Đã tạo vùng kho') . ': ' . $zone_code,
        'zone' => [
            'id' => $zone_id,
            'zone_code' => $zone_code,
            'zone_name' => $zone_name,
            'package_count' => 0
        ],
        'csrf_token' => $newCsrf->get_token_value()
    ]);
    exit;
}

// ===== ASSIGN ZONE: gán kiện vào vùng =====
if ($request === 'assign_zone') {
    $barcode = trim(input_post('barcode'));
    $zone_id = intval(input_post('zone_id'));
    $shelf_position = trim(input_post('shelf_position'));

    if (empty($barcode) || !$zone_id) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Thiếu thông tin'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    // Find zone
    $zone = $ToryHub->get_row_safe("SELECT * FROM `warehouse_zones` WHERE `id` = ? AND `is_active` = 1", [$zone_id]);
    if (!$zone) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Vùng kho không tồn tại'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    // Find package by barcode
    $package = $ToryHub->get_row_safe(
        "SELECT * FROM `packages` WHERE `package_code` = ? OR `tracking_intl` = ? OR `tracking_vn` = ? OR `tracking_cn` = ? LIMIT 1",
        [$barcode, $barcode, $barcode, $barcode]
    );

    if (!$package) {
        $newCsrf = new Csrf();
        echo json_encode([
            'status' => 'error',
            'msg' => __('Không tìm thấy kiện hàng') . ': ' . htmlspecialchars($barcode),
            'csrf_token' => $newCsrf->get_token_value()
        ]);
        exit;
    }

    // Validate package is at VN warehouse
    if ($package['status'] !== 'vn_warehouse') {
        $newCsrf = new Csrf();
        echo json_encode([
            'status' => 'error',
            'msg' => __('Kiện hàng không ở kho VN. Trạng thái') . ': ' . strip_tags(display_package_status($package['status'])),
            'csrf_token' => $newCsrf->get_token_value()
        ]);
        exit;
    }

    // Check if already in same zone
    if ($package['zone_id'] == $zone_id && (empty($shelf_position) || $package['shelf_position'] == $shelf_position)) {
        $newCsrf = new Csrf();
        echo json_encode([
            'status' => 'duplicate',
            'msg' => __('Kiện đã ở vùng') . ' ' . $zone['zone_code'],
            'package_code' => $package['package_code'],
            'csrf_token' => $newCsrf->get_token_value()
        ]);
        exit;
    }

    $oldZone = null;
    if ($package['zone_id']) {
        $oldZone = $ToryHub->get_row_safe("SELECT zone_code FROM `warehouse_zones` WHERE `id` = ?", [$package['zone_id']]);
    }

    $updateData = [
        'zone_id' => $zone_id,
        'update_date' => gettime()
    ];
    if (!empty($shelf_position)) {
        $updateData['shelf_position'] = $shelf_position;
    }

    $ToryHub->update_safe('packages', $updateData, "`id` = ?", [$package['id']]);

    $logMsg = 'Gán kiện ' . $package['package_code'] . ' → ' . $zone['zone_code'];
    if ($oldZone) $logMsg .= ' (từ ' . $oldZone['zone_code'] . ')';
    add_log($user_id, 'ASSIGN_ZONE', $logMsg);

    // Get customer name
    $orderInfo = $ToryHub->get_row_safe(
        "SELECT o.order_code, o.product_name, c.fullname as customer_name
         FROM `package_orders` po JOIN `orders` o ON po.order_id = o.id
         LEFT JOIN `customers` c ON o.customer_id = c.id
         WHERE po.package_id = ? LIMIT 1", [$package['id']]
    );

    $newCsrf = new Csrf();
    echo json_encode([
        'status' => 'success',
        'msg' => $package['package_code'] . ' → ' . $zone['zone_code'] . ($shelf_position ? ' / ' . $shelf_position : ''),
        'package' => [
            'id' => $package['id'],
            'package_code' => $package['package_code'],
            'zone_code' => $zone['zone_code'],
            'zone_name' => $zone['zone_name'],
            'shelf_position' => $shelf_position,
            'customer_name' => $orderInfo['customer_name'] ?? '',
            'product_name' => $orderInfo['product_name'] ?? ''
        ],
        'csrf_token' => $newCsrf->get_token_value()
    ]);
    exit;
}

// ===== BULK ASSIGN =====
if ($request === 'bulk_assign') {
    $package_ids_raw = input_post('package_ids');
    $zone_id = intval(input_post('zone_id'));
    $shelf_position = trim(input_post('shelf_position'));

    if (empty($package_ids_raw) || !$zone_id) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Thiếu thông tin'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    $zone = $ToryHub->get_row_safe("SELECT * FROM `warehouse_zones` WHERE `id` = ? AND `is_active` = 1", [$zone_id]);
    if (!$zone) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Vùng kho không tồn tại'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    $package_ids = json_decode($package_ids_raw, true);
    if (!$package_ids) {
        $package_ids = array_filter(array_map('intval', explode(',', $package_ids_raw)));
    }

    $updated = 0;
    foreach ($package_ids as $pid) {
        $pid = intval($pid);
        $pkg = $ToryHub->get_row_safe("SELECT id, status FROM `packages` WHERE `id` = ? AND `status` = 'vn_warehouse'", [$pid]);
        if ($pkg) {
            $updateData = ['zone_id' => $zone_id, 'update_date' => gettime()];
            if (!empty($shelf_position)) $updateData['shelf_position'] = $shelf_position;
            $ToryHub->update_safe('packages', $updateData, "`id` = ?", [$pid]);
            $updated++;
        }
    }

    add_log($user_id, 'BULK_ASSIGN_ZONE', 'Gán ' . $updated . ' kiện → ' . $zone['zone_code']);

    $newCsrf = new Csrf();
    echo json_encode([
        'status' => 'success',
        'msg' => __('Đã gán') . ' ' . $updated . ' ' . __('kiện vào') . ' ' . $zone['zone_code'],
        'updated_count' => $updated,
        'csrf_token' => $newCsrf->get_token_value()
    ]);
    exit;
}

// ===== GET ZONE PACKAGES =====
if ($request === 'get_zone_packages') {
    $zone_id = input_post('zone_id'); // Can be 0 for "unassigned"

    if ($zone_id === '' || $zone_id === null) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Thiếu thông tin'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    $zone_id = intval($zone_id);

    if ($zone_id === 0) {
        // Unassigned packages at VN warehouse
        $packages = $ToryHub->get_list_safe(
            "SELECT p.id, p.package_code, p.tracking_cn, p.tracking_intl, p.weight_charged, p.shelf_position, p.vn_warehouse_date,
                    GROUP_CONCAT(DISTINCT o.order_code SEPARATOR ', ') as order_codes,
                    GROUP_CONCAT(DISTINCT c.fullname SEPARATOR ', ') as customer_names
             FROM `packages` p
             LEFT JOIN `package_orders` po ON p.id = po.package_id
             LEFT JOIN `orders` o ON po.order_id = o.id
             LEFT JOIN `customers` c ON o.customer_id = c.id
             WHERE p.status = 'vn_warehouse' AND (p.zone_id IS NULL OR p.zone_id = 0)
             GROUP BY p.id ORDER BY p.vn_warehouse_date DESC", []
        );
    } else {
        $packages = $ToryHub->get_list_safe(
            "SELECT p.id, p.package_code, p.tracking_cn, p.tracking_intl, p.weight_charged, p.shelf_position, p.vn_warehouse_date,
                    GROUP_CONCAT(DISTINCT o.order_code SEPARATOR ', ') as order_codes,
                    GROUP_CONCAT(DISTINCT c.fullname SEPARATOR ', ') as customer_names
             FROM `packages` p
             LEFT JOIN `package_orders` po ON p.id = po.package_id
             LEFT JOIN `orders` o ON po.order_id = o.id
             LEFT JOIN `customers` c ON o.customer_id = c.id
             WHERE p.status = 'vn_warehouse' AND p.zone_id = ?
             GROUP BY p.id ORDER BY p.vn_warehouse_date DESC", [$zone_id]
        );
    }

    $newCsrf = new Csrf();
    echo json_encode([
        'status' => 'success',
        'packages' => $packages,
        'csrf_token' => $newCsrf->get_token_value()
    ]);
    exit;
}

// ===== DELETE ZONE =====
if ($request === 'delete_zone') {
    $zone_id = intval(input_post('zone_id'));

    if (!$zone_id) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Thiếu thông tin'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    // Check if zone has packages
    $count = $ToryHub->num_rows_safe("SELECT id FROM `packages` WHERE `zone_id` = ? AND `status` = 'vn_warehouse'", [$zone_id]);
    if ($count > 0) {
        $newCsrf = new Csrf();
        echo json_encode(['status' => 'error', 'msg' => __('Không thể xóa vùng còn') . ' ' . $count . ' ' . __('kiện'), 'csrf_token' => $newCsrf->get_token_value()]);
        exit;
    }

    $ToryHub->update_safe('warehouse_zones', ['is_active' => 0], "`id` = ?", [$zone_id]);

    $newCsrf = new Csrf();
    echo json_encode(['status' => 'success', 'msg' => __('Đã xóa vùng kho'), 'csrf_token' => $newCsrf->get_token_value()]);
    exit;
}

echo json_encode(['status' => 'error', 'msg' => __('Yêu cầu không hợp lệ')]);
