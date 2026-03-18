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

$notInShipment = "p.id NOT IN (SELECT sp.package_id FROM `shipment_packages` sp JOIN `shipments` s ON sp.shipment_id = s.id WHERE s.status IN ('preparing','in_transit'))";

// ======== TÌM KIẾM MÃ HÀNG / MÃ BAO ========
if ($request === 'search_items') {
    $keyword = trim(input_post('keyword') ?? '');
    if (strlen($keyword) < 1) {
        echo json_encode(['status' => 'success', 'items' => []]);
        exit;
    }

    $searchLike = '%' . $keyword . '%';
    $items = [];

    // Wholesale orders
    $orders = $ToryHub->get_list_safe(
        "SELECT o.id, o.product_code as code, 'order' as type, COALESCE(o.cargo_type, '') as cargo_type,
            COUNT(p.id) as pkg_count,
            SUM(COALESCE(p.weight_charged, GREATEST(COALESCE(p.weight_actual,0), COALESCE(p.weight_volume,0)))) as weight,
            SUM(COALESCE(p.length_cm,0) * COALESCE(p.width_cm,0) * COALESCE(p.height_cm,0) / 1000000) as cbm
        FROM `orders` o
        JOIN `package_orders` po ON o.id = po.order_id
        JOIN `packages` p ON po.package_id = p.id
        WHERE o.product_type = 'wholesale' AND p.status = 'cn_warehouse' AND $notInShipment
            AND (o.product_code LIKE ? OR o.order_code LIKE ?)
        GROUP BY o.id
        ORDER BY o.product_code ASC
        LIMIT 20",
        [$searchLike, $searchLike]
    );
    foreach ($orders as $o) {
        $items[] = [
            'id' => $o['id'],
            'code' => $o['code'] ?: ('#' . $o['id']),
            'type' => 'order',
            'cargo_type' => $o['cargo_type'] ?: 'easy',
            'pkg_count' => intval($o['pkg_count']),
            'weight' => round(floatval($o['weight']), 2),
            'cbm' => round(floatval($o['cbm']), 4),
        ];
    }

    // Sealed bags
    $bags = $ToryHub->get_list_safe(
        "SELECT b.id, b.bag_code as code, 'bag' as type,
            COUNT(p.id) as pkg_count,
            COALESCE(b.total_weight, SUM(COALESCE(p.weight_charged, GREATEST(COALESCE(p.weight_actual,0), COALESCE(p.weight_volume,0))))) as weight,
            COALESCE(b.weight_volume, SUM(COALESCE(p.length_cm,0) * COALESCE(p.width_cm,0) * COALESCE(p.height_cm,0) / 1000000)) as cbm
        FROM `bags` b
        JOIN `bag_packages` bp ON b.id = bp.bag_id
        JOIN `packages` p ON bp.package_id = p.id
        WHERE b.status = 'sealed' AND p.status = 'packed' AND $notInShipment
            AND b.bag_code LIKE ?
        GROUP BY b.id
        ORDER BY b.bag_code ASC
        LIMIT 20",
        [$searchLike]
    );
    foreach ($bags as $b) {
        $items[] = [
            'id' => $b['id'],
            'code' => $b['code'],
            'type' => 'bag',
            'cargo_type' => 'easy',
            'pkg_count' => intval($b['pkg_count']),
            'weight' => round(floatval($b['weight']), 2),
            'cbm' => round(floatval($b['cbm']), 4),
        ];
    }

    echo json_encode(['status' => 'success', 'items' => $items]);
    exit;
}

