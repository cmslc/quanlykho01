<?php
require_once(__DIR__.'/../../../models/is_admin.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Hàng chờ xếp xe');

// Filters
$filterSearch = trim(input_get('search') ?? '');
$filterCustomer = input_get('customer_id') ?: '';
$filterType = input_get('type') ?: '';

$notInShipment = "p.id NOT IN (SELECT sp.package_id FROM `shipment_packages` sp JOIN `shipments` s ON sp.shipment_id = s.id WHERE s.status IN ('preparing','in_transit'))";

// === Summary counts ===
// Chỉ đếm kiện thực sự hiển thị: hàng lô cn_warehouse + hàng lẻ packed trong bao sealed
$cntCnWarehouse = $ToryHub->num_rows_safe(
    "SELECT p.id FROM `packages` p
     JOIN `package_orders` po ON p.id = po.package_id
     JOIN `orders` o ON po.order_id = o.id
     WHERE p.status = 'cn_warehouse' AND o.product_type = 'wholesale' AND $notInShipment", []
);
$cntPacked = $ToryHub->num_rows_safe(
    "SELECT p.id FROM `packages` p WHERE p.status = 'packed' AND p.id IN (SELECT bp2.package_id FROM `bag_packages` bp2 JOIN `bags` b2 ON bp2.bag_id = b2.id WHERE b2.status = 'sealed') AND $notInShipment", []
);
$totalPendingPkgs = $cntCnWarehouse + $cntPacked;

$cntLoading = $ToryHub->num_rows_safe(
    "SELECT p.id FROM `packages` p WHERE p.status = 'loading'", []
);
$cntShipping = $ToryHub->num_rows_safe(
    "SELECT p.id FROM `packages` p WHERE p.status = 'shipping'", []
);

// === SEALED BAGS (retail packed) ===
$sealedBags = [];
$bagPkgIdsMap = [];
$bagCustomerMap = [];

