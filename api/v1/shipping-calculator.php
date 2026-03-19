<?php
require_once(__DIR__.'/bootstrap.php');
require_once(__DIR__.'/jwt.php');

$user = api_auth();
api_method('GET');

// Get shipping rates from settings
$shippingRates = [
    'easy' => [
        'per_kg' => floatval($ToryHub->site('shipping_road_easy_per_kg') ?: 25000),
        'per_cbm' => floatval($ToryHub->site('shipping_road_easy_per_cbm') ?: 6000000),
    ],
    'difficult' => [
        'per_kg' => floatval($ToryHub->site('shipping_road_difficult_per_kg') ?: 35000),
        'per_cbm' => floatval($ToryHub->site('shipping_road_difficult_per_cbm') ?: 8000000),
    ],
];
$exchangeRate = floatval($ToryHub->site('exchange_rate_cny_vnd') ?: 3500);

// Pagination
$pg = api_pagination();
$search = $_GET['search'] ?? '';
$filterType = $_GET['type'] ?? ''; // 'bag' or 'wholesale' or ''
$filterCargo = $_GET['cargo'] ?? ''; // 'easy' or 'difficult' or ''

// Get sealed bags (retail)
$sealedBags = [];
if ($filterType !== 'wholesale' && $filterCargo !== 'difficult') {
    $bagWhere = "b.status IN ('sealed','loading','shipping','arrived')";
    $bagParams = [];
    if ($search) { $bagWhere .= " AND b.bag_code LIKE ?"; $bagParams[] = "%$search%"; }

    $sealedBags = $ToryHub->get_list_safe(
        "SELECT b.id as bag_id, b.bag_code, b.status as bag_status,
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
        ORDER BY b.create_date DESC", $bagParams
    );

    // Get customer info per bag
    if (!empty($sealedBags)) {
        $bagIds = array_column($sealedBags, 'bag_id');
        $ph = implode(',', array_fill(0, count($bagIds), '?'));
        $bagCusts = $ToryHub->get_list_safe(
            "SELECT DISTINCT bp.bag_id, c.fullname
             FROM `bag_packages` bp JOIN `packages` p ON bp.package_id = p.id
             JOIN `package_orders` po ON p.id = po.package_id
             JOIN `orders` o ON po.order_id = o.id
             LEFT JOIN `customers` c ON o.customer_id = c.id
             WHERE bp.bag_id IN ($ph) AND c.id IS NOT NULL", $bagIds
        );
        $bagCustMap = [];
        foreach ($bagCusts as $bc) $bagCustMap[$bc['bag_id']][] = $bc['fullname'];
        foreach ($sealedBags as &$bag) {
            $custs = array_unique($bagCustMap[$bag['bag_id']] ?? []);
            $bag['customer_name'] = count($custs) == 1 ? $custs[0] : (count($custs) > 1 ? count($custs) . ' khách' : '-');
        }
        unset($bag);
    }
}

// Get wholesale orders
$wholesaleOrders = [];
if ($filterType !== 'bag') {
    $orderWhere = "o.product_type = 'wholesale'";
    $orderParams = [];
    if ($search) { $orderWhere .= " AND (o.product_code LIKE ? OR o.order_code LIKE ?)"; $s = "%$search%"; $orderParams = [$s, $s]; }
    if ($filterCargo) { $orderWhere .= " AND o.cargo_type = ?"; $orderParams[] = $filterCargo; }

    $wholesaleOrders = $ToryHub->get_list_safe(
        "SELECT o.id, o.product_code, o.cargo_type, o.customer_id, o.status as order_status,
            o.weight_actual as order_weight_actual, o.weight_charged as order_weight_charged, o.volume_actual,
            c.fullname as customer_name,
            COUNT(p.id) as pkg_count,
            SUM(COALESCE(p.weight_charged, 0)) as pkg_weight_charged,
            SUM(COALESCE(p.weight_actual, 0)) as pkg_weight_actual,
            SUM(p.length_cm * p.width_cm * p.height_cm / 1000000) as total_cbm,
            COALESCE(o.domestic_cost, 0) as domestic_cost,
            o.custom_rate_kg, o.custom_rate_cbm,
            o.exchange_rate as order_exchange_rate,
            o.create_date
        FROM `orders` o
        LEFT JOIN `customers` c ON o.customer_id = c.id
        LEFT JOIN `package_orders` po ON o.id = po.order_id
        LEFT JOIN `packages` p ON po.package_id = p.id
        WHERE $orderWhere
        GROUP BY o.id
        ORDER BY o.create_date DESC", $orderParams
    );
}

// Build unified rows with calculated costs
$allRows = [];

