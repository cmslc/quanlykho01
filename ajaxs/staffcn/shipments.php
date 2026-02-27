<?php
define('IN_SITE', true);
require_once(__DIR__.'/../../libs/db.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../libs/lang.php');
require_once(__DIR__.'/../../libs/helper.php');
require_once(__DIR__.'/../../libs/session.php');
require_once(__DIR__.'/../../libs/role.php');
require_once(__DIR__.'/../../libs/csrf.php');
require_once(__DIR__.'/../../libs/database/shipments.php');
require_once(__DIR__.'/../../models/is_staffcn.php');

header('Content-Type: application/json; charset=utf-8');

$csrf = new Csrf();
if (!$csrf->validate()) {
    echo json_encode(['status' => 'error', 'msg' => __('Token không hợp lệ')]);
    exit;
}

$request = input_post('request_name');
$Shipments = new Shipments();

// ======== CREATE SHIPMENT ========
if ($request === 'create') {
    $data = [
        'truck_plate'     => strtoupper(trim(input_post('truck_plate'))),
        'driver_name'     => trim(input_post('driver_name')),
        'driver_phone'    => trim(input_post('driver_phone')),
        'route'           => trim(input_post('route')),
        'max_weight'      => floatval(input_post('max_weight')) ?: null,
        'shipping_cost'   => floatval(input_post('shipping_cost')) ?: null,
        'note'            => trim(input_post('note')),
        'created_by'      => $getUser['id'],
    ];

    $shipment_id = $Shipments->createShipment($data);
    if ($shipment_id) {
        $shipment = $ToryHub->get_row_safe("SELECT * FROM `shipments` WHERE `id` = ?", [$shipment_id]);
        add_log($getUser['id'], 'create_shipment', 'Tạo chuyến xe: ' . $shipment['shipment_code']);
        echo json_encode([
            'status' => 'success',
            'msg' => __('Tạo chuyến xe thành công'),
            'shipment_id' => $shipment_id,
            'shipment_code' => $shipment['shipment_code']
        ]);
    } else {
        echo json_encode(['status' => 'error', 'msg' => __('Lỗi tạo chuyến xe')]);
    }
    exit;
}

// ======== EDIT SHIPMENT ========
if ($request === 'edit') {
    $id = intval(input_post('id'));
    $shipment = $ToryHub->get_row_safe("SELECT * FROM `shipments` WHERE `id` = ?", [$id]);
    if (!$shipment) {
        echo json_encode(['status' => 'error', 'msg' => __('Chuyến xe không tồn tại')]);
        exit;
    }

    $data = [
        'truck_plate'     => strtoupper(trim(input_post('truck_plate'))),
        'driver_name'     => trim(input_post('driver_name')),
        'driver_phone'    => trim(input_post('driver_phone')),
        'route'           => trim(input_post('route')),
        'max_weight'      => floatval(input_post('max_weight')) ?: null,
        'shipping_cost'   => floatval(input_post('shipping_cost')) ?: null,
        'note'            => trim(input_post('note')),
        'update_date'     => gettime()
    ];

    $ToryHub->update_safe('shipments', $data, "`id` = ?", [$id]);
    add_log($getUser['id'], 'edit_shipment', 'Sửa chuyến xe: ' . $shipment['shipment_code']);
    echo json_encode(['status' => 'success', 'msg' => __('Cập nhật chuyến xe thành công')]);
    exit;
}

// ======== DELETE SHIPMENT (only preparing) ========
if ($request === 'delete') {
    $id = intval(input_post('id'));
    $shipment = $ToryHub->get_row_safe("SELECT * FROM `shipments` WHERE `id` = ?", [$id]);
    if (!$shipment) {
        echo json_encode(['status' => 'error', 'msg' => __('Chuyến xe không tồn tại')]);
        exit;
    }
    if ($shipment['status'] !== 'preparing') {
        echo json_encode(['status' => 'error', 'msg' => __('Chỉ có thể xóa chuyến xe đang chuẩn bị')]);
        exit;
    }

    // Revert package statuses before deleting
    require_once(__DIR__.'/../../libs/database/packages.php');
    $Packages = new Packages();

    $pkgs = $ToryHub->get_list_safe(
        "SELECT sp.package_id, p.status FROM `shipment_packages` sp
         JOIN `packages` p ON sp.package_id = p.id
         WHERE sp.shipment_id = ?", [$id]
    );

    $affectedBagIds = [];
    foreach ($pkgs as $p) {
        $bagRow = $ToryHub->get_row_safe(
            "SELECT bag_id FROM `bag_packages` WHERE `package_id` = ?", [$p['package_id']]
        );
        $revertTo = $bagRow ? 'packed' : 'cn_warehouse';
        $Packages->updateStatus(
            $p['package_id'], $revertTo, $getUser['id'],
            __('Xóa chuyến') . ' ' . $shipment['shipment_code']
        );
        if ($bagRow) {
            $affectedBagIds[$bagRow['bag_id']] = true;
        }
    }

    // Revert bag: nếu tất cả kiện trong bao đã về 'packed' thì bao về 'sealed'
    foreach (array_keys($affectedBagIds) as $bagId) {
        $bag = $ToryHub->get_row_safe("SELECT * FROM `bags` WHERE `id` = ?", [$bagId]);
        if (!$bag || $bag['status'] !== 'loading') continue;
        $notPacked = $ToryHub->num_rows_safe(
            "SELECT p.id FROM `bag_packages` bp
             JOIN `packages` p ON bp.package_id = p.id
             WHERE bp.bag_id = ? AND p.status != 'packed'", [$bagId]
        );
        if ($notPacked === 0) {
            $ToryHub->update_safe('bags', ['status' => 'sealed', 'update_date' => gettime()], 'id = ?', [$bagId]);
        }
    }

    $ToryHub->remove_safe('shipment_packages', "`shipment_id` = ?", [$id]);
    $ToryHub->remove_safe('shipments', "`id` = ?", [$id]);

    add_log($getUser['id'], 'delete_shipment', 'Xóa chuyến xe: ' . $shipment['shipment_code']);
    echo json_encode(['status' => 'success', 'msg' => __('Đã xóa chuyến xe')]);
    exit;
}

