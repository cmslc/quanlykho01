<?php
/**
 * Bags (Bao hàng) AJAX handler
 * Actions: create, scan, unscan, seal, ship
 */
define('IN_SITE', true);
require_once(__DIR__.'/../../libs/db.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../libs/lang.php');
require_once(__DIR__.'/../../libs/helper.php');
require_once(__DIR__.'/../../libs/session.php');
require_once(__DIR__.'/../../libs/role.php');
require_once(__DIR__.'/../../libs/csrf.php');
require_once(__DIR__.'/../../libs/database/packages.php');
require_once(__DIR__.'/../../models/is_admin.php');

header('Content-Type: application/json; charset=utf-8');

$csrf = new Csrf();
if (!$csrf->validate()) {
    echo json_encode(['status' => 'error', 'msg' => 'Token không hợp lệ']);
    exit;
}

$request = input_post('request_name');

// ======== CREATE BAG ========
if ($request === 'create') {
    $shipping_method = in_array(input_post('shipping_method'), ['road', 'air']) ? input_post('shipping_method') : 'road';
    $note = trim(input_post('note'));
    $bag_code_input = strtoupper(trim(input_post('bag_code')));

    if ($bag_code_input !== '') {
        // Manual bag code - check duplicate
        $exists = $CMSNT->get_row_safe("SELECT `id` FROM `bags` WHERE `bag_code` = ?", [$bag_code_input]);
        if ($exists) {
            echo json_encode(['status' => 'error', 'msg' => __('Mã bao đã tồn tại') . ': ' . $bag_code_input]);
            exit;
        }
        $bag_code = $bag_code_input;
    } else {
        // Auto-generate bag code: BAO-YYYYMMDD-NNN
        $prefix = 'BAO-' . date('Ymd') . '-';
        $last = $CMSNT->get_row_safe(
            "SELECT `bag_code` FROM `bags` WHERE `bag_code` LIKE ? ORDER BY `id` DESC LIMIT 1",
            [$prefix . '%']
        );
        $seq = $last ? intval(substr($last['bag_code'], -3)) + 1 : 1;
        $bag_code = $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }

    $result = $CMSNT->insert_safe("bags", [
        'bag_code' => $bag_code,
        'status' => 'open',
        'total_packages' => 0,
        'total_weight' => 0,
        'shipping_method' => $shipping_method,
        'note' => $note,
        'created_by' => $getUser['id'],
        'create_date' => gettime(),
        'update_date' => gettime()
    ]);

    if (!$result) {
        echo json_encode(['status' => 'error', 'msg' => __('Lỗi tạo bao hàng')]);
        exit;
    }

    $bagId = $CMSNT->insert_id();
    add_log('create_bag', 'Tạo bao hàng: ' . $bag_code);

    echo json_encode([
        'status' => 'success',
        'msg' => __('Tạo bao hàng thành công'),
        'bag_id' => $bagId,
        'bag_code' => $bag_code
    ]);
    exit;
}

