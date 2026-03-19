<?php
/**
 * API Bags endpoints
 * GET    /bags.php              - List bags
 * GET    /bags.php?id=X         - Bag detail with packages
 * POST   /bags.php?action=create - Create bag
 * POST   /bags.php?action=scan   - Scan package into bag
 * POST   /bags.php?action=unscan - Remove package from bag
 * POST   /bags.php?action=seal   - Seal bag
 * POST   /bags.php?action=unseal - Unseal bag
 * DELETE /bags.php?id=X          - Delete bag
 */

require_once(__DIR__.'/bootstrap.php');
require_once(__DIR__.'/jwt.php');

$user = api_auth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

// ===== CREATE BAG =====
if ($action === 'create' && $method === 'POST') {
    $input = api_input();
    $bag_code_input = strtoupper(trim($input['bag_code'] ?? ''));
    $note = trim($input['note'] ?? '');

    if ($bag_code_input !== '') {
        $exists = $ToryHub->get_row_safe("SELECT `id` FROM `bags` WHERE `bag_code` = ?", [$bag_code_input]);
        if ($exists) api_error('Mã bao đã tồn tại: ' . $bag_code_input);
        $bag_code = $bag_code_input;
    } else {
        $prefix = 'BAO-' . date('Ymd') . '-';
        $last = $ToryHub->get_row_safe(
            "SELECT `bag_code` FROM `bags` WHERE `bag_code` LIKE ? ORDER BY `id` DESC LIMIT 1",
            [$prefix . '%']
        );
        $seq = $last ? intval(substr($last['bag_code'], -3)) + 1 : 1;
        $bag_code = $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }

    $ToryHub->insert_safe('bags', [
        'bag_code' => $bag_code,
        'status' => 'open',
        'total_packages' => 0,
        'total_weight' => 0,
        'note' => $note,
        'created_by' => $user['id'],
        'create_date' => gettime(),
        'update_date' => gettime()
    ]);

    api_success([
        'bag_id' => $ToryHub->insert_id(),
        'bag_code' => $bag_code
    ], 'Tạo bao hàng thành công');
}

// ===== SCAN PACKAGE INTO BAG =====
if ($action === 'scan' && $method === 'POST') {
    $input = api_input();
    $bag_id = intval($input['bag_id'] ?? 0);
    $tracking_cn = strtoupper(trim($input['tracking_cn'] ?? ''));

    if (!$tracking_cn) api_error('Vui lòng nhập mã vận đơn');

    $bag = $ToryHub->get_row_safe("SELECT * FROM `bags` WHERE `id` = ?", [$bag_id]);
    if (!$bag) api_error('Bao hàng không tồn tại');
    if ($bag['status'] !== 'open') api_error('Bao hàng đã đóng, không thể thêm kiện');

    $package = $ToryHub->get_row_safe("SELECT * FROM `packages` WHERE `tracking_cn` = ?", [$tracking_cn]);
    if (!$package) api_error('Không tìm thấy kiện hàng với mã: ' . $tracking_cn);
    if ($package['status'] !== 'cn_warehouse') api_error('Kiện hàng không ở trạng thái kho TQ');

    $existingBag = $ToryHub->get_row_safe(
        "SELECT b.bag_code FROM `bag_packages` bp JOIN `bags` b ON bp.bag_id = b.id WHERE bp.package_id = ?",
        [$package['id']]
    );
    if ($existingBag) api_error('Kiện hàng đã nằm trong bao: ' . $existingBag['bag_code']);

    $ToryHub->insert_safe('bag_packages', [
        'bag_id' => $bag_id,
        'package_id' => $package['id'],
        'scanned_by' => $user['id'],
        'scanned_at' => gettime()
    ]);

    // Update package status to packed
    $ToryHub->update_safe('packages', ['status' => 'packed', 'update_date' => gettime()], "`id` = ?", [$package['id']]);
    $ToryHub->insert_safe('package_status_history', [
        'package_id' => $package['id'],
        'old_status' => 'cn_warehouse',
        'new_status' => 'packed',
        'note' => 'Đóng vào bao ' . $bag['bag_code'],
        'changed_by' => $user['id'],
        'create_date' => gettime()
    ]);

    // Update bag totals
    $totals = $ToryHub->get_row_safe(
        "SELECT COUNT(*) as cnt, COALESCE(SUM(p.weight_charged), 0) as total_w
         FROM `bag_packages` bp JOIN `packages` p ON bp.package_id = p.id WHERE bp.bag_id = ?", [$bag_id]
    );
    $ToryHub->update_safe('bags', [
        'total_packages' => $totals['cnt'],
        'total_weight' => $totals['total_w'],
        'update_date' => gettime()
    ], "`id` = ?", [$bag_id]);

    $orderInfo = $ToryHub->get_row_safe(
        "SELECT o.product_name, o.product_code, c.fullname as customer_name
         FROM `package_orders` po JOIN `orders` o ON po.order_id = o.id
         LEFT JOIN `customers` c ON o.customer_id = c.id
         WHERE po.package_id = ? LIMIT 1", [$package['id']]
    );

    api_success([
        'package' => [
            'id' => $package['id'],
            'package_code' => $package['package_code'],
            'tracking_cn' => $package['tracking_cn'],
            'weight_charged' => floatval($package['weight_charged']),
            'product_name' => $orderInfo['product_name'] ?? '',
            'customer_name' => $orderInfo['customer_name'] ?? '',
        ],
        'bag_total_packages' => intval($totals['cnt']),
        'bag_total_weight' => floatval($totals['total_w'])
    ], 'Đã thêm kiện vào bao');
}

