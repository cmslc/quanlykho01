<?php
$activeTab = input_get('tab') ?: 'tracking';

if ($activeTab === 'tracking') {
    // Tab Mã vận đơn: use existing orders-list with retail filter
    $_productTypeFilter = 'retail';
    $_retailActiveTab = $activeTab;
    $_showRetailTabs = true;
    require(__DIR__.'/orders-list.php');
} else {
    // Tab Mã bao: standalone bag view
    require_once(__DIR__.'/../../../models/is_staffcn.php');
    require_once(__DIR__.'/../../../libs/csrf.php');

    $page_title = __('Danh sách hàng lẻ');

    // Filters
    $filterSearch = trim(input_get('search') ?? '');
    $filterCustomer = input_get('customer_id') ?: '';

    // Pagination
    $perPage = 10;
    $page = max(1, intval(input_get('page') ?? 1));
    $offset = ($page - 1) * $perPage;

    $bagWhere = "1=1";
    $bagParams = [];
    if ($filterSearch) {
        $bagWhere .= " AND b.bag_code LIKE ?";
        $bagParams[] = '%' . $filterSearch . '%';
    }
    if ($filterCustomer) {
        $bagWhere .= " AND b.id IN (SELECT bp2.bag_id FROM `bag_packages` bp2 JOIN `packages` p2 ON bp2.package_id = p2.id JOIN `package_orders` po2 ON p2.id = po2.package_id JOIN `orders` o2 ON po2.order_id = o2.id WHERE o2.customer_id = ?)";
        $bagParams[] = intval($filterCustomer);
    }

    $totalBags = $ToryHub->get_row_safe("SELECT COUNT(*) as cnt FROM `bags` b WHERE $bagWhere", $bagParams)['cnt'] ?? 0;
    $totalPages = max(1, ceil($totalBags / $perPage));
    if ($page > $totalPages) $page = $totalPages;

    $sealedBags = $ToryHub->get_list_safe(
        "SELECT b.id as bag_id, b.bag_code, b.status, b.images as bag_images,
            b.note,
            COUNT(DISTINCT bp.package_id) as pkg_count,
            SUM(CASE WHEN p.status IN ('vn_warehouse', 'delivered') THEN 1 ELSE 0 END) as pkg_received,
            b.total_weight as bag_weight,
            COALESCE(b.weight_volume, 0) as bag_cbm,
            SUM(COALESCE(p.weight_charged, 0)) as pkg_weight_charged,
            SUM(COALESCE(p.weight_actual, 0)) as pkg_weight_actual,
            SUM(COALESCE(p.length_cm,0) * COALESCE(p.width_cm,0) * COALESCE(p.height_cm,0) / 1000000) as pkg_cbm,
            b.create_date, b.update_date
        FROM `bags` b
        LEFT JOIN `bag_packages` bp ON b.id = bp.bag_id
        LEFT JOIN `packages` p ON bp.package_id = p.id
        WHERE $bagWhere
        GROUP BY b.id
        ORDER BY b.update_date DESC
        LIMIT $perPage OFFSET $offset",
        $bagParams
    );

    // Get customers per bag
    $bagCustomerMap = [];
    if (!empty($sealedBags)) {
        $bagIds = array_column($sealedBags, 'bag_id');
        $ph = implode(',', array_fill(0, count($bagIds), '?'));
        $bagCusts = $ToryHub->get_list_safe(
            "SELECT DISTINCT bp.bag_id, c.id as cid, c.fullname
             FROM `bag_packages` bp
             JOIN `packages` p ON bp.package_id = p.id
             JOIN `package_orders` po ON p.id = po.package_id
             JOIN `orders` o ON po.order_id = o.id
             LEFT JOIN `customers` c ON o.customer_id = c.id
             WHERE bp.bag_id IN ($ph) AND c.id IS NOT NULL",
            $bagIds
        );
        foreach ($bagCusts as $bc) {
            $bagCustomerMap[$bc['bag_id']][$bc['cid']] = $bc['fullname'];
        }
    }

    // Get shipment info per bag
    $bagShipmentMap = [];
    if (!empty($sealedBags)) {
        $bagIds = array_column($sealedBags, 'bag_id');
        $ph = implode(',', array_fill(0, count($bagIds), '?'));
        $bagShipments = $ToryHub->get_list_safe(
            "SELECT DISTINCT bp.bag_id, s.shipment_code, s.truck_plate
             FROM `bag_packages` bp
             JOIN `packages` p ON bp.package_id = p.id
             JOIN `shipment_packages` sp ON p.id = sp.package_id
             JOIN `shipments` s ON sp.shipment_id = s.id
             WHERE bp.bag_id IN ($ph)
               AND p.status IN ('loading', 'shipping', 'vn_warehouse', 'delivered')",
            $bagIds
        );
        foreach ($bagShipments as $bs) {
            $bagShipmentMap[$bs['bag_id']] = $bs;
        }
    }

    $bagStatusLabels = [
        'open'      => ['label' => 'Đang mở', 'bg' => 'info', 'icon' => 'ri-folder-open-line'],
        'sealed'    => ['label' => 'Đã niêm phong', 'bg' => 'warning', 'icon' => 'ri-lock-line'],
        'loading'   => ['label' => 'Đang xếp xe', 'bg' => 'secondary', 'icon' => 'ri-truck-line'],
        'shipping'  => ['label' => 'Đang vận chuyển', 'bg' => 'primary', 'icon' => 'ri-ship-line'],
        'arrived'   => ['label' => 'Đã đến kho Việt Nam', 'bg' => 'success', 'icon' => 'ri-check-double-line'],
        'completed' => ['label' => 'Đã nhận đủ', 'bg' => 'success', 'icon' => 'ri-checkbox-circle-line'],
    ];

    $customers = $ToryHub->get_list_safe("SELECT `id`, `fullname` FROM `customers` ORDER BY `fullname` ASC", []);

    require_once(__DIR__.'/header.php');
    require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= $page_title ?></h4>
                    <div class="d-flex gap-2">
                        <a href="<?= base_url('staffcn/orders-add?product_type=retail') ?>" class="btn btn-primary">
                            <i class="ri-add-line"></i> <?= __('Nhập kho') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="row">
            <div class="col-12">
                <ul class="nav nav-tabs nav-tabs-custom mb-3">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= base_url('staffcn/orders-retail?tab=tracking') ?>">
                            <i class="ri-file-list-3-line me-1"></i><?= __('Mã vận đơn') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="<?= base_url('staffcn/orders-retail?tab=bags') ?>">
                            <i class="ri-archive-line me-1"></i><?= __('Mã bao') ?>
                            <span class="badge bg-secondary-subtle text-secondary ms-1"><?= $totalBags ?></span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Filters -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="<?= base_url('staffcn/orders-retail') ?>">
                            <input type="hidden" name="tab" value="bags">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label"><?= __('Tìm kiếm') ?></label>
                                    <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($filterSearch) ?>" placeholder="<?= __('Mã bao...') ?>">
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
                                <div class="col-md-auto">
                                    <button type="submit" class="btn btn-primary"><i class="ri-search-line me-1"></i><?= __('Lọc') ?></button>
                                    <a href="<?= base_url('staffcn/orders-retail?tab=bags') ?>" class="btn btn-secondary"><?= __('Reset') ?></a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bag Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Mã bao') ?> (<?= $totalBags ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:50px;" class="text-center">#</th>
                                        <th><?= __('Mã bao') ?></th>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th class="text-end" style="width:110px;"><?= __('Cân nặng') ?></th>
                                        <th class="text-end" style="width:100px;"><?= __('Số khối') ?></th>
                                        <th><?= __('Chuyến xe') ?></th>
                                        <th style="width:200px;"><?= __('Trạng thái') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($totalBags == 0): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4"><?= __('Không có dữ liệu') ?></td></tr>
                                    <?php endif; ?>
                                    <?php $rowIdx = $offset; foreach ($sealedBags as $bag):
                                        $rowIdx++;
                                        $bagW = floatval($bag['bag_weight'] ?? 0);
                                        $pkgWCharged = floatval($bag['pkg_weight_charged'] ?? 0);
                                        $pkgWActual = floatval($bag['pkg_weight_actual'] ?? 0);
                                        $weight = $pkgWActual > 0 ? $pkgWActual : ($pkgWCharged > 0 ? $pkgWCharged : $bagW);
                                        $bagCbm = floatval($bag['bag_cbm'] ?? 0);
                                        $pkgCbm = floatval($bag['pkg_cbm'] ?? 0);
                                        $cbm = $bagCbm > 0 ? $bagCbm : $pkgCbm;
                                        $pkgCount = intval($bag['pkg_count'] ?? 0);
                                        $pkgReceived = intval($bag['pkg_received'] ?? 0);
                                        $bagCusts = $bagCustomerMap[$bag['bag_id']] ?? [];
                                        $bagDisplayStatus = ($pkgCount > 0 && $pkgReceived >= $pkgCount) ? 'completed' : $bag['status'];
                                        $bsl = $bagStatusLabels[$bagDisplayStatus] ?? $bagStatusLabels['shipping'];
                                        $bagShipment = $bagShipmentMap[$bag['bag_id']] ?? null;
                                    ?>
                                    <tr>
                                        <td class="text-center text-muted"><?= $rowIdx ?></td>
                                        <td>
                                            <strong class="text-primary"><?= htmlspecialchars($bag['bag_code']) ?></strong>
                                            <div class="text-muted"><small><?= $pkgCount ?> <?= __('kiện') ?></small></div>
                                        </td>
                                        <td>
                                            <?php if (count($bagCusts) == 1): ?>
                                                <?= htmlspecialchars(array_values($bagCusts)[0]) ?>
                                            <?php elseif (count($bagCusts) > 1): ?>
                                                <span class="text-muted"><?= count($bagCusts) ?> <?= __('khách') ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end"><?= $weight > 0 ? fnum($weight, 1) . ' kg' : '<span class="text-muted">-</span>' ?></td>
                                        <td class="text-end"><?= $cbm > 0 ? fnum($cbm, 2) . ' m&sup3;' : '<span class="text-muted">-</span>' ?></td>
                                        <td>
                                            <?php if ($bagShipment): ?>
                                            <span class="badge bg-dark-subtle text-dark"><?= htmlspecialchars($bagShipment['shipment_code']) ?></span>
                                            <?php if ($bagShipment['truck_plate']): ?><br><small class="text-muted"><i class="ri-truck-line"></i> <?= htmlspecialchars($bagShipment['truck_plate']) ?></small><?php endif; ?>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $bsl['bg'] ?>-subtle text-<?= $bsl['bg'] ?> fs-12"><i class="<?= $bsl['icon'] ?> me-1"></i><?= __($bsl['label']) ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalPages > 1): ?>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <small class="text-muted">
                                <?= __('Hiển thị') ?> <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalBags) ?> / <?= $totalBags ?>
                            </small>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <?php
                                    $queryParams = $_GET;
                                    unset($queryParams['page'], $queryParams['module'], $queryParams['action']);
                                    $baseUrl = base_url('staffcn/orders-retail') . ($queryParams ? '?' . http_build_query($queryParams) : '');
                                    ?>
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') ?>page=<?= $page - 1 ?>">&laquo;</a>
                                    </li>
                                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') ?>page=<?= $p ?>"><?= $p ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') ?>page=<?= $page + 1 ?>">&raquo;</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

<?php
    require_once(__DIR__.'/footer.php');
}
?>
