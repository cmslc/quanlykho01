<?php
/**
 * API Packages endpoints
 */

require_once(__DIR__.'/bootstrap.php');
require_once(__DIR__.'/jwt.php');

$user = api_auth();
$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

// ===== GET =====
if ($method === 'GET') {
    if ($id) {
        $package = $ToryHub->get_row_safe("SELECT * FROM `packages` WHERE `id` = ?", [$id]);
        if (!$package) api_error('Kiện hàng không tồn tại', 404);

        $orders = $ToryHub->get_list_safe(
            "SELECT o.id, o.order_code, o.product_name, o.product_type, c.fullname as customer_name
             FROM `package_orders` po JOIN `orders` o ON po.order_id = o.id
             LEFT JOIN `customers` c ON o.customer_id = c.id
             WHERE po.package_id = ?", [$id]
        );
        $package['orders'] = $orders;
        api_success(['package' => $package]);
    }

    $pg = api_pagination();
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';

    $where = "1=1"; $params = [];
    if ($status) { $where .= " AND `status` = ?"; $params[] = $status; }
    if ($search) {
        $where .= " AND (`tracking_cn` LIKE ? OR `package_code` LIKE ?)";
        $s = "%$search%"; $params[] = $s; $params[] = $s;
    }

    $total = $ToryHub->get_row_safe("SELECT COUNT(*) as cnt FROM `packages` WHERE $where", $params);
    $listParams = array_merge($params, [$pg['per_page'], $pg['offset']]);
    $packages = $ToryHub->get_list_safe(
        "SELECT * FROM `packages` WHERE $where ORDER BY `id` DESC LIMIT ? OFFSET ?", $listParams
    );

    api_success([
        'packages' => $packages,
        'total' => (int)($total['cnt'] ?? 0),
        'page' => $pg['page'],
        'per_page' => $pg['per_page']
    ]);
}

// ===== PUT: Update Package =====
if ($method === 'PUT' && $id) {
    $input = api_input();
    $package = $ToryHub->get_row_safe("SELECT * FROM `packages` WHERE `id` = ?", [$id]);
    if (!$package) api_error('Kiện hàng không tồn tại', 404);

    $updateData = ['update_date' => gettime()];
    $allowed = ['weight_actual', 'weight_volume', 'weight_charged', 'length_cm', 'width_cm', 'height_cm', 'status', 'note'];

    foreach ($allowed as $field) {
        if (isset($input[$field])) $updateData[$field] = $input[$field];
    }

    // If status changed, record history
    if (isset($input['status']) && $input['status'] !== $package['status']) {
        $ToryHub->insert_safe('package_status_history', [
            'package_id' => $id, 'old_status' => $package['status'],
            'new_status' => $input['status'], 'changed_by' => $user['id'],
            'create_date' => gettime()
        ]);

        if ($input['status'] === 'vn_warehouse') $updateData['vn_warehouse_date'] = gettime();
        if ($input['status'] === 'delivered') $updateData['delivered_date'] = gettime();
    }

    $ToryHub->update_safe('packages', $updateData, "`id` = ?", [$id]);
    api_success([], 'Cập nhật kiện hàng thành công');
}

api_error('Method not allowed', 405);
