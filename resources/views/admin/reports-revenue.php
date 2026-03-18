<?php
require_once(__DIR__.'/../../../models/is_admin.php');

$page_title = __('Báo cáo doanh thu & đơn hàng');

$filterDateFrom = input_get('date_from') ?: date('Y-m-01', strtotime('first day of previous month'));
$filterDateTo = input_get('date_to') ?: date('Y-m-d');
$filterGroup = input_get('group_by') ?: 'day';

// Revenue by period
$groupFormat = $filterGroup === 'month' ? '%Y-%m' : ($filterGroup === 'week' ? '%Y-%u' : '%Y-%m-%d');

$revenueData = $ToryHub->get_list_safe("SELECT DATE_FORMAT(create_date, '$groupFormat') as period,
    COUNT(*) as order_count,
    COALESCE(SUM(grand_total),0) as grand_total
    FROM `orders` WHERE `status` != 'cancelled'
    AND DATE(create_date) >= ? AND DATE(create_date) <= ?
    GROUP BY period ORDER BY period DESC", [$filterDateFrom, $filterDateTo]);

// Totals
$totals = ['order_count' => 0, 'grand_total' => 0];
foreach ($revenueData as $r) {
    $totals['order_count'] += $r['order_count'];
    $totals['grand_total'] += $r['grand_total'];
}

// Order stats (from reports-orders)
$completedOrders = $ToryHub->get_row_safe("SELECT COUNT(*) as cnt FROM `orders` WHERE `status` = 'delivered' AND DATE(create_date) >= ? AND DATE(create_date) <= ?", [$filterDateFrom, $filterDateTo])['cnt'] ?: 0;

$avgOrder = $totals['order_count'] > 0 ? $totals['grand_total'] / $totals['order_count'] : 0;

// Status breakdown
$statusStats = $ToryHub->get_list_safe("SELECT status, COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as total
    FROM `orders` WHERE DATE(create_date) >= ? AND DATE(create_date) <= ?
    GROUP BY status ORDER BY cnt DESC", [$filterDateFrom, $filterDateTo]);

// Weight stats
$weightStats = $ToryHub->get_row_safe("SELECT
    COALESCE(SUM(weight_charged),0) as total_weight,
    COALESCE(AVG(weight_charged),0) as avg_weight,
    COALESCE(MAX(weight_charged),0) as max_weight
    FROM `orders` WHERE `status` != 'cancelled' AND weight_charged > 0
    AND DATE(create_date) >= ? AND DATE(create_date) <= ?", [$filterDateFrom, $filterDateTo]);

// Revenue by platform
$platformData = $ToryHub->get_list_safe("SELECT platform, COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as total
    FROM `orders` WHERE `status` != 'cancelled'
    AND DATE(create_date) >= ? AND DATE(create_date) <= ?
    GROUP BY platform ORDER BY total DESC", [$filterDateFrom, $filterDateTo]);

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Báo cáo doanh thu & đơn hàng') ?></h4>
                    <a href="<?= base_url('ajaxs/admin/export.php') ?>?type=revenue&date_from=<?= $filterDateFrom ?>&date_to=<?= $filterDateTo ?>" class="btn btn-success">
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
                        <form method="GET" action="<?= base_url('admin/reports-revenue') ?>" class="row g-3 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label"><?= __('Từ ngày') ?></label>
                                <input type="date" class="form-control" name="date_from" value="<?= $filterDateFrom ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><?= __('Đến ngày') ?></label>
                                <input type="date" class="form-control" name="date_to" value="<?= $filterDateTo ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><?= __('Nhóm theo') ?></label>
                                <select class="form-select" name="group_by">
                                    <option value="day" <?= $filterGroup == 'day' ? 'selected' : '' ?>><?= __('Ngày') ?></option>
                                    <option value="week" <?= $filterGroup == 'week' ? 'selected' : '' ?>><?= __('Tuần') ?></option>
                                    <option value="month" <?= $filterGroup == 'month' ? 'selected' : '' ?>><?= __('Tháng') ?></option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary"><i class="ri-search-line"></i> <?= __('Xem') ?></button>
                                <a href="<?= base_url('admin/reports-revenue') ?>" class="btn btn-outline-secondary"><?= __('Reset') ?></a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-end justify-content-between mt-2">
                            <div>
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Tổng doanh thu') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-success"><?= format_vnd($totals['grand_total']) ?></h4>
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
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Tổng đơn') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0"><?= $totals['order_count'] ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle rounded fs-3"><i class="ri-shopping-cart-2-line text-primary"></i></span>
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
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Đã giao') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-success"><?= $completedOrders ?></h4>
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
                        <div class="d-flex align-items-end justify-content-between mt-2">
                            <div>
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Giá trị TB/đơn') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-warning"><?= format_vnd($avgOrder) ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning-subtle rounded fs-3"><i class="ri-bar-chart-line text-warning"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart -->
        <?php if (!empty($revenueData)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Biểu đồ doanh thu') ?></h5>
                    </div>
                    <div class="card-body">
                        <div id="revenue-chart" style="min-height: 300px;"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Revenue Table -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Chi tiết doanh thu') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><?= __('Kỳ') ?></th>
                                        <th class="text-end"><?= __('Số đơn') ?></th>
                                        <th class="text-end"><?= __('Doanh thu') ?></th>
                                        <th style="width: 30%;"><?= __('Biểu đồ') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $maxRevenue = max(array_column($revenueData, 'grand_total') ?: [1]);
                                    foreach ($revenueData as $r):
                                        $pct = $maxRevenue > 0 ? round($r['grand_total'] / $maxRevenue * 100) : 0;
                                    ?>
                                    <tr>
                                        <td class="fw-bold"><?= $r['period'] ?></td>
                                        <td class="text-end"><?= $r['order_count'] ?></td>
                                        <td class="text-end fw-bold text-success"><?= format_vnd($r['grand_total']) ?></td>
                                        <td>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-success" style="width: <?= $pct ?>%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($revenueData)): ?>
                                    <tr><td colspan="4" class="text-center text-muted"><?= __('Không có dữ liệu') ?></td></tr>
                                    <?php endif; ?>
                                </tbody>
                                <?php if (!empty($revenueData)): ?>
                                <tfoot class="table-light">
                                    <tr class="fw-bold">
                                        <td><?= __('TỔNG') ?></td>
                                        <td class="text-end"><?= $totals['order_count'] ?></td>
                                        <td class="text-end text-success"><?= format_vnd($totals['grand_total']) ?></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar Stats -->
            <div class="col-lg-4">
                <!-- Status Breakdown -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Theo trạng thái') ?></h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($statusStats as $ss): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div><?= display_order_status($ss['status']) ?></div>
                            <div class="text-end">
                                <span class="badge bg-secondary"><?= $ss['cnt'] ?></span>
                                <br><small class="text-muted"><?= format_vnd($ss['total']) ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($statusStats)): ?>
                        <p class="text-muted text-center mb-0"><?= __('Không có dữ liệu') ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Weight Stats -->
                <?php if ($weightStats && $weightStats['total_weight'] > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Thống kê cân nặng') ?></h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted"><?= __('Tổng cân') ?></td><td class="fw-bold"><?= number_format($weightStats['total_weight'], 2) ?> kg</td></tr>
                            <tr><td class="text-muted"><?= __('TB/đơn') ?></td><td><?= number_format($weightStats['avg_weight'], 2) ?> kg</td></tr>
                            <tr><td class="text-muted"><?= __('Nặng nhất') ?></td><td><?= number_format($weightStats['max_weight'], 2) ?> kg</td></tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Platform Breakdown -->
                <?php if (!empty($platformData)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Theo nền tảng') ?></h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $platformTotal = array_sum(array_column($platformData, 'total'));
                        foreach ($platformData as $p):
                            $pct = $platformTotal > 0 ? round($p['total'] / $platformTotal * 100) : 0;
                            $colors = ['taobao' => 'danger', '1688' => 'warning', 'alibaba' => 'primary', 'other' => 'secondary'];
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span><?= display_platform($p['platform']) ?> (<?= $p['cnt'] ?> <?= __('đơn') ?>)</span>
                                <span class="fw-bold"><?= format_vnd($p['total']) ?></span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-<?= $colors[$p['platform']] ?? 'info' ?>" style="width: <?= $pct ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>

<?php if (!empty($revenueData)): ?>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
(function(){
    var chartData = <?= json_encode(array_reverse($revenueData)) ?>;
    var categories = chartData.map(function(d){ return d.period; });
    var revenues = chartData.map(function(d){ return parseFloat(d.grand_total); });
    var orders = chartData.map(function(d){ return parseInt(d.order_count); });

    function shortVND(val) {
        if (val >= 1000000000) return (val/1000000000).toFixed(1) + 'B';
        if (val >= 1000000) return (val/1000000).toFixed(1) + 'M';
        if (val >= 1000) return (val/1000).toFixed(0) + 'K';
        return val;
    }
    function formatVND(n) { return new Intl.NumberFormat('vi-VN').format(Math.round(n)) + 'đ'; }

    new ApexCharts(document.querySelector('#revenue-chart'), {
        series: [{
            name: '<?= __("Doanh thu") ?>',
            type: 'area',
            data: revenues
        }, {
            name: '<?= __("Số đơn") ?>',
            type: 'line',
            data: orders
        }],
        chart: { height: 300, type: 'line', toolbar: { show: false }, zoom: { enabled: false } },
        colors: ['#0ab39c', '#405189'],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: [2, 2] },
        fill: {
            type: ['gradient', 'solid'],
            gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 }
        },
        xaxis: { categories: categories, labels: { rotate: -45, style: { fontSize: '11px' } } },
        yaxis: [{
            title: { text: '<?= __("Doanh thu") ?>' },
            labels: { formatter: function(val){ return shortVND(val); } }
        }, {
            opposite: true,
            title: { text: '<?= __("Số đơn") ?>' },
            labels: { formatter: function(val){ return Math.round(val); } }
        }],
        tooltip: {
            shared: true,
            y: { formatter: function(val, opts) { return opts.seriesIndex === 0 ? formatVND(val) : val + ' <?= __("đơn") ?>'; } }
        },
        grid: { borderColor: '#f1f1f1' }
    }).render();
})();
</script>
<?php endif; ?>
