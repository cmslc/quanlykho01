<?php
require_once(__DIR__.'/../../../models/is_staffvn.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Báo cáo kho hàng');

// Date filter
$filterDate = input_get('date') ?: date('Y-m-d');
$filterDateFrom = input_get('date_from') ?: $filterDate;
$filterDateTo = input_get('date_to') ?: $filterDate;

// Packages received today
$packagesReceived = $ToryHub->get_list_safe("
    SELECT p.*, GROUP_CONCAT(DISTINCT o.order_code SEPARATOR ', ') as order_codes,
           GROUP_CONCAT(DISTINCT c.fullname SEPARATOR ', ') as customer_names
    FROM `packages` p
    LEFT JOIN `package_orders` po ON p.id = po.package_id
    LEFT JOIN `orders` o ON po.order_id = o.id
    LEFT JOIN `customers` c ON o.customer_id = c.id
    WHERE DATE(p.vn_warehouse_date) >= ? AND DATE(p.vn_warehouse_date) <= ?
    GROUP BY p.id ORDER BY p.vn_warehouse_date DESC
", [$filterDateFrom, $filterDateTo]);

// Orders delivered today
$ordersDelivered = $ToryHub->get_list_safe("
    SELECT o.*, c.fullname as customer_name, c.customer_code,
           cod.amount as cod_amount, cod.payment_method as cod_method
    FROM `orders` o
    LEFT JOIN `customers` c ON o.customer_id = c.id
    LEFT JOIN `cod_collections` cod ON cod.order_id = o.id
    WHERE DATE(o.delivered_date) >= ? AND DATE(o.delivered_date) <= ?
    ORDER BY o.delivered_date DESC
", [$filterDateFrom, $filterDateTo]);

// Summary stats
$totalPkgReceived = count($packagesReceived);
$totalOrdersReceived = $ToryHub->num_rows_safe("SELECT * FROM `orders` WHERE DATE(vn_warehouse_date) >= ? AND DATE(vn_warehouse_date) <= ?", [$filterDateFrom, $filterDateTo]) ?: 0;
$totalOrdersDelivered = count($ordersDelivered);
$totalDeliveredValue = array_sum(array_column($ordersDelivered, 'grand_total'));

// COD summary
$codStats = $ToryHub->get_row_safe("
    SELECT COALESCE(SUM(amount), 0) as total_cod,
           COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN amount ELSE 0 END), 0) as cod_cash,
           COALESCE(SUM(CASE WHEN payment_method = 'transfer' THEN amount ELSE 0 END), 0) as cod_transfer,
           COALESCE(SUM(CASE WHEN payment_method = 'balance' THEN amount ELSE 0 END), 0) as cod_balance,
           COUNT(*) as cod_count
    FROM `cod_collections`
    WHERE DATE(create_date) >= ? AND DATE(create_date) <= ?
", [$filterDateFrom, $filterDateTo]);

// Total weight received
$totalWeight = 0;
foreach ($packagesReceived as $pkg) {
    $totalWeight += floatval($pkg['weight_charged'] ?: $pkg['weight_actual']);
}

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>

        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><i class="ri-bar-chart-box-line me-1"></i><?= __('Báo cáo kho hàng') ?></h4>
                </div>
            </div>
        </div>

        <!-- Date Filter -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card mb-0">
                    <div class="card-body py-3">
                        <form method="GET" class="row g-2 align-items-end">
                            <input type="hidden" name="module" value="staffvn">
                            <input type="hidden" name="action" value="reports">
                            <div class="col-auto">
                                <button type="button" class="btn btn-outline-secondary" id="btn-prev-day"><i class="ri-arrow-left-s-line"></i></button>
                            </div>
                            <div class="col-auto">
                                <label class="form-label mb-0 small"><?= __('Từ ngày') ?></label>
                                <input type="date" class="form-control" name="date_from" id="date-from" value="<?= $filterDateFrom ?>">
                            </div>
                            <div class="col-auto">
                                <label class="form-label mb-0 small"><?= __('Đến ngày') ?></label>
                                <input type="date" class="form-control" name="date_to" id="date-to" value="<?= $filterDateTo ?>">
                            </div>
                            <div class="col-auto">
                                <button type="button" class="btn btn-outline-secondary" id="btn-next-day"><i class="ri-arrow-right-s-line"></i></button>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary"><i class="ri-search-line me-1"></i><?= __('Xem') ?></button>
                            </div>
                            <div class="col-auto">
                                <a href="<?= base_url('ajaxs/staffvn/export.php') ?>?type=daily&date_from=<?= $filterDateFrom ?>&date_to=<?= $filterDateTo ?>" class="btn btn-outline-success">
                                    <i class="ri-file-excel-2-line me-1"></i><?= __('Xuất CSV') ?>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <p class="text-muted mb-1"><?= __('Kiện nhận') ?></p>
                                <h4 class="mb-0"><?= $totalPkgReceived ?></h4>
                                <small class="text-muted"><?= round($totalWeight, 1) ?> kg</small>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-info-subtle text-info rounded-circle fs-3"><i class="ri-inbox-archive-line"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <p class="text-muted mb-1"><?= __('Đơn nhận') ?></p>
                                <h4 class="mb-0"><?= $totalOrdersReceived ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-3"><i class="ri-file-list-3-line"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <p class="text-muted mb-1"><?= __('Đơn giao') ?></p>
                                <h4 class="mb-0"><?= $totalOrdersDelivered ?></h4>
                                <small class="text-muted"><?= format_vnd($totalDeliveredValue) ?></small>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success-subtle text-success rounded-circle fs-3"><i class="ri-truck-line"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <p class="text-muted mb-1"><?= __('COD đã thu') ?></p>
                                <h4 class="mb-0"><?= format_vnd($codStats['total_cod']) ?></h4>
                                <small class="text-muted"><?= $codStats['cod_count'] ?> <?= __('giao dịch') ?></small>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning text-dark rounded-circle fs-3"><i class="ri-money-dollar-circle-line"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- COD Breakdown -->
        <?php if ($codStats['total_cod'] > 0): ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body py-3">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <i class="ri-cash-line text-success fs-20 me-1"></i>
                                <strong><?= __('Tiền mặt') ?>:</strong> <?= format_vnd($codStats['cod_cash']) ?>
                            </div>
                            <div class="col-md-4">
                                <i class="ri-bank-card-line text-primary fs-20 me-1"></i>
                                <strong><?= __('Chuyển khoản') ?>:</strong> <?= format_vnd($codStats['cod_transfer']) ?>
                            </div>
                            <div class="col-md-4">
                                <i class="ri-wallet-3-line text-info fs-20 me-1"></i>
                                <strong><?= __('Trừ số dư') ?>:</strong> <?= format_vnd($codStats['cod_balance']) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tabs: Received / Delivered -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" role="tablist">
                            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-received"><i class="ri-inbox-archive-line me-1"></i><?= __('Kiện đã nhận') ?> (<?= $totalPkgReceived ?>)</a></li>
                            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-delivered"><i class="ri-truck-line me-1"></i><?= __('Đơn đã giao') ?> (<?= $totalOrdersDelivered ?>)</a></li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- Tab: Received -->
                            <div class="tab-pane active" id="tab-received">
                                <?php if (empty($packagesReceived)): ?>
                                    <p class="text-center text-muted py-3"><?= __('Không có kiện hàng nhận trong khoảng thời gian này') ?></p>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th><?= __('Mã kiện') ?></th>
                                                <th><?= __('Tracking') ?></th>
                                                <th><?= __('Cân nặng') ?></th>
                                                <th><?= __('Đơn hàng') ?></th>
                                                <th><?= __('Khách hàng') ?></th>
                                                <th><?= __('Giờ nhận') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($packagesReceived as $pkg): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($pkg['package_code']) ?></strong></td>
                                                <td>
                                                    <small><?= htmlspecialchars($pkg['tracking_intl'] ?: ($pkg['tracking_vn'] ?: '-')) ?></small>
                                                </td>
                                                <td><?= floatval($pkg['weight_charged'] ?: $pkg['weight_actual']) ?> kg</td>
                                                <td><small><?= htmlspecialchars($pkg['order_codes'] ?: '-') ?></small></td>
                                                <td><small><?= htmlspecialchars($pkg['customer_names'] ?: '-') ?></small></td>
                                                <td><?= $pkg['vn_warehouse_date'] ? date('H:i', strtotime($pkg['vn_warehouse_date'])) : '-' ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Tab: Delivered -->
                            <div class="tab-pane" id="tab-delivered">
                                <?php if (empty($ordersDelivered)): ?>
                                    <p class="text-center text-muted py-3"><?= __('Không có đơn hàng giao trong khoảng thời gian này') ?></p>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th><?= __('Mã đơn') ?></th>
                                                <th><?= __('Khách hàng') ?></th>
                                                <th><?= __('Sản phẩm') ?></th>
                                                <th><?= __('Tổng VND') ?></th>
                                                <th><?= __('COD') ?></th>
                                                <th><?= __('PT thanh toán') ?></th>
                                                <th><?= __('Giờ giao') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $methodLabels = ['cash' => __('Tiền mặt'), 'transfer' => __('Chuyển khoản'), 'balance' => __('Trừ số dư')];
                                            foreach ($ordersDelivered as $order): ?>
                                            <tr>
                                                <td><strong><?= $order['order_code'] ?></strong></td>
                                                <td>
                                                    <small><?= htmlspecialchars($order['customer_code'] ?? '') ?></small>
                                                    <br><small class="text-muted"><?= htmlspecialchars($order['customer_name'] ?? '') ?></small>
                                                </td>
                                                <td><small><?= htmlspecialchars(mb_strimwidth($order['product_name'] ?? '', 0, 25, '...')) ?></small></td>
                                                <td class="text-end"><?= format_vnd($order['grand_total']) ?></td>
                                                <td class="text-end">
                                                    <?php if ($order['cod_amount']): ?>
                                                    <span class="text-success"><?= format_vnd($order['cod_amount']) ?></span>
                                                    <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><small><?= $methodLabels[$order['cod_method']] ?? '-' ?></small></td>
                                                <td><?= $order['delivered_date'] ? date('H:i', strtotime($order['delivered_date'])) : '-' ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
$(document).ready(function(){
    // Prev/next day navigation
    function shiftDate(days) {
        var from = new Date($('#date-from').val());
        var to = new Date($('#date-to').val());
        from.setDate(from.getDate() + days);
        to.setDate(to.getDate() + days);
        $('#date-from').val(from.toISOString().split('T')[0]);
        $('#date-to').val(to.toISOString().split('T')[0]);
        $('#date-from').closest('form').submit();
    }
    $('#btn-prev-day').on('click', function(){ shiftDate(-1); });
    $('#btn-next-day').on('click', function(){ shiftDate(1); });
});
</script>
