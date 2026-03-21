<?php
require_once(__DIR__.'/../../../models/is_staffvn.php');
require_once(__DIR__.'/../../../libs/csrf.php');

// Preset filter from wrapper pages (orders-retail.php / orders-wholesale.php)
$_presetFilterType = $_presetFilterType ?? null;
if ($_presetFilterType === 'bags') {
    $page_title = __('Hàng lẻ');
    $filterType = 'bags';
    $_formAction = 'orders-retail';
} elseif ($_presetFilterType === 'wholesale') {
    $page_title = __('Hàng lô');
    $filterType = 'wholesale';
    $_formAction = 'orders-wholesale';
} else {
    $page_title = __('Quản lý mã hàng');
    $filterType = input_get('type') ?: '';
    $_formAction = 'orders-manage';
}

// Filters
$filterSearch = trim(input_get('search') ?? '');
$filterCustomer = input_get('customer_id') ?: '';
$filterConfirm = input_get('confirm') ?: '';
$filterDelivery = input_get('delivery') ?: '';

// Pagination
$perPage = 10;
$page = max(1, intval(input_get('pg') ?? 1));
$offset = ($page - 1) * $perPage;

// === BAGS (Mã bao) ===
$sealedBags = [];
$bagCustomerMap = [];
$totalBags = 0;

if ($filterType !== 'wholesale') {
    $bagWhere = "b.status IN ('shipping', 'arrived')";
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
        ORDER BY FIELD(b.status, 'shipping', 'arrived'), b.update_date DESC
        LIMIT $perPage OFFSET $offset",
        $bagParams
    );

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
}

// === WHOLESALE ORDERS (Mã hàng lô) ===
$wholesaleOrders = [];

if ($filterType !== 'bags') {
    if ($filterDelivery === 'delivered') {
        $orderWhere = "o.product_type = 'wholesale' AND o.status = 'delivered'";
    } elseif ($filterDelivery === 'not_delivered') {
        $orderWhere = "o.product_type = 'wholesale' AND o.status IN ('packed', 'loading', 'shipping', 'vn_warehouse')";
    } else {
        $orderWhere = "o.product_type = 'wholesale' AND o.status IN ('packed', 'loading', 'shipping', 'vn_warehouse', 'delivered')";
    }
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
    if ($filterConfirm === 'pending') {
        $orderWhere .= " AND (o.note IS NULL OR o.note NOT LIKE '%Xác nhận đủ kiện%') AND o.missing_count = 0";
    } elseif ($filterConfirm === 'confirmed') {
        $orderWhere .= " AND o.note LIKE '%Xác nhận đủ kiện%'";
    } elseif ($filterConfirm === 'missing') {
        $orderWhere .= " AND (o.note IS NULL OR o.note NOT LIKE '%Xác nhận đủ kiện%') AND o.missing_count > 0";
    }

    $totalWholesale = $ToryHub->get_row_safe("SELECT COUNT(*) as cnt FROM `orders` o WHERE $orderWhere", $orderParams)['cnt'] ?? 0;

    $wholesaleOrders = $ToryHub->get_list_safe(
        "SELECT o.id, o.product_code, o.order_code, o.product_name, o.volume_actual,
            o.product_image, o.customer_id, o.quantity, o.is_paid, o.status, o.note,
            o.missing_count, o.total_packages,
            c.fullname as customer_name, c.phone as customer_phone,
            COUNT(p.id) as pkg_linked,
            SUM(CASE WHEN p.status IN ('packed', 'loading', 'shipping', 'vn_warehouse', 'delivered') THEN 1 ELSE 0 END) as pkg_shipped,
            SUM(CASE WHEN p.status IN ('vn_warehouse', 'delivered') THEN 1 ELSE 0 END) as pkg_received,
            SUM(COALESCE(p.weight_charged, 0)) as total_weight_charged,
            SUM(COALESCE(p.weight_actual, 0)) as total_weight_actual,
            SUM(COALESCE(p.length_cm,0) * COALESCE(p.width_cm,0) * COALESCE(p.height_cm,0) / 1000000) as total_cbm,
            o.create_date, o.update_date
        FROM `orders` o
        LEFT JOIN `customers` c ON o.customer_id = c.id
        LEFT JOIN `package_orders` po ON o.id = po.order_id
        LEFT JOIN `packages` p ON po.package_id = p.id
        WHERE $orderWhere
        GROUP BY o.id
        ORDER BY o.update_date DESC
        LIMIT $perPage OFFSET $offset",
        $orderParams
    );
}

