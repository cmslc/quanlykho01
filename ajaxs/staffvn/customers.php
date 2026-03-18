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

// Only finance_vn can manage customers
if (!isset($getUser['role']) || $getUser['role'] !== 'finance_vn') {
    echo json_encode(['status' => 'error', 'msg' => 'Không có quyền truy cập']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$csrf = new Csrf();
if (!$csrf->validate()) {
    echo json_encode(['status' => 'error', 'msg' => 'Token không hợp lệ']);
    exit;
}

$request = input_post('request_name');

// ======== ADD ========
if ($request === 'add') {
    $fullname = trim(input_post('fullname'));
    $phone = trim(input_post('phone'));
    $email = trim(input_post('email'));
    $customer_type = input_post('customer_type');
    $address_vn = trim(input_post('address_vn'));
    $zalo = trim(input_post('zalo'));
    $wechat = trim(input_post('wechat'));
    $note = trim(input_post('note'));

    if (empty($fullname)) {
        echo json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập họ tên')]);
        exit;
    }

    // Check duplicate fullname
    $existsName = $ToryHub->get_row_safe("SELECT `id` FROM `customers` WHERE `fullname` = ?", [$fullname]);
    if ($existsName) {
        echo json_encode(['status' => 'error', 'msg' => __('Tên khách hàng đã tồn tại')]);
        exit;
    }

    // Check duplicate phone
    if (!empty($phone)) {
        $exists = $ToryHub->get_row_safe("SELECT `id` FROM `customers` WHERE `phone` = ?", [$phone]);
        if ($exists) {
            echo json_encode(['status' => 'error', 'msg' => __('Số điện thoại đã tồn tại')]);
            exit;
        }
    }

    $validTypes = ['normal', 'vip', 'agent'];
    if (!in_array($customer_type, $validTypes)) {
        $customer_type = 'normal';
    }

    $ToryHub->insert_safe("customers", [
        'fullname' => $fullname,
        'phone' => $phone,
        'email' => $email,
        'customer_type' => $customer_type,
        'address_vn' => $address_vn,
        'zalo' => $zalo,
        'wechat' => $wechat,
        'note' => $note,
        'customer_code' => '',
        'total_orders' => 0,
        'total_spent' => 0,
        'balance' => 0,
        'created_by' => $getUser['id'],
        'create_date' => gettime(),
        'update_date' => gettime()
    ]);

    $newId = $ToryHub->insert_id();

    $customerCode = generate_customer_code($newId);
    $ToryHub->update_safe("customers", ['customer_code' => $customerCode], "id = ?", [$newId]);

    add_log($getUser['id'], 'add_customer', 'Thêm khách hàng: ' . $customerCode . ' - ' . $fullname);
    echo json_encode([
        'status' => 'success',
        'msg' => __('Thêm khách hàng thành công'),
        'customer_id' => $newId,
        'customer_code' => $customerCode,
        'fullname' => $fullname
    ]);
    exit;
}

// ======== EDIT ========
if ($request === 'edit') {
    $id = intval(input_post('id'));
    $customer = $ToryHub->get_row_safe("SELECT * FROM `customers` WHERE `id` = ?", [$id]);
    if (!$customer) {
        echo json_encode(['status' => 'error', 'msg' => __('Khách hàng không tồn tại')]);
        exit;
    }

    $fullname = trim(input_post('fullname'));
    $phone = trim(input_post('phone'));
    $email = trim(input_post('email'));
    $customer_type = input_post('customer_type');
    $address_vn = trim(input_post('address_vn'));
    $zalo = trim(input_post('zalo'));
    $wechat = trim(input_post('wechat'));
    $note = trim(input_post('note'));

    if (empty($fullname)) {
        echo json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập họ tên')]);
        exit;
    }

    $existsName = $ToryHub->get_row_safe("SELECT `id` FROM `customers` WHERE `fullname` = ? AND `id` != ?", [$fullname, $id]);
    if ($existsName) {
        echo json_encode(['status' => 'error', 'msg' => __('Tên khách hàng đã tồn tại')]);
        exit;
    }

    if (!empty($phone)) {
        $exists = $ToryHub->get_row_safe("SELECT `id` FROM `customers` WHERE `phone` = ? AND `id` != ?", [$phone, $id]);
        if ($exists) {
            echo json_encode(['status' => 'error', 'msg' => __('Số điện thoại đã tồn tại')]);
            exit;
        }
    }

    $validTypes = ['normal', 'vip', 'agent'];
    if (!in_array($customer_type, $validTypes)) {
        $customer_type = $customer['customer_type'];
    }

    $ToryHub->update_safe("customers", [
        'fullname' => $fullname,
        'phone' => $phone,
        'email' => $email,
        'customer_type' => $customer_type,
        'address_vn' => $address_vn,
        'zalo' => $zalo,
        'wechat' => $wechat,
        'note' => $note,
        'update_date' => gettime()
    ], "id = ?", [$id]);

    add_log($getUser['id'], 'edit_customer', 'Sửa khách hàng: ' . $customer['customer_code']);
    echo json_encode(['status' => 'success', 'msg' => __('Cập nhật thành công')]);
    exit;
}

// ======== DELETE ========
if ($request === 'delete') {
    $id = intval(input_post('id'));
    $customer = $ToryHub->get_row_safe("SELECT * FROM `customers` WHERE `id` = ?", [$id]);
    if (!$customer) {
        echo json_encode(['status' => 'error', 'msg' => __('Khách hàng không tồn tại')]);
        exit;
    }

    $orderCount = $ToryHub->num_rows_safe("SELECT * FROM `orders` WHERE `customer_id` = ?", [$id]);
    if ($orderCount > 0) {
        echo json_encode(['status' => 'error', 'msg' => __('Không thể xóa khách hàng có đơn hàng. Hãy xóa đơn hàng trước.')]);
        exit;
    }

    $ToryHub->remove_safe("customers", "id = ?", [$id]);
    add_log($getUser['id'], 'delete_customer', 'Xóa khách hàng: ' . $customer['customer_code'] . ' - ' . $customer['fullname']);
    echo json_encode(['status' => 'success', 'msg' => __('Xóa thành công')]);
    exit;
}

echo json_encode(['status' => 'error', 'msg' => 'Invalid request']);
