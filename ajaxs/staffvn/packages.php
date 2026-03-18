<?php
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

// ======== GET PACKAGES BY ORDER ========
if ($request === 'get_order_packages') {
    $order_id = intval(input_post('order_id'));
    $packages = $ToryHub->get_list_safe(
        "SELECT p.*, o.product_name FROM `packages` p
         JOIN `package_orders` po ON p.id = po.package_id
         JOIN `orders` o ON po.order_id = o.id
         WHERE po.order_id = ? ORDER BY p.id ASC", [$order_id]
    );
    $result = [];
    foreach ($packages as $p) {
        $cbm = ($p['length_cm'] * $p['width_cm'] * $p['height_cm']) / 1000000;
        $result[] = [
            'id' => $p['id'],
            'package_code' => $p['package_code'],
            'tracking_cn' => $p['tracking_cn'] ?: '',
            'product_name' => $p['product_name'] ?: '',
            'weight_actual' => floatval($p['weight_actual']),
            'length_cm' => floatval($p['length_cm']),
            'width_cm' => floatval($p['width_cm']),
            'height_cm' => floatval($p['height_cm']),
            'cbm' => round($cbm, 6),
            'status' => $p['status']
        ];
    }
    echo json_encode(['status' => 'success', 'packages' => $result]);
    exit;
}

// ======== GET BAG PACKAGES ========
if ($request === 'get_bag_packages') {
    $bag_id = intval(input_post('bag_id'));
    $packages = $ToryHub->get_list_safe(
        "SELECT p.* FROM `packages` p
         JOIN `bag_packages` bp ON p.id = bp.package_id
         WHERE bp.bag_id = ? ORDER BY p.id ASC", [$bag_id]
    );
    $result = [];
    foreach ($packages as $p) {
        $cbm = ($p['length_cm'] * $p['width_cm'] * $p['height_cm']) / 1000000;
        $result[] = [
            'id' => $p['id'],
            'package_code' => $p['package_code'],
            'tracking_cn' => $p['tracking_cn'] ?: '',
            'weight_actual' => floatval($p['weight_actual']),
            'length_cm' => floatval($p['length_cm']),
            'width_cm' => floatval($p['width_cm']),
            'height_cm' => floatval($p['height_cm']),
            'cbm' => round($cbm, 6),
            'status' => $p['status']
        ];
    }
    echo json_encode(['status' => 'success', 'packages' => $result]);
    exit;
}

echo json_encode(['status' => 'error', 'msg' => 'Invalid request']);
