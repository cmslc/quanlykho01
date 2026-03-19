<?php
/**
 * API Shipments endpoints
 */

require_once(__DIR__.'/bootstrap.php');
require_once(__DIR__.'/jwt.php');

$user = api_auth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

// ===== PENDING ITEMS =====
if ($action === 'pending' && $method === 'GET') {
    $bags = $ToryHub->get_list_safe(
        "SELECT b.* FROM `bags` b WHERE b.status = 'sealed'
         AND b.id NOT IN (SELECT DISTINCT bp2.bag_id FROM `bag_packages` bp2
           JOIN `shipment_packages` sp ON bp2.package_id = sp.package_id)
         ORDER BY b.create_date DESC", []
    );

    $packages = $ToryHub->get_list_safe(
        "SELECT p.* FROM `packages` p
         WHERE p.status IN ('packed','cn_warehouse')
         AND p.id NOT IN (SELECT package_id FROM `bag_packages`)
         AND p.id NOT IN (SELECT package_id FROM `shipment_packages`)
         ORDER BY p.create_date DESC", []
    );

    api_success(['bags' => $bags, 'packages' => $packages]);
}

// ===== ADD PACKAGES TO SHIPMENT =====
if ($action === 'add_packages' && $method === 'POST') {
    $input = api_input();
    $shipment_id = intval($input['shipment_id'] ?? 0);
    $package_ids = $input['package_ids'] ?? '';
    if (is_string($package_ids)) $package_ids = array_filter(array_map('intval', explode(',', $package_ids)));

    $shipment = $ToryHub->get_row_safe("SELECT * FROM `shipments` WHERE `id` = ?", [$shipment_id]);
    if (!$shipment) api_error('Chuyến xe không tồn tại');
    if ($shipment['status'] !== 'preparing') api_error('Chỉ có thể thêm kiện khi chuyến đang chuẩn bị');

    $added = 0; $skipped = 0;
    foreach ($package_ids as $pid) {
        $exists = $ToryHub->get_row_safe("SELECT id FROM `shipment_packages` WHERE `shipment_id` = ? AND `package_id` = ?", [$shipment_id, $pid]);
        if ($exists) { $skipped++; continue; }
        $ToryHub->insert_safe('shipment_packages', [
            'shipment_id' => $shipment_id, 'package_id' => $pid,
            'added_by' => $user['id'], 'added_at' => gettime()
        ]);
        $ToryHub->update_safe('packages', ['status' => 'loading', 'update_date' => gettime()], "`id` = ?", [$pid]);
        $added++;
    }

    // Update shipment totals
    $totals = $ToryHub->get_row_safe(
        "SELECT COUNT(*) as cnt, COALESCE(SUM(p.weight_charged),0) as tw, COALESCE(SUM(p.length_cm*p.width_cm*p.height_cm/1000000),0) as cbm
         FROM `shipment_packages` sp JOIN `packages` p ON sp.package_id = p.id WHERE sp.shipment_id = ?", [$shipment_id]
    );
    $ToryHub->update_safe('shipments', [
        'total_packages' => $totals['cnt'], 'total_weight' => $totals['tw'],
        'total_cbm' => $totals['cbm'], 'update_date' => gettime()
    ], "`id` = ?", [$shipment_id]);

    api_success(['added' => $added, 'skipped' => $skipped], "Đã thêm $added kiện vào chuyến");
}

// ===== REMOVE PACKAGE =====
if ($action === 'remove_package' && $method === 'POST') {
    $input = api_input();
    $shipment_id = intval($input['shipment_id'] ?? 0);
    $package_id = intval($input['package_id'] ?? 0);

    $shipment = $ToryHub->get_row_safe("SELECT * FROM `shipments` WHERE `id` = ?", [$shipment_id]);
    if (!$shipment || $shipment['status'] !== 'preparing') api_error('Chỉ có thể gỡ kiện khi chuyến đang chuẩn bị');

    $bagRow = $ToryHub->get_row_safe("SELECT bag_id FROM `bag_packages` WHERE `package_id` = ?", [$package_id]);
    $revertTo = $bagRow ? 'packed' : 'cn_warehouse';
    $ToryHub->update_safe('packages', ['status' => $revertTo, 'update_date' => gettime()], "`id` = ?", [$package_id]);

    $ToryHub->remove_safe('shipment_packages', "`shipment_id` = ? AND `package_id` = ?", [$shipment_id, $package_id]);

    api_success([], 'Đã gỡ kiện khỏi chuyến');
}

