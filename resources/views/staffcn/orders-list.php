<?php
require_once(__DIR__.'/../../../models/is_staffcn.php');
require_once(__DIR__.'/../../../libs/csrf.php');

// Product type: set by wrapper (orders-retail.php) or default to wholesale
$_productTypeFilter = $_productTypeFilter ?? 'wholesale';
$isRetailPage = $_productTypeFilter === 'retail';
$_currentAction = $isRetailPage ? 'orders-retail' : 'orders-list';

$page_title = $isRetailPage ? __('Danh sách hàng lẻ') : __('Danh sách hàng lô');

// Filters
$filterStatus = input_get('status') ?: '';
$filterCustomer = input_get('customer_id') ?: '';
$filterSearch = trim(input_get('search') ?? '');
$filterDateFrom = input_get('date_from') ?: '';
$filterDateTo = input_get('date_to') ?: '';

$where = "1=1";
$params = [];

if ($filterStatus) {
    $where .= " AND o.status = ?";
    $params[] = $filterStatus;
}
if ($filterCustomer) {
    $where .= " AND o.customer_id = ?";
    $params[] = intval($filterCustomer);
}
// Always filter by product type (set by page)
$where .= " AND o.product_type = ?";
$params[] = $_productTypeFilter;
if ($filterDateFrom) {
    $where .= " AND DATE(o.create_date) >= ?";
    $params[] = $filterDateFrom;
}
if ($filterDateTo) {
    $where .= " AND DATE(o.create_date) <= ?";
    $params[] = $filterDateTo;
}
if ($filterSearch) {
    $where .= " AND (o.order_code LIKE ? OR o.product_name LIKE ? OR o.product_code LIKE ? OR o.id IN (SELECT po.order_id FROM `package_orders` po JOIN `packages` p ON po.package_id = p.id WHERE p.tracking_cn LIKE ?))";
    $searchLike = '%' . $filterSearch . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

// Pagination
$perPage = 10;
$page = max(1, intval(input_get('page') ?: 1));
$totalOrders = $ToryHub->num_rows_safe("SELECT o.id FROM `orders` o WHERE $where", $params);
$totalPages = max(1, ceil($totalOrders / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$orders = $ToryHub->get_list_safe("SELECT o.*, c.fullname as customer_name
    FROM `orders` o LEFT JOIN `customers` c ON o.customer_id = c.id
    WHERE $where ORDER BY o.create_date DESC LIMIT $perPage OFFSET $offset", $params);

// Get tracking codes for orders (from packages)
$orderIds = array_column($orders, 'id');
$trackingMap = [];
if (!empty($orderIds)) {
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $trackings = $ToryHub->get_list_safe(
        "SELECT po.order_id, p.tracking_cn FROM `package_orders` po
         JOIN `packages` p ON po.package_id = p.id
         WHERE po.order_id IN ($placeholders)",
        $orderIds
    );
    foreach ($trackings as $t) {
        $trackingMap[$t['order_id']][] = $t['tracking_cn'];
    }
}

// Get total weight & volume from packages for each order
$weightMap = [];
if (!empty($orderIds)) {
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $weights = $ToryHub->get_list_safe(
        "SELECT po.order_id,
                COUNT(p.id) as total_packages,
                SUM(p.weight_charged) as total_weight_charged,
                SUM(p.weight_actual) as total_weight_actual,
                SUM(p.length_cm * p.width_cm * p.height_cm / 1000000) as total_cbm
         FROM `package_orders` po
         JOIN `packages` p ON po.package_id = p.id
         WHERE po.order_id IN ($placeholders)
         GROUP BY po.order_id",
        $orderIds
    );
    foreach ($weights as $wt) {
        $weightMap[$wt['order_id']] = $wt;
    }
}

// Get package status distribution per order
$pkgStatusMap = [];
if (!empty($orderIds)) {
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $pkgStatuses = $ToryHub->get_list_safe(
        "SELECT po.order_id, p.status, COUNT(*) as cnt
         FROM `package_orders` po
         JOIN `packages` p ON po.package_id = p.id
         WHERE po.order_id IN ($placeholders)
         GROUP BY po.order_id, p.status",
        $orderIds
    );
    foreach ($pkgStatuses as $ps) {
        $pkgStatusMap[$ps['order_id']][$ps['status']] = intval($ps['cnt']);
    }
}

// Get bag codes for orders (only relevant for retail page)
$bagMap = [];
if ($isRetailPage && !empty($orderIds)) {
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $bags = $ToryHub->get_list_safe(
        "SELECT po.order_id, b.bag_code, b.status as bag_status, COUNT(p.id) as pkg_count
         FROM `package_orders` po
         JOIN `packages` p ON po.package_id = p.id
         JOIN `bag_packages` bp ON p.id = bp.package_id
         JOIN `bags` b ON bp.bag_id = b.id
         WHERE po.order_id IN ($placeholders)
         GROUP BY po.order_id, b.id",
        $orderIds
    );
    foreach ($bags as $bg) {
        $bagMap[$bg['order_id']][] = $bg;
    }
}



$customers = $ToryHub->get_list_safe("SELECT `id`, `fullname` FROM `customers` ORDER BY `fullname` ASC", []);

$statuses = ['cn_warehouse', 'packed', 'loading', 'shipping', 'vn_warehouse', 'delivered', 'cancelled'];

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= $page_title ?></h4>
                    <div class="d-flex gap-2">
                        <a href="<?= base_url('staffcn/orders-add?product_type=' . $_productTypeFilter) ?>" class="btn btn-primary">
                            <i class="ri-add-line"></i> <?= __('Nhập kho') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($_showRetailTabs)): ?>
        <!-- Retail Tabs -->
        <div class="row">
            <div class="col-12">
                <ul class="nav nav-tabs nav-tabs-custom mb-3">
                    <li class="nav-item">
                        <a class="nav-link active" href="<?= base_url('staffcn/orders-retail?tab=tracking') ?>">
                            <i class="ri-file-list-3-line me-1"></i><?= __('Mã vận đơn') ?>
                            <span class="badge bg-secondary-subtle text-secondary ms-1"><?= $totalOrders ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= base_url('staffcn/orders-retail?tab=bags') ?>">
                            <i class="ri-archive-line me-1"></i><?= __('Mã bao') ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="<?= base_url('staffcn/' . $_currentAction) ?>">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label"><?= __('Tìm kiếm') ?></label>
                                    <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($filterSearch) ?>" placeholder="<?= __('Tên sản phẩm, mã hàng, mã vận đơn...') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label"><?= __('Trạng thái') ?></label>
                                    <select class="form-select" name="status">
                                        <option value=""><?= __('Tất cả') ?></option>
                                        <?php foreach ($statuses as $s): ?>
                                        <option value="<?= $s ?>" <?= $filterStatus == $s ? 'selected' : '' ?>><?= display_order_status($s) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label"><?= __('Khách hàng') ?></label>
                                    <select class="form-select" name="customer_id">
                                        <option value=""><?= __('Tất cả') ?></option>
                                        <?php foreach ($customers as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= $filterCustomer == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['fullname']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row g-3 align-items-end mt-0">
                                <div class="col-md-2">
                                    <label class="form-label"><?= __('Từ ngày') ?></label>
                                    <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><?= __('Đến ngày') ?></label>
                                    <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($filterDateTo) ?>">
                                </div>
                                <div class="col-md-auto">
                                    <button type="submit" class="btn btn-primary"><i class="ri-search-line me-1"></i><?= __('Lọc') ?></button>
                                    <a href="<?= base_url('staffcn/' . $_currentAction) ?>" class="btn btn-secondary"><?= __('Reset') ?></a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Summary -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex flex-wrap gap-2">
                    <?php
                    $statusColors = [
                        'cn_warehouse' => 'info',
                        'shipping' => 'primary', 'vn_warehouse' => 'success',
                        'delivered' => 'success', 'cancelled' => 'danger'
                    ];
                    $statusCounts = [];
                    foreach ($statuses as $s) {
                        if ($s === 'packed' && !$isRetailPage) continue;
                        $statusCounts[$s] = $ToryHub->num_rows_safe("SELECT * FROM `orders` WHERE `status` = ? AND `product_type` = ?", [$s, $_productTypeFilter]) ?: 0;
                    }
                    ?>
                    <?php foreach ($statuses as $s): ?>
                    <?php if ($s === 'packed' && !$isRetailPage) continue; ?>
                    <a href="<?= base_url('staffcn/' . $_currentAction . '&status=' . $s) ?>" class="text-decoration-none flex-fill" style="min-width:120px;">
                        <div class="card card-animate mb-0 <?= $filterStatus == $s ? 'border border-primary' : '' ?>">
                            <div class="card-body py-2 px-2 text-center">
                                <h5 class="mb-0"><?= $statusCounts[$s] ?></h5>
                                <small class="text-muted" style="font-size:11px;"><?= display_order_status($s) ?></small>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h5 class="card-title mb-0"><?= $page_title ?> (<?= $totalOrders ?>)</h5>
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-success" id="btn-export-excel"><i class="ri-file-excel-2-line me-1"></i><?= __('Xuất Excel') ?></button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><?= __('Mã hàng / Mã vận đơn') ?></th>
                                        <?php if ($isRetailPage): ?><th><?= __('Mã bao') ?></th><?php endif; ?>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th class="text-center" style="width:60px;"><?= __('Ảnh') ?></th>
                                        <th><?= __('Sản phẩm') ?></th>
                                        <th><?= __('Cân nặng / Số khối') ?></th>
                                        <th><?= __('Trạng thái') ?></th>

                                        <th><?= __('Thời gian') ?></th>
                                        <th><?= __('Hành động') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                    <?php
                                        $productType = $order['product_type'] ?? 'retail';
                                        $isRetail = $productType === 'retail';
                                        $orderTrackings = $trackingMap[$order['id']] ?? [];
                                        $pw = $weightMap[$order['id']] ?? null;
                                        $wCharged = $pw['total_weight_charged'] ?? 0;
                                        $wActual = $pw['total_weight_actual'] ?? 0;
                                        $cbm = $pw['total_cbm'] ?? 0;
                                        $orderVolumeActual = floatval($order['volume_actual'] ?? 0);
                                        if ($orderVolumeActual > 0) $cbm = $orderVolumeActual;
                                        $orderWeightActual = floatval($order['weight_actual'] ?? 0);
                                        $orderWeightCharged = floatval($order['weight_charged'] ?? 0);
                                    ?>
                                    <tr>
                                        <?php $pkgCount = $pw['total_packages'] ?? 0; ?>
                                        <?php
                                        $displayWeight = $wActual > 0 ? $wActual
                                            : ($orderWeightActual > 0 ? $orderWeightActual
                                            : ($wCharged > 0 ? $wCharged
                                            : $orderWeightCharged));
                                        ?>
                                        <td>
                                            <?php if ($isRetail): ?>
                                                <?php if (!empty($orderTrackings)): ?>
                                                    <?php foreach ($orderTrackings as $tk): ?>
                                                        <?php if ($tk): ?>
                                                        <a href="<?= base_url('staffcn/orders-detail?id=' . $order['id']) ?>"><strong><?= htmlspecialchars($tk) ?></strong></a><br>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <a href="<?= base_url('staffcn/orders-detail?id=' . $order['id']) ?>" class="text-muted">#<?= $order['id'] ?></a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php if ($order['product_code'] ?? ''): ?>
                                                <a href="<?= base_url('staffcn/orders-detail?id=' . $order['id']) ?>"><strong><?= htmlspecialchars($order['product_code']) ?></strong></a>
                                                <?php else: ?>
                                                <a href="<?= base_url('staffcn/orders-detail?id=' . $order['id']) ?>" class="text-muted">#<?= $order['id'] ?></a>
                                                <?php endif; ?>
                                                <?php if (!empty($order['cn_tracking'])): ?>
                                                <div><small class="text-muted"><i class="ri-truck-line"></i> <?= htmlspecialchars($order['cn_tracking']) ?></small></div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if ($pkgCount > 0): ?>
                                            <div class="mt-1"><a href="#" class="btn-expand-pkgs text-muted text-decoration-none" data-order-id="<?= $order['id'] ?>"><i class="ri-archive-line"></i> <?= $pkgCount ?> <?= __('kiện') ?> <i class="ri-arrow-down-s-line expand-icon fs-14"></i></a></div>
                                            <?php endif; ?>
                                            <?php if (!$isRetail && !empty($order['cargo_type'])): ?>
                                            <div class="mt-1"><?= display_cargo_type($order['cargo_type']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($isRetailPage): ?>
                                        <td>
                                            <?php
                                            $orderBags = $bagMap[$order['id']] ?? [];
                                            if (!empty($orderBags)):
                                                foreach ($orderBags as $bg): ?>
                                                <span class="badge bg-dark-subtle text-dark mb-1"><?= htmlspecialchars($bg['bag_code']) ?> (<?= $bg['pkg_count'] ?>)</span><br>
                                                <?php endforeach;
                                            else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php endif; ?>
                                        <td>
                                            <?php if ($order['customer_id']): ?>
                                            <span class="fw-bold"><?= htmlspecialchars($order['customer_name'] ?? '') ?></span>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if (!empty($order['product_image'])):
                                                $orderImgArr = array_filter(array_map('trim', explode(',', $order['product_image'])));
                                                $orderImgUrls = array_map('get_upload_url', $orderImgArr);
                                                $thumbUrl = $orderImgUrls[0];
                                                $imgCount = count($orderImgArr);
                                            ?>
                                            <a href="#" class="btn-view-images position-relative d-inline-block" data-images="<?= htmlspecialchars(json_encode(array_values($orderImgUrls))) ?>">
                                                <img src="<?= $thumbUrl ?>" class="rounded" style="width:40px;height:40px;object-fit:cover;">
                                                <?php if ($imgCount > 1): ?>
                                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary" style="font-size:10px;"><?= $imgCount ?></span>
                                                <?php endif; ?>
                                            </a>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars(mb_strimwidth($order['product_name'] ?? '', 0, 30, '...')) ?></strong>
                                        </td>
                                        <td>
                                            <?php
                                            if ($displayWeight > 0): ?>
                                                <?= fnum($displayWeight, 1) ?> kg
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                            <?php if ($cbm > 0): ?>
                                                <br><?= fnum($cbm, 2) ?> m&sup3;
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $opkgMap = $pkgStatusMap[$order['id']] ?? [];
                                            if (!empty($opkgMap)):
                                                $opkgLabels = [
                                                    'cn_warehouse' => ['label' => 'Đã về kho Trung Quốc', 'bg' => 'info'],
                                                    'packed'       => ['label' => 'Đã đóng bao', 'bg' => 'dark'],
                                                    'loading'      => ['label' => 'Đang xếp xe', 'bg' => 'secondary'],
                                                    'shipping'     => ['label' => 'Đang vận chuyển', 'bg' => 'primary'],
                                                    'vn_warehouse' => ['label' => 'Đã về kho Việt Nam', 'bg' => 'success'],
                                                    'delivered'    => ['label' => 'Đã giao hàng', 'bg' => 'success'],
                                                    'cancelled'    => ['label' => 'Đã hủy', 'bg' => 'danger'],
                                                    'returned'     => ['label' => 'Hoàn hàng', 'bg' => 'secondary'],
                                                    'damaged'      => ['label' => 'Hỏng hàng', 'bg' => 'danger'],
                                                ];
                                            ?>
                                            <div class="mt-1 d-flex flex-column align-items-start gap-1">
                                                <?php foreach ($opkgLabels as $st => $cfg):
                                                    if ($st === 'packed' && !$isRetail) continue;
                                                    $cnt = $opkgMap[$st] ?? 0;
                                                    if ($cnt > 0):
                                                ?>
                                                <span class="badge bg-<?= $cfg['bg'] ?>-subtle text-<?= $cfg['bg'] ?> fs-12 px-2 py-1"><?= __($cfg['label']) ?>: <?= $cnt ?></span>
                                                <?php endif; endforeach; ?>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= date('d/m/Y H:i', strtotime($order['create_date'])) ?>
                                            <br><small class="text-muted"><?= date('d/m/Y H:i', strtotime($order['update_date'])) ?></small>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="<?= base_url('staffcn/orders-detail?id=' . $order['id']) ?>" class="btn btn-sm btn-soft-info"><i class="ri-eye-line me-1"></i><?= __('Xem') ?></a>
                                                <a href="<?= base_url('staffcn/orders-edit?id=' . $order['id']) ?>" class="btn btn-sm btn-soft-warning"><i class="ri-pencil-line me-1"></i><?= __('Sửa') ?></a>
                                                <button type="button" class="btn btn-sm btn-soft-danger btn-delete-order" data-id="<?= $order['id'] ?>" data-code="<?= htmlspecialchars($order['order_code'] ?: '#' . $order['id']) ?>"><i class="ri-delete-bin-line me-1"></i><?= __('Xóa') ?></button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalPages > 1): ?>
                        <div class="d-flex align-items-center justify-content-between mt-3">
                            <div class="text-muted">
                                <?= __('Hiển thị') ?> <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalOrders) ?> / <?= $totalOrders ?> <?= __('đơn hàng') ?>
                            </div>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <?php
                                    // Build base URL with current filters
                                    $queryParams = $_GET;
                                    unset($queryParams['page']);
                                    unset($queryParams['module'], $queryParams['action']);
                                    $baseUrl = base_url('staffcn/' . $_currentAction) . ($queryParams ? '?' . http_build_query($queryParams) : '');
                                    ?>
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . 'page=' . ($page - 1) ?>">&laquo;</a>
                                    </li>
                                    <?php
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    if ($startPage > 1): ?>
                                        <li class="page-item"><a class="page-link" href="<?= $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . 'page=1' ?>">1</a></li>
                                        <?php if ($startPage > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                                    <?php endif; ?>
                                    <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . 'page=' . $p ?>"><?= $p ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    <?php if ($endPage < $totalPages): ?>
                                        <?php if ($endPage < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                                        <li class="page-item"><a class="page-link" href="<?= $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . 'page=' . $totalPages ?>"><?= $totalPages ?></a></li>
                                    <?php endif; ?>
                                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . 'page=' . ($page + 1) ?>">&raquo;</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>

<!-- Image Gallery Modal (outside layout wrapper for correct z-index) -->
<div class="modal fade" id="imageGalleryModal" tabindex="-1" aria-hidden="true" style="z-index:1060;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark border-0">
            <div class="modal-header border-0 py-2">
                <span class="text-white-50 fs-12" id="gallery-counter"></span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="imageCarousel" class="carousel slide" data-bs-touch="true">
                    <div class="carousel-inner" id="carousel-items"></div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#imageCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#imageCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Format số kiểu VN: dấu . phân tách ngàn, dấu , thập phân, bỏ 0 thừa
function fnum(val, dec) {
    var n = parseFloat(val) || 0;
    var parts = n.toFixed(dec).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    if (parts[1]) parts[1] = parts[1].replace(/0+$/, '');
    return parts[1] ? parts.join(',') : parts[0];
}
$(function(){
    var csrfToken = '<?= (new Csrf())->get_token_value() ?>';
    var pkgAjaxUrl = '<?= base_url('ajaxs/staffcn/packages.php') ?>';

    // Track which orders have been expanded (packages loaded)
    var expandedOrders = {}; // orderId -> true

    // ===== Expand/Collapse group =====
    $(document).on('click', '.btn-expand-group', function(e){
        e.preventDefault();
        var groupId = $(this).data('group-id');
        var $details = $('.pkg-group-detail[data-group-id="' + groupId + '"]');
        var $icon = $(this).find('.grp-icon');
        if ($details.first().hasClass('d-none')) {
            $details.removeClass('d-none');
            $icon.removeClass('ri-arrow-down-s-line').addClass('ri-arrow-up-s-line');
        } else {
            $details.addClass('d-none');
            $icon.removeClass('ri-arrow-up-s-line').addClass('ri-arrow-down-s-line');
        }
    });

    // ===== Expand/Collapse packages =====
    var colCount = $('table.table-hover > thead > tr > th').length;

    $(document).on('click', '.btn-expand-pkgs', function(e){
        e.preventDefault();
        var orderId = $(this).data('order-id');
        var $orderRow = $(this).closest('tr');
        var $expandRow = $('#pkg-row-' + orderId);
        var $icon = $(this).find('.expand-icon');

        if ($expandRow.length && $expandRow.is(':visible')) {
            $expandRow.hide();
            $icon.removeClass('ri-arrow-up-s-line').addClass('ri-arrow-down-s-line');
            return;
        }

        $icon.removeClass('ri-arrow-down-s-line').addClass('ri-arrow-up-s-line');

        if ($expandRow.length) {
            $expandRow.show();
            return;
        }

        var $newRow = $('<tr class="pkg-expand-row" id="pkg-row-' + orderId + '" data-order-id="' + orderId + '"><td colspan="' + colCount + '" class="p-0"><div class="px-4 py-2 bg-light"><div class="text-center text-muted py-2"><i class="ri-loader-4-line ri-spin"></i> <?= __('Đang tải...') ?></div></div></td></tr>');
        $orderRow.after($newRow);

        $.post(pkgAjaxUrl, {
            request_name: 'get_order_packages',
            order_id: orderId,
            csrf_token: csrfToken
        }, function(res){
            if (res.status === 'success') {
                var statusLabels = {
                    'cn_warehouse': '<?= __('Đã về kho Trung Quốc') ?>',
                    'packed': '<?= __('Đã đóng bao') ?>',
                    'loading': '<?= __('Đang xếp xe') ?>',
                    'shipping': '<?= __('Đang vận chuyển') ?>',
                    'vn_warehouse': '<?= __('Đã về kho Việt Nam') ?>',
                    'delivered': '<?= __('Đã giao hàng') ?>',
                    'cancelled': '<?= __('Đã hủy') ?>',
                    'returned': '<?= __('Hoàn hàng') ?>',
                    'damaged': '<?= __('Hỏng hàng') ?>'
                };
                var html = '<table class="table table-sm table-borderless mb-0">';
                html += '<thead><tr>';
                html += '<th><?= __('Mã kiện') ?></th>';
                html += '<th><?= __('Cân nặng') ?></th>';
                html += '<th><?= __('Kích thước') ?></th>';
                html += '<th><?= __('Số khối') ?></th>';
                html += '<th><?= __('Trạng thái') ?></th>';
                html += '</tr></thead><tbody>';

                var groups = [];
                var groupMap = {};
                res.packages.forEach(function(pkg){
                    var key = pkg.weight_actual + '|' + pkg.length_cm + '|' + pkg.width_cm + '|' + pkg.height_cm + '|' + pkg.status;
                    if (groupMap[key] === undefined) {
                        groupMap[key] = groups.length;
                        groups.push({ key: key, pkgs: [pkg] });
                    } else {
                        groups[groupMap[key]].pkgs.push(pkg);
                    }
                });

                groups.forEach(function(group){
                    var pkgs = group.pkgs;
                    var first = pkgs[0];
                    var dim = (first.length_cm > 0 || first.width_cm > 0 || first.height_cm > 0)
                        ? parseFloat(first.length_cm) + '×' + parseFloat(first.width_cm) + '×' + parseFloat(first.height_cm)
                        : '-';

                    if (pkgs.length === 1) {
                        html += '<tr>';
                        html += '<td><strong>' + first.package_code + '</strong></td>';
                        html += '<td>' + (first.weight_actual > 0 ? fnum(first.weight_actual, 2) + ' kg' : '-') + '</td>';
                        html += '<td>' + dim + '</td>';
                        html += '<td>' + (first.cbm > 0 ? fnum(first.cbm, 2) + ' m³' : '-') + '</td>';
                        html += '<td>' + (statusLabels[first.status] || first.status) + '</td>';
                        html += '</tr>';
                    } else {
                        var groupId = 'grp-' + orderId + '-' + group.key.replace(/[|.]/g, '_');
                        var totalW = first.weight_actual * pkgs.length;
                        var totalC = first.cbm * pkgs.length;

                        html += '<tr class="pkg-group-row" data-group-id="' + groupId + '">';
                        html += '<td><a href="#" class="btn-expand-group text-decoration-none" data-group-id="' + groupId + '"><strong>' + pkgs[0].package_code + ' ~ ' + pkgs[pkgs.length-1].package_code + '</strong> <span class="badge bg-primary-subtle text-primary">' + pkgs.length + ' <?= __('kiện') ?></span> <i class="ri-arrow-down-s-line grp-icon"></i></a></td>';
                        html += '<td>' + (first.weight_actual > 0 ? fnum(first.weight_actual, 2) + ' kg × ' + pkgs.length + ' = <strong>' + fnum(totalW, 2) + ' kg</strong>' : '-') + '</td>';
                        html += '<td>' + dim + '</td>';
                        html += '<td>' + (first.cbm > 0 ? fnum(first.cbm, 2) + ' m³ × ' + pkgs.length + ' = <strong>' + fnum(totalC, 2) + ' m³</strong>' : '-') + '</td>';
                        html += '<td>' + (statusLabels[first.status] || first.status) + '</td>';
                        html += '</tr>';

                        pkgs.forEach(function(pkg){
                            html += '<tr class="pkg-group-detail d-none" data-group-id="' + groupId + '">';
                            html += '<td class="ps-4">' + pkg.package_code + '</td>';
                            html += '<td>' + (pkg.weight_actual > 0 ? fnum(pkg.weight_actual, 2) + ' kg' : '-') + '</td>';
                            html += '<td>' + dim + '</td>';
                            html += '<td>' + (pkg.cbm > 0 ? fnum(pkg.cbm, 2) + ' m³' : '-') + '</td>';
                            html += '<td>' + (statusLabels[pkg.status] || pkg.status) + '</td>';
                            html += '</tr>';
                        });
                    }
                });

                html += '</tbody></table>';
                $newRow.find('.bg-light > div').removeClass('text-center').html(html);
                expandedOrders[orderId] = true;
            }
        }, 'json');
    });

    // Delete order
    $(document).on('click', '.btn-delete-order', function(){
        var btn = $(this);
        var id = btn.data('id');
        var code = btn.data('code');
        Swal.fire({
            title: '<?= __('Xác nhận xóa?') ?>',
            html: '<?= __('Bạn có chắc muốn xóa đơn hàng') ?> <strong>' + code + '</strong>?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: '<?= __('Xóa') ?>',
            cancelButtonText: '<?= __('Hủy') ?>'
        }).then(function(result){
            if(result.isConfirmed){
                $.post('<?= base_url('ajaxs/staffcn/orders.php') ?>', {
                    request_name: 'delete',
                    id: id,
                    csrf_token: csrfToken
                }, function(res){
                    if(res.status == 'success'){
                        Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
                    } else {
                        Swal.fire({icon: 'error', title: 'Error', text: res.msg});
                    }
                }, 'json').fail(function(){
                    Swal.fire({icon: 'error', title: 'Error', text: '<?= __('Lỗi kết nối') ?>'});
                });
            }
        });
    });


    // ===== View Images (Bootstrap Carousel) =====
    var galleryCarousel = null;
    var galleryTotal = 0;

    function updateGalleryCounter() {
        var idx = $('#imageCarousel .carousel-item.active').index();
        $('#gallery-counter').text((idx + 1) + ' / ' + galleryTotal);
        if (galleryTotal <= 1) {
            $('#imageCarousel .carousel-control-prev, #imageCarousel .carousel-control-next').addClass('d-none');
        } else {
            $('#imageCarousel .carousel-control-prev, #imageCarousel .carousel-control-next').removeClass('d-none');
        }
    }

    $('#imageCarousel').on('slid.bs.carousel', updateGalleryCounter);

    $(document).on('click', '.btn-view-images', function(e){
        e.preventDefault();
        var images = $(this).data('images');
        if (!images || !images.length) return;
        galleryTotal = images.length;

        var html = '';
        images.forEach(function(url, i){
            html += '<div class="carousel-item' + (i === 0 ? ' active' : '') + '">'
                + '<div class="d-flex align-items-center justify-content-center" style="min-height:300px;">'
                + '<img src="' + url + '" class="d-block" style="max-width:100%;max-height:75vh;object-fit:contain;">'
                + '</div></div>';
        });
        $('#carousel-items').html(html);

        if (galleryCarousel) galleryCarousel.dispose();
        galleryCarousel = new bootstrap.Carousel($('#imageCarousel')[0], { interval: false, touch: true, keyboard: true });

        updateGalleryCounter();
        new bootstrap.Modal($('#imageGalleryModal')[0]).show();
    });

    // Export Excel
    $('#btn-export-excel').on('click', function(){
        var params = new URLSearchParams(window.location.search);
        params.set('product_type', '<?= $_productTypeFilter ?>');
        window.location.href = '<?= base_url('ajaxs/staffcn/orders-export.php') ?>?' + params.toString();
    });

});
</script>
