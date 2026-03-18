<?php
define('IN_SITE', true);
require_once(__DIR__.'/../../libs/db.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../libs/lang.php');
require_once(__DIR__.'/../../libs/helper.php');
require_once(__DIR__.'/../../libs/session.php');
require_once(__DIR__.'/../../libs/role.php');
require_once(__DIR__.'/../../libs/csrf.php');
require_once(__DIR__.'/../../libs/database/packages.php');
require_once(__DIR__.'/../../libs/database/orders.php');
require_once(__DIR__.'/../../models/is_admin.php');

header('Content-Type: application/json; charset=utf-8');

$csrf = new Csrf();
if (!$csrf->validate()) {
    echo json_encode(['status' => 'error', 'msg' => __('Token không hợp lệ')]);
    exit;
}

$request = input_post('request_name');
$Packages = new Packages();

// ======== ADD PACKAGE ========
if ($request === 'add') {
    $tracking_cn = trim(input_post('tracking_cn'));
    $order_ids = $_POST['order_ids'] ?? [];

    $data = [
        'tracking_cn'     => $tracking_cn,
        'tracking_intl'   => trim(input_post('tracking_intl')),
        'tracking_vn'     => trim(input_post('tracking_vn')),
        'weight_actual'   => floatval(input_post('weight_actual')),
        'length_cm'       => floatval(input_post('length_cm')),
        'width_cm'        => floatval(input_post('width_cm')),
        'height_cm'       => floatval(input_post('height_cm')),
        'status'          => 'cn_warehouse',
        'note'            => trim(input_post('note')),
        'created_by'      => $getUser['id'],
    ];
    $data['weight_volume'] = calculate_volume_weight($data['length_cm'], $data['width_cm'], $data['height_cm']);
    $data['weight_charged'] = calculate_charged_weight($data['weight_actual'], $data['weight_volume']);

    $package_id = $Packages->createPackage($data, $order_ids);
    if ($package_id) {
        add_log($getUser['id'], 'add_package', 'Tạo kiện #' . $package_id);
        echo json_encode(['status' => 'success', 'msg' => __('Tạo kiện hàng thành công'), 'package_id' => $package_id]);
    } else {
        echo json_encode(['status' => 'error', 'msg' => __('Lỗi tạo kiện hàng')]);
    }
    exit;
}

// ======== EDIT PACKAGE ========
if ($request === 'edit') {
    $id = intval(input_post('id'));
    $pkg = $ToryHub->get_row_safe("SELECT * FROM `packages` WHERE `id` = ?", [$id]);
    if (!$pkg) {
        echo json_encode(['status' => 'error', 'msg' => __('Kiện hàng không tồn tại')]);
        exit;
    }

    $data = [
        'tracking_cn'     => trim(input_post('tracking_cn')),
        'tracking_intl'   => trim(input_post('tracking_intl')),
        'tracking_vn'     => trim(input_post('tracking_vn')),
        'weight_actual'   => floatval(input_post('weight_actual')),
        'length_cm'       => floatval(input_post('length_cm')),
        'width_cm'        => floatval(input_post('width_cm')),
        'height_cm'       => floatval(input_post('height_cm')),
        'note'            => trim(input_post('note')),
        'update_date'     => gettime()
    ];
    $data['weight_volume'] = calculate_volume_weight($data['length_cm'], $data['width_cm'], $data['height_cm']);
    $data['weight_charged'] = calculate_charged_weight($data['weight_actual'], $data['weight_volume']);

    $ToryHub->update_safe('packages', $data, "`id` = ?", [$id]);

    // Recalculate linked order fees
    $Orders = new Orders();
    $linked = $Packages->getOrdersByPackage($id);
    foreach ($linked as $ord) {
        $Orders->recalculateFeesFromPackages($ord['id']);
    }

    add_log($getUser['id'], 'edit_package', 'Sửa kiện ' . $pkg['package_code']);
    echo json_encode(['status' => 'success', 'msg' => __('Cập nhật kiện hàng thành công')]);
    exit;
}

// ======== DELETE PACKAGE ========
if ($request === 'delete') {
    $id = intval(input_post('id'));
    $pkg = $ToryHub->get_row_safe("SELECT * FROM `packages` WHERE `id` = ?", [$id]);
    if (!$pkg) {
        echo json_encode(['status' => 'error', 'msg' => __('Kiện hàng không tồn tại')]);
        exit;
    }

    $ToryHub->remove_safe('package_status_history', "`package_id` = ?", [$id]);
    $ToryHub->remove_safe('package_orders', "`package_id` = ?", [$id]);
    $ToryHub->remove_safe('packages', "`id` = ?", [$id]);

    add_log($getUser['id'], 'delete_package', 'Xóa kiện ' . $pkg['package_code']);
    echo json_encode(['status' => 'success', 'msg' => __('Xóa kiện hàng thành công')]);
    exit;
}

// ======== LINK ORDER ========
if ($request === 'link_order') {
    $package_id = intval(input_post('package_id'));
    $order_id = intval(input_post('order_id'));
    $result = $Packages->linkOrder($package_id, $order_id);
    if ($result === 'exists') {
        echo json_encode(['status' => 'error', 'msg' => __('Đơn hàng đã liên kết với kiện này')]);
    } elseif ($result) {
        $Packages->syncOrderStatus($order_id, $getUser['id']);
        echo json_encode(['status' => 'success', 'msg' => __('Liên kết thành công')]);
    } else {
        echo json_encode(['status' => 'error', 'msg' => __('Lỗi liên kết')]);
    }
    exit;
}

