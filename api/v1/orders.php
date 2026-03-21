<?php
/**
 * API Orders endpoints
 * GET    /api/v1/orders.php              - Danh sách đơn hàng
 * GET    /api/v1/orders.php?id=123       - Chi tiết đơn hàng
 * POST   /api/v1/orders.php              - Tạo đơn hàng
 * PUT    /api/v1/orders.php?id=123       - Cập nhật đơn hàng
 * POST   /api/v1/orders.php?action=scan  - Quét barcode tạo đơn
 */

require_once(__DIR__.'/bootstrap.php');
require_once(__DIR__.'/jwt.php');

$user = api_auth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

// ===== SCAN (quét barcode tạo đơn nhanh) =====
if ($action === 'scan' && $method === 'POST') {
    $input = api_input();
    $tracking = trim($input['tracking'] ?? '');

    if (empty($tracking)) {
        api_error('Vui lòng nhập mã vận đơn');
    }

    // Tạo đơn hàng lẻ
    $order_code = 'ORD' . date('ymd') . strtoupper(substr(uniqid(), -5));
    $result = $ToryHub->insert_safe('orders', [
        'order_code'    => $order_code,
        'order_type'    => 'shipping',
        'product_type'  => 'retail',
        'status'        => 'cn_warehouse',
        'cn_tracking'   => strtoupper($tracking),
        'created_by'    => $user['id'],
        'create_date'   => gettime(),
        'update_date'   => gettime()
    ]);
    if (!$result) api_error('Lỗi tạo đơn hàng');
    $order_id = $ToryHub->insert_id();
    if (!$order_id) api_error('Lỗi tạo đơn hàng (no ID)');

    // Tạo package
    $package_code = 'PKG' . date('ymd') . strtoupper(substr(uniqid(), -5));
    $result = $ToryHub->insert_safe('packages', [
        'package_code' => $package_code,
        'tracking_cn'  => strtoupper($tracking),
        'status'       => 'cn_warehouse',
        'created_by'   => $user['id'],
        'create_date'  => gettime(),
        'update_date'  => gettime()
    ]);
    if (!$result) api_error('Lỗi tạo kiện hàng');
    $package_id = $ToryHub->insert_id();
    if (!$package_id) api_error('Lỗi tạo kiện hàng (no ID)');

    // Link package to order
    $link = $ToryHub->insert_safe('package_orders', [
        'package_id' => $package_id,
        'order_id'   => $order_id
    ]);
    if (!$link) {
        // Fallback: thử insert trực tiếp bằng raw query
        $ToryHub->connect();
        mysqli_query($ToryHub->ketnoi, "INSERT INTO `package_orders` (`package_id`, `order_id`) VALUES ($package_id, $order_id)");
    }

    api_success([
        'order_id'   => $order_id,
        'order_code' => $order_code,
        'package_id' => $package_id,
        'package_code' => $package_code
    ], 'Đã tạo đơn hàng + kiện');
}

// ===== GET: Danh sách hoặc chi tiết =====
if ($method === 'GET') {

    // Chi tiết đơn hàng
    if ($id) {
        $order = $ToryHub->get_row_safe(
            "SELECT o.*, c.fullname as customer_name, c.phone as customer_phone
             FROM `orders` o
             LEFT JOIN `customers` c ON o.customer_id = c.id
             WHERE o.id = ?",
            [$id]
        );
        if (!$order) api_error('Đơn hàng không tồn tại', 404);

        // Lấy packages
        $packages = $ToryHub->get_list_safe(
            "SELECT p.* FROM `packages` p
             INNER JOIN `package_orders` po ON p.id = po.package_id
             WHERE po.order_id = ?",
            [$id]
        );

        $order['packages'] = $packages;
        api_success(['order' => $order]);
    }

    // Danh sách đơn hàng
    $pg = api_pagination();
    $status = $_GET['status'] ?? '';
    $product_type = $_GET['product_type'] ?? '';
    $search = $_GET['search'] ?? '';

    $where = "1=1";
    $params = [];

    if ($status) {
        $where .= " AND o.status = ?";
        $params[] = $status;
    }
    if ($product_type) {
        $where .= " AND o.product_type = ?";
        $params[] = $product_type;
    }
    if ($search) {
        $where .= " AND (o.order_code LIKE ? OR o.cn_tracking LIKE ? OR o.product_name LIKE ? OR c.fullname LIKE ?)";
        $s = "%$search%";
        $params = array_merge($params, [$s, $s, $s, $s]);
    }

    // Count
    $total = $ToryHub->get_row_safe(
        "SELECT COUNT(*) as cnt FROM `orders` o LEFT JOIN `customers` c ON o.customer_id = c.id WHERE $where",
        $params
    );

    // Fetch
    $countParams = $params;
    $params[] = $pg['per_page'];
    $params[] = $pg['offset'];
    $orders = $ToryHub->get_list_safe(
        "SELECT o.*, c.fullname as customer_name, c.customer_code
         FROM `orders` o
         LEFT JOIN `customers` c ON o.customer_id = c.id
         WHERE $where
         ORDER BY o.id DESC
         LIMIT ? OFFSET ?",
        $params
    );

    api_success([
        'orders' => $orders,
        'total'  => (int)($total['cnt'] ?? 0),
        'page'   => $pg['page'],
        'per_page' => $pg['per_page']
    ]);
}