// ======== LẤY TẤT CẢ HÀNG CHỜ XẾP XE ========
if ($request === 'get_all_pending') {
    $items = [];

    // Sealed bags (retail)
    $bags = $ToryHub->get_list_safe(
        "SELECT b.id, b.bag_code as code, 'bag' as type,
            COUNT(p.id) as pkg_count,
            COALESCE(b.total_weight, SUM(COALESCE(p.weight_charged, GREATEST(COALESCE(p.weight_actual,0), COALESCE(p.weight_volume,0))))) as weight,
            COALESCE(b.weight_volume, SUM(COALESCE(p.length_cm,0) * COALESCE(p.width_cm,0) * COALESCE(p.height_cm,0) / 1000000)) as cbm
        FROM `bags` b
        JOIN `bag_packages` bp ON b.id = bp.bag_id
        JOIN `packages` p ON bp.package_id = p.id
        WHERE b.status = 'sealed' AND p.status = 'packed' AND $notInShipment
        GROUP BY b.id
        ORDER BY b.bag_code ASC",
        []
    );
    foreach ($bags as $b) {
        $items[] = [
            'id' => $b['id'],
            'code' => $b['code'],
            'type' => 'bag',
            'cargo_type' => 'easy',
            'pkg_count' => intval($b['pkg_count']),
            'weight' => round(floatval($b['weight']), 2),
            'cbm' => round(floatval($b['cbm']), 4),
        ];
    }

    // Wholesale orders
    $orders = $ToryHub->get_list_safe(
        "SELECT o.id, o.product_code as code, 'order' as type, COALESCE(o.cargo_type, '') as cargo_type,
            COUNT(p.id) as pkg_count,
            SUM(COALESCE(p.weight_charged, GREATEST(COALESCE(p.weight_actual,0), COALESCE(p.weight_volume,0)))) as weight,
            SUM(COALESCE(p.length_cm,0) * COALESCE(p.width_cm,0) * COALESCE(p.height_cm,0) / 1000000) as cbm
        FROM `orders` o
        JOIN `package_orders` po ON o.id = po.order_id
        JOIN `packages` p ON po.package_id = p.id
        WHERE o.product_type = 'wholesale' AND p.status = 'cn_warehouse' AND $notInShipment
        GROUP BY o.id
        ORDER BY o.product_code ASC",
        []
    );
    foreach ($orders as $o) {
        $items[] = [
            'id' => $o['id'],
            'code' => $o['code'] ?: ('#' . $o['id']),
            'type' => 'order',
            'cargo_type' => $o['cargo_type'] ?: 'easy',
            'pkg_count' => intval($o['pkg_count']),
            'weight' => round(floatval($o['weight']), 2),
            'cbm' => round(floatval($o['cbm']), 4),
        ];
    }

    echo json_encode(['status' => 'success', 'items' => $items]);
    exit;
}

// ======== LƯU CƯỚC NỘI ĐỊA ========
if ($request === 'save_domestic_cost') {
    $itemType = input_post('item_type');
    $itemId = intval(input_post('item_id'));
    $cost = floatval(str_replace(['.', ','], ['', '.'], input_post('cost') ?? '0'));

    if (!$itemId || !in_array($itemType, ['bag', 'order'])) {
        echo json_encode(['status' => 'error', 'msg' => 'Thiếu thông tin']);
        exit;
    }

    $table = $itemType === 'bag' ? 'bags' : 'orders';
    $ToryHub->update_safe($table, ['domestic_cost' => $cost], 'id = ?', [$itemId]);

    echo json_encode(['status' => 'success']);
    exit;
}

// ======== LƯU ĐƠN GIÁ VẬN CHUYỂN ========
if ($request === 'save_rates') {
    $itemType = input_post('item_type');
    $itemId = intval(input_post('item_id'));
    $rateKg = floatval(str_replace(['.', ','], ['', '.'], input_post('rate_kg') ?? '0'));
    $rateCbm = floatval(str_replace(['.', ','], ['', '.'], input_post('rate_cbm') ?? '0'));

    if (!$itemId || !in_array($itemType, ['bag', 'order'])) {
        echo json_encode(['status' => 'error', 'msg' => 'Thiếu thông tin']);
        exit;
    }

    $table = $itemType === 'bag' ? 'bags' : 'orders';
    $ToryHub->update_safe($table, ['custom_rate_kg' => $rateKg, 'custom_rate_cbm' => $rateCbm], 'id = ?', [$itemId]);

    echo json_encode(['status' => 'success']);
    exit;
}

echo json_encode(['status' => 'error', 'msg' => __('Yêu cầu không hợp lệ')]);