// ===== UNSCAN =====
if ($action === 'unscan' && $method === 'POST') {
    $input = api_input();
    $bag_id = intval($input['bag_id'] ?? 0);
    $package_id = intval($input['package_id'] ?? 0);

    $bag = $ToryHub->get_row_safe("SELECT * FROM `bags` WHERE `id` = ?", [$bag_id]);
    if (!$bag || $bag['status'] !== 'open') api_error('Bao hàng đã đóng, không thể gỡ kiện');

    $ToryHub->remove_safe('bag_packages', "`bag_id` = ? AND `package_id` = ?", [$bag_id, $package_id]);

    $package = $ToryHub->get_row_safe("SELECT * FROM `packages` WHERE `id` = ?", [$package_id]);
    if ($package && $package['status'] === 'packed') {
        $ToryHub->update_safe('packages', ['status' => 'cn_warehouse', 'update_date' => gettime()], "`id` = ?", [$package_id]);
        $ToryHub->insert_safe('package_status_history', [
            'package_id' => $package_id, 'old_status' => 'packed', 'new_status' => 'cn_warehouse',
            'note' => 'Gỡ khỏi bao ' . $bag['bag_code'], 'changed_by' => $user['id'], 'create_date' => gettime()
        ]);
    }

    $totals = $ToryHub->get_row_safe(
        "SELECT COUNT(*) as cnt, COALESCE(SUM(p.weight_charged), 0) as total_w
         FROM `bag_packages` bp JOIN `packages` p ON bp.package_id = p.id WHERE bp.bag_id = ?", [$bag_id]
    );
    $ToryHub->update_safe('bags', [
        'total_packages' => $totals['cnt'], 'total_weight' => $totals['total_w'], 'update_date' => gettime()
    ], "`id` = ?", [$bag_id]);

    api_success([
        'bag_total_packages' => intval($totals['cnt']),
        'bag_total_weight' => floatval($totals['total_w'])
    ], 'Đã gỡ kiện khỏi bao');
}

// ===== SEAL BAG =====
if ($action === 'seal' && $method === 'POST') {
    $input = api_input();
    $bag_id = intval($input['bag_id'] ?? 0);

    $bag = $ToryHub->get_row_safe("SELECT * FROM `bags` WHERE `id` = ?", [$bag_id]);
    if (!$bag) api_error('Bao hàng không tồn tại');
    if ($bag['status'] !== 'open') api_error('Bao hàng đã đóng rồi');
    if ($bag['total_packages'] < 1) api_error('Bao hàng chưa có kiện nào');

    $ToryHub->update_safe('bags', [
        'status' => 'sealed', 'sealed_by' => $user['id'],
        'sealed_date' => gettime(), 'update_date' => gettime()
    ], "`id` = ?", [$bag_id]);

    api_success([], 'Đã đóng bao thành công');
}

