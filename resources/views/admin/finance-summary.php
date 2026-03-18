<?php
require_once(__DIR__.'/../../../models/is_admin.php');

$page_title = __('Tổng quan tài chính');

$filterMonth = input_get('month') ?: date('Y-m');
$monthStart = $filterMonth . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));
$filterYear = intval(date('Y', strtotime($monthStart)));
$filterMon = intval(date('m', strtotime($monthStart)));
$periodLabel = __('Tháng') . ' ' . date('m/Y', strtotime($monthStart));

// Date range for chart (12 tháng gần nhất)
$chartDateFrom = date('Y-m-01', strtotime($monthStart . ' -11 months'));
$chartDateTo = $monthEnd;

// Auto-migrate: add exchange_rate columns if missing
$_testCol = @$ToryHub->query("SELECT exchange_rate FROM salaries LIMIT 0");
if (!$_testCol) {
    $ToryHub->query("ALTER TABLE `salaries` ADD COLUMN `exchange_rate` DECIMAL(10,2) DEFAULT NULL AFTER `currency`");
    $ToryHub->query("ALTER TABLE `expenses` ADD COLUMN `exchange_rate` DECIMAL(10,2) DEFAULT NULL AFTER `amount`");
}

// === Cước vận chuyển & Công nợ ===
$shippingRates = [
    'easy'      => ['per_kg' => floatval($ToryHub->site('shipping_road_easy_per_kg') ?: 25000), 'per_cbm' => floatval($ToryHub->site('shipping_road_easy_per_cbm') ?: 6000000)],
    'difficult' => ['per_kg' => floatval($ToryHub->site('shipping_road_difficult_per_kg') ?: 35000), 'per_cbm' => floatval($ToryHub->site('shipping_road_difficult_per_cbm') ?: 8000000)],
];
$exchangeRate = get_exchange_rate();

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
     WHERE o.status != 'cancelled' AND DATE(o.create_date) BETWEEN ? AND ?
     GROUP BY o.id", [$monthStart, $monthEnd]
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

// === Cước vận chuyển mã bao (bags) ===
$bagShipData = $ToryHub->get_list_safe(
    "SELECT b.id, b.bag_code, b.total_weight as bag_weight,
        COALESCE(b.weight_volume, 0) as bag_cbm,
        COALESCE(b.domestic_cost, 0) as domestic_cost,
        b.custom_rate_kg, b.custom_rate_cbm,
        SUM(COALESCE(p.weight_charged, 0)) as pkg_weight_charged,
        SUM(COALESCE(p.weight_actual, 0)) as pkg_weight_actual,
        SUM(COALESCE(p.length_cm,0) * COALESCE(p.width_cm,0) * COALESCE(p.height_cm,0) / 1000000) as pkg_cbm
     FROM `bags` b
     LEFT JOIN `bag_packages` bp ON b.id = bp.bag_id
     LEFT JOIN `packages` p ON bp.package_id = p.id
     WHERE b.status IN ('sealed','loading','shipping','arrived') AND DATE(b.create_date) BETWEEN ? AND ?
     GROUP BY b.id", [$monthStart, $monthEnd]
);
$bagShipTotal = 0;
$bagShipCount = count($bagShipData);
foreach ($bagShipData as $bd) {
    $bw = floatval($bd['bag_weight'] ?? 0);
    $pkgWA = floatval($bd['pkg_weight_actual'] ?? 0);
    $pkgWC = floatval($bd['pkg_weight_charged'] ?? 0);
    $w = $bw > 0 ? $bw : ($pkgWA > 0 ? $pkgWA : $pkgWC);
    $cbm = floatval($bd['bag_cbm'] ?? 0) ?: floatval($bd['pkg_cbm'] ?? 0);
    $rate = $shippingRates['easy'];
    $rkg = $bd['custom_rate_kg'] !== null ? floatval($bd['custom_rate_kg']) : $rate['per_kg'];
    $rcbm = $bd['custom_rate_cbm'] !== null ? floatval($bd['custom_rate_cbm']) : $rate['per_cbm'];
    $domesticVnd = floatval($bd['domestic_cost'] ?? 0) * $exchangeRate;
    $bagShipTotal += max($w * $rkg, $cbm * $rcbm) + $domesticVnd;
}