foreach ($sealedBags as $bag) {
    $bagW = floatval($bag['bag_weight'] ?? 0);
    $pkgWC = floatval($bag['pkg_weight_charged'] ?? 0);
    $pkgWA = floatval($bag['pkg_weight_actual'] ?? 0);
    $w = $bagW > 0 ? $bagW : ($pkgWC > 0 ? $pkgWC : $pkgWA);
    $bc = floatval($bag['bag_cbm'] ?? 0);
    $pc = floatval($bag['pkg_cbm'] ?? 0);
    $c = $bc > 0 ? $bc : $pc;
    $rate = $shippingRates['easy'];
    $rkg = $bag['custom_rate_kg'] !== null ? floatval($bag['custom_rate_kg']) : $rate['per_kg'];
    $rcbm = $bag['custom_rate_cbm'] !== null ? floatval($bag['custom_rate_cbm']) : $rate['per_cbm'];
    $costKg = $w * $rkg;
    $costCbm = $c * $rcbm;
    $domesticVnd = floatval($bag['domestic_cost'] ?? 0) * $exchangeRate;

    $allRows[] = [
        'type' => 'bag',
        'code' => $bag['bag_code'],
        'cargo_type' => 'easy',
        'customer_name' => $bag['customer_name'] ?? '-',
        'pkg_count' => intval($bag['pkg_count']),
        'weight' => round($w, 2),
        'cbm' => round($c, 4),
        'rate_per_kg' => $rkg,
        'rate_per_cbm' => $rcbm,
        'cost_by_kg' => round($costKg),
        'cost_by_cbm' => round($costCbm),
        'domestic_cost' => round($domesticVnd),
        'total_cost' => round(max($costKg, $costCbm) + $domesticVnd),
        'method' => $costCbm > $costKg ? 'CBM' : 'KG',
        'status' => $bag['bag_status'] ?? 'sealed',
        'create_date' => $bag['create_date'],
    ];
}

foreach ($wholesaleOrders as $order) {
    $wC = floatval($order['order_weight_charged'] ?? 0);
    $wA = floatval($order['order_weight_actual'] ?? 0);
    $pkgWC = floatval($order['pkg_weight_charged'] ?? 0);
    $pkgWA = floatval($order['pkg_weight_actual'] ?? 0);
    $w = $pkgWA > 0 ? $pkgWA : ($wA > 0 ? $wA : ($pkgWC > 0 ? $pkgWC : $wC));
    $c = floatval($order['total_cbm'] ?? 0);
    if (floatval($order['volume_actual'] ?? 0) > 0) $c = floatval($order['volume_actual']);
    $cargo = $order['cargo_type'] ?? 'easy';
    $rate = $shippingRates[$cargo] ?? $shippingRates['easy'];
    $rkg = $order['custom_rate_kg'] !== null ? floatval($order['custom_rate_kg']) : $rate['per_kg'];
    $rcbm = $order['custom_rate_cbm'] !== null ? floatval($order['custom_rate_cbm']) : $rate['per_cbm'];
    $costKg = $w * $rkg;
    $costCbm = $c * $rcbm;
    $orderRate = floatval($order['order_exchange_rate'] ?? 0) ?: $exchangeRate;
    $domesticVnd = floatval($order['domestic_cost'] ?? 0) * $orderRate;

    $allRows[] = [
        'type' => 'order',
        'code' => $order['product_code'] ?: '#' . $order['id'],
        'cargo_type' => $cargo,
        'customer_name' => $order['customer_name'] ?? '-',
        'pkg_count' => intval($order['pkg_count']),
        'weight' => round($w, 2),
        'cbm' => round($c, 4),
        'rate_per_kg' => $rkg,
        'rate_per_cbm' => $rcbm,
        'cost_by_kg' => round($costKg),
        'cost_by_cbm' => round($costCbm),
        'domestic_cost' => round($domesticVnd),
        'total_cost' => round(max($costKg, $costCbm) + $domesticVnd),
        'method' => $costCbm > $costKg ? 'CBM' : 'KG',
        'status' => $order['order_status'] ?? '',
        'create_date' => $order['create_date'],
    ];
}

// Grand totals
$grandPkgs = 0; $grandWeight = 0; $grandCbm = 0; $grandCost = 0;
foreach ($allRows as $row) {
    $grandPkgs += $row['pkg_count'];
    $grandWeight += $row['weight'];
    $grandCbm += $row['cbm'];
    $grandCost += $row['total_cost'];
}

// Paginate
$totalRows = count($allRows);
$pagedRows = array_slice($allRows, $pg['offset'], $pg['per_page']);

api_success([
    'items' => $pagedRows,
    'total' => $totalRows,
    'page' => $pg['page'],
    'per_page' => $pg['per_page'],
    'summary' => [
        'total_pkgs' => $grandPkgs,
        'total_weight' => round($grandWeight, 2),
        'total_cbm' => round($grandCbm, 4),
        'total_cost' => round($grandCost),
    ],
    'rates' => $shippingRates,
    'exchange_rate' => $exchangeRate,
]);