// ===== UNSEAL BAG =====
if ($action === 'unseal' && $method === 'POST') {
    $input = api_input();
    $bag_id = intval($input['bag_id'] ?? 0);

    $bag = $ToryHub->get_row_safe("SELECT * FROM `bags` WHERE `id` = ?", [$bag_id]);
    if (!$bag) api_error('Bao hàng không tồn tại');
    if ($bag['status'] !== 'sealed') api_error('Chỉ có thể mở lại bao đang ở trạng thái chờ vận chuyển');

    $ToryHub->update_safe('bags', [
        'status' => 'open', 'sealed_by' => null, 'sealed_date' => null, 'update_date' => gettime()
    ], "`id` = ?", [$bag_id]);

    api_success([], 'Đã mở lại bao thành công');
}

// ===== DELETE BAG =====
if ($method === 'DELETE' && $id) {
    $bag = $ToryHub->get_row_safe("SELECT * FROM `bags` WHERE `id` = ?", [$id]);
    if (!$bag) api_error('Bao hàng không tồn tại');
    if (!in_array($bag['status'], ['open', 'sealed'])) api_error('Không thể xóa bao đang vận chuyển');

    $bagPackages = $ToryHub->get_list_safe("SELECT package_id FROM `bag_packages` WHERE `bag_id` = ?", [$id]);
    foreach ($bagPackages as $bp) {
        $ToryHub->update_safe('packages', ['status' => 'cn_warehouse', 'update_date' => gettime()], "`id` = ?", [$bp['package_id']]);
    }
    $ToryHub->remove_safe('bag_packages', "`bag_id` = ?", [$id]);
    $ToryHub->remove_safe('bags', "`id` = ?", [$id]);

    api_success([], 'Đã xóa bao hàng');
}

// ===== GET =====
if ($method === 'GET') {
    if ($id) {
        $bag = $ToryHub->get_row_safe("SELECT * FROM `bags` WHERE `id` = ?", [$id]);
        if (!$bag) api_error('Bao hàng không tồn tại', 404);

        $packages = $ToryHub->get_list_safe(
            "SELECT p.id, p.package_code, p.tracking_cn, p.weight_charged, p.weight_actual,
                    p.length_cm, p.width_cm, p.height_cm, p.status,
                    o.product_name, o.product_code, c.fullname as customer_name
             FROM `bag_packages` bp
             JOIN `packages` p ON bp.package_id = p.id
             LEFT JOIN `package_orders` po ON p.id = po.package_id
             LEFT JOIN `orders` o ON po.order_id = o.id
             LEFT JOIN `customers` c ON o.customer_id = c.id
             WHERE bp.bag_id = ? ORDER BY bp.id ASC", [$id]
        );

        $bag['packages'] = $packages;
        api_success(['bag' => $bag]);
    }

    $pg = api_pagination();
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';

    $where = "1=1";
    $params = [];

    if ($status) { $where .= " AND `status` = ?"; $params[] = $status; }
    if ($search) { $where .= " AND `bag_code` LIKE ?"; $params[] = "%$search%"; }

    $total = $ToryHub->get_row_safe("SELECT COUNT(*) as cnt FROM `bags` WHERE $where", $params);

    $listParams = array_merge($params, [$pg['per_page'], $pg['offset']]);
    $bags = $ToryHub->get_list_safe(
        "SELECT * FROM `bags` WHERE $where ORDER BY `id` DESC LIMIT ? OFFSET ?", $listParams
    );

    api_success([
        'bags' => $bags,
        'total' => (int)($total['cnt'] ?? 0),
        'page' => $pg['page'],
        'per_page' => $pg['per_page']
    ]);
}

api_error('Method not allowed', 405);