$customers = $ToryHub->get_list_safe("SELECT * FROM `customers` ORDER BY `id` DESC", []);

// Tổng thu từ KH trong kỳ (từ transactions)
$paidInPeriod = $ToryHub->get_list_safe(
    "SELECT customer_id, COALESCE(SUM(ABS(amount)),0) as total_paid
     FROM `transactions` WHERE type IN ('payment','deposit') AND DATE(create_date) BETWEEN ? AND ?
     GROUP BY customer_id",
    [$monthStart, $monthEnd]
);
$paidMap = [];
foreach ($paidInPeriod as $pp) { $paidMap[$pp['customer_id']] = floatval($pp['total_paid']); }

$kpiTotalShip = 0;
$kpiTotalPaid = 0;
$kpiTotalDebt = 0;
$debtCustomers = [];

foreach ($customers as $c) {
    $cid = $c['id'];
    $ship = $totalShipMap[$cid] ?? 0;
    $paid = $paidMap[$cid] ?? 0;
    $debt = max(0, $ship - $paid);
    $kpiTotalShip += $ship;
    $kpiTotalPaid += $paid;
    $kpiTotalDebt += $debt;
    if ($debt > 0) {
        $debtCustomers[] = array_merge($c, ['ship' => $ship, 'paid' => $paid, 'debt' => $debt]);
    }
}
usort($debtCustomers, function($a, $b) { return $b['debt'] <=> $a['debt']; });
$debtCustomers = array_slice($debtCustomers, 0, 10);

// === Chi phí VH tháng ===
$expenseMonth = floatval($ToryHub->get_row_safe(
    "SELECT COALESCE(SUM(amount),0) as total FROM `expenses` WHERE `expense_date` BETWEEN ? AND ?",
    [$monthStart, $monthEnd]
)['total']);
$expenseCategories = $ToryHub->get_list_safe(
    "SELECT category, COALESCE(SUM(amount),0) as total, COUNT(*) as cnt FROM `expenses` WHERE `expense_date` BETWEEN ? AND ? GROUP BY category ORDER BY total DESC",
    [$monthStart, $monthEnd]
);

// === Lương tháng ===
$salaryData = $ToryHub->get_row_safe(
    "SELECT COALESCE(SUM(CASE WHEN currency='CNY' THEN net_salary * COALESCE(NULLIF(exchange_rate,0), ?) ELSE net_salary END),0) as total_vnd,
            COALESCE(SUM(CASE WHEN status='paid' THEN (CASE WHEN currency='CNY' THEN net_salary * COALESCE(NULLIF(exchange_rate,0), ?) ELSE net_salary END) ELSE 0 END),0) as paid_vnd,
            COUNT(*) as staff_count
     FROM `salaries` WHERE `month` = ? AND `year` = ?",
    [$exchangeRate, $exchangeRate, $filterMon, $filterYear]
);
$salaryTotal = floatval($salaryData['total_vnd']);
$salaryPaid = floatval($salaryData['paid_vnd']);
$salaryUnpaid = $salaryTotal - $salaryPaid;
$salaryStaffCount = intval($salaryData['staff_count']);

// === Giao dịch tháng ===
$txnMonth = $ToryHub->get_row_safe(
    "SELECT COUNT(*) as cnt,
            COALESCE(SUM(CASE WHEN type IN ('payment','deposit') THEN ABS(amount) ELSE 0 END),0) as total_in,
            COALESCE(SUM(CASE WHEN type = 'refund' THEN ABS(amount) ELSE 0 END),0) as total_refund
     FROM `transactions` WHERE DATE(create_date) BETWEEN ? AND ?",
    [$monthStart, $monthEnd]
);
$txnCount = intval($txnMonth['cnt']);
$txnTotalIn = floatval($txnMonth['total_in']);
$txnTotalRefund = floatval($txnMonth['total_refund']);