if ($filterType !== 'wholesale') {
    $bagWhere = "b.status = 'sealed' AND p.status = 'packed' AND $notInShipment";
    $bagParams = [];
    if ($filterSearch) {
        $bagWhere .= " AND b.bag_code LIKE ?";
        $bagParams[] = '%' . $filterSearch . '%';
    }
    if ($filterCustomer) {
        $bagWhere .= " AND p.id IN (SELECT po2.package_id FROM `package_orders` po2 JOIN `orders` o2 ON po2.order_id = o2.id WHERE o2.customer_id = ?)";
        $bagParams[] = intval($filterCustomer);
    }

    $sealedBags = $ToryHub->get_list_safe(
        "SELECT b.id as bag_id, b.bag_code, b.images as bag_images,
            COUNT(p.id) as pkg_count,
            b.total_weight as bag_weight,
            COALESCE(b.weight_volume, 0) as bag_cbm,
            SUM(COALESCE(p.weight_charged, 0)) as pkg_weight_charged,
            SUM(COALESCE(p.weight_actual, 0)) as pkg_weight_actual,
            SUM(COALESCE(p.length_cm,0) * COALESCE(p.width_cm,0) * COALESCE(p.height_cm,0) / 1000000) as pkg_cbm,
            b.create_date
        FROM `bags` b
        JOIN `bag_packages` bp ON b.id = bp.bag_id
        JOIN `packages` p ON bp.package_id = p.id
        WHERE $bagWhere
        GROUP BY b.id
        ORDER BY b.create_date DESC",
        $bagParams
    );

    if (!empty($sealedBags)) {
        $bagIds = array_column($sealedBags, 'bag_id');
        $ph = implode(',', array_fill(0, count($bagIds), '?'));

        // Package IDs per bag
        $bagPkgs = $ToryHub->get_list_safe(
            "SELECT bp.bag_id, p.id as package_id FROM `bag_packages` bp
             JOIN `packages` p ON bp.package_id = p.id
             WHERE bp.bag_id IN ($ph) AND p.status = 'packed' AND $notInShipment",
            $bagIds
        );
        foreach ($bagPkgs as $bp) {
            $bagPkgIdsMap[$bp['bag_id']][] = $bp['package_id'];
        }

        // Customer info per bag
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
}

// === WHOLESALE ORDERS (cn_warehouse) ===
$wholesaleOrders = [];

if ($filterType !== 'retail') {
    $orderWhere = "o.product_type = 'wholesale' AND p.status = 'cn_warehouse' AND $notInShipment";
    $orderParams = [];
    if ($filterSearch) {
        $orderWhere .= " AND (o.product_code LIKE ? OR o.order_code LIKE ?)";
        $searchLike = '%' . $filterSearch . '%';
        $orderParams[] = $searchLike;
        $orderParams[] = $searchLike;
    }
    if ($filterCustomer) {
        $orderWhere .= " AND o.customer_id = ?";
        $orderParams[] = intval($filterCustomer);
    }

    $wholesaleOrders = $ToryHub->get_list_safe(
        "SELECT o.id, o.product_code, o.product_name, o.cargo_type, o.product_image, o.customer_id,
            o.weight_charged as order_weight_charged, o.weight_actual as order_weight_actual, o.volume_actual,
            c.fullname as customer_name,
            COUNT(p.id) as pkg_count,
            SUM(COALESCE(p.weight_charged, 0)) as total_weight_charged,
            SUM(COALESCE(p.weight_actual, 0)) as total_weight_actual,
            SUM(p.length_cm * p.width_cm * p.height_cm / 1000000) as total_cbm,
            o.create_date
        FROM `packages` p
        JOIN `package_orders` po ON p.id = po.package_id
        JOIN `orders` o ON po.order_id = o.id
        LEFT JOIN `customers` c ON o.customer_id = c.id
        WHERE $orderWhere
        GROUP BY o.id
        ORDER BY o.create_date DESC",
        $orderParams
    );
}

$totalRows = count($sealedBags) + count($wholesaleOrders);

// === Pagination ===
$perPage = 10;
$page = max(1, intval(input_get('page') ?: 1));
$totalPages = max(1, ceil($totalRows / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

// Slice bags + orders for current page
$bagCount = count($sealedBags);
if ($offset < $bagCount) {
    $sealedBags = array_slice($sealedBags, $offset, $perPage);
    $remaining = $perPage - count($sealedBags);
    $wholesaleOrders = $remaining > 0 ? array_slice($wholesaleOrders, 0, $remaining) : [];
} else {
    $sealedBags = [];
    $wholesaleOrders = array_slice($wholesaleOrders, $offset - $bagCount, $perPage);
}

// Rebuild bagPkgIdsMap & bagCustomerMap for sliced bags
if (!empty($sealedBags)) {
    $slicedBagIds = array_column($sealedBags, 'bag_id');
    $newBagPkgIdsMap = [];
    $newBagCustomerMap = [];
    foreach ($slicedBagIds as $bid) {
        if (isset($bagPkgIdsMap[$bid])) $newBagPkgIdsMap[$bid] = $bagPkgIdsMap[$bid];
        if (isset($bagCustomerMap[$bid])) $newBagCustomerMap[$bid] = $bagCustomerMap[$bid];
    }
    $bagPkgIdsMap = $newBagPkgIdsMap;
    $bagCustomerMap = $newBagCustomerMap;
}

// === Package status distribution per wholesale order ===
$pkgStatusMap = [];
if (!empty($wholesaleOrders)) {
    $woIds = array_column($wholesaleOrders, 'id');
    $ph = implode(',', array_fill(0, count($woIds), '?'));
    $pkgStatuses = $ToryHub->get_list_safe(
        "SELECT po.order_id, p.status, COUNT(*) as cnt
         FROM `package_orders` po
         JOIN `packages` p ON po.package_id = p.id
         WHERE po.order_id IN ($ph)
         GROUP BY po.order_id, p.status",
        $woIds
    );
    foreach ($pkgStatuses as $ps) {
        $pkgStatusMap[$ps['order_id']][$ps['status']] = intval($ps['cnt']);
    }
}

$customers = $ToryHub->get_list_safe("SELECT `id`, `fullname` FROM `customers` ORDER BY `fullname` ASC", []);

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= $page_title ?></h4>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="<?= base_url('admin/shipments-pending') ?>">
                            <input type="hidden" name="module" value="admin">
                            <input type="hidden" name="action" value="shipments-pending">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label"><?= __('Tìm kiếm') ?></label>
                                    <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($filterSearch) ?>" placeholder="<?= __('Mã hàng, mã bao...') ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><?= __('Loại hàng') ?></label>
                                    <select class="form-select" name="type">
                                        <option value=""><?= __('Tất cả') ?></option>
                                        <option value="retail" <?= $filterType == 'retail' ? 'selected' : '' ?>><?= __('Hàng lẻ') ?></option>
                                        <option value="wholesale" <?= $filterType == 'wholesale' ? 'selected' : '' ?>><?= __('Hàng lô') ?></option>
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
                                <div class="col-md-auto">
                                    <button type="submit" class="btn btn-primary"><i class="ri-search-line me-1"></i><?= __('Lọc') ?></button>
                                    <a href="<?= base_url('admin/shipments-pending') ?>" class="btn btn-secondary"><?= __('Reset') ?></a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Packages Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h5 class="card-title mb-0">
                            <?= $page_title ?> (<?= $totalPendingPkgs ?> <?= __('kiện') ?> / <?= $totalRows ?> <?= __('dòng') ?>)
                            <?php if ($cntLoading > 0): ?>
                            <span class="badge bg-secondary-subtle text-secondary fs-12 px-2 py-1 ms-2"><i class="ri-truck-line me-1"></i><?= __('Đang xếp xe') ?>: <?= $cntLoading ?></span>
                            <?php endif; ?>
                            <?php if ($cntShipping > 0): ?>
                            <span class="badge bg-primary-subtle text-primary fs-12 px-2 py-1 ms-1"><i class="ri-ship-line me-1"></i><?= __('Đang vận chuyển') ?>: <?= $cntShipping ?></span>
                            <?php endif; ?>
                        </h5>
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-success" id="btn-export-excel"><i class="ri-file-excel-2-line me-1"></i><?= __('Xuất Excel') ?></button>
                            <button class="btn btn-info" id="btn-load-truck"><i class="ri-truck-line me-1"></i><?= __('Xếp xe') ?></button>
                        </div>
                    </div>
                    <div id="selected-summary" class="card-body border-bottom py-2 d-none">
                        <div class="d-flex align-items-center gap-4 flex-wrap">
                            <span class="text-muted"><?= __('Đã chọn') ?>: <strong id="sum-pkgs">0</strong> <?= __('kiện') ?></span>
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
                                        <th style="width:40px;" class="align-middle"><input type="checkbox" class="form-check-input" id="check-all"></th>
                                        <th class="align-middle"><?= __('Mã hàng') ?></th>
                                        <th class="align-middle"><?= __('Loại hàng') ?></th>
                                        <th class="align-middle"><?= __('Sản phẩm') ?></th>
                                        <th class="align-middle text-center" style="width:60px;"><?= __('Ảnh') ?></th>
                                        <th class="align-middle"><?= __('Khách hàng') ?></th>
                                        <th class="align-middle text-center"><?= __('Số kiện') ?></th>
                                        <th class="align-middle text-end"><?= __('Cân nặng') ?></th>
                                        <th class="align-middle text-end"><?= __('Số khối') ?></th>
                                        <th class="align-middle"><?= __('Trạng thái') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($totalRows === 0): ?>
                                    <tr><td colspan="10" class="text-center text-muted py-4"><?= __('Không có kiện hàng nào chờ xếp xe') ?></td></tr>
                                    <?php endif; ?>

                                    <?php // === BAG ROWS (retail) === ?>
                                    <?php foreach ($sealedBags as $bag):
                                        $bagW = floatval($bag['bag_weight'] ?? 0);
                                        $pkgWCharged = floatval($bag['pkg_weight_charged'] ?? 0);
                                        $pkgWActual = floatval($bag['pkg_weight_actual'] ?? 0);
                                        $weight = $pkgWActual > 0 ? $pkgWActual : ($pkgWCharged > 0 ? $pkgWCharged : $bagW);
                                        $bagCbm = floatval($bag['bag_cbm'] ?? 0);
                                        $pkgCbm = floatval($bag['pkg_cbm'] ?? 0);
                                        $cbm = $bagCbm > 0 ? $bagCbm : $pkgCbm;
                                        $pkgCount = $bag['pkg_count'] ?? 0;
                                        $pkgIds = $bagPkgIdsMap[$bag['bag_id']] ?? [];
                                        $bagCusts = $bagCustomerMap[$bag['bag_id']] ?? [];
                                    ?>
                                    <tr>
                                        <td class="align-middle"><input type="checkbox" class="form-check-input row-check" data-type="bag" data-bag-id="<?= $bag['bag_id'] ?>" data-pkg-ids="<?= implode(',', $pkgIds) ?>" data-weight="<?= $weight ?>" data-cbm="<?= $cbm ?>" data-cargo="easy" data-pkg-count="<?= $pkgCount ?>"></td>
                                        <td class="align-middle">
                                            <a href="<?= base_url('admin/bags-packing?id=' . $bag['bag_id']) ?>"><strong><?= htmlspecialchars($bag['bag_code']) ?></strong></a>
                                            <?php if ($pkgCount > 0): ?>
                                            <div class="mt-1"><span class="text-muted"><i class="ri-archive-line"></i> <?= $pkgCount ?> <?= __('kiện') ?></span></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle"><span class="badge bg-info-subtle text-info"><?= __('Hàng lẻ') ?></span></td>
                                        <td class="align-middle">
                                            <span class="text-muted">-</span>
                                            <div class="mt-1"><?= display_cargo_type('easy') ?></div>
                                        </td>
                                        <td class="align-middle text-center">
                                            <?php if (!empty($bag['bag_images'])):
                                                $bagImgArr = array_filter(array_map('trim', explode(',', $bag['bag_images'])));
                                                $bagImgUrls = array_map('get_upload_url', $bagImgArr);
                                                $thumbUrl = $bagImgUrls[0];
                                                $imgCount = count($bagImgArr);
                                            ?>
                                            <a href="#" class="btn-view-images position-relative d-inline-block" data-images="<?= htmlspecialchars(json_encode(array_values($bagImgUrls))) ?>">
                                                <img src="<?= $thumbUrl ?>" class="rounded" style="width:40px;height:40px;object-fit:cover;">
                                                <?php if ($imgCount > 1): ?>
                                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary" style="font-size:10px;"><?= $imgCount ?></span>
                                                <?php endif; ?>
                                            </a>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle">
                                            <?php if (count($bagCusts) == 1): ?>
                                                <?= htmlspecialchars(array_values($bagCusts)[0]) ?>
                                            <?php elseif (count($bagCusts) > 1): ?>
                                                <span class="text-muted"><?= count($bagCusts) ?> <?= __('khách') ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle text-center"><strong><?= $pkgCount ?></strong></td>
                                        <td class="align-middle text-end"><?= $weight > 0 ? fnum($weight, 1) . ' kg' : '<span class="text-muted">-</span>' ?></td>
                                        <td class="align-middle text-end"><?= $cbm > 0 ? fnum($cbm, 2) . ' m&sup3;' : '<span class="text-muted">-</span>' ?></td>
                                        <td class="align-middle"><span class="badge bg-dark-subtle text-dark" style="font-size:12px;"><?= __('Đã đóng bao') ?>: <?= $pkgCount ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>

                                    <?php // === ORDER ROWS (wholesale) === ?>
                                    <?php foreach ($wholesaleOrders as $order):
                                        $orderWCharged = floatval($order['order_weight_charged'] ?? 0);
                                        $orderWActual  = floatval($order['order_weight_actual'] ?? 0);
                                        $wCharged = floatval($order['total_weight_charged'] ?? 0);
                                        $wActual  = floatval($order['total_weight_actual'] ?? 0);
                                        $weight = $wActual > 0 ? $wActual
                                            : ($orderWActual > 0 ? $orderWActual
                                            : ($wCharged > 0 ? $wCharged
                                            : $orderWCharged));
                                        $cbm = $order['total_cbm'] ?? 0;
                                        if (floatval($order['volume_actual'] ?? 0) > 0) $cbm = floatval($order['volume_actual']);
                                        $pkgCount = $order['pkg_count'] ?? 0;
                                    ?>
                                    <tr>
                                        <td class="align-middle"><input type="checkbox" class="form-check-input row-check" data-type="order" data-order-id="<?= $order['id'] ?>" data-weight="<?= $weight ?>" data-cbm="<?= $cbm ?>" data-cargo="<?= htmlspecialchars($order['cargo_type'] ?? '') ?>" data-pkg-count="<?= $pkgCount ?>"></td>
                                        <td class="align-middle">
                                            <?php if ($order['product_code'] ?? ''): ?>
                                            <a href="<?= base_url('admin/orders-detail?id=' . $order['id']) ?>"><strong><?= htmlspecialchars($order['product_code']) ?></strong></a>
                                            <?php else: ?>
                                            <a href="<?= base_url('admin/orders-detail?id=' . $order['id']) ?>" class="text-muted">#<?= $order['id'] ?></a>
                                            <?php endif; ?>
                                            <?php if ($pkgCount > 0): ?>
                                            <div class="mt-1"><a href="#" class="btn-expand-pkgs text-muted text-decoration-none" data-order-id="<?= $order['id'] ?>"><i class="ri-archive-line"></i> <?= $pkgCount ?> <?= __('kiện') ?> <i class="ri-arrow-down-s-line expand-icon fs-14"></i></a></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle"><span class="badge bg-warning-subtle text-warning"><?= __('Hàng lô') ?></span></td>
                                        <td class="align-middle">
                                            <?= htmlspecialchars($order['product_name'] ?? '-') ?>
                                            <?php if (!empty($order['cargo_type'])): ?>
                                            <div class="mt-1"><?= display_cargo_type($order['cargo_type']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle text-center">
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
                                        <td class="align-middle">
                                            <?php if ($order['customer_id']): ?>
                                            <a href="<?= base_url('admin/customers-detail?id=' . $order['customer_id']) ?>" class="fw-bold"><?= htmlspecialchars($order['customer_name'] ?? '') ?></a>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle text-center"><strong><?= $pkgCount ?></strong></td>
                                        <td class="align-middle text-end"><?= $weight > 0 ? fnum($weight, 1) . ' kg' : '<span class="text-muted">-</span>' ?></td>
                                        <td class="align-middle text-end"><?= $cbm > 0 ? fnum($cbm, 2) . ' m&sup3;' : '<span class="text-muted">-</span>' ?></td>
                                        <td class="align-middle">
                                            <?php
                                            $opkgMap = $pkgStatusMap[$order['id']] ?? [];
                                            $opkgLabels = [
                                                'cn_warehouse' => ['label' => 'Đã về kho Trung Quốc', 'bg' => 'info'],
                                                'packed'       => ['label' => 'Đã đóng bao', 'bg' => 'dark'],
                                                'loading'      => ['label' => 'Đang xếp xe', 'bg' => 'secondary'],
                                                'shipping'     => ['label' => 'Đang vận chuyển', 'bg' => 'primary'],
                                                'vn_warehouse' => ['label' => 'Đã về kho Việt Nam', 'bg' => 'success'],
                                                'delivered'    => ['label' => 'Đã giao hàng', 'bg' => 'success'],
                                            ];
                                            ?>
                                            <div class="d-flex flex-column align-items-start gap-1">
                                                <?php foreach ($opkgLabels as $st => $cfg):
                                                    $cnt = $opkgMap[$st] ?? 0;
                                                    if ($cnt > 0):
                                                ?>
                                                <span class="badge bg-<?= $cfg['bg'] ?>-subtle text-<?= $cfg['bg'] ?>" style="font-size:12px;"><?= __($cfg['label']) ?>: <?= $cnt ?></span>
                                                <?php endif; endforeach; ?>
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
                                <?= __('Hiển thị') ?> <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalRows) ?> / <?= $totalRows ?> <?= __('dòng') ?>
                            </div>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <?php
                                    $queryParams = $_GET;
                                    unset($queryParams['page'], $queryParams['module'], $queryParams['action']);
                                    $baseUrl = base_url('admin/shipments-pending') . ($queryParams ? '&' . http_build_query($queryParams) : '');
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

<!-- Modal Xếp xe -->
<div class="modal fade" id="modalLoadTruck" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ri-truck-line me-2"></i><?= __('Xếp xe vận chuyển') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="alert alert-info py-2 mb-3">
                        <i class="ri-information-line me-1"></i>
                        <?= __('Đã chọn') ?> <strong id="truck-pkg-count">0</strong> <?= __('kiện hàng') ?>
                        — <?= __('Tổng cân') ?>: <strong id="truck-total-weight">0 kg</strong>
                        — <?= __('Tổng khối') ?>: <strong id="truck-total-cbm">0 m³</strong>
                    </div>
                </div>
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-existing-shipment"><?= __('Chuyến xe có sẵn') ?></a></li>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-new-shipment"><?= __('Tạo chuyến mới') ?></a></li>
                </ul>
                <div class="tab-content pt-3">
                    <div class="tab-pane fade show active" id="tab-existing-shipment">
                        <div id="existing-shipments-loading" class="text-center py-3"><i class="ri-loader-4-line ri-spin fs-24"></i></div>
                        <div id="existing-shipments-empty" class="text-center text-muted py-3 d-none">
                            <?= __('Chưa có chuyến xe nào đang chuẩn bị') ?>. <a href="#" onclick="$('a[href=\'#tab-new-shipment\']').tab('show'); return false;"><?= __('Tạo mới') ?></a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0 d-none" id="tbl-existing-shipments">
                                <thead>
                                    <tr>
                                        <th style="width:30px;"></th>
                                        <th><?= __('Mã chuyến') ?></th>
                                        <th><?= __('Biển số xe') ?></th>
                                        <th><?= __('Tài xế') ?></th>
                                        <th><?= __('Tuyến đường') ?></th>
                                        <th><?= __('Số kiện') ?></th>
                                        <th><?= __('Tổng cân') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="existing-shipments-body"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-new-shipment">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label"><?= __('Biển số xe') ?> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="new-truck-plate" placeholder="<?= __('VD: 29C-12345') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><?= __('Tên tài xế') ?></label>
                                <input type="text" class="form-control" id="new-driver-name">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><?= __('SĐT tài xế') ?></label>
                                <input type="text" class="form-control" id="new-driver-phone">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= __('Tuyến đường') ?></label>
                                <input type="text" class="form-control" id="new-route" value="<?= __('Kho Trung Quốc - Cửa Khẩu') ?>" placeholder="<?= __('VD: Quảng Châu - Hà Nội') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= __('Trọng tải tối đa (kg)') ?></label>
                                <input type="number" class="form-control" id="new-max-weight" step="0.01">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><?= __('Chi phí vận chuyển') ?></label>
                                <input type="number" class="form-control" id="new-shipping-cost" step="0.01">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label"><?= __('Ghi chú') ?></label>
                                <input type="text" class="form-control" id="new-note">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= __('Đóng') ?></button>
                <button type="button" class="btn btn-warning" id="btn-confirm-load-truck">
                    <i class="ri-truck-line me-1"></i><?= __('Xếp xe') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js"></script>
<script>
function esc(s){ if(!s) return ''; var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
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
    var shipmentAjaxUrl = '<?= base_url('ajaxs/admin/shipments.php') ?>';
    var expandedOrders = {};

    // ===== Summary =====
    function updateSelectedSummary(){
        var pkgCount = 0, totalWeight = 0, totalCbm = 0, cbmEasy = 0, cbmDifficult = 0;

        // Bag rows - only count if checked
        $('.row-check[data-type="bag"]:checked').each(function(){
            pkgCount += parseInt($(this).data('pkg-count')) || 0;
            totalWeight += parseFloat($(this).data('weight')) || 0;
            var c = parseFloat($(this).data('cbm')) || 0;
            totalCbm += c;
            var cargo = $(this).data('cargo');
            if (cargo === 'easy') cbmEasy += c;
            else if (cargo === 'difficult') cbmDifficult += c;
        });

        // Order rows - iterate ALL to handle expanded partial selection
        $('.row-check[data-type="order"]').each(function(){
            var orderId = $(this).data('order-id');
            var cargo = $(this).data('cargo');
            if (expandedOrders[orderId]) {
                // Expanded: count checked sub-packages
                $('#pkg-row-' + orderId + ' .sub-pkg-check:checked').each(function(){
                    pkgCount++;
                    var w = parseFloat($(this).data('weight')) || 0;
                    var c = parseFloat($(this).data('cbm')) || 0;
                    totalWeight += w;
                    totalCbm += c;
                    if (cargo === 'easy') cbmEasy += c;
                    else if (cargo === 'difficult') cbmDifficult += c;
                });
            } else if ($(this).is(':checked')) {
                // Not expanded: use aggregate from row data
                pkgCount += parseInt($(this).data('pkg-count')) || 0;
                var w = parseFloat($(this).data('weight')) || 0;
                var c = parseFloat($(this).data('cbm')) || 0;
                totalWeight += w;
                totalCbm += c;
                if (cargo === 'easy') cbmEasy += c;
                else if (cargo === 'difficult') cbmDifficult += c;
            }
        });

        if (pkgCount > 0) {
            $('#selected-summary').removeClass('d-none');
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

    // ===== Check all =====
    $('#check-all').on('change', function(){
        var checked = this.checked;
        $('.row-check').prop('checked', checked);
        // Sync expanded order sub-checks + groups
        $('.row-check[data-type="order"]').each(function(){
            var oid = $(this).data('order-id');
            if (expandedOrders[oid]) {
                $('#pkg-row-' + oid + ' .sub-pkg-check').prop('checked', checked);
                $('#pkg-row-' + oid + ' .sub-pkg-check-all').prop('checked', checked).prop('indeterminate', false);
                $('#pkg-row-' + oid + ' .sub-pkg-group-check').prop('checked', checked).prop('indeterminate', false);
                $('#pkg-row-' + oid + ' .grp-qty-input').each(function(){
                    var total = parseInt($(this).attr('max')) || 0;
                    $(this).val(checked ? total : 0);
                    updateGroupDisplay($(this).data('group-id'));
                });
            }
        });
        updateSelectedSummary();
    });

    // ===== Row checkbox =====
    $(document).on('change', '.row-check', function(){
        var type = $(this).data('type');
        if (type === 'order') {
            var oid = $(this).data('order-id');
            if (expandedOrders[oid]) {
                var checked = this.checked;
                $('#pkg-row-' + oid + ' .sub-pkg-check').prop('checked', checked);
                $('#pkg-row-' + oid + ' .sub-pkg-check-all').prop('checked', checked).prop('indeterminate', false);
                $('#pkg-row-' + oid + ' .sub-pkg-group-check').prop('checked', checked).prop('indeterminate', false);
                $('#pkg-row-' + oid + ' .grp-qty-input').each(function(){
                    var total = parseInt($(this).attr('max')) || 0;
                    $(this).val(checked ? total : 0);
                    updateGroupDisplay($(this).data('group-id'));
                });
            }
        }
        var total = $('.row-check').length;
        var chk = $('.row-check:checked').length;
        $('#check-all').prop('checked', total === chk).prop('indeterminate', chk > 0 && chk < total);
        updateSelectedSummary();
    });

    // ===== Sub-pkg check-all =====
    $(document).on('change', '.sub-pkg-check-all', function(){
        var orderId = $(this).data('order-id');
        var checked = this.checked;
        $('#pkg-row-' + orderId + ' .sub-pkg-check').prop('checked', checked);
        $('#pkg-row-' + orderId + ' .sub-pkg-group-check').prop('checked', checked).prop('indeterminate', false);
        $('#pkg-row-' + orderId + ' .grp-qty-input').each(function(){
            var total = parseInt($(this).attr('max')) || 0;
            $(this).val(checked ? total : 0);
            updateGroupDisplay($(this).data('group-id'));
        });
        syncOrderCheckbox(orderId);
        updateSelectedSummary();
    });

    // ===== Individual sub-pkg =====
    $(document).on('change', '.sub-pkg-check', function(){
        var orderId = $(this).closest('.pkg-expand-row').data('order-id');
        var groupId = $(this).data('group-id');
        if (groupId) {
            var $groupChecks = $('.sub-pkg-check[data-group-id="' + groupId + '"]');
            var total = $groupChecks.length;
            var checked = $groupChecks.filter(':checked').length;
            var $groupCb = $('.sub-pkg-group-check[data-group-id="' + groupId + '"]');
            $groupCb.prop('checked', total === checked).prop('indeterminate', checked > 0 && checked < total);
            $('.grp-qty-input[data-group-id="' + groupId + '"]').val(checked);
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
        var total = parseInt($(this).data('total')) || $checks.length;
        $('.grp-qty-input[data-group-id="' + groupId + '"]').val(checked ? total : 0);
        updateGroupDisplay(groupId);
        var orderId = $(this).closest('.pkg-expand-row').data('order-id');
        syncOrderCheckbox(orderId);
        updateSelectedSummary();
    });

    // ===== Group quantity input =====
    $(document).on('input change', '.grp-qty-input', function(){
        var groupId = $(this).data('group-id');
        var $checks = $('.sub-pkg-check[data-group-id="' + groupId + '"]');
        var total = $checks.length;
        var qty = Math.max(0, Math.min(total, parseInt($(this).val()) || 0));
        $(this).val(qty);
        $checks.each(function(i){ $(this).prop('checked', i < qty); });
        var $groupCb = $('.sub-pkg-group-check[data-group-id="' + groupId + '"]');
        $groupCb.prop('checked', qty === total).prop('indeterminate', qty > 0 && qty < total);
        updateGroupDisplay(groupId);
        var orderId = $(this).closest('.pkg-expand-row').data('order-id');
        syncOrderCheckbox(orderId);
        updateSelectedSummary();
    });

    // ===== Group expand/collapse =====
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

    // Update group row weight/cbm display based on selected qty
    function updateGroupDisplay(groupId) {
        var $checks = $('.sub-pkg-check[data-group-id="' + groupId + '"]');
        var checkedCount = $checks.filter(':checked').length;
        var $wCell = $('.grp-weight-cell[data-group-id="' + groupId + '"]');
        var $cCell = $('.grp-cbm-cell[data-group-id="' + groupId + '"]');
        var unitW = parseFloat($wCell.data('unit-w')) || 0;
        var unitC = parseFloat($cCell.data('unit-c')) || 0;
        if (unitW > 0) {
            $wCell.html('1 <?= __('kiện') ?>: ' + fnum(unitW, 2) + ' kg<br><strong>' + fnum(unitW * checkedCount, 2) + ' kg</strong>');
        }
        if (unitC > 0) {
            $cCell.html('1 <?= __('kiện') ?>: ' + fnum(unitC, 2) + ' m³<br><strong>' + fnum(unitC * checkedCount, 2) + ' m³</strong>');
        }
    }

    function syncOrderCheckbox(orderId){
        var $row = $('#pkg-row-' + orderId);
        var total = $row.find('.sub-pkg-check').length;
        var checked = $row.find('.sub-pkg-check:checked').length;
        $row.find('.sub-pkg-check-all').prop('checked', total === checked).prop('indeterminate', checked > 0 && checked < total);
        var $orderCb = $('.row-check[data-order-id="' + orderId + '"]');
        $orderCb.prop('checked', checked > 0 && total === checked);
        $orderCb.prop('indeterminate', checked > 0 && checked < total);
        var totalAll = $('.row-check').length;
        var chkAll = $('.row-check:checked').length;
        var indAll = $('.row-check:indeterminate').length;
        $('#check-all').prop('checked', totalAll === chkAll && indAll === 0).prop('indeterminate', (chkAll > 0 || indAll > 0) && (chkAll < totalAll || indAll > 0));
    }

    // ===== Expand packages (wholesale orders only) =====
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
        if ($expandRow.length) { $expandRow.show(); updateSelectedSummary(); return; }

        var $newRow = $('<tr class="pkg-expand-row" id="pkg-row-' + orderId + '" data-order-id="' + orderId + '"><td colspan="' + colCount + '" class="p-0"><div class="px-4 py-2 bg-light"><div class="text-muted py-2"><i class="ri-loader-4-line ri-spin"></i> <?= __('Đang tải...') ?></div></div></td></tr>');
        $orderRow.after($newRow);
        var orderChecked = $('.row-check[data-order-id="' + orderId + '"]').is(':checked');

        $.post(pkgAjaxUrl, { request_name: 'get_order_packages', order_id: orderId, csrf_token: csrfToken }, function(res){
            if (res.status === 'success') {
                var statusLabels = { 'cn_warehouse': '<?= __('Đã về kho Trung Quốc') ?>', 'packed': '<?= __('Đã đóng bao') ?>', 'loading': '<?= __('Đang xếp xe') ?>', 'shipping': '<?= __('Đang vận chuyển') ?>' };
                var pendingPkgs = res.packages.filter(function(p){ return p.status === 'cn_warehouse'; });

                var html = '<table class="table table-sm table-borderless mb-0"><thead><tr>';
                html += '<th style="width:30px;"><input type="checkbox" class="form-check-input sub-pkg-check-all" data-order-id="' + orderId + '"' + (orderChecked ? ' checked' : '') + '></th>';
                html += '<th><?= __('Mã kiện') ?></th><th><?= __('Cân nặng') ?></th><th><?= __('Kích thước') ?></th><th><?= __('Số khối') ?></th><th><?= __('Trạng thái') ?></th>';
                html += '</tr></thead><tbody>';

                // Group packages by same weight + dimensions + status
                var groups = [];
                var groupMap = {};
                pendingPkgs.forEach(function(pkg){
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
                pendingPkgs.forEach(function(pkg){ pkgIndexMap[pkg.id] = ++pkgIndex; });

                groups.forEach(function(group){
                    var pkgs = group.pkgs;
                    var first = pkgs[0];
                    var dim = (first.length_cm > 0 || first.width_cm > 0 || first.height_cm > 0)
                        ? parseFloat(first.length_cm) + '×' + parseFloat(first.width_cm) + '×' + parseFloat(first.height_cm) : '-';

                    if (pkgs.length === 1) {
                        html += '<tr>';
                        html += '<td><input type="checkbox" class="form-check-input sub-pkg-check" value="' + first.id + '" data-weight="' + first.weight_actual + '" data-cbm="' + first.cbm + '"' + (orderChecked ? ' checked' : '') + '></td>';
                        html += '<td><strong>' + esc(first.package_code) + '</strong></td>';
                        html += '<td>' + (first.weight_actual > 0 ? fnum(first.weight_actual, 2) + ' kg' : '-') + '</td>';
                        html += '<td>' + dim + '</td>';
                        html += '<td>' + (first.cbm > 0 ? fnum(first.cbm, 2) + ' m³' : '-') + '</td>';
                        html += '<td>' + (statusLabels[first.status] || first.status) + '</td>';
                        html += '</tr>';
                    } else {
                        // Grouped packages - collapsible with qty input
                        var groupId = 'grp-' + orderId + '-' + group.key.replace(/[|.]/g, '_');
                        var totalW = first.weight_actual * pkgs.length;
                        var totalC = first.cbm * pkgs.length;
                        var firstIdx = pkgIndexMap[pkgs[0].id];
                        var lastIdx = pkgIndexMap[pkgs[pkgs.length - 1].id];
                        var initQty = orderChecked ? pkgs.length : 0;

                        html += '<tr class="pkg-group-row" data-group-id="' + groupId + '">';
                        html += '<td><input type="checkbox" class="form-check-input sub-pkg-group-check" data-group-id="' + groupId + '" data-total="' + pkgs.length + '"' + (orderChecked ? ' checked' : '') + '></td>';
                        html += '<td><a href="#" class="btn-expand-group text-decoration-none" data-group-id="' + groupId + '"><strong>' + esc(pkgs[0].package_code) + ' ~ ' + esc(pkgs[pkgs.length - 1].package_code) + '</strong> <span class="badge bg-primary-subtle text-primary">' + pkgs.length + ' <?= __('kiện') ?></span> <i class="ri-arrow-down-s-line grp-icon"></i></a>';
                        html += ' <input type="number" class="form-control form-control-sm d-inline-block grp-qty-input" data-group-id="' + groupId + '" min="0" max="' + pkgs.length + '" value="' + initQty + '" style="width:70px;" title="<?= __('Nhập số kiện muốn chọn') ?>">';
                        html += ' <span class="text-muted">/ ' + pkgs.length + '</span>';
                        html += '</td>';
                        html += '<td class="grp-weight-cell" data-group-id="' + groupId + '" data-unit-w="' + first.weight_actual + '">' + (first.weight_actual > 0 ? '1 <?= __('kiện') ?>: ' + fnum(first.weight_actual, 2) + ' kg<br><strong>' + fnum(orderChecked ? totalW : 0, 2) + ' kg</strong>' : '-') + '</td>';
                        html += '<td>' + dim + '</td>';
                        html += '<td class="grp-cbm-cell" data-group-id="' + groupId + '" data-unit-c="' + first.cbm + '">' + (first.cbm > 0 ? '1 <?= __('kiện') ?>: ' + fnum(first.cbm, 2) + ' m³<br><strong>' + fnum(orderChecked ? totalC : 0, 2) + ' m³</strong>' : '-') + '</td>';
                        html += '<td>' + (statusLabels[first.status] || first.status) + '</td>';
                        html += '</tr>';

                        // Hidden individual rows
                        pkgs.forEach(function(pkg){
                            var idx = pkgIndexMap[pkg.id];
                            html += '<tr class="pkg-group-detail d-none" data-group-id="' + groupId + '">';
                            html += '<td class="ps-4"><input type="checkbox" class="form-check-input sub-pkg-check" value="' + pkg.id + '" data-weight="' + pkg.weight_actual + '" data-cbm="' + pkg.cbm + '" data-group-id="' + groupId + '"' + (orderChecked ? ' checked' : '') + '></td>';
                            html += '<td class="ps-4">' + esc(pkg.package_code) + '</td>';
                            html += '<td>' + (pkg.weight_actual > 0 ? fnum(pkg.weight_actual, 2) + ' kg' : '-') + '</td>';
                            html += '<td>' + dim + '</td>';
                            html += '<td>' + (pkg.cbm > 0 ? fnum(pkg.cbm, 2) + ' m³' : '-') + '</td>';
                            html += '<td>' + (statusLabels[pkg.status] || pkg.status) + '</td>';
                            html += '</tr>';
                        });
                    }
                });

                html += '</tbody></table>';
                $newRow.find('.bg-light > div').html(html);
                expandedOrders[orderId] = true;
                updateSelectedSummary();
            }
        }, 'json');
    });

    // ===== XẾP XE =====
    var selectedShipmentId = null;

    function collectAllPackageIds(callback) {
        var packageIds = [];
        var orderIdsToLoad = [];

        $('.row-check:checked').each(function(){
            var type = $(this).data('type');
            if (type === 'bag') {
                var ids = ($(this).data('pkg-ids') || '').toString().split(',');
                ids.forEach(function(id){ if (id) packageIds.push(id); });
            } else {
                var orderId = $(this).data('order-id');
                if (expandedOrders[orderId]) {
                    $('#pkg-row-' + orderId + ' .sub-pkg-check:checked').each(function(){
                        packageIds.push($(this).val());
                    });
                } else {
                    orderIdsToLoad.push(orderId);
                }
            }
        });

        // Also check indeterminate orders (partially selected expanded)
        $('.row-check:indeterminate').each(function(){
            var type = $(this).data('type');
            if (type === 'order') {
                var orderId = $(this).data('order-id');
                if (expandedOrders[orderId]) {
                    $('#pkg-row-' + orderId + ' .sub-pkg-check:checked').each(function(){
                        packageIds.push($(this).val());
                    });
                }
            }
        });

        if (orderIdsToLoad.length > 0) {
            var loadRequests = orderIdsToLoad.map(function(oid){
                return $.post(pkgAjaxUrl, { request_name: 'get_order_packages', order_id: oid, csrf_token: csrfToken }, null, 'json');
            });
            $.when.apply($, loadRequests).then(function(){
                var results = loadRequests.length === 1 ? [arguments] : Array.from(arguments);
                results.forEach(function(r){
                    var res = r[0] || r;
                    if (res.status === 'success' && res.packages) {
                        res.packages.forEach(function(pkg){
                            if (pkg.status === 'cn_warehouse') packageIds.push(pkg.id.toString());
                        });
                    }
                });
                callback(packageIds);
            });
        } else {
            callback(packageIds);
        }
    }

    $('#btn-load-truck').on('click', function(){
        var hasSelection = $('.row-check:checked').length > 0 || $('.row-check:indeterminate').length > 0;
        if (!hasSelection) {
            Swal.fire({icon: 'warning', title: '<?= __('Vui lòng chọn ít nhất 1 kiện hàng') ?>'});
            return;
        }
        collectAllPackageIds(function(packageIds){
            if (packageIds.length === 0) {
                Swal.fire({icon: 'warning', title: '<?= __('Không có kiện hàng nào để xếp xe') ?>'});
                return;
            }
            openLoadTruckModal(packageIds);
        });
    });

    function openLoadTruckModal(packageIds) {
        $('#modalLoadTruck').data('packageIds', packageIds);
        var totalW = 0, totalC = 0;
        // Bags: only checked
        $('.row-check[data-type="bag"]:checked').each(function(){
            totalW += parseFloat($(this).data('weight')) || 0;
            totalC += parseFloat($(this).data('cbm')) || 0;
        });
        // Orders: handle expanded partial selection
        $('.row-check[data-type="order"]').each(function(){
            var orderId = $(this).data('order-id');
            if (expandedOrders[orderId]) {
                $('#pkg-row-' + orderId + ' .sub-pkg-check:checked').each(function(){
                    totalW += parseFloat($(this).data('weight')) || 0;
                    totalC += parseFloat($(this).data('cbm')) || 0;
                });
            } else if ($(this).is(':checked')) {
                totalW += parseFloat($(this).data('weight')) || 0;
                totalC += parseFloat($(this).data('cbm')) || 0;
            }
        });
        $('#truck-pkg-count').text(packageIds.length);
        $('#truck-total-weight').text(fnum(totalW, 1) + ' kg');
        $('#truck-total-cbm').text(fnum(totalC, 2) + ' m³');

        selectedShipmentId = null;
        $('#existing-shipments-body').empty();
        $('#tbl-existing-shipments').addClass('d-none');
        $('#existing-shipments-empty').addClass('d-none');
        $('#existing-shipments-loading').removeClass('d-none');
        $('#new-truck-plate, #new-driver-name, #new-driver-phone, #new-max-weight, #new-shipping-cost, #new-note').val('');
        $('#new-route').val('<?= __('Kho Trung Quốc - Cửa Khẩu') ?>');

        $.post(shipmentAjaxUrl, { request_name: 'get_preparing', csrf_token: csrfToken }, function(res){
            $('#existing-shipments-loading').addClass('d-none');
            if (res.status === 'success' && res.shipments && res.shipments.length > 0) {
                var html = '';
                res.shipments.forEach(function(s){
                    html += '<tr class="shipment-select-row" data-id="' + s.id + '" style="cursor:pointer;">';
                    html += '<td><input type="radio" name="select_shipment" class="form-check-input" value="' + s.id + '"></td>';
                    html += '<td><strong>' + (s.shipment_code || '') + '</strong></td>';
                    html += '<td>' + (s.truck_plate || '-') + '</td>';
                    html += '<td>' + (s.driver_name || '-') + '</td>';
                    html += '<td>' + (s.route || '-') + '</td>';
                    html += '<td>' + (s.total_packages || 0) + '</td>';
                    html += '<td>' + fnum(parseFloat(s.total_weight) || 0, 1) + ' kg</td>';
                    html += '</tr>';
                });
                $('#existing-shipments-body').html(html);
                $('#tbl-existing-shipments').removeClass('d-none');
            } else {
                $('#existing-shipments-empty').removeClass('d-none');
                $('a[href="#tab-new-shipment"]').tab('show');
            }
        }, 'json');

        new bootstrap.Modal(document.getElementById('modalLoadTruck')).show();
    }

    $(document).on('click', '.shipment-select-row', function(){
        selectedShipmentId = $(this).data('id');
        $(this).find('input[type="radio"]').prop('checked', true);
        $('.shipment-select-row').removeClass('table-primary');
        $(this).addClass('table-primary');
    });

    $('#btn-confirm-load-truck').on('click', function(){
        var packageIds = $('#modalLoadTruck').data('packageIds') || [];
        if (packageIds.length === 0) return;
        var btn = $(this);
        var activeTab = $('#tab-new-shipment').hasClass('show') ? 'new' : 'existing';

        if (activeTab === 'existing') {
            if (!selectedShipmentId) { Swal.fire({icon: 'warning', title: '<?= __('Vui lòng chọn một chuyến xe') ?>'}); return; }
            btn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin me-1"></i><?= __('Đang xử lý...') ?>');
            $.post(shipmentAjaxUrl, { request_name: 'add_packages', shipment_id: selectedShipmentId, package_ids: packageIds.join(','), csrf_token: csrfToken }, function(res){
                if (res.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('modalLoadTruck')).hide();
                    Swal.fire({icon: 'success', title: res.msg, timer: 2000, showConfirmButton: false}).then(function(){ location.reload(); });
                } else {
                    Swal.fire({icon: 'error', title: 'Error', text: res.msg});
                    btn.prop('disabled', false).html('<i class="ri-truck-line me-1"></i><?= __('Xếp xe') ?>');
                }
            }, 'json').fail(function(){ Swal.fire({icon: 'error', title: 'Error', text: '<?= __('Lỗi kết nối') ?>'}); btn.prop('disabled', false).html('<i class="ri-truck-line me-1"></i><?= __('Xếp xe') ?>'); });
        } else {
            var truckPlate = $('#new-truck-plate').val().trim();
            if (!truckPlate) { Swal.fire({icon: 'warning', title: '<?= __('Vui lòng nhập biển số xe') ?>'}); return; }
            btn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin me-1"></i><?= __('Đang xử lý...') ?>');
            $.post(shipmentAjaxUrl, {
                request_name: 'create', truck_plate: truckPlate, driver_name: $('#new-driver-name').val(),
                driver_phone: $('#new-driver-phone').val(), route: $('#new-route').val(), max_weight: $('#new-max-weight').val(),
                shipping_cost: $('#new-shipping-cost').val(), note: $('#new-note').val(), csrf_token: csrfToken
            }, function(res){
                if (res.status === 'success' && res.shipment_id) {
                    $.post(shipmentAjaxUrl, { request_name: 'add_packages', shipment_id: res.shipment_id, package_ids: packageIds.join(','), csrf_token: csrfToken }, function(res2){
                        bootstrap.Modal.getInstance(document.getElementById('modalLoadTruck')).hide();
                        if (res2.status === 'success') {
                            Swal.fire({
                                icon: 'success', title: '<?= __('Tạo chuyến xe và xếp kiện thành công') ?>',
                                html: res2.msg + '<br><a href="<?= base_url('admin/shipments-detail?id=') ?>' + res.shipment_id + '" class="btn btn-sm btn-primary mt-2"><i class="ri-eye-line me-1"></i><?= __('Xem chuyến xe') ?></a>',
                                showConfirmButton: true, confirmButtonText: '<?= __('Ở lại trang này') ?>'
                            }).then(function(){ location.reload(); });
                        } else {
                            Swal.fire({icon: 'error', title: 'Error', text: res2.msg});
                            btn.prop('disabled', false).html('<i class="ri-truck-line me-1"></i><?= __('Xếp xe') ?>');
                        }
                    }, 'json');
                } else {
                    Swal.fire({icon: 'error', title: 'Error', text: res.msg});
                    btn.prop('disabled', false).html('<i class="ri-truck-line me-1"></i><?= __('Xếp xe') ?>');
                }
            }, 'json').fail(function(){ Swal.fire({icon: 'error', title: 'Error', text: '<?= __('Lỗi kết nối') ?>'}); btn.prop('disabled', false).html('<i class="ri-truck-line me-1"></i><?= __('Xếp xe') ?>'); });
        }
    });

    // ===== Export Excel =====
    $('#btn-export-excel').on('click', function(){
        var rows = [];
        rows.push(['<?= __('Mã hàng') ?>', '<?= __('Loại') ?>', '<?= __('Khách hàng') ?>', '<?= __('Số kiện') ?>', '<?= __('Cân nặng (kg)') ?>', '<?= __('Số khối (m³)') ?>', '<?= __('Trạng thái') ?>']);

        <?php foreach ($sealedBags as $bag):
            $bagW = floatval($bag['bag_weight'] ?? 0); $pkgWC = floatval($bag['pkg_weight_charged'] ?? 0); $pkgWA = floatval($bag['pkg_weight_actual'] ?? 0);
            $w = $pkgWA > 0 ? $pkgWA : ($pkgWC > 0 ? $pkgWC : $bagW);
            $bc = floatval($bag['bag_cbm'] ?? 0); $pc = floatval($bag['pkg_cbm'] ?? 0); $c = $bc > 0 ? $bc : $pc;
            $bCusts = $bagCustomerMap[$bag['bag_id']] ?? [];
            $custName = count($bCusts) == 1 ? array_values($bCusts)[0] : (count($bCusts) > 1 ? count($bCusts) . ' khách' : '');
        ?>
        rows.push([<?= json_encode($bag['bag_code']) ?>, '<?= __('Mã bao') ?>', <?= json_encode($custName) ?>, <?= intval($bag['pkg_count']) ?>, <?= round($w, 2) ?>, <?= round($c, 4) ?>, '<?= __('Đã đóng bao') ?>']);
        <?php endforeach; ?>

        <?php foreach ($wholesaleOrders as $order):
            $wC = $order['total_weight_charged'] ?? 0; $wA = $order['total_weight_actual'] ?? 0; $w = $wA > 0 ? $wA : $wC;
            $c = $order['total_cbm'] ?? 0;
            if (floatval($order['volume_actual'] ?? 0) > 0) $c = floatval($order['volume_actual']);
        ?>
        rows.push([<?= json_encode($order['product_code'] ?? '#' . $order['id']) ?>, '<?= __('Mã hàng') ?>', <?= json_encode($order['customer_name'] ?? '') ?>, <?= intval($order['pkg_count']) ?>, <?= round($w, 2) ?>, <?= round($c, 4) ?>, '<?= __('Đã về kho TQ') ?>']);
        <?php endforeach; ?>

        var ws = XLSX.utils.aoa_to_sheet(rows);
        ws['!cols'] = rows[0].map(function(h){ return {wch: Math.max(String(h).length + 2, 12)}; });
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Sheet1');
        XLSX.writeFile(wb, '<?= __('hang-cho-xep-xe') ?>_' + new Date().toISOString().slice(0,10) + '.xlsx');
    });

    // ===== View Images (Bootstrap Carousel) =====
    var galleryCarousel = null;
    var galleryTotal = 0;

    function updateGalleryCounter() {
        var idx = $('#imageCarousel .carousel-item.active').index();
        $('#gallery-counter').text((idx + 1) + ' / ' + galleryTotal);
        // Hide prev/next when single image
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

        // Build carousel items
        var html = '';
        images.forEach(function(url, i){
            html += '<div class="carousel-item' + (i === 0 ? ' active' : '') + '">'
                + '<div class="d-flex align-items-center justify-content-center" style="min-height:300px;">'
                + '<img src="' + url + '" class="d-block" style="max-width:100%;max-height:75vh;object-fit:contain;">'
                + '</div></div>';
        });
        $('#carousel-items').html(html);

        // Reset carousel to first slide
        if (galleryCarousel) galleryCarousel.dispose();
        galleryCarousel = new bootstrap.Carousel($('#imageCarousel')[0], { interval: false, touch: true, keyboard: true });

        updateGalleryCounter();
        new bootstrap.Modal($('#imageGalleryModal')[0]).show();
    });
});
</script>
