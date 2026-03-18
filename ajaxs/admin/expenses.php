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
    echo json_encode(['status' => 'error', 'msg' => __('Phiên làm việc hết hạn, vui lòng tải lại trang')]);
    exit;
}

$request = input_post('request_name');

$exchangeRate = get_exchange_rate();

// ADD EXPENSE
if ($request === 'add') {
    $category = check_string(input_post('category'));
    $amountCny = floatval(input_post('amount'));
    $amount = round($amountCny * $exchangeRate);
    $expense_date = check_string(input_post('expense_date'));
    $description = trim(input_post('description'));

    if (empty($category)) {
        echo json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập danh mục')]);
        exit();
    }

    if ($amount <= 0) {
        echo json_encode(['status' => 'error', 'msg' => __('Số tiền phải lớn hơn 0')]);
        exit();
    }

    if (empty($expense_date)) {
        echo json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn ngày chi')]);
        exit();
    }

    $result = $ToryHub->insert_safe('expenses', [
        'category'      => $category,
        'amount'        => $amount,
        'exchange_rate' => $exchangeRate,
        'description'   => $description,
        'expense_date'  => $expense_date,
        'created_by'    => $getUser['id'],
        'create_date'   => gettime()
    ]);

    if (!$result) {
        echo json_encode(['status' => 'error', 'msg' => __('Lỗi lưu dữ liệu')]);
        exit();
    }

    add_log($getUser['id'], 'ADD_EXPENSE', "Added expense: $category - $amount");
    echo json_encode(['status' => 'success', 'msg' => __('Thêm chi phí thành công')]);
    exit();
}

// EDIT EXPENSE
if ($request === 'edit') {
    $id = intval(input_post('id'));
    $category = check_string(input_post('category'));
    $amountCny = floatval(input_post('amount'));
    $storedRate = floatval(input_post('stored_exchange_rate'));
    $useRate = $storedRate > 0 ? $storedRate : $exchangeRate;
    $amount = round($amountCny * $useRate);
    $expense_date = check_string(input_post('expense_date'));
    $description = trim(input_post('description'));

    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'msg' => __('ID không hợp lệ')]);
        exit();
    }

    if (empty($category)) {
        echo json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập danh mục')]);
        exit();
    }

    if ($amount <= 0) {
        echo json_encode(['status' => 'error', 'msg' => __('Số tiền phải lớn hơn 0')]);
        exit();
    }

    if (empty($expense_date)) {
        echo json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn ngày chi')]);
        exit();
    }

    $ToryHub->update_safe('expenses', [
        'category'      => $category,
        'amount'        => $amount,
        'exchange_rate' => $useRate,
        'description'   => $description,
        'expense_date'  => $expense_date,
    ], "`id` = ?", [$id]);

    add_log($getUser['id'], 'EDIT_EXPENSE', "Edited expense ID: $id - $category - $amount");
    echo json_encode(['status' => 'success', 'msg' => __('Cập nhật chi phí thành công')]);
    exit();
}

// DELETE EXPENSE
if ($request === 'delete') {
    $id = intval(input_post('id'));

    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'msg' => __('ID không hợp lệ')]);
        exit();
    }

    $ToryHub->remove_safe('expenses', "`id` = ?", [$id]);
    add_log($getUser['id'], 'DELETE_EXPENSE', "Deleted expense ID: $id");
    echo json_encode(['status' => 'success', 'msg' => __('Đã xóa chi phí')]);
    exit();
}

echo json_encode(['status' => 'error', 'msg' => 'Invalid request']);
