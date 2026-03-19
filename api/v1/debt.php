<?php
require_once(__DIR__.'/bootstrap.php');
require_once(__DIR__.'/jwt.php');

$user = api_auth();
api_method('GET');

$shippingRates = [
    'easy' => ['per_kg' => floatval($ToryHub->site('shipping_road_easy_per_kg') ?: 25000), 'per_cbm' => floatval($ToryHub->site('shipping_road_easy_per_cbm') ?: 6000000)],
    'difficult' => ['per_kg' => floatval($ToryHub->site('shipping_road_difficult_per_kg') ?: 35000), 'per_cbm' => floatval($ToryHub->site('shipping_road_difficult_per_cbm') ?: 8000000)],
];
$exchangeRate = floatval($ToryHub->site('exchange_rate_cny_vnd') ?: 3500);

// Calculate total shipping cost per customer from orders
$orderShipData = $ToryHub->get_list_safe(
    "SELECT o.id, o.customer_id, o.cargo_type,
        o.weight_charged as order_weight_charged, o.weight_actual as order_weight_actual,
        o.custom_rate_kg, o.custom_rate_cbm, o.volume_actual,
        COALESCE(o.domestic_cost, 0) as domestic_cost,
        o.exchange_rate as order_exchange_rate,
        SUM(COALESCE(p.weight_charged,0)) as pkg_weight_charged,
        SUM(COALESCE(p.weight_actual,0)) as pkg_weight_actual,
        SUM(COALESCE(p.length_cm * p.width_cm * p.height_cm / 1000000, 0)) as total_cbm
     FROM `orders` o
     LEFT JOIN `package_orders` po ON o.id = po.order_id
     LEFT JOIN `packages` p ON po.package_id = p.id
     WHERE o.status != 'cancelled'
     GROUP BY o.id", []
);

$totalShipMap = [];
foreach ($orderShipData as $od) {
    $cid = $od['customer_id'];
    if (!$cid) continue;
    $wC = floatval($od['order_weight_charged']); $wA = floatval($od['order_weight_actual']);
    $pkgWC = floatval($od['pkg_weight_charged']); $pkgWA = floatval($od['pkg_weight_actual']);
    $w = $pkgWA > 0 ? $pkgWA : ($wA > 0 ? $wA : ($pkgWC > 0 ? $pkgWC : $wC));
    $cbm = floatval($od['total_cbm']);
    if (floatval($od['volume_actual']) > 0) $cbm = floatval($od['volume_actual']);
    $cargo = $od['cargo_type'] ?: 'easy';
    $rate = $shippingRates[$cargo] ?? $shippingRates['easy'];
    $rkg = $od['custom_rate_kg'] !== null ? floatval($od['custom_rate_kg']) : $rate['per_kg'];
    $rcbm = $od['custom_rate_cbm'] !== null ? floatval($od['custom_rate_cbm']) : $rate['per_cbm'];
    $orderRate = floatval($od['order_exchange_rate']) ?: $exchangeRate;
    $domesticVnd = floatval($od['domestic_cost']) * $orderRate;
    $cost = max($w * $rkg, $cbm * $rcbm) + $domesticVnd;
    if (!isset($totalShipMap[$cid])) $totalShipMap[$cid] = 0;
    $totalShipMap[$cid] += $cost;
}

$customers = $ToryHub->get_list_safe("SELECT * FROM `customers` ORDER BY `id` DESC", []);

$kpiTotalShip = 0; $kpiTotalPaid = 0; $kpiTotalDebt = 0;
$debtCustomers = [];

foreach ($customers as $c) {
    $cid = $c['id'];
    $ship = $totalShipMap[$cid] ?? 0;
    $paid = floatval($c['total_spent'] ?? 0);
    $debt = max(0, $ship - $paid);
    $kpiTotalShip += $ship;
    $kpiTotalPaid += $paid;
    $kpiTotalDebt += $debt;
    if ($debt > 0) {
        $debtCustomers[] = [
            'id' => $c['id'],
            'customer_code' => $c['customer_code'],
            'fullname' => $c['fullname'],
            'phone' => $c['phone'] ?? '',
            'customer_type' => $c['customer_type'] ?? 'normal',
            'total_shipping' => round($ship),
            'total_paid' => round($paid),
            'debt' => round($debt),
        ];
    }
}

usort($debtCustomers, function($a, $b) { return $b['debt'] <=> $a['debt']; });

api_success([
    'customers' => $debtCustomers,
    'summary' => [
        'total_debt' => round($kpiTotalDebt),
        'debt_count' => count($debtCustomers),
        'total_shipping' => round($kpiTotalShip),
        'total_paid' => round($kpiTotalPaid),
    ],
]);