// ======== ADD PACKAGES ========
if ($request === 'add_packages') {
    $shipment_id = intval(input_post('shipment_id'));
    $shipment = $ToryHub->get_row_safe("SELECT * FROM `shipments` WHERE `id` = ?", [$shipment_id]);
    if (!$shipment) {
        echo json_encode(['status' => 'error', 'msg' => __('Chuyến xe không tồn tại')]);
        exit;
    }
    if ($shipment['status'] !== 'preparing') {
        echo json_encode(['status' => 'error', 'msg' => __('Chỉ có thể thêm kiện khi chuyến đang chuẩn bị')]);
        exit;
    }

    $package_ids_raw = input_post('package_ids');
    $package_ids = array_filter(array_map('intval', explode(',', $package_ids_raw ?: '')));
    if (empty($package_ids)) {
        echo json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn ít nhất 1 kiện hàng')]);
        exit;
    }

    $result = $Shipments->addPackages($shipment_id, $package_ids, $getUser['id']);
    add_log($getUser['id'], 'add_to_shipment', 'Thêm ' . $result['added'] . ' kiện vào chuyến ' . $shipment['shipment_code']);

    echo json_encode([
        'status' => 'success',
        'msg' => __('Đã thêm') . ' ' . $result['added'] . ' ' . __('kiện vào chuyến') . ($result['skipped'] > 0 ? ' (' . __('bỏ qua') . ' ' . $result['skipped'] . ')' : ''),
        'added' => $result['added'],
        'skipped' => $result['skipped']
    ]);
    exit;
}

// ======== REMOVE PACKAGE ========
if ($request === 'remove_package') {
    $shipment_id = intval(input_post('shipment_id'));
    $package_id = intval(input_post('package_id'));

    $shipment = $ToryHub->get_row_safe("SELECT * FROM `shipments` WHERE `id` = ?", [$shipment_id]);
    if (!$shipment || $shipment['status'] !== 'preparing') {
        echo json_encode(['status' => 'error', 'msg' => __('Chỉ có thể gỡ kiện khi chuyến đang chuẩn bị')]);
        exit;
    }

    // Revert package status before removing
    require_once(__DIR__.'/../../libs/database/packages.php');
    $Packages = new Packages();
    $bagRow = $ToryHub->get_row_safe(
        "SELECT bag_id FROM `bag_packages` WHERE `package_id` = ?", [$package_id]
    );
    $revertTo = $bagRow ? 'packed' : 'cn_warehouse';
    $Packages->updateStatus($package_id, $revertTo, $getUser['id'], __('Gỡ khỏi chuyến') . ' ' . $shipment['shipment_code']);

    // Revert bag if all its packages are now packed
    if ($bagRow) {
        $bag = $ToryHub->get_row_safe("SELECT * FROM `bags` WHERE `id` = ?", [$bagRow['bag_id']]);
        if ($bag && $bag['status'] === 'loading') {
            $notPacked = $ToryHub->num_rows_safe(
                "SELECT p.id FROM `bag_packages` bp
                 JOIN `packages` p ON bp.package_id = p.id
                 WHERE bp.bag_id = ? AND p.status != 'packed'", [$bagRow['bag_id']]
            );
            if ($notPacked === 0) {
                $ToryHub->update_safe('bags', ['status' => 'sealed', 'update_date' => gettime()], 'id = ?', [$bagRow['bag_id']]);
            }
        }
    }

    $Shipments->removePackage($shipment_id, $package_id);
    echo json_encode(['status' => 'success', 'msg' => __('Đã gỡ kiện khỏi chuyến')]);
    exit;
}

// ======== UPDATE STATUS ========
if ($request === 'update_status') {
    $id = intval(input_post('shipment_id'));
    $new_status = input_post('new_status');

    $valid = ['preparing', 'in_transit', 'arrived', 'completed'];
    if (!in_array($new_status, $valid)) {
        echo json_encode(['status' => 'error', 'msg' => __('Trạng thái không hợp lệ')]);
        exit;
    }

    $shipment = $ToryHub->get_row_safe("SELECT * FROM `shipments` WHERE `id` = ?", [$id]);
    if (!$shipment) {
        echo json_encode(['status' => 'error', 'msg' => __('Chuyến xe không tồn tại')]);
        exit;
    }

    $result = $Shipments->updateStatus($id, $new_status, $getUser['id']);
    if ($result) {
        add_log($getUser['id'], 'update_shipment_status', 'Cập nhật chuyến ' . $shipment['shipment_code'] . ' → ' . $new_status);
        echo json_encode(['status' => 'success', 'msg' => __('Cập nhật trạng thái chuyến xe thành công')]);
    } else {
        echo json_encode(['status' => 'error', 'msg' => __('Lỗi cập nhật trạng thái')]);
    }
    exit;
}

// ======== GET PREPARING SHIPMENTS (for modal in orders-list) ========
if ($request === 'get_preparing') {
    $shipments = $ToryHub->get_list_safe(
        "SELECT * FROM `shipments` WHERE `status` = 'preparing' ORDER BY `create_date` DESC", []
    );
    echo json_encode(['status' => 'success', 'shipments' => $shipments]);
    exit;
}

echo json_encode(['status' => 'error', 'msg' => 'Invalid request']);
