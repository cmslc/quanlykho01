<?php
require_once(__DIR__.'/../../../models/is_admin.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$id = intval(input_get('id'));
$customer = $ToryHub->get_row_safe("SELECT * FROM `customers` WHERE `id` = ?", [$id]);
if (!$customer) {
    redirect(base_url('admin/customers-list'));
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
                    <div>
                        <a href="<?= base_url('admin/customers-edit?id=' . $customer['id']) ?>" class="btn btn-sm btn-warning">
                            <i class="ri-pencil-line"></i> <?= __('Sửa') ?>
                        </a>
                        <a href="<?= base_url('admin/customers-list') ?>" class="btn btn-sm btn-secondary">
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
                                <h4 class="fs-22 fw-semibold mt-4 mb-0"><?= $customer['total_orders'] ?></h4>
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
            <!-- Customer Details -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Thông tin liên hệ') ?></h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted"><?= __('Họ tên') ?></td><td class="fw-bold"><?= htmlspecialchars($customer['fullname']) ?></td></tr>
                            <tr><td class="text-muted"><?= __('Điện thoại') ?></td><td><?= htmlspecialchars($customer['phone'] ?? '-') ?></td></tr>
                            <tr><td class="text-muted">Email</td><td><?= htmlspecialchars($customer['email'] ?? '-') ?></td></tr>
                            <tr><td class="text-muted"><?= __('Địa chỉ VN') ?></td><td><?= htmlspecialchars($customer['address_vn'] ?? '-') ?></td></tr>
                            <tr><td class="text-muted">Zalo</td><td><?= htmlspecialchars($customer['zalo'] ?? '-') ?></td></tr>
                            <tr><td class="text-muted">WeChat</td><td><?= htmlspecialchars($customer['wechat'] ?? '-') ?></td></tr>
                            <tr><td class="text-muted"><?= __('Ghi chú') ?></td><td><?= htmlspecialchars($customer['note'] ?? '-') ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Orders -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0"><?= __('Đơn hàng') ?></h5>
                        <a href="<?= base_url('admin/orders-add?customer_id=' . $customer['id']) ?>" class="btn btn-sm btn-primary">
                            <i class="ri-add-line"></i> <?= __('Tạo đơn') ?>
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><?= __('Mã hàng') ?></th>
                                        <th><?= __('Sản phẩm') ?></th>
                                        <th><?= __('Cước vận chuyển') ?></th>
                                        <th><?= __('Trạng thái') ?></th>
                                        <th><?= __('Ngày tạo') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($orders)): ?>
                                    <tr><td colspan="5" class="text-center text-muted"><?= __('Chưa có đơn hàng') ?></td></tr>
                                    <?php else: ?>
                                    <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><a href="<?= base_url('admin/orders-detail?id=' . $order['id']) ?>"><strong><?= htmlspecialchars($order['product_code'] ?? '') ?></strong></a></td>
                                        <td><?= htmlspecialchars(mb_strimwidth($order['product_name'] ?? '', 0, 40, '...')) ?></td>
                                        <td class="text-primary fw-bold"><?= format_vnd($order['ship_cost']) ?></td>
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