// ======== SCAN PACKAGE INTO BAG ========
if ($request === 'scan') {
    $bag_id = intval(input_post('bag_id'));
    $tracking_cn = strtoupper(trim(input_post('tracking_cn')));

    if (!$tracking_cn) {
        echo json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập mã vận đơn')]);
        exit;
    }

    // Check bag exists and is open
    $bag = $CMSNT->get_row_safe("SELECT * FROM `bags` WHERE `id` = ?", [$bag_id]);
    if (!$bag) {
        echo json_encode(['status' => 'error', 'msg' => __('Bao hàng không tồn tại')]);
        exit;
    }
    if ($bag['status'] !== 'open') {
        echo json_encode(['status' => 'error', 'msg' => __('Bao hàng đã đóng, không thể thêm kiện')]);
        exit;
    }

    // Find package by tracking_cn
    $package = $CMSNT->get_row_safe("SELECT * FROM `packages` WHERE `tracking_cn` = ?", [$tracking_cn]);
    if (!$package) {
        echo json_encode(['status' => 'error', 'msg' => __('Không tìm thấy kiện hàng với mã') . ': ' . $tracking_cn]);
        exit;
    }

    // Check package is in cn_warehouse status
    if ($package['status'] !== 'cn_warehouse') {
        $currentStatus = display_package_status($package['status']);
        echo json_encode(['status' => 'error', 'msg' => __('Kiện hàng không ở trạng thái kho Trung Quốc. Trạng thái hiện tại') . ': ' . strip_tags($currentStatus)]);
        exit;
    }

    // Check if already in a bag
    $existingBag = $CMSNT->get_row_safe(
        "SELECT b.bag_code FROM `bag_packages` bp JOIN `bags` b ON bp.bag_id = b.id WHERE bp.package_id = ?",
        [$package['id']]
    );
    if ($existingBag) {
        echo json_encode(['status' => 'error', 'msg' => __('Kiện hàng đã nằm trong bao') . ': ' . $existingBag['bag_code']]);
        exit;
    }

    // Add to bag
    $CMSNT->insert_safe("bag_packages", [
        'bag_id' => $bag_id,
        'package_id' => $package['id'],
        'scanned_by' => $getUser['id'],
        'scanned_at' => gettime()
    ]);

    // Update package status to packed
    $Packages = new Packages();
    $Packages->updateStatus($package['id'], 'packed', $getUser['id'], __('Đóng vào bao') . ' ' . $bag['bag_code']);

    // Update bag totals
    $totals = $CMSNT->get_row_safe(
        "SELECT COUNT(*) as cnt, COALESCE(SUM(p.weight_charged), 0) as total_w
         FROM `bag_packages` bp JOIN `packages` p ON bp.package_id = p.id
         WHERE bp.bag_id = ?", [$bag_id]
    );
    $CMSNT->update_safe("bags", [
        'total_packages' => $totals['cnt'],
        'total_weight' => $totals['total_w'],
        'update_date' => gettime()
    ], "id = ?", [$bag_id]);

    // Get order info for this package
    $orderInfo = $CMSNT->get_row_safe(
        "SELECT o.id, o.product_code, o.product_name, o.product_type, c.fullname as customer_name
         FROM `package_orders` po
         JOIN `orders` o ON po.order_id = o.id
         LEFT JOIN `customers` c ON o.customer_id = c.id
         WHERE po.package_id = ? LIMIT 1", [$package['id']]
    );

    echo json_encode([
        'status' => 'success',
        'msg' => __('Đã thêm kiện vào bao'),
        'package' => [
            'id' => $package['id'],
            'package_code' => $package['package_code'],
            'tracking_cn' => $package['tracking_cn'],
            'weight_charged' => $package['weight_charged'],
            'order_product' => $orderInfo['product_name'] ?? '',
            'order_code' => $orderInfo['product_code'] ?? '',
            'customer_name' => $orderInfo['customer_name'] ?? '',
            'product_type' => $orderInfo['product_type'] ?? ''
        ],
        'bag_total_packages' => intval($totals['cnt']),
        'bag_total_weight' => floatval($totals['total_w'])
    ]);
    exit;
}

// ======== UNSCAN (remove package from bag) ========
if ($request === 'unscan') {
    $bag_id = intval(input_post('bag_id'));
    $package_id = intval(input_post('package_id'));

    $bag = $CMSNT->get_row_safe("SELECT * FROM `bags` WHERE `id` = ?", [$bag_id]);
    if (!$bag || $bag['status'] !== 'open') {
        echo json_encode(['status' => 'error', 'msg' => __('Bao hàng đã đóng, không thể gỡ kiện')]);
        exit;
    }

    // Remove from bag
    $CMSNT->remove_safe("bag_packages", "bag_id = ? AND package_id = ?", [$bag_id, $package_id]);

    // Revert package status to cn_warehouse
    $Packages = new Packages();
    $package = $CMSNT->get_row_safe("SELECT * FROM `packages` WHERE `id` = ?", [$package_id]);
    if ($package && $package['status'] === 'packed') {
        $Packages->updateStatus($package_id, 'cn_warehouse', $getUser['id'], __('Gỡ khỏi bao') . ' ' . $bag['bag_code']);
    }

    // Update bag totals
    $totals = $CMSNT->get_row_safe(
        "SELECT COUNT(*) as cnt, COALESCE(SUM(p.weight_charged), 0) as total_w
         FROM `bag_packages` bp JOIN `packages` p ON bp.package_id = p.id
         WHERE bp.bag_id = ?", [$bag_id]
    );
    $CMSNT->update_safe("bags", [
        'total_packages' => $totals['cnt'],
        'total_weight' => $totals['total_w'],
        'update_date' => gettime()
    ], "id = ?", [$bag_id]);

    echo json_encode([
        'status' => 'success',
        'msg' => __('Đã gỡ kiện khỏi bao'),
        'bag_total_packages' => intval($totals['cnt']),
        'bag_total_weight' => floatval($totals['total_w'])
    ]);
    exit;
}