// ===== UPDATE STATUS =====
if ($action === 'update_status' && $method === 'POST') {
    $input = api_input();
    $sid = intval($input['shipment_id'] ?? 0);
    $new_status = $input['new_status'] ?? '';

    $valid = ['preparing', 'in_transit', 'arrived', 'completed'];
    if (!in_array($new_status, $valid)) api_error('Trạng thái không hợp lệ');

    $shipment = $ToryHub->get_row_safe("SELECT * FROM `shipments` WHERE `id` = ?", [$sid]);
    if (!$shipment) api_error('Chuyến xe không tồn tại');

    $updateData = ['status' => $new_status, 'update_date' => gettime()];
    if ($new_status === 'in_transit') $updateData['departed_date'] = gettime();
    if ($new_status === 'arrived') $updateData['arrived_date'] = gettime();

    $ToryHub->update_safe('shipments', $updateData, "`id` = ?", [$sid]);

    // Update package statuses
    if ($new_status === 'in_transit') {
        $pkgs = $ToryHub->get_list_safe("SELECT package_id FROM `shipment_packages` WHERE `shipment_id` = ?", [$sid]);
        foreach ($pkgs as $p) {
            $ToryHub->update_safe('packages', ['status' => 'shipping', 'shipping_date' => gettime(), 'update_date' => gettime()], "`id` = ?", [$p['package_id']]);
        }
    } elseif ($new_status === 'arrived') {
        $pkgs = $ToryHub->get_list_safe("SELECT package_id FROM `shipment_packages` WHERE `shipment_id` = ?", [$sid]);
        foreach ($pkgs as $p) {
            $ToryHub->update_safe('packages', ['status' => 'vn_warehouse', 'vn_warehouse_date' => gettime(), 'update_date' => gettime()], "`id` = ?", [$p['package_id']]);
        }
    }

    api_success([], 'Cập nhật trạng thái chuyến xe thành công');
}

// ===== POST: Create Shipment =====
if ($method === 'POST' && !$action) {
    $input = api_input();

    $prefix = 'CX-' . date('Ymd') . '-';
    $last = $ToryHub->get_row_safe("SELECT `shipment_code` FROM `shipments` WHERE `shipment_code` LIKE ? ORDER BY `id` DESC LIMIT 1", [$prefix . '%']);
    $seq = $last ? intval(substr($last['shipment_code'], -3)) + 1 : 1;
    $shipment_code = $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);

    $ToryHub->insert_safe('shipments', [
        'shipment_code' => $shipment_code,
        'truck_plate' => strtoupper(trim($input['truck_plate'] ?? '')),
        'driver_name' => trim($input['driver_name'] ?? ''),
        'driver_phone' => trim($input['driver_phone'] ?? ''),
        'route' => trim($input['route'] ?? ''),
        'max_weight' => floatval($input['max_weight'] ?? 0) ?: null,
        'shipping_cost' => floatval($input['shipping_cost'] ?? 0) ?: null,
        'note' => trim($input['note'] ?? ''),
        'status' => 'preparing',
        'created_by' => $user['id'],
        'create_date' => gettime(),
        'update_date' => gettime()
    ]);

    api_success([
        'shipment_id' => $ToryHub->insert_id(),
        'shipment_code' => $shipment_code
    ], 'Tạo chuyến xe thành công');
}

