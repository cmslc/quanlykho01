<?php
require_once(__DIR__.'/../../../models/is_admin.php');

$page_title = __('Tổng quan tài chính');

// Overall stats
$totalDeposit = $ToryHub->get_row_safe("SELECT COALESCE(SUM(amount),0) as total FROM `transactions` WHERE `type` = 'deposit'", [])['total'];
$totalPayment = $ToryHub->get_row_safe("SELECT COALESCE(SUM(ABS(amount)),0) as total FROM `transactions` WHERE `type` = 'payment'", [])['total'];
$totalRefund = $ToryHub->get_row_safe("SELECT COALESCE(SUM(amount),0) as total FROM `transactions` WHERE `type` = 'refund'", [])['total'];
$totalRevenue = $ToryHub->get_row_safe("SELECT COALESCE(SUM(grand_total),0) as total FROM `orders` WHERE `status` != 'cancelled'", [])['total'];
$totalShippingFee = $ToryHub->get_row_safe("SELECT COALESCE(SUM(shipping_fee_cn + shipping_fee_intl),0) as total FROM `orders` WHERE `status` != 'cancelled'", [])['total'];

// Customers with debt (negative balance)
$debtCustomers = $ToryHub->get_list_safe("SELECT `id`, `customer_code`, `fullname`, `balance`, `total_orders`, `total_spent`
    FROM `customers` WHERE `balance` < 0 ORDER BY `balance` ASC LIMIT 20", []);
$totalDebt = $ToryHub->get_row_safe("SELECT COALESCE(SUM(ABS(balance)),0) as total FROM `customers` WHERE `balance` < 0", [])['total'];

// Top spending customers
$topCustomers = $ToryHub->get_list_safe("SELECT `id`, `customer_code`, `fullname`, `balance`, `total_orders`, `total_spent`
    FROM `customers` ORDER BY `total_spent` DESC LIMIT 10", []);

// Monthly revenue (last 6 months)
$monthlyRevenue = $ToryHub->get_list_safe("SELECT DATE_FORMAT(create_date, '%Y-%m') as month,
    COUNT(*) as order_count, COALESCE(SUM(grand_total),0) as revenue
    FROM `orders` WHERE `status` != 'cancelled' AND create_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(create_date, '%Y-%m') ORDER BY month ASC", []);

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

        <!-- Main Stats -->
        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-end justify-content-between mt-2">
                            <div>
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Tổng doanh thu') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-success"><?= format_vnd($totalRevenue) ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success-subtle rounded fs-3"><i class="ri-money-dollar-circle-line text-success"></i></span>
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
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Phí vận chuyển') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-info"><?= format_vnd($totalShippingFee) ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-info-subtle rounded fs-3"><i class="ri-truck-line text-info"></i></span>
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
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Tổng công nợ') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-danger"><?= format_vnd($totalDebt) ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-danger-subtle rounded fs-3"><i class="ri-error-warning-line text-danger"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Monthly Revenue Chart -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Doanh thu theo tháng') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><?= __('Tháng') ?></th>
                                        <th class="text-end"><?= __('Số đơn') ?></th>
                                        <th class="text-end"><?= __('Doanh thu') ?></th>
                                        <th><?= __('Biểu đồ') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $maxRevenue = max(array_column($monthlyRevenue, 'revenue') ?: [1]);
                                    foreach ($monthlyRevenue as $m):
                                        $pct = $maxRevenue > 0 ? round($m['revenue'] / $maxRevenue * 100) : 0;
                                    ?>
                                    <tr>
                                        <td class="fw-bold"><?= $m['month'] ?></td>
                                        <td class="text-end"><?= $m['order_count'] ?></td>
                                        <td class="text-end text-success fw-bold"><?= format_vnd($m['revenue']) ?></td>
                                        <td style="width: 30%;">
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

                <!-- Top Customers -->
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
                                        <th class="text-end"><?= __('Tổng đơn') ?></th>
                                        <th class="text-end"><?= __('Tổng chi tiêu') ?></th>
                                        <th class="text-end"><?= __('Số dư') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topCustomers as $idx => $tc): ?>
                                    <tr>
                                        <td><?= $idx + 1 ?></td>
                                        <td>
                                            <a href="<?= base_url('admin/customers-detail&id=' . $tc['id']) ?>">
                                                <strong><?= htmlspecialchars($tc['customer_code']) ?></strong> - <?= htmlspecialchars($tc['fullname']) ?>
                                            </a>
                                        </td>
                                        <td class="text-end"><?= $tc['total_orders'] ?></td>
                                        <td class="text-end text-success fw-bold"><?= format_vnd($tc['total_spent']) ?></td>
                                        <td class="text-end <?= $tc['balance'] < 0 ? 'text-danger fw-bold' : '' ?>"><?= format_vnd($tc['balance']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Debt Customers -->
            <div class="col-lg-4">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="card-title text-white mb-0"><i class="ri-error-warning-line"></i> <?= __('Khách hàng nợ') ?> (<?= count($debtCustomers) ?>)</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($debtCustomers)): ?>
                        <div class="p-3 text-center text-muted"><?= __('Không có khách hàng nợ') ?></div>
                        <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($debtCustomers as $dc): ?>
                            <a href="<?= base_url('admin/customers-detail&id=' . $dc['id']) ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($dc['customer_code']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($dc['fullname']) ?></small>
                                    </div>
                                    <span class="text-danger fw-bold"><?= format_vnd($dc['balance']) ?></span>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-center">
                        <strong><?= __('Tổng nợ') ?>: <span class="text-danger"><?= format_vnd($totalDebt) ?></span></strong>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>
