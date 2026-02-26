<?php
require_once(__DIR__.'/../../../models/is_admin.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Tích cước đơn hàng');

// Filters
$filterSearch = trim(input_get('search') ?? '');
$filterCustomer = input_get('customer_id') ?: '';
$filterType = input_get('type') ?: '';
$filterCargo = input_get('cargo') ?: '';
$_wmin = input_get('weight_min'); $filterWeightMin = ($_wmin !== '' && $_wmin !== null && floatval($_wmin) > 0) ? floatval($_wmin) : null;
$_wmax = input_get('weight_max'); $filterWeightMax = ($_wmax !== '' && $_wmax !== null && floatval($_wmax) > 0) ? floatval($_wmax) : null;
$_cmin = input_get('cbm_min'); $filterCbmMin = ($_cmin !== '' && $_cmin !== null && floatval($_cmin) > 0) ? floatval($_cmin) : null;
$_cmax = input_get('cbm_max'); $filterCbmMax = ($_cmax !== '' && $_cmax !== null && floatval($_cmax) > 0) ? floatval($_cmax) : null;
$filterSort = input_get('sort') ?: '';
$filterSortDir = input_get('dir') ?: 'asc';
$perPage = intval(input_get('per_page') ?: 50);
$currentPage = max(1, intval(input_get('page') ?: 1));

$notInShipment = "p.id NOT IN (SELECT sp.package_id FROM `shipment_packages` sp JOIN `shipments` s ON sp.shipment_id = s.id WHERE s.status IN ('preparing','in_transit'))";

// === Summary counts ===
$cntCnWarehouse = $ToryHub->num_rows_safe(
    "SELECT p.id FROM `packages` p WHERE p.status = 'cn_warehouse' AND $notInShipment", []
);
$cntPacked = $ToryHub->num_rows_safe(
    "SELECT p.id FROM `packages` p WHERE p.status = 'packed' AND p.id IN (SELECT bp2.package_id FROM `bag_packages` bp2 JOIN `bags` b2 ON bp2.bag_id = b2.id WHERE b2.status = 'sealed') AND $notInShipment", []
);
$totalPendingPkgs = $cntCnWarehouse + $cntPacked;

// === SEALED BAGS (retail packed) ===
$sealedBags = [];
$bagPkgIdsMap = [];
$bagCustomerMap = [];