// Total for current view
if ($filterType === 'bags' || $_presetFilterType === 'bags') {
    $totalRows = $totalBags;
} elseif ($filterType === 'wholesale' || $_presetFilterType === 'wholesale') {
    $totalRows = $totalWholesale ?? 0;
} else {
    $totalRows = $totalBags + ($totalWholesale ?? 0);
}
$totalPages = max(1, ceil($totalRows / $perPage));
if ($page > $totalPages) $page = $totalPages;

$bagStatusLabels = [
    'sealed' => ['label' => 'Chờ vận chuyển', 'bg' => 'warning', 'icon' => 'ri-time-line'],
    'loading' => ['label' => 'Đang xếp xe', 'bg' => 'secondary', 'icon' => 'ri-truck-line'],
    'shipping' => ['label' => 'Đang vận chuyển', 'bg' => 'primary', 'icon' => 'ri-ship-line'],
    'arrived' => ['label' => 'Đã đến kho VN', 'bg' => 'success', 'icon' => 'ri-check-double-line'],
    'completed' => ['label' => 'Đã nhận đủ', 'bg' => 'success', 'icon' => 'ri-checkbox-circle-line'],
];

$customers = $ToryHub->get_list_safe("SELECT `id`, `fullname` FROM `customers` ORDER BY `fullname` ASC", []);