// ===== PUT: Edit Shipment =====
if ($method === 'PUT' && $id) {
    $input = api_input();
    $shipment = $ToryHub->get_row_safe("SELECT * FROM `shipments` WHERE `id` = ?", [$id]);
    if (!$shipment) api_error('Chuyến xe không tồn tại', 404);

    $ToryHub->update_safe('shipments', [
        'truck_plate' => strtoupper(trim($input['truck_plate'] ?? $shipment['truck_plate'])),
        'driver_name' => trim($input['driver_name'] ?? $shipment['driver_name']),
        'driver_phone' => trim($input['driver_phone'] ?? $shipment['driver_phone']),
        'route' => trim($input['route'] ?? $shipment['route']),
        'max_weight' => floatval($input['max_weight'] ?? $shipment['max_weight']) ?: null,
        'shipping_cost' => floatval($input['shipping_cost'] ?? $shipment['shipping_cost']) ?: null,
        'note' => trim($input['note'] ?? $shipment['note']),
        'update_date' => gettime()
    ], "`id` = ?", [$id]);

    api_success([], 'Cập nhật chuyến xe thành công');
}

// ===== DELETE =====
if ($method === 'DELETE' && $id) {
    $shipment = $ToryHub->get_row_safe("SELECT * FROM `shipments` WHERE `id` = ?", [$id]);
    if (!$shipment) api_error('Chuyến xe không tồn tại');
    if ($shipment['status'] !== 'preparing') api_error('Chỉ có thể xóa chuyến xe đang chuẩn bị');

    $pkgs = $ToryHub->get_list_safe("SELECT package_id FROM `shipment_packages` WHERE `shipment_id` = ?", [$id]);
    foreach ($pkgs as $p) {
        $bagRow = $ToryHub->get_row_safe("SELECT bag_id FROM `bag_packages` WHERE `package_id` = ?", [$p['package_id']]);
        $revertTo = $bagRow ? 'packed' : 'cn_warehouse';
        $ToryHub->update_safe('packages', ['status' => $revertTo, 'update_date' => gettime()], "`id` = ?", [$p['package_id']]);
    }
    $ToryHub->remove_safe('shipment_packages', "`shipment_id` = ?", [$id]);
    $ToryHub->remove_safe('shipments', "`id` = ?", [$id]);

    api_success([], 'Đã xóa chuyến xe');
}

// ===== GET =====
if ($method === 'GET') {
    if ($id) {
        $shipment = $ToryHub->get_row_safe("SELECT * FROM `shipments` WHERE `id` = ?", [$id]);
        if (!$shipment) api_error('Chuyến xe không tồn tại', 404);

        $packages = $ToryHub->get_list_safe(
            "SELECT p.*, o.product_name, c.fullname as customer_name
             FROM `shipment_packages` sp
             JOIN `packages` p ON sp.package_id = p.id
             LEFT JOIN `package_orders` po ON p.id = po.package_id
             LEFT JOIN `orders` o ON po.order_id = o.id
             LEFT JOIN `customers` c ON o.customer_id = c.id
             WHERE sp.shipment_id = ? ORDER BY sp.id ASC", [$id]
        );
        $shipment['packages'] = $packages;
        api_success(['shipment' => $shipment]);
    }

    $pg = api_pagination();
    $status = $_GET['status'] ?? '';
    $where = "1=1"; $params = [];
    if ($status) { $where .= " AND `status` = ?"; $params[] = $status; }

    $total = $ToryHub->get_row_safe("SELECT COUNT(*) as cnt FROM `shipments` WHERE $where", $params);
    $listParams = array_merge($params, [$pg['per_page'], $pg['offset']]);
    $shipments = $ToryHub->get_list_safe("SELECT * FROM `shipments` WHERE $where ORDER BY `id` DESC LIMIT ? OFFSET ?", $listParams);

    api_success([
        'shipments' => $shipments,
        'total' => (int)($total['cnt'] ?? 0),
        'page' => $pg['page'],
        'per_page' => $pg['per_page']
    ]);
}

api_error('Method not allowed', 405);