if ($filterType !== 'wholesale' && $filterCargo !== 'difficult') {
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
            COALESCE(b.domestic_cost, 0) as domestic_cost,
            b.custom_rate_kg, b.custom_rate_cbm,
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
        "SELECT o.id, o.product_code, o.cargo_type, o.product_image, o.customer_id,
            c.fullname as customer_name, c.customer_code,
            COUNT(p.id) as pkg_count,
            SUM(COALESCE(p.weight_charged, 0)) as total_weight_charged,
            SUM(COALESCE(p.weight_actual, 0)) as total_weight_actual,
            SUM(p.length_cm * p.width_cm * p.height_cm / 1000000) as total_cbm,
            COALESCE(o.domestic_cost, 0) as domestic_cost,
            o.custom_rate_kg, o.custom_rate_cbm,
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

// Post-filter bags by weight/cbm
if (!empty($sealedBags) && ($filterWeightMin !== null || $filterWeightMax !== null || $filterCbmMin !== null || $filterCbmMax !== null)) {
    $sealedBags = array_values(array_filter($sealedBags, function($bag) use ($filterWeightMin, $filterWeightMax, $filterCbmMin, $filterCbmMax) {
        $bagW = floatval($bag['bag_weight'] ?? 0); $pkgWC = floatval($bag['pkg_weight_charged'] ?? 0); $pkgWA = floatval($bag['pkg_weight_actual'] ?? 0);
        $w = $bagW > 0 ? $bagW : ($pkgWC > 0 ? $pkgWC : $pkgWA);
        $bc = floatval($bag['bag_cbm'] ?? 0); $pc = floatval($bag['pkg_cbm'] ?? 0); $c = $bc > 0 ? $bc : $pc;
        if ($filterWeightMin !== null && $w < $filterWeightMin) return false;
        if ($filterWeightMax !== null && $w > $filterWeightMax) return false;
        if ($filterCbmMin !== null && $c < $filterCbmMin) return false;
        if ($filterCbmMax !== null && $c > $filterCbmMax) return false;
        return true;
    }));
}

// Post-filter orders by cargo type and weight/cbm
if (!empty($wholesaleOrders) && $filterCargo) {
    $wholesaleOrders = array_values(array_filter($wholesaleOrders, function($o) use ($filterCargo) {
        return ($o['cargo_type'] ?? 'easy') === $filterCargo;
    }));
}
if (!empty($wholesaleOrders) && ($filterWeightMin !== null || $filterWeightMax !== null || $filterCbmMin !== null || $filterCbmMax !== null)) {
    $wholesaleOrders = array_values(array_filter($wholesaleOrders, function($o) use ($filterWeightMin, $filterWeightMax, $filterCbmMin, $filterCbmMax) {
        $wC = $o['total_weight_charged'] ?? 0; $wA = $o['total_weight_actual'] ?? 0; $w = $wC > 0 ? $wC : $wA;
        $c = $o['total_cbm'] ?? 0;
        if ($filterWeightMin !== null && $w < $filterWeightMin) return false;
        if ($filterWeightMax !== null && $w > $filterWeightMax) return false;
        if ($filterCbmMin !== null && $c < $filterCbmMin) return false;
        if ($filterCbmMax !== null && $c > $filterCbmMax) return false;
        return true;
    }));
}

$totalRows = count($sealedBags) + count($wholesaleOrders);

$customers = $ToryHub->get_list_safe("SELECT `id`, `customer_code`, `fullname` FROM `customers` ORDER BY `fullname` ASC", []);

// === Shipping rates for cost calculation ===
$shippingRates = [
    'road' => [
        'easy'      => ['per_kg' => floatval($ToryHub->site('shipping_road_easy_per_kg') ?: 25000), 'per_cbm' => floatval($ToryHub->site('shipping_road_easy_per_cbm') ?: 6000000)],
        'difficult' => ['per_kg' => floatval($ToryHub->site('shipping_road_difficult_per_kg') ?: 35000), 'per_cbm' => floatval($ToryHub->site('shipping_road_difficult_per_cbm') ?: 8000000)],
    ],
];
function calcShippingCost($weight, $cbm, $method, $cargoType, $rates) {
    $cargoType = $cargoType ?: 'easy';
    $r = $rates['road'][$cargoType] ?? $rates['road']['easy'];
    $byKg = $weight * $r['per_kg'];
    $byCbm = $cbm * $r['per_cbm'];
    return ['by_kg' => $byKg, 'by_cbm' => $byCbm, 'max' => max($byKg, $byCbm)];
}

// === Build unified rows for sorting and totals ===
$allRows = [];
foreach ($sealedBags as $bag) {
    $bagW = floatval($bag['bag_weight'] ?? 0); $pkgWC = floatval($bag['pkg_weight_charged'] ?? 0); $pkgWA = floatval($bag['pkg_weight_actual'] ?? 0);
    $w = $bagW > 0 ? $bagW : ($pkgWC > 0 ? $pkgWC : $pkgWA);
    $bc = floatval($bag['bag_cbm'] ?? 0); $pc = floatval($bag['pkg_cbm'] ?? 0); $c = $bc > 0 ? $bc : $pc;
    $rate = $shippingRates['road']['easy'] ?? $shippingRates['road']['easy'];
    $rkg = $bag['custom_rate_kg'] !== null ? floatval($bag['custom_rate_kg']) : $rate['per_kg'];
    $rcbm = $bag['custom_rate_cbm'] !== null ? floatval($bag['custom_rate_cbm']) : $rate['per_cbm'];
    $cost = ['by_kg' => $w * $rkg, 'by_cbm' => $c * $rcbm, 'max' => max($w * $rkg, $c * $rcbm)];
    $allRows[] = ['type' => 'bag', 'data' => $bag, 'weight' => $w, 'cbm' => $c, 'cost' => $cost, 'pkg_count' => intval($bag['pkg_count'] ?? 0), 'cargo' => 'easy', 'rate_kg' => $rkg, 'rate_cbm' => $rcbm, 'domestic_cost' => floatval($bag['domestic_cost'] ?? 0)];
}
foreach ($wholesaleOrders as $order) {
    $wC = $order['total_weight_charged'] ?? 0; $wA = $order['total_weight_actual'] ?? 0; $w = $wC > 0 ? $wC : $wA;
    $c = $order['total_cbm'] ?? 0; $cargo = $order['cargo_type'] ?? 'easy';
    $rate = $shippingRates['road'][$cargo] ?? $shippingRates['road']['easy'];
    $rkg = $order['custom_rate_kg'] !== null ? floatval($order['custom_rate_kg']) : $rate['per_kg'];
    $rcbm = $order['custom_rate_cbm'] !== null ? floatval($order['custom_rate_cbm']) : $rate['per_cbm'];
    $cost = ['by_kg' => floatval($w) * $rkg, 'by_cbm' => floatval($c) * $rcbm, 'max' => max(floatval($w) * $rkg, floatval($c) * $rcbm)];
    $allRows[] = ['type' => 'order', 'data' => $order, 'weight' => floatval($w), 'cbm' => floatval($c), 'cost' => $cost, 'pkg_count' => intval($order['pkg_count'] ?? 0), 'cargo' => $cargo, 'rate_kg' => $rkg, 'rate_cbm' => $rcbm, 'domestic_cost' => floatval($order['domestic_cost'] ?? 0)];
}

// Sort
if ($filterSort) {
    usort($allRows, function($a, $b) use ($filterSort, $filterSortDir) {
        switch ($filterSort) {
            case 'weight': $va = $a['weight']; $vb = $b['weight']; break;
            case 'cbm': $va = $a['cbm']; $vb = $b['cbm']; break;
            case 'cost': $va = $a['cost']['max']; $vb = $b['cost']['max']; break;
            case 'pkg_count': $va = $a['pkg_count']; $vb = $b['pkg_count']; break;
            default: return 0;
        }
        return $filterSortDir === 'desc' ? ($vb <=> $va) : ($va <=> $vb);
    });
}

// Grand totals
$grandPkgs = 0; $grandWeight = 0; $grandCbm = 0; $grandCostKg = 0; $grandCostCbm = 0;
$warnRows = 0;
foreach ($allRows as &$row) {
    $grandPkgs += $row['pkg_count'];
    $grandWeight += $row['weight'];
    $grandCbm += $row['cbm'];
    $grandCostKg += $row['cost']['by_kg'];
    $grandCostCbm += $row['cost']['by_cbm'];
    $row['warn'] = ($row['weight'] <= 0 || $row['cbm'] <= 0);
    if ($row['warn']) $warnRows++;
}
unset($row);

// Pagination
$totalPages = $perPage > 0 ? max(1, ceil(count($allRows) / $perPage)) : 1;
$currentPage = min($currentPage, $totalPages);
$pagedRows = $perPage > 0 ? array_slice($allRows, ($currentPage - 1) * $perPage, $perPage) : $allRows;

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

        <!-- KPI Cards -->
        <div class="row mb-3">
            <div class="col">
                <div class="card card-body py-3 mb-0">
                    <div class="text-muted fs-12 mb-1"><?= __('Tổng kiện') ?></div>
                    <h5 class="mb-0"><?= number_format($grandPkgs) ?></h5>
                </div>
            </div>
            <div class="col">
                <div class="card card-body py-3 mb-0">
                    <div class="text-muted fs-12 mb-1"><?= __('Tổng cân nặng') ?></div>
                    <h5 class="mb-0 text-primary"><?= fnum($grandWeight, 1) ?> <small class="fs-12">kg</small></h5>
                </div>
            </div>
            <div class="col">
                <div class="card card-body py-3 mb-0">
                    <div class="text-muted fs-12 mb-1"><?= __('Tổng số khối') ?></div>
                    <h5 class="mb-0 text-primary"><?= fnum($grandCbm, 2) ?> <small class="fs-12">m&sup3;</small></h5>
                </div>
            </div>
            <div class="col">
                <div class="card card-body py-3 mb-0">
                    <div class="text-muted fs-12 mb-1"><?= __('Cước theo kg') ?></div>
                    <h5 class="mb-0 text-danger"><?= format_vnd($grandCostKg) ?></h5>
                </div>
            </div>
            <div class="col">
                <div class="card card-body py-3 mb-0">
                    <div class="text-muted fs-12 mb-1"><?= __('Cước theo m³') ?></div>
                    <h5 class="mb-0 text-danger"><?= format_vnd($grandCostCbm) ?></h5>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <?php
        $baseUrl = base_url('admin/shipping-calculator');
        function buildFilterUrl($params, $baseUrl) {
            $q = http_build_query(array_filter($params, function($v){ return $v !== '' && $v !== null; }));
            return $baseUrl . ($q ? '&' . $q : '');
        }
        $currentFilters = [
            'search' => $filterSearch, 'customer_id' => $filterCustomer, 'type' => $filterType,
            'cargo' => $filterCargo, 'weight_min' => $filterWeightMin, 'weight_max' => $filterWeightMax,
            'cbm_min' => $filterCbmMin, 'cbm_max' => $filterCbmMax, 'sort' => $filterSort, 'dir' => $filterSortDir, 'per_page' => $perPage
        ];
        ?>
        <div class="row mb-3">
            <div class="col-12">
                <form method="get" action="<?= $baseUrl ?>" class="card card-body py-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label mb-1 fs-12"><?= __('Tìm kiếm') ?></label>
                            <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($filterSearch) ?>" placeholder="<?= __('Mã hàng, mã bao...') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-1 fs-12"><?= __('Khách hàng') ?></label>
                            <select name="customer_id" class="form-select">
                                <option value=""><?= __('Tất cả') ?></option>
                                <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $filterCustomer == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['fullname']) ?> (<?= $c['customer_code'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label mb-1 fs-12"><?= __('Loại') ?></label>
                            <select name="type" class="form-select">
                                <option value=""><?= __('Tất cả') ?></option>
                                <option value="retail" <?= $filterType === 'retail' ? 'selected' : '' ?>><?= __('Mã bao') ?></option>
                                <option value="wholesale" <?= $filterType === 'wholesale' ? 'selected' : '' ?>><?= __('Mã hàng') ?></option>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label mb-1 fs-12"><?= __('Phân loại') ?></label>
                            <select name="cargo" class="form-select">
                                <option value=""><?= __('Tất cả') ?></option>
                                <option value="easy" <?= $filterCargo === 'easy' ? 'selected' : '' ?>><?= __('Hàng dễ') ?></option>
                                <option value="difficult" <?= $filterCargo === 'difficult' ? 'selected' : '' ?>><?= __('Hàng khó') ?></option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-1 fs-12"><?= __('Cân nặng (kg)') ?></label>
                            <div class="input-group">
                                <input type="text" inputmode="decimal" name="weight_min" class="form-control" value="<?= $filterWeightMin !== null ? $filterWeightMin : '' ?>" placeholder="<?= __('Từ') ?>">
                                <span class="input-group-text">-</span>
                                <input type="text" inputmode="decimal" name="weight_max" class="form-control" value="<?= $filterWeightMax !== null ? $filterWeightMax : '' ?>" placeholder="<?= __('Đến') ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-1 fs-12"><?= __('Số khối (m³)') ?></label>
                            <div class="input-group">
                                <input type="text" inputmode="decimal" name="cbm_min" class="form-control" value="<?= $filterCbmMin !== null ? $filterCbmMin : '' ?>" placeholder="<?= __('Từ') ?>">
                                <span class="input-group-text">-</span>
                                <input type="text" inputmode="decimal" name="cbm_max" class="form-control" value="<?= $filterCbmMax !== null ? $filterCbmMax : '' ?>" placeholder="<?= __('Đến') ?>">
                            </div>
                        </div>
                        <div class="col-md-2 d-flex gap-1">
                            <input type="hidden" name="sort" value="<?= htmlspecialchars($filterSort) ?>">
                            <input type="hidden" name="dir" value="<?= htmlspecialchars($filterSortDir) ?>">
                            <input type="hidden" name="per_page" value="<?= $perPage ?>">
                            <button type="submit" class="btn btn-primary"><i class="ri-filter-3-line me-1"></i><?= __('Lọc') ?></button>
                            <a href="<?= $baseUrl ?>" class="btn btn-outline-secondary"><i class="ri-refresh-line"></i></a>
                        </div>
                    </div>
                    <?php if ($warnRows > 0): ?>
                    <div class="mt-2"><span class="badge bg-warning-subtle text-warning"><i class="ri-alert-line me-1"></i><?= $warnRows ?> <?= __('dòng thiếu dữ liệu cân nặng hoặc số khối') ?></span></div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Pending Packages Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0"><?= $page_title ?> (<?= $totalPendingPkgs ?> <?= __('kiện') ?> / <?= $totalRows ?> <?= __('mã') ?>)</h5>
                        <button type="button" class="btn btn-success" id="btn-export-excel"><i class="ri-file-excel-2-line me-1"></i><?= __('Xuất Excel') ?></button>
                    </div>
                    <div id="selected-summary" class="card-body border-bottom py-2 d-none">
                        <div class="d-flex align-items-center gap-4 flex-wrap">
                            <span class="text-muted"><?= __('Đã chọn') ?>: <strong id="sum-pkgs">0</strong> <?= __('kiện') ?></span>
                            <span><?= __('Tổng cân nặng') ?>: <strong id="sum-weight" class="text-primary">0 kg</strong></span>
                            <span><?= __('Tổng số khối') ?>: <strong id="sum-cbm" class="text-primary">0 m³</strong></span>
                            <span><?= __('Cước theo kg') ?>: <strong id="sum-cost-kg" class="text-danger">0 ₫</strong></span>
                            <span><?= __('Cước theo m³') ?>: <strong id="sum-cost-cbm" class="text-danger">0 ₫</strong></span>
                            <span><?= __('Cước nội địa') ?>: <strong id="sum-domestic" class="text-warning">¥0</strong> <small id="sum-domestic-vnd" class="text-muted"></small></span>
                            <span id="sum-cargo" class="d-none">
                                <span class="badge bg-success-subtle text-success fs-12 px-2 py-1"><i class="ri-truck-line me-1"></i><?= __('Hàng dễ') ?>: <strong id="sum-cargo-easy">0 m³</strong> (<span id="sum-cargo-easy-pct">0</span>%)</span>
                                <span class="badge bg-danger-subtle text-danger fs-12 px-2 py-1"><i class="ri-alarm-warning-line me-1"></i><?= __('Hàng khó') ?>: <strong id="sum-cargo-difficult">0 m³</strong> (<span id="sum-cargo-difficult-pct">0</span>%)</span>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <?php
                                $sortIcon = function($col) use ($filterSort, $filterSortDir) {
                                    if ($filterSort !== $col) return '<i class="ri-arrow-up-down-line text-muted ms-1 fs-12"></i>';
                                    return $filterSortDir === 'asc' ? '<i class="ri-arrow-up-s-fill text-primary ms-1"></i>' : '<i class="ri-arrow-down-s-fill text-primary ms-1"></i>';
                                };
                                $sortUrl = function($col) use ($currentFilters, $baseUrl, $filterSort, $filterSortDir) {
                                    $p = $currentFilters;
                                    $p['sort'] = $col;
                                    $p['dir'] = ($filterSort === $col && $filterSortDir === 'asc') ? 'desc' : 'asc';
                                    $p['page'] = 1;
                                    return buildFilterUrl($p, $baseUrl);
                                };
                                ?>
                                <thead>
                                    <tr>
                                        <th class="align-middle"><input type="checkbox" class="form-check-input" id="check-all"></th>
                                        <th class="align-middle"><?= __('Mã hàng / Mã bao') ?></th>
                                        <th class="align-middle text-center"><?= __('Ảnh') ?></th>
                                        <th class="align-middle"><?= __('Phân loại') ?></th>
                                        <th class="align-middle"><?= __('Khách hàng') ?></th>
                                        <th class="align-middle"><?= __('Cân nặng') ?> / <?= __('Số khối') ?></th>
                                        <th class="align-middle"><?= __('Đơn giá') ?></th>
                                        <th class="align-middle"><a href="<?= $sortUrl('cost') ?>" class="text-dark text-decoration-none"><?= __('Cước vận chuyển') ?><?= $sortIcon('cost') ?></a></th>
                                        <th class="align-middle"><?= __('Cước nội địa') ?> <small class="text-muted">(¥)</small></th>
                                        <th class="align-middle"><?= __('Tổng cước') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($pagedRows)): ?>
                                    <tr><td colspan="10" class="text-center text-muted py-4"><?= __('Không có kiện hàng nào') ?></td></tr>
                                    <?php endif; ?>

                                    <?php foreach ($pagedRows as $row):
                                        $weight = $row['weight'];
                                        $cbm = $row['cbm'];
                                        $shipCost = $row['cost'];
                                        $pkgCount = $row['pkg_count'];
                                        $warnClass = $row['warn'] ? ' table-warning' : '';
                                    ?>
                                    <?php if ($row['type'] === 'bag'):
                                        $bag = $row['data'];
                                        $pkgIds = $bagPkgIdsMap[$bag['bag_id']] ?? [];
                                        $bagCusts = $bagCustomerMap[$bag['bag_id']] ?? [];
                                    ?>
                                    <tr class="<?= $warnClass ?>">
                                        <td class="align-middle"><input type="checkbox" class="form-check-input row-check" data-type="bag" data-bag-id="<?= $bag['bag_id'] ?>" data-pkg-ids="<?= implode(',', $pkgIds) ?>" data-weight="<?= $weight ?>" data-cbm="<?= $cbm ?>" data-cargo="easy" data-pkg-count="<?= $pkgCount ?>" data-shipping-cost="<?= $shipCost['max'] ?>"></td>
                                        <td class="align-middle">
                                            <strong><?= htmlspecialchars($bag['bag_code']) ?></strong>
                                            <?php if ($pkgCount > 0): ?>
                                            <div class="mt-1"><a href="#" class="btn-expand-bag text-muted text-decoration-none" data-bag-id="<?= $bag['bag_id'] ?>"><i class="ri-archive-line"></i> <?= $pkgCount ?> <?= __('kiện') ?> <i class="ri-arrow-down-s-line expand-icon fs-14"></i></a></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle text-center">
                                            <?php if (!empty($bag['bag_images'])):
                                                $bagImgArr = array_filter(array_map('trim', explode(',', $bag['bag_images'])));
                                                $bagImgUrls = array_map('get_upload_url', $bagImgArr); $thumbUrl = $bagImgUrls[0]; $imgCount = count($bagImgArr);
                                            ?>
                                            <a href="#" class="btn-view-images position-relative d-inline-block" data-images="<?= htmlspecialchars(json_encode(array_values($bagImgUrls))) ?>">
                                                <img src="<?= $thumbUrl ?>" class="rounded" style="width:40px;height:40px;object-fit:cover;">
                                                <?php if ($imgCount > 1): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary" style="font-size:10px;"><?= $imgCount ?></span><?php endif; ?>
                                            </a>
                                            <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                                        </td>
                                        <td class="align-middle"><?= display_cargo_type('easy') ?></td>
                                        <td class="align-middle"><?php if (count($bagCusts) == 1): ?><?= htmlspecialchars(array_values($bagCusts)[0]) ?><?php elseif (count($bagCusts) > 1): ?><span class="text-muted"><?= count($bagCusts) ?> <?= __('khách') ?></span><?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                                        <td class="align-middle">
                                            <div><?= $weight > 0 ? fnum($weight, 1) . ' kg' : '<span class="text-warning"><i class="ri-alert-line"></i> 0 kg</span>' ?></div>
                                            <div><?= $cbm > 0 ? fnum($cbm, 2) . ' m&sup3;' : '<span class="text-warning"><i class="ri-alert-line"></i> 0 m&sup3;</span>' ?></div>
                                        </td>
                                        <td class="align-middle" data-item-type="bag" data-item-id="<?= $bag['bag_id'] ?>">
                                            <div class="mb-1 d-flex align-items-center gap-1"><small class="text-muted" style="min-width:22px;">Kg</small><input type="text" inputmode="decimal" class="form-control form-control-sm rate-input rate-kg" data-weight="<?= $weight ?>" data-cbm="<?= $cbm ?>" value="<?= number_format($row['rate_kg'], 0, '', '.') ?>" style="width:85px;"></div>
                                            <div class="d-flex align-items-center gap-1"><small class="text-muted" style="min-width:22px;">M³</small><input type="text" inputmode="decimal" class="form-control form-control-sm rate-input rate-cbm" data-weight="<?= $weight ?>" data-cbm="<?= $cbm ?>" value="<?= number_format($row['rate_cbm'], 0, '', '.') ?>" style="width:85px;"></div>
                                        </td>
                                        <td class="align-middle cost-cell"><?php if ($shipCost['max'] > 0): ?>
                                            <div><small class="text-muted">Kg:</small> <span class="cost-kg <?= $shipCost['by_kg'] >= $shipCost['by_cbm'] ? 'fw-bold text-danger' : 'text-muted' ?>"><?= format_vnd($shipCost['by_kg']) ?></span></div>
                                            <div><small class="text-muted">M³:</small> <span class="cost-cbm <?= $shipCost['by_cbm'] > $shipCost['by_kg'] ? 'fw-bold text-danger' : 'text-muted' ?>"><?= format_vnd($shipCost['by_cbm']) ?></span></div>
                                        <?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                                        <td class="align-middle">
                                            <div class="d-flex align-items-center gap-1"><span class="text-muted">¥</span><input type="text" inputmode="decimal" class="form-control form-control-sm domestic-cost-input" data-item-type="bag" data-item-id="<?= $bag['bag_id'] ?>" value="<?= $row['domestic_cost'] > 0 ? number_format($row['domestic_cost'], 0, '', '.') : '0' ?>" style="width:80px;"></div>
                                            <div class="domestic-vnd-label text-muted" style="font-size:11px;"><?= $row['domestic_cost'] > 0 ? '≈ ' . format_vnd($row['domestic_cost'] * get_exchange_rate()) : '' ?></div>
                                        </td>
                                        <td class="align-middle total-cost-cell"><strong class="text-danger"><?= format_vnd($shipCost['max'] + $row['domestic_cost'] * get_exchange_rate()) ?></strong></td>
                                    </tr>
                                    <?php else:
                                        $order = $row['data'];
                                        $orderCargoType = $order['cargo_type'] ?? 'easy';
                                    ?>
                                    <tr class="<?= $warnClass ?>">
                                        <td class="align-middle"><input type="checkbox" class="form-check-input row-check" data-type="order" data-order-id="<?= $order['id'] ?>" data-weight="<?= $weight ?>" data-cbm="<?= $cbm ?>" data-cargo="<?= htmlspecialchars($orderCargoType) ?>" data-pkg-count="<?= $pkgCount ?>" data-shipping-cost="<?= $shipCost['max'] ?>"></td>
                                        <td class="align-middle">
                                            <?php if ($order['product_code'] ?? ''): ?>
                                            <a href="<?= base_url('admin/orders-detail&id=' . $order['id']) ?>"><strong><?= htmlspecialchars($order['product_code']) ?></strong></a>
                                            <?php else: ?>
                                            <a href="<?= base_url('admin/orders-detail&id=' . $order['id']) ?>" class="text-muted">#<?= $order['id'] ?></a>
                                            <?php endif; ?>
                                            <?php if ($pkgCount > 0): ?>
                                            <div class="mt-1"><a href="#" class="btn-expand-pkgs text-muted text-decoration-none" data-order-id="<?= $order['id'] ?>"><i class="ri-archive-line"></i> <?= $pkgCount ?> <?= __('kiện') ?> <i class="ri-arrow-down-s-line expand-icon fs-14"></i></a></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle text-center">
                                            <?php if (!empty($order['product_image'])):
                                                $orderImgArr = array_filter(array_map('trim', explode(',', $order['product_image'])));
                                                $orderImgUrls = array_map('get_upload_url', $orderImgArr); $thumbUrl = $orderImgUrls[0]; $imgCount = count($orderImgArr);
                                            ?>
                                            <a href="#" class="btn-view-images position-relative d-inline-block" data-images="<?= htmlspecialchars(json_encode(array_values($orderImgUrls))) ?>">
                                                <img src="<?= $thumbUrl ?>" class="rounded" style="width:40px;height:40px;object-fit:cover;">
                                                <?php if ($imgCount > 1): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary" style="font-size:10px;"><?= $imgCount ?></span><?php endif; ?>
                                            </a>
                                            <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                                        </td>
                                        <td class="align-middle"><?= !empty($orderCargoType) ? display_cargo_type($orderCargoType) : '<span class="text-muted">-</span>' ?></td>
                                        <td class="align-middle"><?php if ($order['customer_id']): ?><a href="<?= base_url('admin/customers-detail&id=' . $order['customer_id']) ?>" class="fw-bold"><?= htmlspecialchars($order['customer_name'] ?? '') ?></a><?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                                        <td class="align-middle">
                                            <div><?= $weight > 0 ? fnum($weight, 1) . ' kg' : '<span class="text-warning"><i class="ri-alert-line"></i> 0 kg</span>' ?></div>
                                            <div><?= $cbm > 0 ? fnum($cbm, 2) . ' m&sup3;' : '<span class="text-warning"><i class="ri-alert-line"></i> 0 m&sup3;</span>' ?></div>
                                        </td>
                                        <td class="align-middle" data-item-type="order" data-item-id="<?= $order['id'] ?>">
                                            <div class="mb-1 d-flex align-items-center gap-1"><small class="text-muted" style="min-width:22px;">Kg</small><input type="text" inputmode="decimal" class="form-control form-control-sm rate-input rate-kg" data-weight="<?= $weight ?>" data-cbm="<?= $cbm ?>" value="<?= number_format($row['rate_kg'], 0, '', '.') ?>" style="width:85px;"></div>
                                            <div class="d-flex align-items-center gap-1"><small class="text-muted" style="min-width:22px;">M³</small><input type="text" inputmode="decimal" class="form-control form-control-sm rate-input rate-cbm" data-weight="<?= $weight ?>" data-cbm="<?= $cbm ?>" value="<?= number_format($row['rate_cbm'], 0, '', '.') ?>" style="width:85px;"></div>
                                        </td>
                                        <td class="align-middle cost-cell"><?php if ($shipCost['max'] > 0): ?>
                                            <div><small class="text-muted">Kg:</small> <span class="cost-kg <?= $shipCost['by_kg'] >= $shipCost['by_cbm'] ? 'fw-bold text-danger' : 'text-muted' ?>"><?= format_vnd($shipCost['by_kg']) ?></span></div>
                                            <div><small class="text-muted">M³:</small> <span class="cost-cbm <?= $shipCost['by_cbm'] > $shipCost['by_kg'] ? 'fw-bold text-danger' : 'text-muted' ?>"><?= format_vnd($shipCost['by_cbm']) ?></span></div>
                                        <?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                                        <td class="align-middle">
                                            <div class="d-flex align-items-center gap-1"><span class="text-muted">¥</span><input type="text" inputmode="decimal" class="form-control form-control-sm domestic-cost-input" data-item-type="order" data-item-id="<?= $order['id'] ?>" value="<?= $row['domestic_cost'] > 0 ? number_format($row['domestic_cost'], 0, '', '.') : '0' ?>" style="width:80px;"></div>
                                            <div class="domestic-vnd-label text-muted" style="font-size:11px;"><?= $row['domestic_cost'] > 0 ? '≈ ' . format_vnd($row['domestic_cost'] * get_exchange_rate()) : '' ?></div>
                                        </td>
                                        <td class="align-middle total-cost-cell"><strong class="text-danger"><?= format_vnd($shipCost['max'] + $row['domestic_cost'] * get_exchange_rate()) ?></strong></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalPages > 1 || $totalRows > 20): ?>
                        <div class="d-flex align-items-center justify-content-between mt-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-muted fs-12"><?= __('Hiển thị') ?></span>
                                <select class="form-select" style="width:auto;" onchange="location.href='<?= buildFilterUrl(array_merge($currentFilters, ['page' => 1, 'per_page' => '']), $baseUrl) ?>&per_page='+this.value">
                                    <?php foreach ([20, 50, 100, 200] as $pp): ?>
                                    <option value="<?= $pp ?>" <?= $perPage == $pp ? 'selected' : '' ?>><?= $pp ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="text-muted fs-12"><?= __('dòng / trang') ?></span>
                                <span class="text-muted fs-12 ms-2">(<?= (($currentPage - 1) * $perPage + 1) ?>-<?= min($currentPage * $perPage, $totalRows) ?> / <?= $totalRows ?>)</span>
                            </div>
                            <?php if ($totalPages > 1): ?>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= buildFilterUrl(array_merge($currentFilters, ['page' => $currentPage - 1]), $baseUrl) ?>">&laquo;</a>
                                    </li>
                                    <?php
                                    $startP = max(1, $currentPage - 2);
                                    $endP = min($totalPages, $currentPage + 2);
                                    if ($startP > 1): ?><li class="page-item"><a class="page-link" href="<?= buildFilterUrl(array_merge($currentFilters, ['page' => 1]), $baseUrl) ?>">1</a></li><?php if ($startP > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; endif;
                                    for ($p = $startP; $p <= $endP; $p++): ?>
                                    <li class="page-item <?= $p == $currentPage ? 'active' : '' ?>"><a class="page-link" href="<?= buildFilterUrl(array_merge($currentFilters, ['page' => $p]), $baseUrl) ?>"><?= $p ?></a></li>
                                    <?php endfor;
                                    if ($endP < $totalPages): ?><?php if ($endP < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?><li class="page-item"><a class="page-link" href="<?= buildFilterUrl(array_merge($currentFilters, ['page' => $totalPages]), $baseUrl) ?>"><?= $totalPages ?></a></li><?php endif; ?>
                                    <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= buildFilterUrl(array_merge($currentFilters, ['page' => $currentPage + 1]), $baseUrl) ?>">&raquo;</a>
                                    </li>
                                </ul>
                            </nav>
                            <?php endif; ?>
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
    var calcAjaxUrl = '<?= base_url('ajaxs/admin/shipping-calculator.php') ?>';
    var expandedOrders = {};
    var shippingRates = <?= json_encode($shippingRates) ?>;
    var exchangeRate = <?= get_exchange_rate() ?>;

    function calcShipCostJS(weight, cbm, method, cargoType) {
        method = method || 'road';
        cargoType = cargoType || 'easy';
        var r = (shippingRates[method] && shippingRates[method][cargoType]) ? shippingRates[method][cargoType] : shippingRates['road']['easy'];
        var byKg = weight * r.per_kg;
        var byCbm = cbm * r.per_cbm;
        return { by_kg: byKg, by_cbm: byCbm, max: Math.max(byKg, byCbm) };
    }

    function renderShipCostCell(cost) {
        if (cost.max <= 0) return '-';
        var kgClass = cost.by_kg >= cost.by_cbm ? 'fw-bold text-danger' : 'text-muted';
        var cbmClass = cost.by_cbm > cost.by_kg ? 'fw-bold text-danger' : 'text-muted';
        return '<div><small class="text-muted">Kg:</small> <span class="cost-kg ' + kgClass + '">' + formatVnd(cost.by_kg) + '</span></div>'
             + '<div><small class="text-muted">M³:</small> <span class="cost-cbm ' + cbmClass + '">' + formatVnd(cost.by_cbm) + '</span></div>';
    }

    function parseRate(val) {
        return parseFloat(String(val).replace(/\./g, '').replace(',', '.')) || 0;
    }

    function updateRowTotalCost($tr) {
        var rateKg = parseRate($tr.find('.rate-kg').val());
        var rateCbm = parseRate($tr.find('.rate-cbm').val());
        var weight = parseFloat($tr.find('.rate-kg').data('weight')) || 0;
        var cbm = parseFloat($tr.find('.rate-kg').data('cbm')) || 0;
        var byKg = weight * rateKg;
        var byCbm = cbm * rateCbm;
        var shipCost = Math.max(byKg, byCbm);
        var domesticCny = parseRate($tr.find('.domestic-cost-input').val());
        var domesticVnd = domesticCny * exchangeRate;
        var total = shipCost + domesticVnd;
        $tr.find('.total-cost-cell').html('<strong class="text-danger">' + formatVnd(total) + '</strong>');
        $tr.find('.domestic-vnd-label').text(domesticCny > 0 ? '≈ ' + formatVnd(domesticVnd) : '');
        // Update cost-cell
        var $cell = $tr.find('.cost-cell');
        if (byKg > 0 || byCbm > 0) {
            var kgClass = byKg >= byCbm ? 'fw-bold text-danger' : 'text-muted';
            var cbmClass = byCbm > byKg ? 'fw-bold text-danger' : 'text-muted';
            $cell.html(
                '<div><small class="text-muted">Kg:</small> <span class="cost-kg ' + kgClass + '">' + formatVnd(byKg) + '</span></div>'
              + '<div><small class="text-muted">M³:</small> <span class="cost-cbm ' + cbmClass + '">' + formatVnd(byCbm) + '</span></div>'
            );
        }
    }

    // Recalculate cost when rate inputs change + auto-save
    var rateTimers = {};
    $(document).on('input', '.rate-input', function(){
        var $input = $(this);
        var $tr = $input.closest('tr');
        $input.addClass('border-warning');
        updateRowTotalCost($tr);
        updateSelectedSummary();

        var $td = $input.closest('td');
        var itemType = $td.data('item-type');
        var itemId = $td.data('item-id');
        if (!itemType || !itemId) return;
        var key = itemType + '-' + itemId;
        if (rateTimers[key]) clearTimeout(rateTimers[key]);
        rateTimers[key] = setTimeout(function(){
            $.post(calcAjaxUrl, {
                request_name: 'save_rates',
                item_type: itemType,
                item_id: itemId,
                rate_kg: $td.find('.rate-kg').val(),
                rate_cbm: $td.find('.rate-cbm').val(),
                csrf_token: csrfToken
            }, function(res){
                if (res.status === 'success') {
                    $td.find('.rate-input').removeClass('border-warning').addClass('border-success');
                    setTimeout(function(){ $td.find('.rate-input').removeClass('border-success'); }, 1500);
                    Swal.fire({icon: 'success', text: '<?= __('Đã cập nhật đơn giá') ?>', toast: true, position: 'top-end', timer: 1500, showConfirmButton: false, customClass: {popup: 'fs-14'}});
                }
            }, 'json');
        }, 600);
    });

    var domesticTimers = {};
    $(document).on('input', '.domestic-cost-input', function(){
        var $input = $(this);
        $input.addClass('border-warning');
        updateRowTotalCost($input.closest('tr'));
        updateSelectedSummary();

        var itemType = $input.data('item-type');
        var itemId = $input.data('item-id');
        var key = itemType + '-' + itemId;
        if (domesticTimers[key]) clearTimeout(domesticTimers[key]);
        domesticTimers[key] = setTimeout(function(){
            $.post(calcAjaxUrl, {
                request_name: 'save_domestic_cost',
                item_type: itemType,
                item_id: itemId,
                cost: $input.val(),
                csrf_token: csrfToken
            }, function(res){
                if (res.status === 'success') {
                    $input.removeClass('border-warning').addClass('border-success');
                    setTimeout(function(){ $input.removeClass('border-success'); }, 1500);
                    Swal.fire({icon: 'success', text: '<?= __('Đã cập nhật cước nội địa') ?>', toast: true, position: 'top-end', timer: 1500, showConfirmButton: false, customClass: {popup: 'fs-14'}});
                }
            }, 'json');
        }, 600);
    });

    function formatVnd(val) {
        var n = Math.round(val);
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ' ₫';
    }

    // ===== Summary =====
    function updateSelectedSummary(){
        var pkgCount = 0, totalWeight = 0, totalCbm = 0, cbmEasy = 0, cbmDifficult = 0, totalCostKg = 0, totalCostCbm = 0, totalDomestic = 0;

        function getRowRates($tr) {
            return {
                per_kg: parseRate($tr.find('.rate-kg').val()),
                per_cbm: parseRate($tr.find('.rate-cbm').val())
            };
        }

        // Bag rows - only count if checked
        $('.row-check[data-type="bag"]:checked').each(function(){
            var $tr = $(this).closest('tr');
            var rates = getRowRates($tr);
            pkgCount += parseInt($(this).data('pkg-count')) || 0;
            totalWeight += parseFloat($(this).data('weight')) || 0;
            var c = parseFloat($(this).data('cbm')) || 0;
            totalCbm += c;
            var w = parseFloat($(this).data('weight')) || 0;
            var cargo = $(this).data('cargo') || 'easy';
            totalCostKg += w * rates.per_kg;
            totalCostCbm += c * rates.per_cbm;
            totalDomestic += parseRate($tr.find('.domestic-cost-input').val());
            if (cargo === 'easy') cbmEasy += c;
            else if (cargo === 'difficult') cbmDifficult += c;
        });

        // Order rows - iterate ALL to handle expanded partial selection
        $('.row-check[data-type="order"]').each(function(){
            var $tr = $(this).closest('tr');
            var rates = getRowRates($tr);
            var orderId = $(this).data('order-id');
            var cargo = $(this).data('cargo');
            if (expandedOrders[orderId]) {
                // Expanded: count checked sub-packages
                var hasCheckedSub = false;
                $('#pkg-row-' + orderId + ' .sub-pkg-check:checked').each(function(){
                    hasCheckedSub = true;
                    pkgCount++;
                    var w = parseFloat($(this).data('weight')) || 0;
                    var c = parseFloat($(this).data('cbm')) || 0;
                    totalWeight += w;
                    totalCbm += c;
                    totalCostKg += w * rates.per_kg;
                    totalCostCbm += c * rates.per_cbm;
                    if (cargo === 'easy') cbmEasy += c;
                    else if (cargo === 'difficult') cbmDifficult += c;
                });
                if (hasCheckedSub) totalDomestic += parseRate($tr.find('.domestic-cost-input').val());
            } else if ($(this).is(':checked')) {
                // Not expanded: use aggregate from row data
                pkgCount += parseInt($(this).data('pkg-count')) || 0;
                var w = parseFloat($(this).data('weight')) || 0;
                var c = parseFloat($(this).data('cbm')) || 0;
                totalWeight += w;
                totalCbm += c;
                totalCostKg += w * rates.per_kg;
                totalCostCbm += c * rates.per_cbm;
                totalDomestic += parseRate($tr.find('.domestic-cost-input').val());
                if (cargo === 'easy') cbmEasy += c;
                else if (cargo === 'difficult') cbmDifficult += c;
            }
        });

        if (pkgCount > 0) {
            $('#selected-summary').removeClass('d-none');
            $('#sum-pkgs').text(pkgCount);
            $('#sum-weight').text(fnum(totalWeight, 1) + ' kg');
            $('#sum-cbm').text(fnum(totalCbm, 2) + ' m³');
            $('#sum-cost-kg').text(formatVnd(totalCostKg));
            $('#sum-cost-cbm').text(formatVnd(totalCostCbm));
            $('#sum-domestic').text('¥' + fnum(totalDomestic, 0));
            $('#sum-domestic-vnd').text(totalDomestic > 0 ? '≈ ' + formatVnd(totalDomestic * exchangeRate) : '');
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

        var $newRow = $('<tr class="pkg-expand-row" id="pkg-row-' + orderId + '" data-order-id="' + orderId + '"><td colspan="' + colCount + '" class="p-0"><div class="py-2 bg-light" style="padding-left:48px;padding-right:12px;"><div class="text-center text-muted py-2"><i class="ri-loader-4-line ri-spin"></i> <?= __('Đang tải...') ?></div></div></td></tr>');
        $orderRow.after($newRow);
        var orderChecked = $('.row-check[data-order-id="' + orderId + '"]').is(':checked');

        var orderCargo = $('.row-check[data-order-id="' + orderId + '"]').data('cargo') || 'easy';
        var orderMethod = $('.row-check[data-order-id="' + orderId + '"]').data('ship-method') || 'road';

        $.post(pkgAjaxUrl, { request_name: 'get_order_packages', order_id: orderId, csrf_token: csrfToken }, function(res){
            if (res.status === 'success') {
                var statusLabels = { 'cn_warehouse': '<?= __('Đã về kho Trung Quốc') ?>', 'packed': '<?= __('Đã đóng bao') ?>', 'loading': '<?= __('Đang xếp xe') ?>', 'shipping': '<?= __('Đang vận chuyển') ?>' };
                var pendingPkgs = res.packages.filter(function(p){ return p.status === 'cn_warehouse'; });

                var html = '<table class="table table-sm table-borderless mb-0 text-start"><thead><tr>';
                html += '<th style="width:30px;"><input type="checkbox" class="form-check-input sub-pkg-check-all" data-order-id="' + orderId + '"' + (orderChecked ? ' checked' : '') + '></th>';
                html += '<th><?= __('Kiện') ?></th><th><?= __('Cân nặng / Số khối') ?></th><th><?= __('Kích thước') ?></th><th><?= __('Cước') ?></th><th><?= __('Trạng thái') ?></th>';
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
                        var idx = pkgIndexMap[first.id];
                        var pkgCost = calcShipCostJS(parseFloat(first.weight_actual) || 0, parseFloat(first.cbm) || 0, orderMethod, orderCargo);
                        html += '<tr>';
                        html += '<td><input type="checkbox" class="form-check-input sub-pkg-check" value="' + first.id + '" data-weight="' + first.weight_actual + '" data-cbm="' + first.cbm + '"' + (orderChecked ? ' checked' : '') + '></td>';
                        html += '<td><strong><?= __('Kiện') ?> ' + idx + '</strong></td>';
                        html += '<td>' + (first.weight_actual > 0 ? fnum(first.weight_actual, 2) + ' kg' : '-') + '<br>' + (first.cbm > 0 ? fnum(first.cbm, 2) + ' m³' : '-') + '</td>';
                        html += '<td>' + dim + '</td>';
                        html += '<td>' + renderShipCostCell(pkgCost) + '</td>';
                        html += '<td>' + (statusLabels[first.status] || first.status) + '</td>';
                        html += '</tr>';
                    } else {
                        // Grouped packages - collapsible with qty input
                        var groupId = 'grp-' + orderId + '-' + group.key.replace(/[|.]/g, '_');
                        var totalW = first.weight_actual * pkgs.length;
                        var totalC = first.cbm * pkgs.length;
                        var unitCost = calcShipCostJS(parseFloat(first.weight_actual) || 0, parseFloat(first.cbm) || 0, orderMethod, orderCargo);
                        var firstIdx = pkgIndexMap[pkgs[0].id];
                        var lastIdx = pkgIndexMap[pkgs[pkgs.length - 1].id];

                        html += '<tr class="pkg-group-row" data-group-id="' + groupId + '">';
                        html += '<td><input type="checkbox" class="form-check-input sub-pkg-group-check" data-group-id="' + groupId + '" data-total="' + pkgs.length + '"' + (orderChecked ? ' checked' : '') + '></td>';
                        html += '<td><a href="#" class="btn-expand-group text-decoration-none" data-group-id="' + groupId + '"><strong><?= __('Kiện') ?> ' + firstIdx + ' ~ ' + lastIdx + '</strong> <span class="badge bg-primary-subtle text-primary">' + pkgs.length + ' <?= __('kiện') ?></span> <i class="ri-arrow-down-s-line grp-icon"></i></a></td>';
                        var totalCost = calcShipCostJS(totalW, totalC, orderMethod, orderCargo);
                        html += '<td>' + (totalW > 0 ? fnum(totalW, 2) + ' kg' : '-') + '<br>' + (totalC > 0 ? fnum(totalC, 2) + ' m³' : '-') + '</td>';
                        html += '<td>' + dim + '</td>';
                        html += '<td>' + renderShipCostCell(totalCost) + '</td>';
                        html += '<td>' + (statusLabels[first.status] || first.status) + '</td>';
                        html += '</tr>';

                        // Hidden individual rows
                        pkgs.forEach(function(pkg){
                            var idx = pkgIndexMap[pkg.id];
                            var pCost = calcShipCostJS(parseFloat(pkg.weight_actual) || 0, parseFloat(pkg.cbm) || 0, orderMethod, orderCargo);
                            html += '<tr class="pkg-group-detail d-none" data-group-id="' + groupId + '">';
                            html += '<td class="ps-4"><input type="checkbox" class="form-check-input sub-pkg-check" value="' + pkg.id + '" data-weight="' + pkg.weight_actual + '" data-cbm="' + pkg.cbm + '" data-group-id="' + groupId + '"' + (orderChecked ? ' checked' : '') + '></td>';
                            html += '<td class="ps-4"><?= __('Kiện') ?> ' + idx + '</td>';
                            html += '<td>' + (pkg.weight_actual > 0 ? fnum(pkg.weight_actual, 2) + ' kg' : '-') + '<br>' + (pkg.cbm > 0 ? fnum(pkg.cbm, 2) + ' m³' : '-') + '</td>';
                            html += '<td>' + dim + '</td>';
                            html += '<td>' + renderShipCostCell(pCost) + '</td>';
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

    // ===== Expand packages (bag rows) =====
    var expandedBags = {};
    $(document).on('click', '.btn-expand-bag', function(e){
        e.preventDefault();
        var bagId = $(this).data('bag-id');
        var $bagRow = $(this).closest('tr');
        var $expandRow = $('#pkg-bag-row-' + bagId);
        var $icon = $(this).find('.expand-icon');

        if ($expandRow.length && $expandRow.is(':visible')) {
            $expandRow.hide();
            $icon.removeClass('ri-arrow-up-s-line').addClass('ri-arrow-down-s-line');
            return;
        }
        $icon.removeClass('ri-arrow-down-s-line').addClass('ri-arrow-up-s-line');
        if ($expandRow.length) { $expandRow.show(); return; }

        var $newRow = $('<tr class="pkg-expand-row" id="pkg-bag-row-' + bagId + '"><td colspan="' + colCount + '" class="p-0"><div class="py-2 bg-light" style="padding-left:48px;padding-right:12px;"><div class="text-center text-muted py-2"><i class="ri-loader-4-line ri-spin"></i> <?= __('Đang tải...') ?></div></div></td></tr>');
        $bagRow.after($newRow);

        $.post(pkgAjaxUrl, { request_name: 'get_bag_packages', bag_id: bagId, csrf_token: csrfToken }, function(res){
            if (res.status === 'success') {
                var html = '<table class="table table-sm table-borderless mb-0 text-start"><thead><tr>';
                html += '<th><?= __('Kiện') ?></th><th><?= __('Cân nặng / Số khối') ?></th><th><?= __('Kích thước') ?></th><th><?= __('Trạng thái') ?></th>';
                html += '</tr></thead><tbody>';

                var statusLabels = { 'cn_warehouse': '<?= __('Đã về kho Trung Quốc') ?>', 'packed': '<?= __('Đã đóng bao') ?>', 'loading': '<?= __('Đang xếp xe') ?>', 'shipping': '<?= __('Đang vận chuyển') ?>' };

                res.packages.forEach(function(pkg, i){
                    var dim = (pkg.length_cm > 0 || pkg.width_cm > 0 || pkg.height_cm > 0)
                        ? parseFloat(pkg.length_cm) + '×' + parseFloat(pkg.width_cm) + '×' + parseFloat(pkg.height_cm) : '-';
                    html += '<tr>';
                    html += '<td><strong><?= __('Kiện') ?> ' + (i + 1) + '</strong></td>';
                    html += '<td>' + (pkg.weight_actual > 0 ? fnum(pkg.weight_actual, 2) + ' kg' : '-') + '<br>' + (pkg.cbm > 0 ? fnum(pkg.cbm, 2) + ' m³' : '-') + '</td>';
                    html += '<td>' + dim + '</td>';
                    html += '<td>' + (statusLabels[pkg.status] || pkg.status) + '</td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';
                $newRow.find('.bg-light > div').html(html);
                expandedBags[bagId] = true;
            }
        }, 'json');
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

    // ===== Export Excel =====
    var exportData = [
        <?php foreach ($allRows as $r):
            $d = $r['data'];
            if ($r['type'] === 'bag'):
                $bCusts = $bagCustomerMap[$d['bag_id']] ?? [];
                $custName = count($bCusts) == 1 ? array_values($bCusts)[0] : (count($bCusts) > 1 ? count($bCusts) . ' khách' : '');
        ?>
        {code: <?= json_encode($d['bag_code']) ?>, type: '<?= __('Mã bao') ?>', cargo: '<?= __('Hàng dễ') ?>', customer: <?= json_encode($custName) ?>, pkgs: <?= $r['pkg_count'] ?>, weight: <?= round($r['weight'], 2) ?>, cbm: <?= round($r['cbm'], 4) ?>, status: '<?= __('Đã đóng bao') ?>'},
        <?php else:
                $cargoLabel = ($r['cargo'] === 'difficult') ? __('Hàng khó') : __('Hàng dễ');
        ?>
        {code: <?= json_encode($d['product_code'] ?? '#' . $d['id']) ?>, type: '<?= __('Mã hàng') ?>', cargo: <?= json_encode($cargoLabel) ?>, customer: <?= json_encode($d['customer_name'] ?? '') ?>, pkgs: <?= $r['pkg_count'] ?>, weight: <?= round($r['weight'], 2) ?>, cbm: <?= round($r['cbm'], 4) ?>, status: '<?= __('Đã về kho TQ') ?>'},
        <?php endif; endforeach; ?>
    ];

    $('#btn-export-excel').on('click', function(){
        var rows = [];
        rows.push(['<?= __('Mã hàng / Mã bao') ?>', '<?= __('Loại') ?>', '<?= __('Phân loại') ?>', '<?= __('Khách hàng') ?>', '<?= __('Cân nặng (kg)') ?>', '<?= __('Số khối (m³)') ?>', '<?= __('Đơn giá / kg') ?>', '<?= __('Đơn giá / m³') ?>', '<?= __('Cước theo kg (₫)') ?>', '<?= __('Cước theo m³ (₫)') ?>', '<?= __('Cước nội địa (¥)') ?>', '<?= __('Cước nội địa (₫)') ?>', '<?= __('Tổng cước (₫)') ?>']);

        // Read rates from DOM inputs (captures manual edits)
        var $tableRows = $('table.table-hover > tbody > tr:not(.pkg-expand-row)');
        $tableRows.each(function(i){
            if (i >= exportData.length) return;
            var d = exportData[i];
            var rateKg = parseRate($(this).find('.rate-kg').val());
            var rateCbm = parseRate($(this).find('.rate-cbm').val());
            var costKg = Math.round(d.weight * rateKg);
            var costCbm = Math.round(d.cbm * rateCbm);
            var domesticCny = parseRate($(this).find('.domestic-cost-input').val());
            var domesticVnd = Math.round(domesticCny * exchangeRate);
            var totalCost = Math.max(costKg, costCbm) + domesticVnd;
            rows.push([d.code, d.type, d.cargo, d.customer, d.weight, d.cbm, rateKg, rateCbm, costKg, costCbm, domesticCny, domesticVnd, totalCost]);
        });

        function xlsEsc(v) {
            if (v === null || v === undefined) return '';
            return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
        var xml = '<?xml version="1.0" encoding="UTF-8"?>\n'
            + '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">\n'
            + '<Styles><Style ss:ID="H"><Font ss:Bold="1"/></Style></Styles>\n'
            + '<Worksheet ss:Name="Sheet1"><Table>\n';
        rows.forEach(function(row, ri){
            xml += '<Row>';
            row.forEach(function(cell){
                var t = (typeof cell === 'number') ? 'Number' : 'String';
                var s = (ri === 0) ? ' ss:StyleID="H"' : '';
                xml += '<Cell' + s + '><Data ss:Type="' + t + '">' + xlsEsc(cell) + '</Data></Cell>';
            });
            xml += '</Row>\n';
        });
        xml += '</Table></Worksheet></Workbook>';

        var blob = new Blob([xml], { type: 'application/vnd.ms-excel;charset=utf-8;' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = '<?= __('tich-cuoc-don-hang') ?>_' + new Date().toISOString().slice(0,10) + '.xls';
        a.click();
        URL.revokeObjectURL(url);
    });

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