// === Giao dịch gần đây ===
$recentTxns = $ToryHub->get_list_safe(
    "SELECT t.*, c.fullname as customer_name FROM `transactions` t
     LEFT JOIN `customers` c ON t.customer_id = c.id
     ORDER BY t.create_date DESC LIMIT 10", []
);

// === Doanh thu theo tháng (12 tháng gần nhất) ===
$monthlyRevenue = $ToryHub->get_list_safe(
    "SELECT DATE_FORMAT(create_date, '%Y-%m') as month,
        COUNT(*) as order_count, COALESCE(SUM(grand_total),0) as revenue
     FROM `orders` WHERE `status` != 'cancelled' AND DATE(create_date) >= ? AND DATE(create_date) <= ?
     GROUP BY DATE_FORMAT(create_date, '%Y-%m') ORDER BY month DESC",
    [$chartDateFrom, $chartDateTo]
);

// === Top KH chi tiêu ===
$topCustomers = $ToryHub->get_list_safe(
    "SELECT `id`, `fullname`, `total_spent` FROM `customers` ORDER BY `total_spent` DESC LIMIT 10", []
);

// === Doanh thu đơn hàng tháng ===
$revenueMonth = $ToryHub->get_row_safe(
    "SELECT COUNT(*) as order_count, COALESCE(SUM(grand_total),0) as revenue,
            COALESCE(SUM(CASE WHEN product_type='wholesale' THEN grand_total ELSE 0 END),0) as revenue_wholesale,
            COUNT(CASE WHEN product_type='wholesale' THEN 1 END) as cnt_wholesale
     FROM `orders` WHERE `status` != 'cancelled' AND DATE(create_date) BETWEEN ? AND ?",
    [$monthStart, $monthEnd]
);
$revenueWholesale = floatval($revenueMonth['revenue_wholesale']);
$revenueRetail = $bagShipTotal; // Doanh thu retail = cước vận chuyển mã bao
$cntWholesale = intval($revenueMonth['cnt_wholesale']);
$cntRetail = $bagShipCount;
$revenueMonthTotal = $revenueWholesale + $revenueRetail;
$orderCountMonth = $cntWholesale + $cntRetail;

