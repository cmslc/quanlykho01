<?php
require_once(__DIR__.'/../../../models/is_admin.php');
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

$orders = $ToryHub->get_list_safe("SELECT o.*, c.fullname as customer_name, c.customer_code
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

$customers = $ToryHub->get_list_safe("SELECT `id`, `customer_code`, `fullname` FROM `customers` ORDER BY `fullname` ASC", []);

$statuses = ['cn_warehouse', 'packed', 'loading', 'shipping', 'vn_warehouse', 'delivered', 'cancelled'];

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= $page_title ?></h4>
                    <a href="<?= base_url('admin/orders-add&product_type=' . $_productTypeFilter) ?>" class="btn btn-primary">
                        <i class="ri-add-line"></i> <?= __('Nhập kho') ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="<?= base_url('admin/' . $_currentAction) ?>">
                            <input type="hidden" name="module" value="admin">
                            <input type="hidden" name="action" value="<?= $_currentAction ?>">
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
                                        <option value="<?= $c['id'] ?>" <?= $filterCustomer == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['customer_code'] . ' - ' . $c['fullname']) ?></option>
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
                                    <a href="<?= base_url('admin/' . $_currentAction) ?>" class="btn btn-secondary"><?= __('Reset') ?></a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Summary -->
        <div class="row">
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
            <div class="col">
                <a href="<?= base_url('admin/' . $_currentAction . '&status=' . $s) ?>" class="text-decoration-none">
                    <div class="card card-animate <?= $filterStatus == $s ? 'border border-primary' : '' ?>">
                        <div class="card-body py-2 text-center">
                            <h5 class="mb-0"><?= $statusCounts[$s] ?></h5>
                            <small class="text-muted"><?= display_order_status($s) ?></small>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Orders Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h5 class="card-title mb-0"><?= $page_title ?> (<?= $totalOrders ?>)</h5>
                        <div class="d-flex align-items-center gap-2">
                            <select class="form-select" id="bulk-action" style="width:220px;">
                                <option value=""><?= __('Hành động hàng loạt') ?></option>
                                <?php foreach ($statuses as $s): ?>
                                <option value="<?= $s ?>"><?= display_order_status($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-primary" id="btn-bulk-apply"><?= __('Áp dụng') ?></button>
                            <button class="btn btn-success" id="btn-export-excel"><i class="ri-file-excel-2-line me-1"></i><?= __('Xuất Excel') ?></button>
                        </div>
                    </div>
                    <div id="selected-summary" class="card-body border-bottom py-2 d-none">
                        <div class="d-flex align-items-center gap-4 flex-wrap">
                            <span class="text-muted"><?= __('Đã chọn') ?>: <strong id="sum-orders">0</strong> <?= __('đơn') ?>, <strong id="sum-pkgs">0</strong> <?= __('kiện') ?></span>
                            <span><?= __('Tổng cân nặng') ?>: <strong id="sum-weight" class="text-primary">0 kg</strong></span>
                            <span><?= __('Tổng số khối') ?>: <strong id="sum-cbm" class="text-primary">0 m³</strong></span>
                            <span id="sum-cargo" class="d-none">
                                <span class="badge bg-success-subtle text-success fs-12 px-2 py-1"><i class="ri-truck-line me-1"></i><?= __('Hàng dễ') ?>: <strong id="sum-cargo-easy">0 m³</strong> (<span id="sum-cargo-easy-pct">0</span>%)</span>
                                <span class="badge bg-danger-subtle text-danger fs-12 px-2 py-1"><i class="ri-alarm-warning-line me-1"></i><?= __('Hàng khó') ?>: <strong id="sum-cargo-difficult">0 m³</strong> (<span id="sum-cargo-difficult-pct">0</span>%)</span>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:40px;"><input type="checkbox" class="form-check-input" id="check-all"></th>
                                        <th><?= __('Mã hàng / Mã vận đơn') ?></th>
                                        <?php if ($isRetailPage): ?><th><?= __('Mã bao') ?></th><?php endif; ?>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th><?= __('Sản phẩm') ?></th>
                                        <th class="text-center" style="width:60px;"><?= __('Ảnh') ?></th>
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
                                        $orderWeightActual = floatval($order['weight_actual'] ?? 0);
                                        $orderWeightCharged = floatval($order['weight_charged'] ?? 0);
                                    ?>
                                    <tr>
                                        <?php $pkgCount = $pw['total_packages'] ?? 0; ?>
                                        <?php
                                        if (!$isRetail) {
                                            $displayWeight = $orderWeightCharged > 0 ? $orderWeightCharged
                                                : ($orderWeightActual > 0 ? $orderWeightActual
                                                : ($wCharged > 0 ? $wCharged
                                                : $wActual));
                                        } else {
                                            $displayWeight = $wCharged > 0 ? $wCharged : ($wActual > 0 ? $wActual : 0);
                                        }
                                        ?>
                                        <td><input type="checkbox" class="form-check-input order-check" value="<?= $order['id'] ?>" data-weight="<?= $displayWeight ?>" data-cbm="<?= $cbm ?>" data-cargo="<?= htmlspecialchars($order['cargo_type'] ?? '') ?>" data-pkg-count="<?= $pkgCount ?>"></td>
                                        <td>
                                            <?php if ($isRetail): ?>
                                                <?php if (!empty($orderTrackings)): ?>
                                                    <?php foreach ($orderTrackings as $tk): ?>
                                                        <?php if ($tk): ?>
                                                        <a href="<?= base_url('admin/orders-detail&id=' . $order['id']) ?>"><strong><?= htmlspecialchars($tk) ?></strong></a><br>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <a href="<?= base_url('admin/orders-detail&id=' . $order['id']) ?>" class="text-muted">#<?= $order['id'] ?></a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php if ($order['product_code'] ?? ''): ?>
                                                <a href="<?= base_url('admin/orders-detail&id=' . $order['id']) ?>"><strong><?= htmlspecialchars($order['product_code']) ?></strong></a>
                                                <?php else: ?>
                                                <a href="<?= base_url('admin/orders-detail&id=' . $order['id']) ?>" class="text-muted">#<?= $order['id'] ?></a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if ($pkgCount > 0): ?>
                                            <div class="mt-1"><a href="#" class="btn-expand-pkgs text-muted text-decoration-none" data-order-id="<?= $order['id'] ?>"><i class="ri-archive-line"></i> <?= $pkgCount ?> <?= __('kiện') ?> <i class="ri-arrow-down-s-line expand-icon fs-14"></i></a></div>
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
                                            <a href="<?= base_url('admin/customers-detail&id=' . $order['customer_id']) ?>" class="fw-bold"><?= htmlspecialchars($order['customer_name'] ?? '') ?></a>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars(mb_strimwidth($order['product_name'] ?? '', 0, 30, '...')) ?></strong>
                                            <?php if (!$isRetail && !empty($order['cargo_type'])): ?>
                                            <div class="mt-1"><?= display_cargo_type($order['cargo_type']) ?></div>
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
                                            <?= display_order_status($order['status']) ?>
                                            <?php
                                            $opkgMap = $pkgStatusMap[$order['id']] ?? [];
                                            if (count($opkgMap) > 1):
                                                $opkgLabels = [
                                                    'cn_warehouse' => ['label' => 'Đã về kho Trung Quốc', 'bg' => 'info'],
                                                    'packed'       => ['label' => 'Đã đóng bao', 'bg' => 'dark'],
                                                    'shipping'     => ['label' => 'Đang vận chuyển', 'bg' => 'primary'],
                                                    'vn_warehouse' => ['label' => 'Đã về kho Việt Nam', 'bg' => 'success'],
                                                    'delivered'    => ['label' => 'Đã giao hàng', 'bg' => 'success'],
                                                ];
                                            ?>
                                            <div class="mt-1">
                                                <?php foreach ($opkgLabels as $st => $cfg):
                                                    if ($st === 'packed' && !$isRetail) continue;
                                                    $cnt = $opkgMap[$st] ?? 0;
                                                    if ($cnt > 0):
                                                ?>
                                                <span class="badge bg-<?= $cfg['bg'] ?>-subtle text-<?= $cfg['bg'] ?>" style="font-size:10px;"><?= __($cfg['label']) ?>: <?= $cnt ?></span>
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
                                                <a href="<?= base_url('admin/orders-detail&id=' . $order['id']) ?>" class="btn btn-sm btn-info"><i class="ri-eye-line me-1"></i><?= __('Xem') ?></a>
                                                <a href="<?= base_url('admin/orders-edit&id=' . $order['id']) ?>" class="btn btn-sm btn-primary"><i class="ri-pencil-line me-1"></i><?= __('Sửa') ?></a>
                                                <button type="button" class="btn btn-sm btn-danger btn-delete-order" data-id="<?= $order['id'] ?>" data-code="<?= htmlspecialchars($order['order_code'] ?: '#' . $order['id']) ?>"><i class="ri-delete-bin-line me-1"></i><?= __('Xóa') ?></button>
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
                                    $baseUrl = base_url('admin/' . $_currentAction) . ($queryParams ? '&' . http_build_query($queryParams) : '');
                                    ?>
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $baseUrl . '&page=' . ($page - 1) ?>">&laquo;</a>
                                    </li>
                                    <?php
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    if ($startPage > 1): ?>
                                        <li class="page-item"><a class="page-link" href="<?= $baseUrl . '&page=1' ?>">1</a></li>
                                        <?php if ($startPage > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                                    <?php endif; ?>
                                    <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= $baseUrl . '&page=' . $p ?>"><?= $p ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    <?php if ($endPage < $totalPages): ?>
                                        <?php if ($endPage < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                                        <li class="page-item"><a class="page-link" href="<?= $baseUrl . '&page=' . $totalPages ?>"><?= $totalPages ?></a></li>
                                    <?php endif; ?>
                                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $baseUrl . '&page=' . ($page + 1) ?>">&raquo;</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

<!-- Image Gallery Modal -->
<div class="modal fade" id="imageGalleryModal" tabindex="-1" aria-hidden="true">
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

<?php require_once(__DIR__.'/footer.php'); ?>

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
    var pkgAjaxUrl = '<?= base_url('ajaxs/admin/packages.php') ?>';

    // Track which orders have been expanded (packages loaded)
    var expandedOrders = {}; // orderId -> true

    // ===== Summary calculation =====
    function updateSelectedSummary(){
        var orderCount = 0, pkgCount = 0, totalWeight = 0, totalCbm = 0, cbmEasy = 0, cbmDifficult = 0;

        $('.order-check').each(function(){
            var $cb = $(this);
            var orderId = $cb.val();
            var cargo = $cb.data('cargo');

            if (expandedOrders[orderId]) {
                // Expanded: count only checked packages
                var $pkgChecked = $('#pkg-row-' + orderId + ' .sub-pkg-check:checked');
                if ($pkgChecked.length > 0) {
                    orderCount++;
                    $pkgChecked.each(function(){
                        pkgCount++;
                        var w = parseFloat($(this).data('weight')) || 0;
                        var c = parseFloat($(this).data('cbm')) || 0;
                        totalWeight += w;
                        totalCbm += c;
                        if (cargo === 'easy') cbmEasy += c;
                        else if (cargo === 'difficult') cbmDifficult += c;
                    });
                }
            } else if ($cb.is(':checked')) {
                // Not expanded: use aggregate
                orderCount++;
                pkgCount += parseInt($cb.data('pkg-count')) || 0;
                var w = parseFloat($cb.data('weight')) || 0;
                var c = parseFloat($cb.data('cbm')) || 0;
                totalWeight += w;
                totalCbm += c;
                if (cargo === 'easy') cbmEasy += c;
                else if (cargo === 'difficult') cbmDifficult += c;
            }
        });

        if (orderCount > 0 || pkgCount > 0) {
            $('#selected-summary').removeClass('d-none');
            $('#sum-orders').text(orderCount);
            $('#sum-pkgs').text(pkgCount);
            $('#sum-weight').text(fnum(totalWeight, 1) + ' kg');
            $('#sum-cbm').text(fnum(totalCbm, 2) + ' m³');
            var cbmCargo = cbmEasy + cbmDifficult;
            if (cbmCargo > 0) {
                $('#sum-cargo').removeClass('d-none');
                $('#sum-cargo-easy').text(fnum(cbmEasy, 2) + ' m³');
                $('#sum-cargo-easy-pct').text(Math.round(cbmEasy / cbmCargo * 100));
                $('#sum-cargo-difficult').text(fnum(cbmDifficult, 2) + ' m³');
                $('#sum-cargo-difficult-pct').text(Math.round(cbmDifficult / cbmCargo * 100));
            } else {
                $('#sum-cargo').addClass('d-none');
            }
        } else {
            $('#selected-summary').addClass('d-none');
        }
    }

    // ===== Select all orders =====
    $('#check-all').on('change', function(){
        var checked = this.checked;
        $('.order-check').prop('checked', checked);
        // Sync expanded package checkboxes
        $('.order-check').each(function(){
            var orderId = $(this).val();
            if (expandedOrders[orderId]) {
                $('#pkg-row-' + orderId + ' .sub-pkg-check').prop('checked', checked);
                $('#pkg-row-' + orderId + ' .sub-pkg-check-all').prop('checked', checked).prop('indeterminate', false);
                $('#pkg-row-' + orderId + ' .sub-pkg-group-check').prop('checked', checked).prop('indeterminate', false);
            }
        });
        updateSelectedSummary();
    });

    // ===== Order checkbox change =====
    $(document).on('change', '.order-check', function(){
        var orderId = $(this).val();
        var checked = this.checked;
        // Sync package checkboxes if expanded
        if (expandedOrders[orderId]) {
            $('#pkg-row-' + orderId + ' .sub-pkg-check').prop('checked', checked);
            $('#pkg-row-' + orderId + ' .sub-pkg-check-all').prop('checked', checked).prop('indeterminate', false);
            $('#pkg-row-' + orderId + ' .sub-pkg-group-check').prop('checked', checked).prop('indeterminate', false);
        }
        // Update master checkbox
        var total = $('.order-check').length;
        var chk = $('.order-check:checked').length;
        $('#check-all').prop('checked', total === chk).prop('indeterminate', chk > 0 && chk < total);
        updateSelectedSummary();
    });

    // ===== Package "select all" checkbox =====
    $(document).on('change', '.sub-pkg-check-all', function(){
        var orderId = $(this).data('order-id');
        var checked = this.checked;
        $('#pkg-row-' + orderId + ' .sub-pkg-check').prop('checked', checked);
        $('#pkg-row-' + orderId + ' .sub-pkg-group-check').prop('checked', checked).prop('indeterminate', false);
        // Sync order checkbox
        syncOrderCheckbox(orderId);
        updateSelectedSummary();
    });

    // ===== Individual package checkbox =====
    $(document).on('change', '.sub-pkg-check', function(){
        var orderId = $(this).closest('.pkg-expand-row').data('order-id');
        // Sync group checkbox + qty input if part of a group
        var groupId = $(this).data('group-id');
        if (groupId) {
            var $groupChecks = $('.sub-pkg-check[data-group-id="' + groupId + '"]');
            var total = $groupChecks.length;
            var checked = $groupChecks.filter(':checked').length;
            var $groupCb = $('.sub-pkg-group-check[data-group-id="' + groupId + '"]');
            $groupCb.prop('checked', total === checked).prop('indeterminate', checked > 0 && checked < total);
            updateGroupDisplay(groupId);
        }
        syncOrderCheckbox(orderId);
        updateSelectedSummary();
    });

    // ===== Group checkbox =====
    $(document).on('change', '.sub-pkg-group-check', function(){
        var groupId = $(this).data('group-id');
        var checked = this.checked;
        var $checks = $('.sub-pkg-check[data-group-id="' + groupId + '"]');
        $checks.prop('checked', checked);
        updateGroupDisplay(groupId);
        var orderId = $(this).closest('.pkg-expand-row').data('order-id');
        syncOrderCheckbox(orderId);
        updateSelectedSummary();
    });

    // Update group row weight/cbm display based on selected qty
    function updateGroupDisplay(groupId) {
        var $checks = $('.sub-pkg-check[data-group-id="' + groupId + '"]');
        var checkedCount = $checks.filter(':checked').length;
        var $wCell = $('.grp-weight-cell[data-group-id="' + groupId + '"]');
        var $cCell = $('.grp-cbm-cell[data-group-id="' + groupId + '"]');
        var unitW = parseFloat($wCell.data('unit-w')) || 0;
        var unitC = parseFloat($cCell.data('unit-c')) || 0;
        if (unitW > 0) {
            $wCell.html('1 kiện: ' + fnum(unitW, 2) + ' kg<br><strong>' + fnum(unitW * checkedCount, 2) + ' kg</strong>');
        }
        if (unitC > 0) {
            $cCell.html('1 kiện: ' + fnum(unitC, 2) + ' m³<br><strong>' + fnum(unitC * checkedCount, 2) + ' m³</strong>');
        }
    }

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

    function syncOrderCheckbox(orderId){
        var $row = $('#pkg-row-' + orderId);
        var total = $row.find('.sub-pkg-check').length;
        var checked = $row.find('.sub-pkg-check:checked').length;
        // Update sub-pkg-check-all
        $row.find('.sub-pkg-check-all').prop('checked', total === checked).prop('indeterminate', checked > 0 && checked < total);
        // Update order checkbox
        var $orderCb = $('.order-check[value="' + orderId + '"]');
        $orderCb.prop('checked', checked > 0 && total === checked);
        $orderCb.prop('indeterminate', checked > 0 && checked < total);
        // Update master checkbox
        var totalOrders = $('.order-check').length;
        var chkOrders = $('.order-check:checked').length;
        var indOrders = $('.order-check:indeterminate').length;
        $('#check-all').prop('checked', totalOrders === chkOrders && indOrders === 0).prop('indeterminate', (chkOrders > 0 || indOrders > 0) && (chkOrders < totalOrders || indOrders > 0));
    }

    // ===== Expand/Collapse packages =====
    var colCount = $('table.table-hover > thead > tr > th').length;

    $(document).on('click', '.btn-expand-pkgs', function(e){
        e.preventDefault();
        var orderId = $(this).data('order-id');
        var $orderRow = $(this).closest('tr');
        var $expandRow = $('#pkg-row-' + orderId);
        var $icon = $(this).find('.expand-icon');

        if ($expandRow.length && $expandRow.is(':visible')) {
            // Collapse (keep expanded state for accurate summary)
            $expandRow.hide();
            $icon.removeClass('ri-arrow-up-s-line').addClass('ri-arrow-down-s-line');
            return;
        }

        // Show expand icon
        $icon.removeClass('ri-arrow-down-s-line').addClass('ri-arrow-up-s-line');

        if ($expandRow.length) {
            // Already created, just show
            $expandRow.show();
            updateSelectedSummary();
            return;
        }

        // Create expand row dynamically
        var cargo = $('.order-check[value="' + orderId + '"]').data('cargo');
        var $newRow = $('<tr class="pkg-expand-row" id="pkg-row-' + orderId + '" data-order-id="' + orderId + '" data-cargo="' + (cargo || '') + '"><td colspan="' + colCount + '" class="p-0"><div class="px-4 py-2 bg-light"><div class="text-center text-muted py-2"><i class="ri-loader-4-line ri-spin"></i> <?= __('Đang tải...') ?></div></div></td></tr>');
        $orderRow.after($newRow);

        // Load packages via AJAX
        var orderChecked = $('.order-check[value="' + orderId + '"]').is(':checked');

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
                    'cancelled': '<?= __('Đã hủy') ?>'
                };
                var html = '<table class="table table-sm table-borderless mb-0">';
                html += '<thead><tr>';
                html += '<th style="width:30px;"><input type="checkbox" class="form-check-input sub-pkg-check-all" data-order-id="' + orderId + '"' + (orderChecked ? ' checked' : '') + '></th>';
                html += '<th><?= __('Mã kiện') ?></th>';
                html += '<th><?= __('Cân nặng') ?></th>';
                html += '<th><?= __('Kích thước') ?></th>';
                html += '<th><?= __('Số khối') ?></th>';
                html += '<th><?= __('Trạng thái') ?></th>';
                html += '</tr></thead><tbody>';

                // Group packages by same weight + dimensions + status
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

                // Build sequential index for each package
                var pkgIndex = 0;
                var pkgIndexMap = {};
                res.packages.forEach(function(pkg){ pkgIndexMap[pkg.id] = ++pkgIndex; });

                groups.forEach(function(group){
                    var pkgs = group.pkgs;
                    var first = pkgs[0];
                    var dim = (first.length_cm > 0 || first.width_cm > 0 || first.height_cm > 0)
                        ? parseFloat(first.length_cm) + '×' + parseFloat(first.width_cm) + '×' + parseFloat(first.height_cm)
                        : '-';

                    if (pkgs.length === 1) {
                        // Single package
                        var idx = pkgIndexMap[first.id];
                        html += '<tr>';
                        html += '<td><input type="checkbox" class="form-check-input sub-pkg-check" value="' + first.id + '" data-weight="' + first.weight_actual + '" data-cbm="' + first.cbm + '"' + (orderChecked ? ' checked' : '') + '></td>';
                        html += '<td><strong>' + first.package_code + '</strong></td>';
                        html += '<td>' + (first.weight_actual > 0 ? fnum(first.weight_actual, 2) + ' kg' : '-') + '</td>';
                        html += '<td>' + dim + '</td>';
                        html += '<td>' + (first.cbm > 0 ? fnum(first.cbm, 2) + ' m³' : '-') + '</td>';
                        html += '<td>' + (statusLabels[first.status] || first.status) + '</td>';
                        html += '</tr>';
                    } else {
                        // Grouped packages - collapsible group row
                        var groupId = 'grp-' + orderId + '-' + group.key.replace(/[|.]/g, '_');
                        var ids = pkgs.map(function(p){ return p.id; });
                        var totalW = first.weight_actual * pkgs.length;
                        var totalC = first.cbm * pkgs.length;
                        var firstIdx = pkgIndexMap[pkgs[0].id];
                        var lastIdx = pkgIndexMap[pkgs[pkgs.length - 1].id];

                        html += '<tr class="pkg-group-row" data-group-id="' + groupId + '">';
                        html += '<td><input type="checkbox" class="form-check-input sub-pkg-group-check" data-group-id="' + groupId + '" data-ids=\'' + JSON.stringify(ids) + '\' data-total="' + pkgs.length + '"' + (orderChecked ? ' checked' : '') + '></td>';
                        html += '<td><a href="#" class="btn-expand-group text-decoration-none" data-group-id="' + groupId + '"><strong>' + pkgs[0].package_code + ' ~ ' + pkgs[pkgs.length-1].package_code + '</strong> <span class="badge bg-primary-subtle text-primary">' + pkgs.length + ' <?= __('kiện') ?></span> <i class="ri-arrow-down-s-line grp-icon"></i></a>';
                        html += '</td>';
                        html += '<td class="grp-weight-cell" data-group-id="' + groupId + '" data-unit-w="' + first.weight_actual + '">' + (first.weight_actual > 0 ? '1 kiện: ' + fnum(first.weight_actual, 2) + ' kg<br><strong>' + fnum(orderChecked ? totalW : 0, 2) + ' kg</strong>' : '-') + '</td>';
                        html += '<td>' + dim + '</td>';
                        html += '<td class="grp-cbm-cell" data-group-id="' + groupId + '" data-unit-c="' + first.cbm + '">' + (first.cbm > 0 ? '1 kiện: ' + fnum(first.cbm, 2) + ' m³<br><strong>' + fnum(orderChecked ? totalC : 0, 2) + ' m³</strong>' : '-') + '</td>';
                        html += '<td>' + (statusLabels[first.status] || first.status) + '</td>';
                        html += '</tr>';

                        // Hidden individual rows
                        pkgs.forEach(function(pkg){
                            var idx = pkgIndexMap[pkg.id];
                            html += '<tr class="pkg-group-detail d-none" data-group-id="' + groupId + '">';
                            html += '<td class="ps-4"><input type="checkbox" class="form-check-input sub-pkg-check" value="' + pkg.id + '" data-weight="' + pkg.weight_actual + '" data-cbm="' + pkg.cbm + '" data-group-id="' + groupId + '"' + (orderChecked ? ' checked' : '') + '></td>';
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
                updateSelectedSummary();
            }
        }, 'json');
    });

    // Bulk apply - smart: package-level for expanded, order-level for non-expanded
    $('#btn-bulk-apply').on('click', function(){
        var newStatus = $('#bulk-action').val();
        var orderIds = [];   // non-expanded fully-checked orders
        var packageIds = []; // specific packages from expanded orders

        $('.order-check').each(function(){
            var orderId = $(this).val();
            if (expandedOrders[orderId]) {
                // Expanded: collect only checked package IDs
                $('#pkg-row-' + orderId + ' .sub-pkg-check:checked').each(function(){
                    packageIds.push($(this).val());
                });
            } else if (this.checked) {
                // Non-expanded fully checked: update whole order
                orderIds.push(orderId);
            }
        });

        if (orderIds.length === 0 && packageIds.length === 0) {
            Swal.fire({icon: 'warning', title: '<?= __('Vui lòng chọn ít nhất 1 đơn hàng hoặc kiện hàng') ?>'});
            return;
        }
        if (!newStatus) {
            Swal.fire({icon: 'warning', title: '<?= __('Vui lòng chọn hành động') ?>'});
            return;
        }

        var msgParts = [];
        if (orderIds.length > 0) msgParts.push(orderIds.length + ' <?= __('đơn hàng') ?>');
        if (packageIds.length > 0) msgParts.push(packageIds.length + ' <?= __('kiện hàng') ?>');

        Swal.fire({
            title: '<?= __('Xác nhận cập nhật hàng loạt?') ?>',
            html: '<?= __('Cập nhật') ?> <strong>' + msgParts.join(' + ') + '</strong>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<?= __('Áp dụng') ?>',
            cancelButtonText: '<?= __('Hủy') ?>'
        }).then(function(result){
            if (!result.isConfirmed) return;

            var btn = $('#btn-bulk-apply');
            btn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin me-1"></i>');

            var requests = [];

            // Update whole orders (non-expanded)
            if (orderIds.length > 0) {
                requests.push($.post('<?= base_url('ajaxs/admin/orders-status.php') ?>', {
                    request_name: 'bulk_update_status',
                    order_ids: orderIds.join(','),
                    new_status: newStatus,
                    note: '<?= __('Cập nhật hàng loạt') ?>',
                    csrf_token: csrfToken
                }, null, 'json'));
            }

            // Update specific packages (expanded)
            if (packageIds.length > 0) {
                requests.push($.post(pkgAjaxUrl, {
                    request_name: 'bulk_update_status',
                    package_ids: packageIds.join(','),
                    new_status: newStatus,
                    csrf_token: csrfToken
                }, null, 'json'));
            }

            $.when.apply($, requests).then(function(){
                // Collect results from all requests
                var results = requests.length === 1 ? [arguments] : Array.from(arguments);
                var allSuccess = true;
                var msgs = [];
                results.forEach(function(r){
                    var res = r[0] || r;
                    if (res.status !== 'success') allSuccess = false;
                    if (res.msg) msgs.push(res.msg);
                });
                if (allSuccess) {
                    Swal.fire({icon: 'success', title: msgs.join(', '), timer: 2000, showConfirmButton: false}).then(function(){ location.reload(); });
                } else {
                    Swal.fire({icon: 'error', title: 'Error', text: msgs.join(', ')});
                    btn.prop('disabled', false).html('<?= __('Áp dụng') ?>');
                }
            }, function(){
                Swal.fire({icon: 'error', title: 'Error', text: '<?= __('Lỗi kết nối') ?>'});
                btn.prop('disabled', false).html('<?= __('Áp dụng') ?>');
            });
        });
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
                $.post('<?= base_url('ajaxs/admin/orders.php') ?>', {
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

    // Export Excel
    $('#btn-export-excel').on('click', function(){
        var params = new URLSearchParams(window.location.search);
        params.set('export', 'excel');
        params.set('product_type', '<?= $_productTypeFilter ?>');
        window.location.href = '<?= base_url('ajaxs/admin/orders-export.php') ?>?' + params.toString();
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

});
</script>
