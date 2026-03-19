<?php
/**
 * API Customers endpoints
 * GET  /api/v1/customers.php          - Danh sách khách hàng
 * GET  /api/v1/customers.php?id=123   - Chi tiết khách hàng
 * POST /api/v1/customers.php          - Tạo khách hàng
 */

require_once(__DIR__.'/bootstrap.php');
require_once(__DIR__.'/jwt.php');

$user = api_auth();
$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

// ===== GET =====
if ($method === 'GET') {

    if ($id) {
        $customer = $ToryHub->get_row_safe("SELECT * FROM `customers` WHERE `id` = ?", [$id]);
        if (!$customer) api_error('Khách hàng không tồn tại', 404);
        api_success(['customer' => $customer]);
    }

    $pg = api_pagination();
    $search = $_GET['search'] ?? '';

    $where = "1=1";
    $params = [];

    if ($search) {
        $where .= " AND (`fullname` LIKE ? OR `phone` LIKE ? OR `customer_code` LIKE ?)";
        $s = "%$search%";
        $params = [$s, $s, $s];
    }

    $total = $ToryHub->get_row_safe("SELECT COUNT(*) as cnt FROM `customers` WHERE $where", $params);

    $listParams = array_merge($params, [$pg['per_page'], $pg['offset']]);
    $customers = $ToryHub->get_list_safe(
        "SELECT `id`, `customer_code`, `fullname`, `phone`, `customer_type`, `balance`, `total_orders`
         FROM `customers` WHERE $where ORDER BY `id` DESC LIMIT ? OFFSET ?",
        $listParams
    );

    api_success([
        'customers' => $customers,
        'total'     => (int)($total['cnt'] ?? 0),
        'page'      => $pg['page'],
        'per_page'  => $pg['per_page']
    ]);
}

// ===== POST =====
if ($method === 'POST') {
    $input = api_input();
    $fullname = trim($input['fullname'] ?? '');

    if (empty($fullname)) {
        api_error('Vui lòng nhập họ tên');
    }

    $customer_code = 'KH' . date('ymd') . strtoupper(substr(uniqid(), -4));

    $ToryHub->insert_safe('customers', [
        'customer_code' => $customer_code,
        'fullname'      => $fullname,
        'phone'         => $input['phone'] ?? '',
        'wechat'        => $input['wechat'] ?? '',
        'zalo'          => $input['zalo'] ?? '',
        'address_vn'    => $input['address_vn'] ?? '',
        'customer_type' => $input['customer_type'] ?? 'normal',
        'create_date'   => gettime(),
        'update_date'   => gettime()
    ]);

    api_success([
        'customer_id'   => $ToryHub->insert_id(),
        'customer_code' => $customer_code,
        'fullname'      => $fullname
    ], 'Tạo khách hàng thành công');
}

api_error('Method not allowed', 405);
