<?php
require_once(__DIR__.'/../../../models/is_staffvn.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Danh sách chuyến xe');

// Filters
$filterStatus = input_get('status') ?: '';
$filterSearch = trim(input_get('search') ?? '');

$where = "s.status IN ('in_transit', 'arrived', 'completed')";
$params = [];

if ($filterStatus && in_array($filterStatus, ['in_transit', 'arrived', 'completed'])) {
    $where = "s.status = ?";
    $params[] = $filterStatus;
}
if ($filterSearch) {
    $where .= " AND (s.shipment_code LIKE ? OR s.truck_plate LIKE ? OR s.driver_name LIKE ?)";
    $searchLike = '%' . $filterSearch . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

// Pagination
$perPage = 10;
$page = max(1, intval(input_get('pg') ?: 1));
$totalShipments = $ToryHub->num_rows_safe("SELECT s.id FROM `shipments` s WHERE $where", $params);
$totalPages = max(1, ceil($totalShipments / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$shipments = $ToryHub->get_list_safe(
    "SELECT s.*, u.fullname as creator_name,
            (SELECT COUNT(DISTINCT COALESCE(b.bag_code, o.product_code, o.order_code, p.package_code))
             FROM `shipment_packages` sp2
             JOIN `packages` p ON sp2.package_id = p.id
             LEFT JOIN `bag_packages` bp ON p.id = bp.package_id
             LEFT JOIN `bags` b ON bp.bag_id = b.id
             LEFT JOIN `package_orders` po ON p.id = po.package_id
             LEFT JOIN `orders` o ON po.order_id = o.id
             WHERE sp2.shipment_id = s.id) as total_ma_hang
     FROM `shipments` s
     LEFT JOIN `users` u ON s.created_by = u.id
     WHERE $where
     ORDER BY s.create_date DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

$statuses = ['in_transit', 'arrived', 'completed'];
$statusLabels = [
    'in_transit' => ['label' => 'Vận chuyển', 'bg' => 'primary-subtle', 'text' => 'primary', 'icon' => 'ri-truck-line'],
    'arrived'    => ['label' => 'Đã đến', 'bg' => 'success-subtle', 'text' => 'success', 'icon' => 'ri-map-pin-2-line'],
    'completed'  => ['label' => 'Hoàn thành', 'bg' => 'secondary-subtle', 'text' => 'secondary', 'icon' => 'ri-check-double-line'],
];

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Danh sách chuyến xe') ?></h4>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="<?= base_url('staffvn/orders-list') ?>">
                            <input type="hidden" name="module" value="staffvn">
                            <input type="hidden" name="action" value="orders-list">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label"><?= __('Tìm kiếm') ?></label>
                                    <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($filterSearch) ?>" placeholder="<?= __('Mã chuyến, biển số, tài xế...') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label"><?= __('Trạng thái') ?></label>
                                    <select class="form-select" name="status">
                                        <option value=""><?= __('Tất cả') ?></option>
                                        <?php foreach ($statuses as $s): ?>
                                        <option value="<?= $s ?>" <?= $filterStatus == $s ? 'selected' : '' ?>><?= __($statusLabels[$s]['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-auto">
                                    <button type="submit" class="btn btn-primary"><i class="ri-search-line me-1"></i><?= __('Lọc') ?></button>
                                    <a href="<?= base_url('staffvn/orders-list') ?>" class="btn btn-secondary"><?= __('Reset') ?></a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Shipments Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Chuyến xe') ?> (<?= $totalShipments ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><?= __('Mã chuyến') ?></th>
                                        <th><?= __('Biển số / Tài xế') ?></th>
                                        <th><?= __('Tuyến đường') ?></th>
                                        <th><?= __('Tổng cân') ?></th>
                                        <th><?= __('Tổng khối') ?></th>
                                        <th><?= __('Trạng thái') ?></th>
                                        <th><?= __('Ngày tạo') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($shipments)): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4"><?= __('Chưa có chuyến xe nào') ?></td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($shipments as $s): ?>
                                    <?php $cfg = $statusLabels[$s['status']] ?? $statusLabels['in_transit']; ?>
                                    <tr>
                                        <td>
                                            <a href="<?= base_url('staffvn/orders-detail?id=' . $s['id']) ?>"><strong><?= htmlspecialchars($s['shipment_code']) ?></strong></a>
                                            <br><small class="text-muted"><?= $s['total_ma_hang'] ?: 0 ?> <?= __('mã hàng') ?> &middot; <?= $s['total_packages'] ?> <?= __('kiện') ?></small>
                                        </td>
                                        <td>
                                            <?php if ($s['truck_plate']): ?>
                                            <strong><?= htmlspecialchars($s['truck_plate']) ?></strong><br>
                                            <?php endif; ?>
                                            <?= $s['driver_name'] ? htmlspecialchars($s['driver_name']) : '' ?>
                                        </td>
                                        <td><?= $s['route'] ? htmlspecialchars($s['route']) : '' ?></td>
                                        <td><?= $s['total_weight'] > 0 ? fnum($s['total_weight'], 1) . ' kg' : '' ?>
                                            <?php if ($s['max_weight'] > 0): ?>
                                            <br><small class="text-muted">/<?= fnum($s['max_weight'], 0) ?> kg</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $s['total_cbm'] > 0 ? fnum($s['total_cbm'], 2) . ' m³' : '' ?></td>
                                        <td><span class="badge bg-<?= $cfg['bg'] ?> text-<?= $cfg['text'] ?> fs-12 px-2 py-1"><i class="<?= $cfg['icon'] ?> me-1"></i><?= __($cfg['label']) ?></span></td>
                                        <td>
                                            <?= date('d/m/Y H:i', strtotime($s['create_date'])) ?>
                                            <?php if ($s['departed_date']): ?>
                                            <br><small class="text-muted"><?= __('Xuất') ?>: <?= date('d/m/Y', strtotime($s['departed_date'])) ?></small>
                                            <?php endif; ?>
                                            <?php if ($s['arrived_date']): ?>
                                            <br><small class="text-muted"><?= __('Đến') ?>: <?= date('d/m/Y', strtotime($s['arrived_date'])) ?></small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalPages > 1): ?>
                        <div class="d-flex align-items-center justify-content-between mt-3">
                            <div class="text-muted"><?= __('Hiển thị') ?> <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalShipments) ?> / <?= $totalShipments ?></div>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <?php
                                    $queryParams = $_GET;
                                    unset($queryParams['pg']);
                                    $baseUrl = base_url('staffvn/orders-list') . ($queryParams ? '?' . http_build_query($queryParams) : '');
                                    ?>
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . 'page=' . ($page - 1) ?>">&laquo;</a></li>
                                    <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                                    <li class="page-item <?= $p == $page ? 'active' : '' ?>"><a class="page-link" href="<?= $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . 'page=' . $p ?>"><?= $p ?></a></li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="<?= $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . 'page=' . ($page + 1) ?>">&raquo;</a></li>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>
