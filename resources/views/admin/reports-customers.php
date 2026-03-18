<?php
require_once(__DIR__.'/../../../models/is_admin.php');

$page_title = __('Báo cáo khách hàng');

$filterDateFrom = input_get('date_from') ?: date('Y-m-01', strtotime('first day of previous month'));
$filterDateTo = input_get('date_to') ?: date('Y-m-d');

// Customer stats
$totalCustomers = $ToryHub->num_rows_safe("SELECT * FROM `customers`", []) ?: 0;
$newCustomers = $ToryHub->num_rows_safe("SELECT * FROM `customers` WHERE DATE(create_date) >= ? AND DATE(create_date) <= ?", [$filterDateFrom, $filterDateTo]) ?: 0;
$activeCustomers = $ToryHub->get_row_safe("SELECT COUNT(DISTINCT customer_id) as cnt FROM `orders` WHERE DATE(create_date) >= ? AND DATE(create_date) <= ?", [$filterDateFrom, $filterDateTo])['cnt'] ?: 0;
$periodSpent = floatval($ToryHub->get_row_safe("SELECT COALESCE(SUM(grand_total),0) as total FROM `orders` WHERE `status` != 'cancelled' AND DATE(create_date) >= ? AND DATE(create_date) <= ?", [$filterDateFrom, $filterDateTo])['total']);