// ======== UPDATE BAG WEIGHT & DIMENSIONS ========
if ($request === 'update_bag_weight') {
    $bag_id = intval(input_post('bag_id'));
    $weight = floatval(input_post('weight'));
    $length_cm = floatval(input_post('length_cm'));
    $width_cm = floatval(input_post('width_cm'));
    $height_cm = floatval(input_post('height_cm'));
    $weight_volume = ($length_cm * $width_cm * $height_cm) / 1000000;

    $bag = $CMSNT->get_row_safe("SELECT * FROM `bags` WHERE `id` = ?", [$bag_id]);
    if (!$bag || $bag['status'] !== 'open') {
        echo json_encode(['status' => 'error', 'msg' => __('Bao hàng đã đóng, không thể cập nhật')]);
        exit;
    }

    $CMSNT->update_safe("bags", [
        'total_weight' => $weight,
        'length_cm' => $length_cm,
        'width_cm' => $width_cm,
        'height_cm' => $height_cm,
        'weight_volume' => round($weight_volume, 2),
        'update_date' => gettime()
    ], "id = ?", [$bag_id]);

    echo json_encode([
        'status' => 'success',
        'msg' => __('Đã cập nhật thông tin bao'),
        'bag_total_weight' => floatval($weight)
    ]);
    exit;
}

// ======== SEAL BAG ========
if ($request === 'seal') {
    $bag_id = intval(input_post('bag_id'));

    $bag = $CMSNT->get_row_safe("SELECT * FROM `bags` WHERE `id` = ?", [$bag_id]);
    if (!$bag) {
        echo json_encode(['status' => 'error', 'msg' => __('Bao hàng không tồn tại')]);
        exit;
    }
    if ($bag['status'] !== 'open') {
        echo json_encode(['status' => 'error', 'msg' => __('Bao hàng đã đóng rồi')]);
        exit;
    }
    if ($bag['total_packages'] < 1) {
        echo json_encode(['status' => 'error', 'msg' => __('Bao hàng chưa có kiện nào')]);
        exit;
    }

    $CMSNT->update_safe("bags", [
        'status' => 'sealed',
        'sealed_by' => $getUser['id'],
        'sealed_date' => gettime(),
        'update_date' => gettime()
    ], "id = ?", [$bag_id]);

    add_log('seal_bag', 'Đóng bao: ' . $bag['bag_code'] . ' (' . $bag['total_packages'] . ' kiện)');

    echo json_encode(['status' => 'success', 'msg' => __('Đã đóng bao thành công')]);
    exit;
}

// ======== UNSEAL BAG (sealed -> open) ========
if ($request === 'unseal') {
    $bag_id = intval(input_post('bag_id'));

    $bag = $CMSNT->get_row_safe("SELECT * FROM `bags` WHERE `id` = ?", [$bag_id]);
    if (!$bag) {
        echo json_encode(['status' => 'error', 'msg' => __('Bao hàng không tồn tại')]);
        exit;
    }
    if ($bag['status'] !== 'sealed') {
        echo json_encode(['status' => 'error', 'msg' => __('Chỉ có thể mở lại bao đang ở trạng thái đã đóng')]);
        exit;
    }

    $CMSNT->update_safe("bags", [
        'status' => 'open',
        'sealed_by' => null,
        'sealed_date' => null,
        'update_date' => gettime()
    ], "id = ?", [$bag_id]);

    add_log('unseal_bag', 'Mở lại bao: ' . $bag['bag_code']);

    echo json_encode(['status' => 'success', 'msg' => __('Đã mở lại bao thành công')]);
    exit;
}

// ======== SHIP BAG (sealed -> shipping, all packages -> shipping) ========
if ($request === 'ship') {
    $bag_id = intval(input_post('bag_id'));

    $bag = $CMSNT->get_row_safe("SELECT * FROM `bags` WHERE `id` = ?", [$bag_id]);
    if (!$bag) {
        echo json_encode(['status' => 'error', 'msg' => __('Bao hàng không tồn tại')]);
        exit;
    }
    if ($bag['status'] !== 'sealed') {
        echo json_encode(['status' => 'error', 'msg' => __('Bao hàng cần ở trạng thái đã đóng để xuất vận chuyển')]);
        exit;
    }

    // Update bag status
    $CMSNT->update_safe("bags", [
        'status' => 'shipping',
        'update_date' => gettime()
    ], "id = ?", [$bag_id]);

    // Update all packages in this bag to shipping
    $Packages = new Packages();
    $bagPackages = $CMSNT->get_list_safe(
        "SELECT package_id FROM `bag_packages` WHERE `bag_id` = ?", [$bag_id]
    );
    foreach ($bagPackages as $bp) {
        $Packages->updateStatus($bp['package_id'], 'shipping', $getUser['id'], __('Xuất vận chuyển bao') . ' ' . $bag['bag_code']);
    }

    add_log('ship_bag', 'Xuất vận chuyển bao: ' . $bag['bag_code']);

    echo json_encode(['status' => 'success', 'msg' => __('Xuất vận chuyển thành công') . ' (' . count($bagPackages) . ' ' . __('kiện') . ')']);
    exit;
}