// Tổng chi tháng
$totalCostMonth = $expenseMonth + $salaryTotal;

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Tổng quan tài chính') ?> <small class="text-muted fs-14 fw-normal">— <?= $periodLabel ?></small></h4>
                    <form method="GET" action="<?= base_url('admin/finance-summary') ?>" class="d-flex gap-2 align-items-center">
                        <input type="month" class="form-control form-control-sm" name="month" value="<?= $filterMonth ?>" style="width:160px" onchange="this.form.submit()">
                        <?php
                        $prevMonth = date('Y-m', strtotime($monthStart . ' -1 month'));
                        $nextMonth = date('Y-m', strtotime($monthStart . ' +1 month'));
                        ?>
                        <a href="<?= base_url('admin/finance-summary?month=' . $prevMonth) ?>" class="btn btn-sm btn-outline-secondary" title="<?= __('Tháng trước') ?>"><i class="ri-arrow-left-s-line"></i></a>
                        <a href="<?= base_url('admin/finance-summary') ?>" class="btn btn-sm btn-outline-primary" title="<?= __('Tháng hiện tại') ?>"><i class="ri-calendar-todo-line"></i></a>
                        <a href="<?= base_url('admin/finance-summary?month=' . $nextMonth) ?>" class="btn btn-sm btn-outline-secondary" title="<?= __('Tháng sau') ?>"><i class="ri-arrow-right-s-line"></i></a>
                    </form>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <?php
        // Prepare shipping breakdown by cargo type
        $shipEasyTotal = 0; $shipDifficultTotal = 0; $shipEasyCount = 0; $shipDifficultCount = 0;
        $shipDomesticTotal = 0;
        foreach ($orderShipData as $od) {
            $cargo = $od['cargo_type'] ?? 'easy';
            $pkgWA = floatval($od['pkg_weight_actual'] ?? 0);
            $wA = floatval($od['order_weight_actual'] ?? 0);
            $pkgWC = floatval($od['pkg_weight_charged'] ?? 0);
            $wC = floatval($od['order_weight_charged'] ?? 0);
            $w = $pkgWA > 0 ? $pkgWA : ($wA > 0 ? $wA : ($pkgWC > 0 ? $pkgWC : $wC));
            $cbm = floatval($od['total_cbm'] ?? 0);
            if (floatval($od['volume_actual'] ?? 0) > 0) $cbm = floatval($od['volume_actual']);
            $rate = $shippingRates[$cargo] ?? $shippingRates['easy'];
            $rkg = $od['custom_rate_kg'] !== null ? floatval($od['custom_rate_kg']) : $rate['per_kg'];
            $rcbm = $od['custom_rate_cbm'] !== null ? floatval($od['custom_rate_cbm']) : $rate['per_cbm'];
            $orderRate = floatval($od['order_exchange_rate'] ?? 0) ?: $exchangeRate;
            $domesticVnd = floatval($od['domestic_cost'] ?? 0) * $orderRate;
            $shipCost = max($w * $rkg, $cbm * $rcbm);
            $shipDomesticTotal += $domesticVnd;
            if ($cargo === 'difficult') { $shipDifficultTotal += $shipCost; $shipDifficultCount++; }
            else { $shipEasyTotal += $shipCost; $shipEasyCount++; }
        }
        $totalOrderCount = count($orderShipData);
        ?>
        <div class="row">
            <div class="col-xl col-md-6">
                <div class="card card-animate" style="cursor:pointer;" data-bs-toggle="modal" data-bs-target="#modal-kpi-ship">
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
            <div class="col-xl col-md-6">
                <div class="card card-animate" style="cursor:pointer;" data-bs-toggle="modal" data-bs-target="#modal-kpi-paid">
                    <div class="card-body">
                        <div class="d-flex align-items-end justify-content-between mt-2">
                            <div>
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Đã thu từ KH') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-success"><?= format_vnd($kpiTotalPaid) ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success-subtle rounded fs-3"><i class="ri-checkbox-circle-line text-success"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl col-md-6">
                <div class="card card-animate" style="cursor:pointer;" data-bs-toggle="modal" data-bs-target="#modal-kpi-debt">
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
            <div class="col-xl col-md-6">
                <div class="card card-animate" style="cursor:pointer;" data-bs-toggle="modal" data-bs-target="#modal-kpi-expense">
                    <div class="card-body">
                        <div class="d-flex align-items-end justify-content-between mt-2">
                            <div>
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Tổng chi') ?> <?= $periodLabel ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-warning"><?= format_vnd($totalCostMonth) ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning-subtle rounded fs-3"><i class="ri-money-cny-circle-line text-warning"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Tổng cước vận chuyển -->
        <div class="modal fade" id="modal-kpi-ship" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ri-truck-line text-primary me-2"></i><?= __('Tổng cước vận chuyển') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3"><?= __('Công thức') ?>: <code>MAX(cân nặng × đơn giá/kg, số khối × đơn giá/m³) + cước nội địa</code></p>
                <table class="table table-sm table-bordered mb-3">
                    <thead class="table-light"><tr><th><?= __('Hạng mục') ?></th><th class="text-end"><?= __('Số đơn') ?></th><th class="text-end"><?= __('Thành tiền') ?></th></tr></thead>
                    <tbody>
                        <tr><td><?= __('Hàng dễ') ?> <small class="text-muted">(<?= format_vnd($shippingRates['easy']['per_kg']) ?>/kg, <?= format_vnd($shippingRates['easy']['per_cbm']) ?>/m³)</small></td><td class="text-end"><?= $shipEasyCount ?></td><td class="text-end"><?= format_vnd($shipEasyTotal) ?></td></tr>
                        <tr><td><?= __('Hàng khó') ?> <small class="text-muted">(<?= format_vnd($shippingRates['difficult']['per_kg']) ?>/kg, <?= format_vnd($shippingRates['difficult']['per_cbm']) ?>/m³)</small></td><td class="text-end"><?= $shipDifficultCount ?></td><td class="text-end"><?= format_vnd($shipDifficultTotal) ?></td></tr>
                        <tr><td><?= __('Cước nội địa (quy VNĐ)') ?></td><td class="text-end">-</td><td class="text-end"><?= format_vnd($shipDomesticTotal) ?></td></tr>
                    </tbody>
                    <tfoot class="table-light"><tr><td class="fw-bold"><?= __('Tổng cộng') ?> (<?= $totalOrderCount ?> <?= __('đơn') ?>)</td><td></td><td class="text-end fw-bold text-primary"><?= format_vnd($kpiTotalShip) ?></td></tr></tfoot>
                </table>
                <div class="alert alert-light mb-0 py-2 fs-12">
                    <i class="ri-information-line me-1"></i><?= __('Đơn giá mặc định từ cài đặt. Đơn hàng có custom rate sẽ dùng rate riêng.') ?>
                </div>
            </div>
        </div></div></div>

        <!-- Modal: Đã thu từ KH -->
        <div class="modal fade" id="modal-kpi-paid" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ri-checkbox-circle-line text-success me-2"></i><?= __('Đã thu từ KH') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3"><?= __('Công thức') ?>: <code>SUM(total_spent)</code> <?= __('từ tất cả khách hàng') ?></p>
                <table class="table table-sm table-bordered mb-3">
                    <thead class="table-light"><tr><th><?= __('Hạng mục') ?></th><th class="text-end"><?= __('Giá trị') ?></th></tr></thead>
                    <tbody>
                        <tr><td><?= __('Tổng KH') ?></td><td class="text-end"><?= count($customers) ?></td></tr>
                        <tr><td><?= __('Giao dịch') ?> <?= $periodLabel ?></td><td class="text-end"><?= $txnCount ?> <?= __('giao dịch') ?></td></tr>
                        <tr><td><?= __('Thu trong tháng') ?> <small class="text-muted">(payment + deposit)</small></td><td class="text-end text-success"><?= format_vnd($txnTotalIn) ?></td></tr>
                        <tr><td><?= __('Hoàn trả trong tháng') ?> <small class="text-muted">(refund)</small></td><td class="text-end text-danger"><?= format_vnd($txnTotalRefund) ?></td></tr>
                    </tbody>
                    <tfoot class="table-light"><tr><td class="fw-bold"><?= __('Tổng đã thu (toàn bộ)') ?></td><td class="text-end fw-bold text-success"><?= format_vnd($kpiTotalPaid) ?></td></tr></tfoot>
                </table>
            </div>
        </div></div></div>

        <!-- Modal: Tổng công nợ -->
        <div class="modal fade" id="modal-kpi-debt" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ri-error-warning-line text-danger me-2"></i><?= __('Tổng công nợ') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3"><?= __('Công thức') ?>: <code>MAX(0, cước vận chuyển − đã thanh toán)</code> <?= __('cho mỗi KH') ?></p>
                <table class="table table-sm table-bordered mb-3">
                    <thead class="table-light"><tr><th><?= __('Hạng mục') ?></th><th class="text-end"><?= __('Giá trị') ?></th></tr></thead>
                    <tbody>
                        <tr><td><?= __('Tổng cước vận chuyển') ?></td><td class="text-end"><?= format_vnd($kpiTotalShip) ?></td></tr>
                        <tr><td><?= __('Đã thu từ KH') ?></td><td class="text-end text-success">− <?= format_vnd($kpiTotalPaid) ?></td></tr>
                        <tr><td><?= __('KH còn nợ') ?></td><td class="text-end"><?= count($debtCustomers) ?> / <?= count($customers) ?> <?= __('khách') ?></td></tr>
                    </tbody>
                    <tfoot class="table-light"><tr><td class="fw-bold"><?= __('Tổng công nợ') ?></td><td class="text-end fw-bold text-danger"><?= format_vnd($kpiTotalDebt) ?></td></tr></tfoot>
                </table>
                <?php if (!empty($debtCustomers)): ?>
                <p class="fw-medium mb-2"><?= __('Top KH nợ nhiều nhất') ?>:</p>
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light"><tr><th><?= __('Khách hàng') ?></th><th class="text-end"><?= __('Cước') ?></th><th class="text-end"><?= __('Đã trả') ?></th><th class="text-end"><?= __('Còn nợ') ?></th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($debtCustomers, 0, 5) as $dc): ?>
                        <tr><td><?= htmlspecialchars($dc['fullname']) ?></td><td class="text-end"><?= format_vnd($dc['ship']) ?></td><td class="text-end text-success"><?= format_vnd($dc['paid']) ?></td><td class="text-end text-danger fw-bold"><?= format_vnd($dc['debt']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div></div></div>

        <!-- Modal: Tổng chi trong kỳ -->
        <div class="modal fade" id="modal-kpi-expense" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ri-money-cny-circle-line text-warning me-2"></i><?= __('Tổng chi') ?> <?= $periodLabel ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3"><?= __('Công thức') ?>: <code><?= __('Chi phí vận hành') ?> + <?= __('Lương nhân viên') ?></code></p>
                <table class="table table-sm table-bordered mb-3">
                    <thead class="table-light"><tr><th colspan="2"><?= __('Chi phí vận hành') ?></th></tr></thead>
                    <tbody>
                        <?php if (!empty($expenseCategories)): ?>
                        <?php foreach ($expenseCategories as $ec): ?>
                        <tr><td><?= htmlspecialchars(__($ec['category'] ?: 'Khác')) ?> <small class="text-muted">(<?= $ec['cnt'] ?> <?= __('khoản') ?>)</small></td><td class="text-end"><?= format_vnd($ec['total']) ?></td></tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr><td colspan="2" class="text-muted text-center"><?= __('Không có chi phí') ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-light"><tr><td class="fw-bold"><?= __('Tổng chi phí VH') ?></td><td class="text-end fw-bold"><?= format_vnd($expenseMonth) ?></td></tr></tfoot>
                </table>
                <table class="table table-sm table-bordered mb-3">
                    <thead class="table-light"><tr><th colspan="2"><?= __('Lương nhân viên') ?> (<?= $salaryStaffCount ?> <?= __('người') ?>)</th></tr></thead>
                    <tbody>
                        <tr><td><?= __('Đã trả') ?></td><td class="text-end text-success"><?= format_vnd($salaryPaid) ?></td></tr>
                        <tr><td><?= __('Chưa trả') ?></td><td class="text-end text-danger"><?= format_vnd($salaryUnpaid) ?></td></tr>
                    </tbody>
                    <tfoot class="table-light"><tr><td class="fw-bold"><?= __('Tổng lương') ?></td><td class="text-end fw-bold"><?= format_vnd($salaryTotal) ?></td></tr></tfoot>
                </table>
                <div class="alert alert-warning-subtle border-warning mb-0 py-2">
                    <strong><?= __('Tổng chi') ?>:</strong> <?= format_vnd($expenseMonth) ?> + <?= format_vnd($salaryTotal) ?> = <strong class="text-warning"><?= format_vnd($totalCostMonth) ?></strong>
                </div>
            </div>
        </div></div></div>


        <!-- Main Content: 2 columns -->
        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-8">
                <!-- Doanh thu theo tháng -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Doanh thu 12 tháng gần nhất') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><?= __('Tháng') ?></th>
                                        <th class="text-end"><?= __('Số đơn') ?></th>
                                        <th class="text-end"><?= __('Doanh thu') ?></th>
                                        <th style="width: 30%;"><?= __('Biểu đồ') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $maxRevenue = max(array_column($monthlyRevenue, 'revenue') ?: [1]);
                                    foreach ($monthlyRevenue as $m):
                                        $pct = $maxRevenue > 0 ? round($m['revenue'] / $maxRevenue * 100) : 0;
                                    ?>
                                    <tr>
                                        <td class="fw-bold"><?= date('m-Y', strtotime($m['month'] . '-01')) ?></td>
                                        <td class="text-end"><?= $m['order_count'] ?></td>
                                        <td class="text-end text-success fw-bold"><?= format_vnd($m['revenue']) ?></td>
                                        <td>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-success" style="width: <?= $pct ?>%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($monthlyRevenue)): ?>
                                    <tr><td colspan="4" class="text-center text-muted"><?= __('Chưa có dữ liệu') ?></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Giao dịch gần đây -->
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0"><?= __('Giao dịch gần đây') ?></h5>
                        <a href="<?= base_url('admin/transactions') ?>" class="btn btn-sm btn-soft-info"><?= __('Xem tất cả') ?></a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th><?= __('Loại') ?></th>
                                        <th class="text-end"><?= __('Số tiền') ?></th>
                                        <th><?= __('Ngày') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentTxns as $tx):
                                        $txType = $tx['type'] ?? '';
                                        $txBadge = match($txType) {
                                            'payment' => 'bg-primary-subtle text-primary',
                                            'deposit' => 'bg-success-subtle text-success',
                                            'refund'  => 'bg-warning-subtle text-warning',
                                            default   => 'bg-secondary-subtle text-secondary',
                                        };
                                        $txLabel = match($txType) {
                                            'payment' => __('Thanh toán'),
                                            'deposit' => __('Nạp tiền'),
                                            'refund'  => __('Hoàn tiền'),
                                            default   => $txType,
                                        };
                                        $txAmount = floatval($tx['amount']);
                                    ?>
                                    <tr>
                                        <td>
                                            <?php if ($tx['customer_name']): ?>
                                            <a href="<?= base_url('admin/customers-detail?id=' . $tx['customer_id']) ?>"><?= htmlspecialchars($tx['customer_name']) ?></a>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge <?= $txBadge ?>"><?= $txLabel ?></span></td>
                                        <td class="text-end fw-bold <?= $txAmount >= 0 ? 'text-success' : 'text-danger' ?>"><?= format_vnd(abs($txAmount)) ?></td>
                                        <td><small class="text-muted"><?= date('d/m/Y', strtotime($tx['create_date'])) ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($recentTxns)): ?>
                                    <tr><td colspan="4" class="text-center text-muted"><?= __('Chưa có giao dịch') ?></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Top khách hàng chi tiêu -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Top khách hàng chi tiêu') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th class="text-end"><?= __('Đã thanh toán') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topCustomers as $idx => $tc): ?>
                                    <tr>
                                        <td><?= $idx + 1 ?></td>
                                        <td>
                                            <a href="<?= base_url('admin/customers-detail?id=' . $tc['id']) ?>">
                                                <?= htmlspecialchars($tc['fullname']) ?>
                                            </a>
                                        </td>
                                        <td class="text-end text-success fw-bold"><?= format_vnd($tc['total_spent']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-lg-4">
                <!-- Giao dịch tháng -->
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h6 class="card-title mb-0"><i class="ri-exchange-line text-info me-1"></i> <?= __('Giao dịch tháng') ?></h6>
                        <a href="<?= base_url('admin/transactions') ?>" class="btn btn-sm btn-soft-info"><?= __('Chi tiết') ?></a>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted"><?= __('Thanh toán') ?></span>
                            <span class="text-success fw-bold"><?= format_vnd($txnTotalIn) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted"><?= __('Hoàn tiền') ?></span>
                            <span class="text-danger fw-bold"><?= format_vnd($txnTotalRefund) ?></span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted"><?= __('Số giao dịch') ?></span>
                            <span class="fw-bold"><?= $txnCount ?></span>
                        </div>
                    </div>
                </div>

                <!-- Chi phí VH tháng -->
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h6 class="card-title mb-0"><i class="ri-shopping-bag-line text-warning me-1"></i> <?= __('Chi phí VH tháng') ?></h6>
                        <a href="<?= base_url('admin/expenses') ?>" class="btn btn-sm btn-soft-warning"><?= __('Chi tiết') ?></a>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted"><?= __('Tổng chi') ?></span>
                            <span class="text-danger fw-bold"><?= format_vnd($expenseMonth) ?></span>
                        </div>
                        <?php foreach (array_slice($expenseCategories, 0, 3) as $ec): ?>
                        <div class="d-flex justify-content-between mb-1">
                            <small class="text-muted"><?= htmlspecialchars($ec['category']) ?> (<?= $ec['cnt'] ?>)</small>
                            <small class="fw-medium"><?= format_vnd($ec['total']) ?></small>
                        </div>
                        <?php endforeach; ?>
                        <?php if (count($expenseCategories) > 3): ?>
                        <small class="text-muted">+<?= count($expenseCategories) - 3 ?> <?= __('danh mục khác') ?></small>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Lương tháng -->
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h6 class="card-title mb-0"><i class="ri-user-star-line text-primary me-1"></i> <?= __('Lương tháng') ?></h6>
                        <a href="<?= base_url('admin/salary-list') ?>" class="btn btn-sm btn-soft-primary"><?= __('Chi tiết') ?></a>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted"><?= __('Tổng lương') ?></span>
                            <span class="fw-bold"><?= format_vnd($salaryTotal) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted"><?= __('Đã trả') ?></span>
                            <span class="text-success fw-bold"><?= format_vnd($salaryPaid) ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted"><?= __('Chưa trả') ?></span>
                            <span class="text-danger fw-bold"><?= format_vnd($salaryUnpaid) ?></span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted"><?= __('Nhân viên') ?></span>
                            <span class="fw-bold"><?= $salaryStaffCount ?></span>
                        </div>
                    </div>
                </div>

                <!-- Khách hàng nợ -->
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white d-flex align-items-center justify-content-between">
                        <h6 class="card-title text-white mb-0"><i class="ri-error-warning-line"></i> <?= __('Khách hàng nợ') ?></h6>
                        <a href="<?= base_url('admin/debt-list') ?>" class="btn btn-sm btn-light"><?= __('Xem tất cả') ?></a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($debtCustomers)): ?>
                        <div class="p-3 text-center text-muted"><?= __('Không có khách hàng nợ') ?></div>
                        <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($debtCustomers as $dc): ?>
                            <a href="<?= base_url('admin/customers-detail?id=' . $dc['id']) ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong><?= htmlspecialchars($dc['fullname']) ?></strong>
                                    <div class="text-end">
                                        <span class="text-danger fw-bold"><?= format_vnd($dc['debt']) ?></span>
                                        <br><small class="text-muted"><?= __('Cước') ?>: <?= format_vnd($dc['ship']) ?></small>
                                    </div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-center">
                        <strong><?= __('Tổng nợ') ?>: <span class="text-danger"><?= format_vnd($kpiTotalDebt) ?></span></strong>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>
