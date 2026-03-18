<?php
require_once(__DIR__.'/../../../models/is_staffcn.php');
if (get_user_role() !== 'finance_cn') { redirect(base_url('staffcn/home')); }

$page_title = __('Tổng quan tài chính');

$filterDateFrom = input_get('date_from') ?: date('Y-m-01', strtotime('-5 months'));
$filterDateTo = input_get('date_to') ?: date('Y-m-d');
$currentMonth = intval(date('m'));
$currentYear = intval(date('Y'));
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

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
    $w = $wC > 0 ? $wC : ($wA > 0 ? $wA : ($pkgWC > 0 ? $pkgWC : $pkgWA));
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
usort($debtCustomers, function($a, $b) { return $b['debt'] <=> $a['debt']; });
$debtCustomers = array_slice($debtCustomers, 0, 10);

// === Chi phí VH tháng này ===
$expenseMonth = floatval($ToryHub->get_row_safe(
    "SELECT COALESCE(SUM(amount),0) as total FROM `expenses` WHERE `expense_date` BETWEEN ? AND ?",
    [$monthStart, $monthEnd]
)['total']);
$expenseCategories = $ToryHub->get_list_safe(
    "SELECT category, COALESCE(SUM(amount),0) as total, COUNT(*) as cnt FROM `expenses` WHERE `expense_date` BETWEEN ? AND ? GROUP BY category ORDER BY total DESC",
    [$monthStart, $monthEnd]
);

// === Lương tháng này ===
$salaryData = $ToryHub->get_row_safe(
    "SELECT COALESCE(SUM(CASE WHEN currency='CNY' THEN net_salary * COALESCE(NULLIF(exchange_rate,0), ?) ELSE net_salary END),0) as total_vnd,
            COALESCE(SUM(CASE WHEN status='paid' THEN (CASE WHEN currency='CNY' THEN net_salary * COALESCE(NULLIF(exchange_rate,0), ?) ELSE net_salary END) ELSE 0 END),0) as paid_vnd,
            COUNT(*) as staff_count
     FROM `salaries` WHERE `month` = ? AND `year` = ?",
    [$exchangeRate, $exchangeRate, $currentMonth, $currentYear]
);
$salaryTotal = floatval($salaryData['total_vnd']);
$salaryPaid = floatval($salaryData['paid_vnd']);
$salaryUnpaid = $salaryTotal - $salaryPaid;
$salaryStaffCount = intval($salaryData['staff_count']);

// === Giao dịch tháng này ===
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

// === Doanh thu theo tháng (trong kỳ filter) ===
$monthlyRevenue = $ToryHub->get_list_safe(
    "SELECT DATE_FORMAT(create_date, '%Y-%m') as month,
        COUNT(*) as order_count, COALESCE(SUM(grand_total),0) as revenue
     FROM `orders` WHERE `status` != 'cancelled' AND DATE(create_date) >= ? AND DATE(create_date) <= ?
     GROUP BY DATE_FORMAT(create_date, '%Y-%m') ORDER BY month DESC",
    [$filterDateFrom, $filterDateTo]
);

// === Top KH chi tiêu ===
$topCustomers = $ToryHub->get_list_safe(
    "SELECT `id`, `fullname`, `total_spent` FROM `customers` ORDER BY `total_spent` DESC LIMIT 10", []
);

// Tổng chi tháng này
$totalCostMonth = $expenseMonth + $salaryTotal;

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Tổng quan tài chính') ?></h4>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row">
            <div class="col-xl col-md-6">
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
            <div class="col-xl col-md-6">
                <div class="card card-animate">
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
            <div class="col-xl col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-end justify-content-between mt-2">
                            <div>
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Tổng chi tháng này') ?></p>
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

        <!-- Main Content: 2 columns -->
        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-8">
                <!-- Doanh thu theo tháng -->
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0"><?= __('Doanh thu theo tháng') ?></h5>
                        <form method="GET" action="<?= base_url('staffcn/finance-summary') ?>" class="d-flex gap-2 align-items-center">
                            <input type="date" class="form-control form-control-sm" name="date_from" value="<?= $filterDateFrom ?>" style="width:140px">
                            <span class="text-muted">-</span>
                            <input type="date" class="form-control form-control-sm" name="date_to" value="<?= $filterDateTo ?>" style="width:140px">
                            <button type="submit" class="btn btn-sm btn-primary"><i class="ri-search-line"></i></button>
                        </form>
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
                        <a href="<?= base_url('staffcn/transactions') ?>" class="btn btn-sm btn-soft-info"><?= __('Xem tất cả') ?></a>
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
                                            <a href="<?= base_url('staffcn/customers-detail?id=' . $tx['customer_id']) ?>"><?= htmlspecialchars($tx['customer_name']) ?></a>
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
                                            <a href="<?= base_url('staffcn/customers-detail?id=' . $tc['id']) ?>">
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
                        <a href="<?= base_url('staffcn/transactions') ?>" class="btn btn-sm btn-soft-info"><?= __('Chi tiết') ?></a>
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
                        <a href="<?= base_url('staffcn/expenses') ?>" class="btn btn-sm btn-soft-warning"><?= __('Chi tiết') ?></a>
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
                        <a href="<?= base_url('staffcn/salary-list') ?>" class="btn btn-sm btn-soft-primary"><?= __('Chi tiết') ?></a>
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
                        <a href="<?= base_url('staffcn/debt-list') ?>" class="btn btn-sm btn-light"><?= __('Xem tất cả') ?></a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($debtCustomers)): ?>
                        <div class="p-3 text-center text-muted"><?= __('Không có khách hàng nợ') ?></div>
                        <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($debtCustomers as $dc): ?>
                            <a href="<?= base_url('staffcn/customers-detail?id=' . $dc['id']) ?>" class="list-group-item list-group-item-action">
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