// ======== UNLINK ORDER ========
if ($request === 'unlink_order') {
    $package_id = intval(input_post('package_id'));
    $order_id = intval(input_post('order_id'));
    $Packages->unlinkOrder($package_id, $order_id);
    echo json_encode(['status' => 'success', 'msg' => __('Đã gỡ liên kết')]);
    exit;
}

// ======== UPDATE STATUS ========
if ($request === 'update_status') {
    $id = intval(input_post('package_id'));
    $new_status = input_post('new_status');
    $note = trim(input_post('note'));

    $valid = ['cn_warehouse', 'packed', 'loading', 'shipping', 'vn_warehouse', 'delivered', 'returned', 'damaged'];
    if (!in_array($new_status, $valid)) {
        echo json_encode(['status' => 'error', 'msg' => __('Trạng thái không hợp lệ')]);
        exit;
    }

    $result = $Packages->updateStatus($id, $new_status, $getUser['id'], $note);
    if ($result === 'duplicate') {
        echo json_encode(['status' => 'error', 'msg' => __('Kiện hàng đã ở trạng thái này')]);
    } elseif ($result) {
        echo json_encode(['status' => 'success', 'msg' => __('Cập nhật trạng thái kiện thành công')]);
    } else {
        echo json_encode(['status' => 'error', 'msg' => __('Lỗi cập nhật trạng thái')]);
    }
    exit;
}

// ======== BULK UPDATE STATUS ========
if ($request === 'bulk_update_status') {
    $package_ids_raw = input_post('package_ids');
    $new_status = input_post('new_status');
    $note = trim(input_post('note')) ?: __('Cập nhật hàng loạt');

    $valid = ['cn_warehouse', 'packed', 'loading', 'shipping', 'vn_warehouse', 'delivered', 'returned', 'damaged'];
    if (!in_array($new_status, $valid)) {
        echo json_encode(['status' => 'error', 'msg' => __('Trạng thái không hợp lệ')]);
        exit;
    }

    $package_ids = array_filter(array_map('intval', explode(',', $package_ids_raw ?: '')));
    if (empty($package_ids)) {
        echo json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn ít nhất 1 kiện hàng')]);
        exit;
    }

    $updated = 0;
    $skipped = 0;
    foreach ($package_ids as $pid) {
        $result = $Packages->updateStatus($pid, $new_status, $getUser['id'], $note);
        if ($result === 'duplicate' || !$result) {
            $skipped++;
        } else {
            $updated++;
        }
    }

    echo json_encode([
        'status' => 'success',
        'msg' => __('Đã cập nhật') . ' ' . $updated . '/' . count($package_ids) . ' ' . __('kiện') . ($skipped > 0 ? ' (' . __('bỏ qua') . ' ' . $skipped . ')' : ''),
        'updated' => $updated,
        'skipped' => $skipped
    ]);
    exit;
}

// ======== MERGE ========
if ($request === 'merge') {
    $source_ids = json_decode(input_post('source_package_ids'), true);
    if (empty($source_ids) || count($source_ids) < 2) {
        echo json_encode(['status' => 'error', 'msg' => __('Cần chọn ít nhất 2 kiện để gộp')]);
        exit;
    }
    $data = [
        'tracking_intl'   => trim(input_post('tracking_intl')),
        'status'          => input_post('status') ?: 'cn_warehouse',
        'note'            => trim(input_post('note')),
    ];
    $new_id = $Packages->mergePackages($source_ids, $data, $getUser['id']);
    if ($new_id) {
        add_log($getUser['id'], 'merge_packages', 'Gộp kiện ' . implode(',', $source_ids) . ' → ' . $new_id);
        echo json_encode(['status' => 'success', 'msg' => __('Gộp kiện thành công'), 'package_id' => $new_id]);
    } else {
        echo json_encode(['status' => 'error', 'msg' => __('Lỗi gộp kiện')]);
    }
    exit;
}

// ======== SPLIT ========
if ($request === 'split') {
    $source_id = intval(input_post('source_package_id'));
    $splits = json_decode(input_post('splits'), true);
    if (empty($splits) || count($splits) < 2) {
        echo json_encode(['status' => 'error', 'msg' => __('Cần tách thành ít nhất 2 kiện')]);
        exit;
    }
    $new_ids = $Packages->splitPackage($source_id, $splits, $getUser['id']);
    if ($new_ids) {
        add_log($getUser['id'], 'split_package', 'Tách kiện ' . $source_id . ' → ' . implode(',', $new_ids));
        echo json_encode(['status' => 'success', 'msg' => __('Tách kiện thành công'), 'package_ids' => $new_ids]);
    } else {
        echo json_encode(['status' => 'error', 'msg' => __('Lỗi tách kiện')]);
    }
    exit;
}

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

// ======== SEARCH ORDERS (for linking) ========
if ($request === 'search_orders') {
    $keyword = trim(input_post('keyword'));
    if (strlen($keyword) < 2) {
        echo json_encode(['status' => 'error', 'msg' => __('Nhập ít nhất 2 ký tự')]);
        exit;
    }
    $orders = $ToryHub->get_list_safe(
        "SELECT o.id, o.order_code, o.product_name, o.status, c.fullname as customer_name
         FROM `orders` o LEFT JOIN `customers` c ON o.customer_id = c.id
         WHERE o.order_code LIKE ? OR o.cn_tracking LIKE ? OR c.customer_code LIKE ?
         ORDER BY o.create_date DESC LIMIT 20",
        ['%'.$keyword.'%', '%'.$keyword.'%', '%'.$keyword.'%']
    );
    echo json_encode(['status' => 'success', 'orders' => $orders]);
    exit;
}

echo json_encode(['status' => 'error', 'msg' => 'Invalid request']);