$csrf = new Csrf();
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
                        <form method="GET" action="<?= base_url('staffvn/' . $_formAction) ?>">
                            <input type="hidden" name="module" value="staffvn">
                            <input type="hidden" name="action" value="<?= $_formAction ?>">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label"><?= __('Tìm kiếm') ?></label>
                                    <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($filterSearch) ?>" placeholder="<?= $_presetFilterType === 'bags' ? __('Mã bao...') : ($_presetFilterType === 'wholesale' ? __('Mã hàng lô...') : __('Mã bao, mã hàng lô...')) ?>">
                                </div>
                                <?php if (!$_presetFilterType): ?>
                                <div class="col-md-2">
                                    <label class="form-label"><?= __('Loại hàng') ?></label>
                                    <select class="form-select" name="type">
                                        <option value=""><?= __('Tất cả') ?></option>
                                        <option value="bags" <?= $filterType == 'bags' ? 'selected' : '' ?>><?= __('Hàng lẻ') ?></option>
                                        <option value="wholesale" <?= $filterType == 'wholesale' ? 'selected' : '' ?>><?= __('Hàng lô') ?></option>
                                    </select>
                                </div>
                                <?php endif; ?>
                                <?php if ($_presetFilterType === 'wholesale'): ?>
                                <div class="col-md-2">
                                    <label class="form-label"><?= __('Xác nhận') ?></label>
                                    <select class="form-select" name="confirm">
                                        <option value=""><?= __('Tất cả') ?></option>
                                        <option value="pending" <?= $filterConfirm == 'pending' ? 'selected' : '' ?>><?= __('Chờ xác nhận') ?></option>
                                        <option value="confirmed" <?= $filterConfirm == 'confirmed' ? 'selected' : '' ?>><?= __('Xác nhận đủ') ?></option>
                                        <option value="missing" <?= $filterConfirm == 'missing' ? 'selected' : '' ?>><?= __('Đang thiếu') ?></option>
                                    </select>
                                </div>
                                <?php endif; ?>
                                <?php if ($_presetFilterType === 'wholesale'): ?>
                                <div class="col-md-2">
                                    <label class="form-label"><?= __('Giao hàng') ?></label>
                                    <select class="form-select" name="delivery">
                                        <option value=""><?= __('Tất cả') ?></option>
                                        <option value="not_delivered" <?= $filterDelivery == 'not_delivered' ? 'selected' : '' ?>><?= __('Chưa giao') ?></option>
                                        <option value="delivered" <?= $filterDelivery == 'delivered' ? 'selected' : '' ?>><?= __('Đã giao') ?></option>
                                    </select>
                                </div>
                                <?php endif; ?>
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
                                    <a href="<?= base_url('staffvn/' . $_formAction) ?>" class="btn btn-secondary"><?= __('Reset') ?></a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= $page_title ?> (<?= $totalRows ?> <?= __('dòng') ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <?php $colCount = $_presetFilterType === 'wholesale' ? 8 : 7; ?>
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:50px;" class="text-center">#</th>
                                        <th><?= __('Mã hàng') ?></th>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th class="text-end" style="width:110px;"><?= __('Cân nặng') ?></th>
                                        <th class="text-end" style="width:100px;"><?= __('Số khối') ?></th>
                                        <th style="width:150px;"><?= __('Trạng thái') ?></th>
                                        <?php if ($_presetFilterType === 'wholesale'): ?>
                                        <th style="width:200px;"><?= __('Ghi chú') ?></th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($totalRows === 0): ?>
                                    <tr><td colspan="<?= $colCount ?>" class="text-center text-muted py-4"><?= __('Không có dữ liệu') ?></td></tr>
                                    <?php endif; ?>

                                    <?php // === BAG ROWS === ?>
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
                                        $pctReceived = $pkgCount > 0 ? round($pkgReceived / $pkgCount * 100) : 0;
                                        $bagCusts = $bagCustomerMap[$bag['bag_id']] ?? [];
                                        $bagDisplayStatus = ($pkgCount > 0 && $pkgReceived >= $pkgCount) ? 'completed' : $bag['status'];
                                        $bsl = $bagStatusLabels[$bagDisplayStatus] ?? $bagStatusLabels['shipping'];
                                        $bagImgArr = !empty($bag['bag_images']) ? array_filter(array_map('trim', explode(',', $bag['bag_images']))) : [];
                                        $bagImgUrls = array_map('get_upload_url', $bagImgArr);
                                    ?>
                                    <tr class="bag-row" data-bag-id="<?= $bag['bag_id'] ?>" style="cursor:pointer;">
                                        <td class="text-center text-muted"><?= $rowIdx ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if (!empty($bagImgUrls)): ?>
                                                <a href="#" class="btn-view-images flex-shrink-0" data-images="<?= htmlspecialchars(json_encode(array_values($bagImgUrls))) ?>">
                                                    <img src="<?= $bagImgUrls[0] ?>" class="rounded" style="width:36px;height:36px;object-fit:cover;">
                                                </a>
                                                <?php endif; ?>
                                                <div>
                                                    <strong class="text-primary"><?= htmlspecialchars($bag['bag_code']) ?></strong>
                                                    <i class="ri-arrow-right-s-line fs-14 toggle-icon text-muted ms-1"></i>
                                                </div>
                                            </div>
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
                                            <span class="badge bg-<?= $bsl['bg'] ?>-subtle text-<?= $bsl['bg'] ?>" style="font-size:11px;"><i class="<?= $bsl['icon'] ?> me-1"></i><?= __($bsl['label']) ?><?php if ($pkgCount > 0): ?> <span class="text-success"><?= $pkgReceived ?></span>/<span class="text-danger"><?= $pkgCount ?></span><?php endif; ?></span>
                                        </td>
                                    </tr>
                                    <tr class="bag-detail-row d-none" id="bag-detail-<?= $bag['bag_id'] ?>">
                                        <td colspan="<?= $colCount ?>" class="p-0">
                                            <div class="px-4 py-2 bg-light">
                                                <div class="bag-packages-content">
                                                    <div class="text-center py-2 text-muted"><i class="ri-loader-4-line ri-spin fs-20"></i> <?= __('Đang tải...') ?></div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>

                                    <?php // === WHOLESALE ORDER ROWS === ?>
                                    <?php foreach ($wholesaleOrders as $order):
                                        $rowIdx++;
                                    ?><?php
                                        $wCharged = floatval($order['total_weight_charged'] ?? 0);
                                        $wActual = floatval($order['total_weight_actual'] ?? 0);
                                        $weight = $wActual > 0 ? $wActual : $wCharged;
                                        $cbm = floatval($order['total_cbm'] ?? 0);
                                        if (floatval($order['volume_actual'] ?? 0) > 0) $cbm = floatval($order['volume_actual']);
                                        $pkgLinked = intval($order['pkg_linked'] ?? 0);
                                        $pkgShipped = intval($order['pkg_shipped'] ?? 0);
                                        $pkgCount = $pkgShipped;
                                        $pkgReceived = intval($order['pkg_received'] ?? 0);
                                        $missingCount = intval($order['missing_count'] ?? 0);
                                        $pctReceived = $pkgCount > 0 ? round($pkgReceived / $pkgCount * 100) : 0;
                                        $orderImgArr = parse_product_images($order['product_image']);
                                        $orderImgUrls = array_map('get_upload_url', $orderImgArr);
                                    ?>
                                    <tr class="order-row" data-order-id="<?= $order['id'] ?>" data-pkg-count="<?= $pkgCount ?>" data-pkg-received="<?= $pkgReceived ?>" style="cursor:pointer;">
                                        <td class="text-center text-muted"><?= $rowIdx ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if (!empty($orderImgUrls)): ?>
                                                <a href="#" class="btn-view-images flex-shrink-0" data-images="<?= htmlspecialchars(json_encode(array_values($orderImgUrls))) ?>">
                                                    <img src="<?= $orderImgUrls[0] ?>" class="rounded" style="width:36px;height:36px;object-fit:cover;">
                                                </a>
                                                <?php endif; ?>
                                                <div>
                                                    <?php if ($order['product_code'] ?? ''): ?>
                                                    <strong class="text-primary"><?= htmlspecialchars($order['product_code']) ?></strong>
                                                    <?php else: ?>
                                                    <span class="text-muted">#<?= $order['id'] ?></span>
                                                    <?php endif; ?>
                                                    <i class="ri-arrow-right-s-line fs-14 toggle-icon text-muted ms-1"></i>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($order['customer_id']): ?>
                                            <?= htmlspecialchars($order['customer_name'] ?? '') ?>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end"><?= $weight > 0 ? fnum($weight, 1) . ' kg' : '<span class="text-muted">-</span>' ?></td>
                                        <td class="text-end"><?= $cbm > 0 ? fnum($cbm, 2) . ' m&sup3;' : '<span class="text-muted">-</span>' ?></td>
                                        <td>
                                            <?php
                                                $pkgTotalNum = intval($order['total_packages'] ?? 0) ?: $pkgLinked;
                                                $pkgInTransit = $pkgShipped - $pkgReceived;
                                            ?>
                                            <div><span class="badge bg-secondary-subtle text-secondary fs-12"><?= __('Tổng số kiện') ?>: <?= $pkgTotalNum ?></span></div>
                                            <?php if ($pkgInTransit > 0): ?>
                                            <div class="mt-1"><span class="badge bg-primary-subtle text-primary fs-12"><?= __('Đang vận chuyển') ?>: <?= $pkgInTransit ?></span></div>
                                            <?php endif; ?>
                                            <?php if ($pkgReceived > 0): ?>
                                            <div class="mt-1"><span class="badge bg-success-subtle text-success fs-12"><?= __('Đã về kho Việt Nam') ?>: <?= $pkgReceived ?></span></div>
                                            <?php endif; ?>
                                            <?php if ($order['is_paid']): ?>
                                            <div class="mt-1"><span class="badge bg-success-subtle text-success fs-12"><?= __('Đã thanh toán') ?></span></div>
                                            <?php endif; ?>
                                        </td>
                                        <?php
                                            $hasPartialNote = !empty($order['note']) && strpos($order['note'], __('Thiếu kiện')) !== false;
                                            $allReceived = $pkgCount > 0 && $pkgReceived >= $pkgCount;
                                        ?>
                                        <?php if ($_presetFilterType === 'wholesale'): ?>
                                        <td class="note-cell">
                                            <?php if ($hasPartialNote && !$allReceived):
                                                $noteLines = explode("\n", $order['note']);
                                                foreach ($noteLines as $nl):
                                                    // Extract custom note: "... Thiếu kiện: X kiện - custom note (user)"
                                                    if (preg_match('/' . preg_quote(__('Thiếu kiện'), '/') . ':\s*\d+\s*' . preg_quote(__('kiện'), '/') . '\s*-\s*(.+?)\s*\([^)]*\)\s*$/', trim($nl), $nm)):
                                            ?>
                                            <div><small class="text-warning"><i class="ri-error-warning-line me-1"></i><?= htmlspecialchars(trim($nm[1])) ?></small></div>
                                            <?php endif; endforeach; endif; ?>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <tr class="order-detail-row d-none" id="order-detail-<?= $order['id'] ?>">
                                        <td colspan="<?= $colCount ?>" class="p-0">
                                            <div class="px-4 py-2 bg-light">
                                                <div class="order-packages-content">
                                                    <div class="text-center py-2 text-muted"><i class="ri-loader-4-line ri-spin fs-20"></i> <?= __('Đang tải...') ?></div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalPages > 1): ?>
                        <!-- Pagination -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <small class="text-muted">
                                <?= __('Hiển thị') ?> <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalRows) ?> / <?= $totalRows ?>
                            </small>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <?php
                                    $queryParams = $_GET;
                                    unset($queryParams['pg']);
                                    $baseUrl = base_url('staffvn/' . $_formAction) . '?' . http_build_query($queryParams);
                                    ?>
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $baseUrl ?>&page=<?= $page - 1 ?>">&laquo;</a>
                                    </li>
                                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= $baseUrl ?>&page=<?= $p ?>"><?= $p ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $baseUrl ?>&page=<?= $page + 1 ?>">&raquo;</a>
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
$(function(){
    var csrfToken = '<?= $csrf->get_token_value() ?>';
    var csrfName = '<?= $csrf->get_token_name() ?>';
    var loadedBags = {};
    var loadedOrders = {};
    var ajaxUrl = '<?= base_url("ajaxs/staffvn/bag-unpack.php") ?>';

    // ===== Expand bag rows =====
    $(document).on('click', '.bag-row', function(e){
        if ($(e.target).closest('.btn-view-images').length) return;
        var bagId = $(this).data('bag-id');
        var $detail = $('#bag-detail-' + bagId);
        var $icon = $(this).find('.toggle-icon');
        if ($detail.hasClass('d-none')) {
            $detail.removeClass('d-none');
            $icon.removeClass('ri-arrow-right-s-line').addClass('ri-arrow-down-s-line');
            if (!loadedBags[bagId]) loadBagPackages(bagId);
        } else {
            $detail.addClass('d-none');
            $icon.removeClass('ri-arrow-down-s-line').addClass('ri-arrow-right-s-line');
        }
    });

    function loadBagPackages(bagId) {
        var $c = $('#bag-detail-' + bagId).find('.bag-packages-content');
        $.post(ajaxUrl, {
            request_name: 'load_bag_detail', bag_id: bagId, [csrfName]: csrfToken
        }, function(res){
            if (res.csrf_token) csrfToken = res.csrf_token;
            if (res.status === 'success' && res.packages && res.packages.length > 0) {
                var h = '<table class="table table-sm table-borderless mb-0 text-muted"><thead><tr>';
                h += '<th>#</th><th><?= __("Mã vận đơn") ?></th>';
                h += '<th><?= __("Sản phẩm") ?></th><th><?= __("Khách hàng") ?></th>';
                h += '<th><?= __("Cân nặng") ?></th><th><?= __("Trạng thái") ?></th>';
                h += '</tr></thead><tbody>';
                res.packages.forEach(function(p, i){
                    h += '<tr>';
                    h += '<td>' + (i + 1) + '</td>';
                    h += '<td><code class="fs-11">' + esc(p.tracking_cn || '-') + '</code></td>';
                    h += '<td><small>' + esc(p.product_name || '-') + '</small></td>';
                    h += '<td><small>' + esc(p.customer_name || '-') + '</small></td>';
                    h += '<td>' + (p.weight_actual > 0 ? p.weight_actual + ' kg' : (p.weight_charged > 0 ? p.weight_charged + ' kg' : '-')) + '</td>';
                    h += '<td>' + (p.status_html || '-') + '</td>';
                    h += '</tr>';
                });
                h += '</tbody></table>';
                $c.html(h);
                loadedBags[bagId] = true;
            } else {
                $c.html('<div class="text-center py-2 text-muted"><?= __("Bao trống") ?></div>');
                loadedBags[bagId] = true;
            }
        }, 'json').fail(function(){
            $c.html('<div class="text-center py-2 text-danger"><?= __("Lỗi tải dữ liệu") ?></div>');
        });
    }

    // ===== Expand wholesale order rows =====
    $(document).on('click', '.order-row', function(e){
        if ($(e.target).closest('.btn-view-images').length) return;
        var orderId = $(this).data('order-id');
        var $detail = $('#order-detail-' + orderId);
        var $icon = $(this).find('.toggle-icon');
        if ($detail.hasClass('d-none')) {
            $detail.removeClass('d-none');
            $icon.removeClass('ri-arrow-right-s-line').addClass('ri-arrow-down-s-line');
            if (!loadedOrders[orderId]) loadOrderPackages(orderId);
        } else {
            $detail.addClass('d-none');
            $icon.removeClass('ri-arrow-down-s-line').addClass('ri-arrow-right-s-line');
        }
    });

    function loadOrderPackages(orderId) {
        var $c = $('#order-detail-' + orderId).find('.order-packages-content');
        $.post(ajaxUrl, {
            request_name: 'load_order_detail', order_id: orderId, [csrfName]: csrfToken
        }, function(res){
            if (res.csrf_token) csrfToken = res.csrf_token;
            if (res.status === 'success' && res.packages && res.packages.length > 0) {
                var h = '<table class="table table-sm table-borderless mb-0 text-muted"><thead><tr>';
                h += '<th>#</th><th><?= __("Mã vận đơn") ?></th>';
                h += '<th><?= __("Cân nặng") ?></th><th><?= __("Số khối") ?></th><th><?= __("Trạng thái") ?></th>';
                h += '</tr></thead><tbody>';
                res.packages.forEach(function(p, i){
                    h += '<tr>';
                    h += '<td>' + (i + 1) + '</td>';
                    h += '<td><code class="fs-11">' + esc(p.tracking_cn || '-') + '</code></td>';
                    h += '<td>' + (p.weight_charged > 0 ? p.weight_charged + ' kg' : (p.weight_actual > 0 ? p.weight_actual + ' kg' : '-')) + '</td>';
                    h += '<td>' + (p.cbm > 0 ? p.cbm + ' m³' : '-') + '</td>';
                    h += '<td>' + (p.status_html || '-') + '</td>';
                    h += '</tr>';
                });
                h += '</tbody></table>';
                $c.html(h);
                loadedOrders[orderId] = true;
            } else {
                $c.html('<div class="text-center py-2 text-muted"><?= __("Không có kiện hàng") ?></div>');
                loadedOrders[orderId] = true;
            }
        }, 'json').fail(function(){
            $c.html('<div class="text-center py-2 text-danger"><?= __("Lỗi tải dữ liệu") ?></div>');
        });
    }

    // ===== Image Gallery =====
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
        e.stopPropagation();
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

    function esc(s){ if(!s) return ''; var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

});
</script>