// Customer type breakdown
$typeStats = $ToryHub->get_list_safe("SELECT customer_type, COUNT(*) as cnt, COALESCE(SUM(total_spent),0) as spent
    FROM `customers` GROUP BY customer_type", []);

// Top customers by spending in period
$topCustomers = $ToryHub->get_list_safe("SELECT c.id, c.fullname, c.customer_type, c.total_spent,
    COUNT(o.id) as period_orders, COALESCE(SUM(o.grand_total),0) as period_spent
    FROM `customers` c
    LEFT JOIN `orders` o ON c.id = o.customer_id AND o.status != 'cancelled'
        AND DATE(o.create_date) >= ? AND DATE(o.create_date) <= ?
    GROUP BY c.id HAVING period_orders > 0
    ORDER BY period_spent DESC LIMIT 20", [$filterDateFrom, $filterDateTo]);

// Customers with no orders in period (inactive)
$inactiveCustomers = $ToryHub->get_list_safe("SELECT c.id, c.fullname, c.customer_type, c.total_spent,
    (SELECT MAX(o.create_date) FROM orders o WHERE o.customer_id = c.id) as last_order_date
    FROM `customers` c
    WHERE c.id NOT IN (SELECT DISTINCT customer_id FROM orders WHERE DATE(create_date) >= ? AND DATE(create_date) <= ? AND status != 'cancelled')
    ORDER BY last_order_date DESC LIMIT 20", [$filterDateFrom, $filterDateTo]);

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Báo cáo khách hàng') ?></h4>
                    <a href="<?= base_url('ajaxs/admin/export.php') ?>?type=customers&date_from=<?= $filterDateFrom ?>&date_to=<?= $filterDateTo ?>" class="btn btn-success">
                        <i class="ri-file-excel-2-line"></i> <?= __('Xuất Excel') ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="<?= base_url('admin/reports-customers') ?>" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label"><?= __('Từ ngày') ?></label>
                                <input type="date" class="form-control" name="date_from" value="<?= $filterDateFrom ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><?= __('Đến ngày') ?></label>
                                <input type="date" class="form-control" name="date_to" value="<?= $filterDateTo ?>">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary"><i class="ri-search-line"></i> <?= __('Xem') ?></button>
                                <a href="<?= base_url('admin/reports-customers') ?>" class="btn btn-outline-secondary"><?= __('Reset') ?></a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary -->
        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-end justify-content-between mt-2">
                            <div>
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Tổng khách hàng') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0"><?= $totalCustomers ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-info-subtle rounded fs-3"><i class="ri-user-line text-info"></i></span>
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
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Khách mới trong kỳ') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-success"><?= $newCustomers ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success-subtle rounded fs-3"><i class="ri-user-add-line text-success"></i></span>
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
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Có đơn trong kỳ') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-primary"><?= $activeCustomers ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle rounded fs-3"><i class="ri-user-star-line text-primary"></i></span>
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
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Chi tiêu trong kỳ') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-warning"><?= format_vnd($periodSpent) ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning-subtle rounded fs-3"><i class="ri-money-dollar-circle-line text-warning"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart: Top customers -->
        <?php if (!empty($topCustomers)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Top khách hàng trong kỳ') ?></h5>
                    </div>
                    <div class="card-body">
                        <div id="top-customers-chart" style="min-height: 300px;"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Top Customers Table -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Chi tiết khách hàng trong kỳ') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th><?= __('Loại') ?></th>
                                        <th class="text-end"><?= __('Đơn trong kỳ') ?></th>
                                        <th class="text-end"><?= __('Chi tiêu trong kỳ') ?></th>
                                        <th class="text-end"><?= __('Tổng chi tiêu') ?></th>
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
                                        <td><?= display_customer_type($tc['customer_type']) ?></td>
                                        <td class="text-end"><?= $tc['period_orders'] ?></td>
                                        <td class="text-end text-success fw-bold"><?= format_vnd($tc['period_spent']) ?></td>
                                        <td class="text-end"><?= format_vnd($tc['total_spent']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($topCustomers)): ?>
                                    <tr><td colspan="6" class="text-center text-muted"><?= __('Không có dữ liệu') ?></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Inactive Customers -->
                <?php if (!empty($inactiveCustomers)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Khách hàng không hoạt động trong kỳ') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th><?= __('Loại') ?></th>
                                        <th class="text-end"><?= __('Tổng chi tiêu') ?></th>
                                        <th><?= __('Đơn cuối') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inactiveCustomers as $ic): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= base_url('admin/customers-detail?id=' . $ic['id']) ?>">
                                                <?= htmlspecialchars($ic['fullname']) ?>
                                            </a>
                                        </td>
                                        <td><?= display_customer_type($ic['customer_type']) ?></td>
                                        <td class="text-end"><?= format_vnd($ic['total_spent']) ?></td>
                                        <td><?= $ic['last_order_date'] ? date('d/m/Y', strtotime($ic['last_order_date'])) : '-' ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Customer Type Breakdown -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Phân loại khách hàng') ?></h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($typeStats as $ts): ?>
                        <div class="mb-4">
                            <div class="d-flex justify-content-between mb-1">
                                <span><?= display_customer_type($ts['customer_type']) ?></span>
                                <span class="fw-bold"><?= $ts['cnt'] ?> <?= __('KH') ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small class="text-muted"><?= __('Tổng chi tiêu') ?>: <?= format_vnd($ts['spent']) ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>

<?php if (!empty($topCustomers)): ?>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
(function(){
    var topData = <?= json_encode(array_slice($topCustomers, 0, 10)) ?>;
    var names = topData.map(function(c){ return c.fullname; });
    var spent = topData.map(function(c){ return parseFloat(c.period_spent); });

    function shortVND(val) {
        if (val >= 1000000000) return (val/1000000000).toFixed(1) + 'B';
        if (val >= 1000000) return (val/1000000).toFixed(1) + 'M';
        if (val >= 1000) return (val/1000).toFixed(0) + 'K';
        return val;
    }
    function formatVND(n) { return new Intl.NumberFormat('vi-VN').format(Math.round(n)) + 'đ'; }

    new ApexCharts(document.querySelector('#top-customers-chart'), {
        series: [{ name: '<?= __("Chi tiêu trong kỳ") ?>', data: spent }],
        chart: { type: 'bar', height: 300, toolbar: { show: false } },
        plotOptions: { bar: { horizontal: true, barHeight: '50%' } },
        colors: ['#405189'],
        dataLabels: {
            enabled: true,
            formatter: function(val){ return shortVND(val); },
            offsetX: 10,
            style: { fontSize: '11px' }
        },
        xaxis: {
            categories: names,
            labels: { formatter: function(val){ return shortVND(val); } }
        },
        yaxis: { labels: { maxWidth: 120, style: { fontSize: '12px' } } },
        grid: { borderColor: '#f1f1f1' },
        tooltip: { y: { formatter: function(val){ return formatVND(val); } } }
    }).render();
})();
</script>
<?php endif; ?>