// ===== POST: Tạo đơn hàng =====
if ($method === 'POST' && !$action) {
    $input = api_input();

    $order_code = 'ORD' . date('ymd') . strtoupper(substr(uniqid(), -5));
    $data = [
        'order_code'    => $order_code,
        'order_type'    => 'shipping',
        'product_type'  => $input['product_type'] ?? 'wholesale',
        'cargo_type'    => $input['cargo_type'] ?? 'easy',
        'customer_id'   => $input['customer_id'] ?? null,
        'status'        => $input['status'] ?? 'cn_warehouse',
        'product_code'  => $input['product_code'] ?? '',
        'cn_tracking'   => strtoupper($input['tracking_number'] ?? ''),
        'product_name'  => $input['product_name'] ?? '',
        'weight_actual' => $input['weight_actual'] ?? 0,
        'volume_actual' => $input['volume_actual'] ?? 0,
        'created_by'    => $user['id'],
        'create_date'   => gettime(),
        'update_date'   => gettime()
    ];

    $ToryHub->beginTransaction();
    try {
        $ToryHub->insert_safe('orders', $data);
        $order_id = $ToryHub->insert_id();

        // Tạo packages nếu có
        $packages = $input['packages'] ?? [];
        foreach ($packages as $pkg) {
            $pkg_code = 'PKG' . date('ymd') . strtoupper(substr(uniqid(), -5));
            $ToryHub->insert_safe('packages', [
                'package_code'  => $pkg_code,
                'tracking_cn'   => strtoupper($pkg['tracking_cn'] ?? ''),
                'status'        => $data['status'],
                'weight_actual' => $pkg['weight'] ?? 0,
                'length_cm'     => $pkg['length_cm'] ?? 0,
                'width_cm'      => $pkg['width_cm'] ?? 0,
                'height_cm'     => $pkg['height_cm'] ?? 0,
                'created_by'    => $user['id'],
                'create_date'   => gettime(),
                'update_date'   => gettime()
            ]);
            $pkg_id = $ToryHub->insert_id();
            $ToryHub->insert_safe('package_orders', [
                'package_id' => $pkg_id,
                'order_id'   => $order_id
            ]);
        }

        // Cập nhật total_packages
        if (count($packages) > 0) {
            $ToryHub->update_safe('orders', ['total_packages' => count($packages)], "`id` = ?", [$order_id]);
        }

        $ToryHub->commit();
        api_success(['order_id' => $order_id, 'order_code' => $order_code], 'Tạo đơn hàng thành công');
    } catch (\Exception $e) {
        $ToryHub->rollBack();
        api_error('Lỗi tạo đơn hàng: ' . $e->getMessage(), 500);
    }
}

// ===== PUT: Cập nhật đơn hàng =====
if ($method === 'PUT' && $id) {
    $input = api_input();

    $order = $ToryHub->get_row_safe("SELECT * FROM `orders` WHERE `id` = ?", [$id]);
    if (!$order) api_error('Đơn hàng không tồn tại', 404);

    $updateData = ['update_date' => gettime()];
    $allowed = ['status', 'product_name', 'product_code', 'cargo_type', 'customer_id',
                'weight_actual', 'volume_actual', 'cn_tracking'];

    foreach ($allowed as $field) {
        if (isset($input[$field])) {
            $updateData[$field] = $field === 'cn_tracking' ? strtoupper($input[$field]) : $input[$field];
        }
    }

    $ToryHub->update_safe('orders', $updateData, "`id` = ?", [$id]);
    api_success(['order_id' => $id], 'Cập nhật thành công');
}

api_error('Method not allowed', 405);