// ======== DELETE BAG (only if open) ========
if ($request === 'delete') {
    $bag_id = intval(input_post('bag_id'));

    $bag = $CMSNT->get_row_safe("SELECT * FROM `bags` WHERE `id` = ?", [$bag_id]);
    if (!$bag) {
        echo json_encode(['status' => 'error', 'msg' => __('Bao hàng không tồn tại')]);
        exit;
    }
    if ($bag['status'] !== 'open') {
        echo json_encode(['status' => 'error', 'msg' => __('Chỉ có thể xóa bao đang mở')]);
        exit;
    }

    // Revert all packages back to cn_warehouse
    $Packages = new Packages();
    $bagPackages = $CMSNT->get_list_safe(
        "SELECT bp.package_id FROM `bag_packages` bp JOIN `packages` p ON bp.package_id = p.id WHERE bp.bag_id = ? AND p.status = 'packed'",
        [$bag_id]
    );
    foreach ($bagPackages as $bp) {
        $Packages->updateStatus($bp['package_id'], 'cn_warehouse', $getUser['id'], __('Xóa bao') . ' ' . $bag['bag_code']);
    }

    $CMSNT->remove_safe("bag_packages", "bag_id = ?", [$bag_id]);
    $CMSNT->remove_safe("bags", "id = ?", [$bag_id]);

    add_log('delete_bag', 'Xóa bao hàng: ' . $bag['bag_code']);

    echo json_encode(['status' => 'success', 'msg' => __('Đã xóa bao hàng')]);
    exit;
}

// ======== UPLOAD IMAGES ========
if ($request === 'upload_images') {
    $bag_id = intval(input_post('bag_id'));

    $bag = $CMSNT->get_row_safe("SELECT * FROM `bags` WHERE `id` = ?", [$bag_id]);
    if (!$bag) {
        echo json_encode(['status' => 'error', 'msg' => __('Bao hàng không tồn tại')]);
        exit;
    }

    if (empty($_FILES['images']['name'][0])) {
        echo json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn ảnh')]);
        exit;
    }

    $currentImages = !empty($bag['images']) ? array_filter(array_map('trim', explode(',', $bag['images']))) : [];
    $newPaths = [];

    foreach ($_FILES['images']['name'] as $i => $name) {
        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK || empty($name)) continue;
        $file = [
            'name'     => $_FILES['images']['name'][$i],
            'type'     => $_FILES['images']['type'][$i],
            'tmp_name' => $_FILES['images']['tmp_name'][$i],
            'error'    => $_FILES['images']['error'][$i],
            'size'     => $_FILES['images']['size'][$i],
        ];
        $upload = upload_image($file, 'bags');
        if ($upload['status'] === 'error') {
            echo json_encode(['status' => 'error', 'msg' => $upload['msg']]);
            exit;
        }
        $newPaths[] = $upload['path'];
    }

    $allImages = array_merge($currentImages, $newPaths);
    $CMSNT->update_safe("bags", [
        'images' => implode(',', $allImages),
        'update_date' => gettime()
    ], "id = ?", [$bag_id]);

    // Return all images with URLs
    $imageList = [];
    foreach ($allImages as $img) {
        $imageList[] = ['path' => $img, 'url' => get_upload_url($img)];
    }

    echo json_encode([
        'status' => 'success',
        'msg' => __('Tải ảnh thành công'),
        'images' => $imageList
    ]);
    exit;
}

// ======== DELETE IMAGE ========
if ($request === 'delete_image') {
    $bag_id = intval(input_post('bag_id'));
    $image_path = trim(input_post('image_path'));

    $bag = $CMSNT->get_row_safe("SELECT * FROM `bags` WHERE `id` = ?", [$bag_id]);
    if (!$bag) {
        echo json_encode(['status' => 'error', 'msg' => __('Bao hàng không tồn tại')]);
        exit;
    }

    $currentImages = !empty($bag['images']) ? array_filter(array_map('trim', explode(',', $bag['images']))) : [];
    $remaining = array_values(array_filter($currentImages, function($img) use ($image_path) {
        return $img !== $image_path;
    }));

    // Delete file
    delete_uploaded_file($image_path);

    $CMSNT->update_safe("bags", [
        'images' => !empty($remaining) ? implode(',', $remaining) : null,
        'update_date' => gettime()
    ], "id = ?", [$bag_id]);

    echo json_encode([
        'status' => 'success',
        'msg' => __('Đã xóa ảnh')
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'msg' => 'Invalid request']);
