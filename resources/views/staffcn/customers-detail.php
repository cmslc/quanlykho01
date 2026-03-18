<?php
require_once(__DIR__.'/../../../models/is_staffcn.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$id = intval(input_get('id'));
$customer = $ToryHub->get_row_safe("SELECT * FROM `customers` WHERE `id` = ?", [$id]);
if (!$customer) {
    redirect(base_url('staffcn/home'));
}

$page_title = __('Chi tiết khách hàng') . ': ' . $customer['fullname'];

// Shipping rates + exchange rate
$shippingRates = [
    'easy'      => ['per_kg' => floatval($ToryHub->site('shipping_road_easy_per_kg') ?: 25000), 'per_cbm' => floatval($ToryHub->site('shipping_road_easy_per_cbm') ?: 6000000)],
    'difficult' => ['per_kg' => floatval($ToryHub->site('shipping_road_difficult_per_kg') ?: 35000), 'per_cbm' => floatval($ToryHub->site('shipping_road_difficult_per_cbm') ?: 8000000)],
];
$exchangeRate = get_exchange_rate();

// Orders with shipping data
$orders = $ToryHub->get_list_safe(
    "SELECT o.*,
        o.weight_charged as order_weight_charged,
        o.weight_actual as order_weight_actual,
        o.custom_rate_kg, o.custom_rate_cbm,
        SUM(p.weight_charged) as pkg_weight_charged,
        SUM(p.weight_actual) as pkg_weight_actual,
        SUM(p.length_cm * p.width_cm * p.height_cm / 1000000) as total_cbm
     FROM `orders` o
     LEFT JOIN `package_orders` po ON o.id = po.order_id
     LEFT JOIN `packages` p ON po.package_id = p.id
     WHERE o.customer_id = ?
     GROUP BY o.id
     ORDER BY o.create_date DESC", [$id]
);

// Calculate shipping cost per order
$totalShipCost = 0;
foreach ($orders as &$od) {
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
    $orderRate = floatval($od['exchange_rate'] ?? 0) ?: $exchangeRate;
    $domesticVnd = floatval($od['domestic_cost'] ?? 0) * $orderRate;
    $od['ship_cost'] = ($od['status'] !== 'cancelled') ? max($w * $rkg, $cbm * $rcbm) + $domesticVnd : 0;
    $totalShipCost += $od['ship_cost'];
}
unset($od);

$totalPaid = floatval($customer['total_spent'] ?? 0);
$totalDebt = max(0, $totalShipCost - $totalPaid);

// Transactions
$transactions = $ToryHub->get_list_safe("SELECT * FROM `transactions` WHERE `customer_id` = ? ORDER BY `create_date` DESC LIMIT 50", [$id]);

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Chi tiết khách hàng') ?>: <?= htmlspecialchars($customer['fullname']) ?></h4>
                    <div class="d-flex gap-2">
                        <a href="<?= base_url('ajaxs/staffcn/customer-detail-export.php') ?>?id=<?= $id ?>" class="btn btn-success">
                            <i class="ri-file-excel-2-line"></i> <?= __('Xuất Excel') ?>
                        </a>
                        <a href="javascript:history.back()" class="btn btn-secondary">
                            <i class="ri-arrow-left-line"></i> <?= __('Quay lại') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer Info Cards -->
        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-end justify-content-between">
                            <div>
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Tổng cước') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-primary"><?= format_vnd($totalShipCost) ?></h4>
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
                        <div class="d-flex align-items-end justify-content-between">
                            <div>
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Đã thanh toán') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-success"><?= format_vnd($totalPaid) ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success-subtle rounded fs-3"><i class="ri-checkbox-circle-line text-success"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-end justify-content-between">
                            <div>
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Đang nợ') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 <?= $totalDebt > 0 ? 'text-danger' : 'text-success' ?>"><?= format_vnd($totalDebt) ?></h4>
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
                        <div class="d-flex align-items-end justify-content-between">
                            <div>
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Đơn hàng') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0"><?= $customer['total_orders'] ?? count($orders) ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-info-subtle rounded fs-3"><i class="ri-shopping-bag-line text-info"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Thông tin liên hệ') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3"><span class="text-muted"><?= __('Mã KH') ?>:</span> <strong><?= htmlspecialchars($customer['customer_code'] ?? '-') ?></strong></div>
                            <div class="col-md-3"><span class="text-muted"><?= __('Họ tên') ?>:</span> <strong><?= htmlspecialchars($customer['fullname']) ?></strong></div>
                            <div class="col-md-3"><span class="text-muted"><?= __('Điện thoại') ?>:</span> <?= htmlspecialchars($customer['phone'] ?? '-') ?></div>
                            <div class="col-md-3"><span class="text-muted">Email:</span> <?= htmlspecialchars($customer['email'] ?? '-') ?></div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-3"><span class="text-muted"><?= __('Địa chỉ VN') ?>:</span> <?= htmlspecialchars($customer['address_vn'] ?? '-') ?></div>
                            <div class="col-md-3"><span class="text-muted">Zalo:</span> <?= htmlspecialchars($customer['zalo'] ?? '-') ?></div>
                            <div class="col-md-3"><span class="text-muted">WeChat:</span> <?= htmlspecialchars($customer['wechat'] ?? '-') ?></div>
                            <div class="col-md-3"><span class="text-muted"><?= __('Ghi chú') ?>:</span> <?= htmlspecialchars($customer['note'] ?? '-') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Orders -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Đơn hàng') ?> (<?= count($orders) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><?= __('Mã hàng') ?></th>
                                        <th><?= __('Loại') ?></th>
                                        <th class="text-end"><?= __('Cân nặng') ?></th>
                                        <th class="text-end"><?= __('Số khối') ?></th>
                                        <th class="text-end"><?= __('Cước vận chuyển') ?></th>
                                        <th><?= __('Trạng thái') ?></th>
                                        <th><?= __('Ngày tạo') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($orders)): ?>
                                    <tr><td colspan="7" class="text-center text-muted"><?= __('Chưa có đơn hàng') ?></td></tr>
                                    <?php else: ?>
                                    <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= base_url('staffcn/orders-detail?id=' . $order['id']) ?>">
                                                <strong><?= htmlspecialchars($order['product_code'] ?? $order['order_code'] ?? '') ?></strong>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if ($order['product_type'] === 'wholesale'): ?>
                                            <span class="badge bg-primary-subtle text-primary"><?= __('Hàng lô') ?></span>
                                            <?php else: ?>
                                            <span class="badge bg-info-subtle text-info"><?= __('Hàng lẻ') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <?php
                                            $weightKg = floatval($order['pkg_weight_actual'] ?? 0) ?: floatval($order['pkg_weight_charged'] ?? 0) ?: floatval($order['order_weight_actual'] ?? 0) ?: floatval($order['order_weight_charged'] ?? 0);
                                            $cbm = floatval($order['total_cbm'] ?? 0);
                                            if (floatval($order['volume_actual'] ?? 0) > 0) $cbm = floatval($order['volume_actual']);
                                        ?>
                                        <td class="text-end"><?= $weightKg > 0 ? rtrim(rtrim(number_format($weightKg, 2), '0'), '.') . ' kg' : '-' ?></td>
                                        <td class="text-end"><?= $cbm > 0 ? rtrim(rtrim(number_format($cbm, 4), '0'), '.') . ' m³' : '-' ?></td>
                                        <td class="text-end text-primary fw-bold"><?= format_vnd($order['ship_cost']) ?></td>
                                        <td><?= display_order_status($order['status']) ?></td>
                                        <td><?= $order['create_date'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Transactions -->
                <?php if (!empty($transactions)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Lịch sử giao dịch') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><?= __('Loại') ?></th>
                                        <th><?= __('Số tiền') ?></th>
                                        <th><?= __('Mô tả') ?></th>
                                        <th><?= __('Ngày') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $txn): ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $txnBadge = ['deposit' => 'success', 'payment' => 'primary', 'refund' => 'warning', 'adjustment' => 'info'];
                                            $txnLabel = ['deposit' => __('Nạp tiền'), 'payment' => __('Thanh toán'), 'refund' => __('Hoàn tiền'), 'adjustment' => __('Điều chỉnh')];
                                            ?>
                                            <span class="badge bg-<?= $txnBadge[$txn['type']] ?? 'secondary' ?>"><?= $txnLabel[$txn['type']] ?? $txn['type'] ?></span>
                                        </td>
                                        <td class="<?= $txn['amount'] >= 0 ? 'text-success' : 'text-danger' ?> fw-bold"><?= format_vnd($txn['amount']) ?></td>
                                        <td><?= htmlspecialchars($txn['description'] ?? '') ?></td>
                                        <td><?= $txn['create_date'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>
