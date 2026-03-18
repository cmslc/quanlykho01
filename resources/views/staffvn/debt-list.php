<?php
require_once(__DIR__.'/../../../models/is_staffvn.php');
if ($getUser['role'] !== 'finance_vn') { redirect(base_url('staffvn/home')); }

$page_title = __('Công nợ khách hàng');

// Shipping rates + exchange rate
$shippingRates = [
    'easy'      => ['per_kg' => floatval($ToryHub->site('shipping_road_easy_per_kg') ?: 25000), 'per_cbm' => floatval($ToryHub->site('shipping_road_easy_per_cbm') ?: 6000000)],
    'difficult' => ['per_kg' => floatval($ToryHub->site('shipping_road_difficult_per_kg') ?: 35000), 'per_cbm' => floatval($ToryHub->site('shipping_road_difficult_per_cbm') ?: 8000000)],
];
$exchangeRate = get_exchange_rate();

// Tổng cước vận chuyển theo customer (quốc tế + nội địa TQ)
$orderShipData = $ToryHub->get_list_safe(
    "SELECT o.id, o.customer_id, o.cargo_type,
        o.weight_charged as order_weight_charged,
        o.weight_actual as order_weight_actual,
        o.custom_rate_kg, o.custom_rate_cbm, o.volume_actual,
        o.domestic_cost, o.exchange_rate as order_exchange_rate,
        SUM(p.weight_charged) as pkg_weight_charged,
        SUM(p.weight_actual) as pkg_weight_actual,
        SUM(p.length_cm * p.width_cm * p.height_cm / 1000000) as total_cbm
     FROM `orders` o
     LEFT JOIN `package_orders` po ON o.id = po.order_id
     LEFT JOIN `packages` p ON po.package_id = p.id
     WHERE o.status != 'cancelled'
     GROUP BY o.id", []
);
$totalShipMap = [];
foreach ($orderShipData as $od) {
    $cid = $od['customer_id'];
    $wC = floatval($od['order_weight_charged'] ?? 0);
    $wA = floatval($od['order_weight_actual'] ?? 0);
    $pkgWC = floatval($od['pkg_weight_charged'] ?? 0);
    $pkgWA = floatval($od['pkg_weight_actual'] ?? 0);
    $w = $pkgWA > 0 ? $pkgWA : ($wA > 0 ? $wA : ($pkgWC > 0 ? $pkgWC : $wC));
    $cbm = floatval($od['total_cbm'] ?? 0);
    if (floatval($od['volume_actual'] ?? 0) > 0) $cbm = floatval($od['volume_actual']);
    $cargo = $od['cargo_type'] ?? 'easy';
    $rate = $shippingRates[$cargo] ?? $shippingRates['easy'];
    $rkg = $od['custom_rate_kg'] !== null ? floatval($od['custom_rate_kg']) : $rate['per_kg'];
    $rcbm = $od['custom_rate_cbm'] !== null ? floatval($od['custom_rate_cbm']) : $rate['per_cbm'];
    $orderRate = floatval($od['order_exchange_rate'] ?? 0) ?: $exchangeRate;
    $domesticVnd = floatval($od['domestic_cost'] ?? 0) * $orderRate;
    $cost = max($w * $rkg, $cbm * $rcbm) + $domesticVnd;
    if (!isset($totalShipMap[$cid])) $totalShipMap[$cid] = 0;
    $totalShipMap[$cid] += $cost;
}

// Tính nợ per customer, chỉ lấy KH có nợ > 0
$customers = $ToryHub->get_list_safe("SELECT * FROM `customers` ORDER BY `id` DESC", []);

$kpiTotalShip = 0;
$kpiTotalPaid = 0;
$kpiTotalDebt = 0;
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
        $debtCustomers[] = array_merge($c, ['ship' => $ship, 'paid' => $paid, 'debt' => $debt]);
    }
}

// Sắp xếp nợ giảm dần
usort($debtCustomers, function($a, $b) { return $b['debt'] <=> $a['debt']; });

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Công nợ khách hàng') ?></h4>
                    <a href="<?= base_url('ajaxs/staffvn/debt-export.php') ?>" class="btn btn-success">
                        <i class="ri-file-excel-2-line"></i> <?= __('Xuất Excel') ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-end justify-content-between mt-2">
                            <div>
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Tổng công nợ') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-danger"><?= format_vnd($kpiTotalDebt) ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-danger-subtle rounded fs-3"><i class="ri-error-warning-line text-danger"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-end justify-content-between mt-2">
                            <div>
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Khách hàng nợ') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-warning"><?= count($debtCustomers) ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning-subtle rounded fs-3"><i class="ri-user-line text-warning"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-end justify-content-between mt-2">
                            <div>
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Tổng cước vận chuyển') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-primary"><?= format_vnd($kpiTotalShip) ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle rounded fs-3"><i class="ri-truck-line text-primary"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-end justify-content-between mt-2">
                            <div>
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Đã thanh toán') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-success"><?= format_vnd($kpiTotalPaid) ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success-subtle rounded fs-3"><i class="ri-checkbox-circle-line text-success"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Debt Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Danh sách khách hàng nợ') ?> (<?= count($debtCustomers) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table data-table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?= __('Họ tên') ?></th>
                                        <th><?= __('Điện thoại') ?></th>
                                        <th><?= __('Loại') ?></th>
                                        <th class="text-end"><?= __('Tổng cước') ?></th>
                                        <th class="text-end"><?= __('Đã thanh toán') ?></th>
                                        <th class="text-end"><?= __('Đang nợ') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $stt = 0; foreach ($debtCustomers as $dc): $stt++; ?>
                                    <tr>
                                        <td><?= $stt ?></td>
                                        <td>
                                            <a href="<?= base_url('staffvn/customers-detail?id=' . $dc['id']) ?>" class="fw-medium"><?= htmlspecialchars($dc['fullname']) ?></a>
                                        </td>
                                        <td><?= htmlspecialchars($dc['phone'] ?? '') ?></td>
                                        <td><?= display_customer_type($dc['customer_type']) ?></td>
                                        <td class="text-end text-primary"><?= format_vnd($dc['ship']) ?></td>
                                        <td class="text-end text-success"><?= format_vnd($dc['paid']) ?></td>
                                        <td class="text-end"><span class="badge bg-danger-subtle text-danger fs-12 px-2 py-1"><?= format_vnd($dc['debt']) ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($debtCustomers)): ?>
                                    <tr><td colspan="7" class="text-center text-muted"><?= __('Không có khách hàng nợ') ?></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>

