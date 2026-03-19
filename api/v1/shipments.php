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
    $notInShipment = "p.id NOT IN (SELECT sp.package_id FROM `shipment_packages` sp JOIN `shipments` s ON sp.shipment_id = s.id WHERE s.status IN ('preparing','in_transit'))";

    // Summary counts
    $cntCnWarehouse = $ToryHub->get_row_safe(
        "SELECT COUNT(*) as cnt FROM `packages` p
         JOIN `package_orders` po ON p.id = po.package_id
         JOIN `orders` o ON po.order_id = o.id
         WHERE p.status = 'cn_warehouse' AND o.product_type = 'wholesale' AND $notInShipment", []
    );
    $cntPacked = $ToryHub->get_row_safe(
        "SELECT COUNT(*) as cnt FROM `packages` p WHERE p.status = 'packed'
         AND p.id IN (SELECT bp2.package_id FROM `bag_packages` bp2 JOIN `bags` b2 ON bp2.bag_id = b2.id WHERE b2.status = 'sealed')
         AND $notInShipment", []
    );

    // Sealed bags (retail packed)
    $sealedBags = $ToryHub->get_list_safe(
        "SELECT b.id as bag_id, b.bag_code,
            COUNT(p.id) as pkg_count,
            b.total_weight as bag_weight,
            COALESCE(b.weight_volume, 0) as bag_cbm,
            SUM(COALESCE(p.weight_charged, 0)) as pkg_weight_charged,
            SUM(COALESCE(p.weight_actual, 0)) as pkg_weight_actual,
            SUM(COALESCE(p.length_cm,0) * COALESCE(p.width_cm,0) * COALESCE(p.height_cm,0) / 1000000) as pkg_cbm,
            b.create_date
        FROM `bags` b
        JOIN `bag_packages` bp ON b.id = bp.bag_id
        JOIN `packages` p ON bp.package_id = p.id
        WHERE b.status = 'sealed' AND p.status = 'packed' AND $notInShipment
        GROUP BY b.id
        ORDER BY b.create_date DESC", []
    );

    // Get package IDs per bag
    if (!empty($sealedBags)) {
        $bagIds = array_column($sealedBags, 'bag_id');
        $ph = implode(',', array_fill(0, count($bagIds), '?'));
        $bagPkgs = $ToryHub->get_list_safe(
            "SELECT bp.bag_id, p.id as package_id FROM `bag_packages` bp
             JOIN `packages` p ON bp.package_id = p.id
             WHERE bp.bag_id IN ($ph) AND p.status = 'packed' AND $notInShipment", $bagIds
        );
        $bagPkgMap = [];
        foreach ($bagPkgs as $bp) {
            $bagPkgMap[$bp['bag_id']][] = $bp['package_id'];
        }
        // Get customer info per bag
        $bagCusts = $ToryHub->get_list_safe(
            "SELECT DISTINCT bp.bag_id, c.fullname
             FROM `bag_packages` bp
             JOIN `packages` p ON bp.package_id = p.id
             JOIN `package_orders` po ON p.id = po.package_id
             JOIN `orders` o ON po.order_id = o.id
             LEFT JOIN `customers` c ON o.customer_id = c.id
             WHERE bp.bag_id IN ($ph) AND c.id IS NOT NULL", $bagIds
        );
        $bagCustMap = [];
        foreach ($bagCusts as $bc) {
            $bagCustMap[$bc['bag_id']][] = $bc['fullname'];
        }
        foreach ($sealedBags as &$bag) {
            $bag['package_ids'] = $bagPkgMap[$bag['bag_id']] ?? [];
            $custs = array_unique($bagCustMap[$bag['bag_id']] ?? []);
            $bag['customer_names'] = $custs;
            $bag['customer_name'] = count($custs) == 1 ? $custs[0] : (count($custs) > 1 ? count($custs) . ' khách' : '');
        }
        unset($bag);
    }

    // Wholesale orders (cn_warehouse packages)
    $wholesaleOrders = $ToryHub->get_list_safe(
        "SELECT o.id, o.product_code, o.product_name, o.cargo_type, o.customer_id,
            o.weight_charged as order_weight_charged, o.weight_actual as order_weight_actual, o.volume_actual,
            c.fullname as customer_name,
            COUNT(p.id) as pkg_count,
            SUM(COALESCE(p.weight_charged, 0)) as total_weight_charged,
            SUM(COALESCE(p.weight_actual, 0)) as total_weight_actual,
            SUM(p.length_cm * p.width_cm * p.height_cm / 1000000) as total_cbm,
            o.create_date
        FROM `packages` p
        JOIN `package_orders` po ON p.id = po.package_id
        JOIN `orders` o ON po.order_id = o.id
        LEFT JOIN `customers` c ON o.customer_id = c.id
        WHERE o.product_type = 'wholesale' AND p.status = 'cn_warehouse' AND $notInShipment
        GROUP BY o.id
        ORDER BY o.create_date DESC", []
    );

    // Get package IDs per wholesale order
    if (!empty($wholesaleOrders)) {
        $woIds = array_column($wholesaleOrders, 'id');
        $ph = implode(',', array_fill(0, count($woIds), '?'));
        $orderPkgs = $ToryHub->get_list_safe(
            "SELECT po.order_id, p.id as package_id FROM `package_orders` po
             JOIN `packages` p ON po.package_id = p.id
             WHERE po.order_id IN ($ph) AND p.status = 'cn_warehouse' AND $notInShipment", $woIds
        );
        $orderPkgMap = [];
        foreach ($orderPkgs as $op) {
            $orderPkgMap[$op['order_id']][] = $op['package_id'];
        }
        foreach ($wholesaleOrders as &$order) {
            $order['package_ids'] = $orderPkgMap[$order['id']] ?? [];
            // Calculate display weight
            $wA = floatval($order['total_weight_actual'] ?? 0);
            $wC = floatval($order['total_weight_charged'] ?? 0);
            $oWA = floatval($order['order_weight_actual'] ?? 0);
            $oWC = floatval($order['order_weight_charged'] ?? 0);
            $order['display_weight'] = $wA > 0 ? $wA : ($oWA > 0 ? $oWA : ($wC > 0 ? $wC : $oWC));
            $cbm = floatval($order['total_cbm'] ?? 0);
            if (floatval($order['volume_actual'] ?? 0) > 0) $cbm = floatval($order['volume_actual']);
            $order['display_cbm'] = $cbm;
        }
        unset($order);
    }

    api_success([
        'sealed_bags' => $sealedBags,
        'wholesale_orders' => $wholesaleOrders,
        'summary' => [
            'total_pending' => (int)($cntCnWarehouse['cnt'] ?? 0) + (int)($cntPacked['cnt'] ?? 0),
            'cn_warehouse' => (int)($cntCnWarehouse['cnt'] ?? 0),
            'packed' => (int)($cntPacked['cnt'] ?? 0),
        ]
    ]);
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
