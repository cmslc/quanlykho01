<?php
require_once(__DIR__.'/../../../models/is_admin.php');
require_once(__DIR__.'/../../../libs/csrf.php');
require_once(__DIR__.'/../../../libs/database/orders.php');

$page_title = 'Dashboard';

// ===== KPI Data =====
$monthStart = date('Y-m-01');

$revenueThisMonth = $ToryHub->get_row_safe(
    "SELECT COALESCE(SUM(grand_total),0) as total FROM `orders` WHERE `status` != 'cancelled' AND DATE(create_date) >= ?",
    [$monthStart]
)['total'];

$ordersThisMonth = $ToryHub->num_rows_safe(
    "SELECT * FROM `orders` WHERE DATE(create_date) >= ?", [$monthStart]
) ?: 0;

$packagesInTransit = $ToryHub->num_rows_safe(
    "SELECT * FROM `packages` WHERE `status` IN ('cn_warehouse','shipping','vn_warehouse')", []
) ?: 0;

$totalCustomers = $ToryHub->num_rows_safe("SELECT * FROM `customers`", []) ?: 0;

$pendingOrders = $ToryHub->num_rows_safe(
    "SELECT * FROM `orders` WHERE `status` = 'cn_warehouse'", []
) ?: 0;

$exchangeRate = get_exchange_rate();

// ===== Chart Data (initial load) =====
$revenueChartData = $ToryHub->get_list_safe(
    "SELECT DATE(create_date) as date, COALESCE(SUM(grand_total),0) as revenue, COUNT(*) as orders_count
     FROM `orders` WHERE `status` != 'cancelled' AND create_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
     GROUP BY DATE(create_date) ORDER BY date ASC", []
);

$orderStatusData = $ToryHub->get_list_safe(
    "SELECT `status`, COUNT(*) as count FROM `orders` WHERE `status` != 'cancelled'
     GROUP BY `status` ORDER BY FIELD(status,'cn_warehouse','packed','loading','shipping','vn_warehouse','delivered')", []
);

$packagePipelineData = $ToryHub->get_list_safe(
    "SELECT `status`, COUNT(*) as count FROM `packages` GROUP BY `status`
     ORDER BY FIELD(status,'cn_warehouse','packed','loading','shipping','vn_warehouse','delivered')", []
);

$topCustomersData = $ToryHub->get_list_safe(
    "SELECT `customer_code`, `fullname`, `total_spent` FROM `customers` ORDER BY `total_spent` DESC LIMIT 5", []
);

// ===== Table Data =====
$recentOrders = $ToryHub->get_list_safe(
    "SELECT o.*, c.fullname as customer_name, c.customer_code
     FROM `orders` o LEFT JOIN `customers` c ON o.customer_id = c.id
     ORDER BY o.create_date DESC LIMIT 10", []
);

$cnWarehouseOrders = $ToryHub->num_rows_safe("SELECT * FROM `orders` WHERE `status` = 'cn_warehouse'", []) ?: 0;
$shippingOrders = $ToryHub->num_rows_safe("SELECT * FROM `orders` WHERE `status` = 'shipping'", []) ?: 0;
$vnWarehouseOrders = $ToryHub->num_rows_safe("SELECT * FROM `orders` WHERE `status` = 'vn_warehouse'", []) ?: 0;

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <style>
            .bg-purple-subtle { background-color: rgba(111, 66, 193, 0.18) !important; }
            .text-purple { color: #6f42c1 !important; }
        </style>

        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Dashboard</h4>
                    <div class="page-title-right">
                        <span class="text-muted fs-13"><i class="ri-calendar-line me-1"></i><?= date('d/m/Y H:i') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 1: KPI Cards -->
        <div class="row">
            <div class="col-xl-2 col-md-4 col-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1 overflow-hidden">
                                <p class="text-uppercase fw-medium text-muted text-truncate mb-0 fs-12"><?= __('Doanh thu tháng') ?></p>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success-subtle rounded fs-3"><i class="ri-money-dollar-circle-line text-success"></i></span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <h4 class="fs-20 fw-semibold mb-0 text-success"><?= format_vnd($revenueThisMonth) ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1 overflow-hidden">
                                <p class="text-uppercase fw-medium text-muted text-truncate mb-0 fs-12"><?= __('Đơn tháng này') ?></p>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle rounded fs-3"><i class="ri-shopping-cart-2-line text-primary"></i></span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <h4 class="fs-20 fw-semibold mb-0"><?= $ordersThisMonth ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1 overflow-hidden">
                                <p class="text-uppercase fw-medium text-muted text-truncate mb-0 fs-12"><?= __('Kiện đang chuyển') ?></p>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-purple-subtle rounded fs-3"><i class="ri-archive-line text-purple"></i></span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <h4 class="fs-20 fw-semibold mb-0"><?= $packagesInTransit ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1 overflow-hidden">
                                <p class="text-uppercase fw-medium text-muted text-truncate mb-0 fs-12"><?= __('Khách hàng') ?></p>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success-subtle rounded fs-3"><i class="ri-user-heart-line text-success"></i></span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <h4 class="fs-20 fw-semibold mb-0"><?= $totalCustomers ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1 overflow-hidden">
                                <p class="text-uppercase fw-medium text-muted text-truncate mb-0 fs-12"><?= __('Chờ xử lý') ?></p>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning rounded fs-3"><i class="ri-time-line text-dark"></i></span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <h4 class="fs-20 fw-semibold mb-0"><?= $pendingOrders ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1 overflow-hidden">
                                <p class="text-uppercase fw-medium text-muted text-truncate mb-0 fs-12"><?= __('Tỷ giá CNY') ?></p>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-info-subtle rounded fs-3"><i class="ri-money-cny-circle-line text-info"></i></span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <h4 class="fs-20 fw-semibold mb-0"><?= format_vnd($exchangeRate) ?>/¥</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 2: Revenue Chart + Order Status Donut -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0"><i class="ri-line-chart-line me-1"></i><?= __('Doanh thu') ?></h5>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-primary period-btn" data-period="7d">7D</button>
                            <button type="button" class="btn btn-outline-primary period-btn active" data-period="30d">30D</button>
                            <button type="button" class="btn btn-outline-primary period-btn" data-period="90d">90D</button>
                            <button type="button" class="btn btn-outline-primary period-btn" data-period="12m">12M</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="revenue-area-chart" style="min-height: 350px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="ri-pie-chart-2-line me-1"></i><?= __('Trạng thái đơn hàng') ?></h5>
                    </div>
                    <div class="card-body">
                        <div id="order-status-donut" style="min-height: 350px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 3: Package Pipeline + Top Customers -->
        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0"><i class="ri-archive-line me-1"></i><?= __('Tình trạng kiện hàng') ?></h5>
                        <a href="<?= base_url('admin/packages-list') ?>" class="btn btn-sm btn-soft-primary"><?= __('Xem tất cả') ?></a>
                    </div>
                    <div class="card-body">
                        <div id="package-pipeline-chart" style="min-height: 280px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0"><i class="ri-vip-crown-line me-1"></i><?= __('Top khách hàng') ?></h5>
                        <a href="<?= base_url('admin/customers-list') ?>" class="btn btn-sm btn-soft-primary"><?= __('Xem tất cả') ?></a>
                    </div>
                    <div class="card-body">
                        <div id="top-customers-chart" style="min-height: 280px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 4: Recent Orders + Sidebar -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0"><i class="ri-file-list-3-line me-1"></i><?= __('Đơn hàng gần đây') ?></h5>
                        <a href="<?= base_url('admin/orders-list') ?>" class="btn btn-sm btn-soft-primary"><?= __('Xem tất cả') ?></a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th><?= __('Mã đơn') ?></th>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th><?= __('Sản phẩm') ?></th>
                                        <th class="text-end"><?= __('Tổng tiền') ?></th>
                                        <th><?= __('Trạng thái') ?></th>
                                        <th><?= __('Ngày tạo') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentOrders)): ?>
                                    <tr><td colspan="6" class="text-center text-muted py-4"><?= __('Chưa có đơn hàng') ?></td></tr>
                                    <?php else: ?>
                                    <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td><a href="<?= base_url('admin/orders-detail&id=' . $order['id']) ?>"><strong><?= $order['order_code'] ?></strong></a></td>
                                        <td><?= htmlspecialchars($order['customer_name'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars(mb_strimwidth($order['product_name'] ?? '', 0, 25, '...')) ?></td>
                                        <td class="text-end fw-semibold"><?= format_vnd($order['grand_total']) ?></td>
                                        <td><?= display_order_status($order['status']) ?></td>
                                        <td class="text-muted fs-12"><?= date('d/m H:i', strtotime($order['create_date'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <!-- Warehouse Status -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="ri-building-2-line me-1"></i><?= __('Tình trạng kho') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span><i class="ri-map-pin-line me-1 text-warning"></i><?= __('Tại kho Trung Quốc') ?></span>
                            <span class="badge bg-warning fs-13"><?= $cnWarehouseOrders ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span><i class="ri-truck-line me-1 text-purple"></i><?= __('Đang vận chuyển') ?></span>
                            <span class="badge bg-purple fs-13"><?= $shippingOrders ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span><i class="ri-home-4-line me-1 text-success"></i><?= __('Tại kho Việt Nam') ?></span>
                            <span class="badge bg-success fs-13"><?= $vnWarehouseOrders ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center">
                            <span><strong><i class="ri-error-warning-line me-1 text-danger"></i><?= __('Chờ xử lý') ?></strong></span>
                            <span class="badge bg-danger fs-13"><?= $pendingOrders ?></span>
                        </div>
                    </div>
                </div>
                <!-- Quick Links -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="ri-flashlight-line me-1"></i><?= __('Truy cập nhanh') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="<?= base_url('admin/orders-add') ?>" class="btn btn-soft-primary btn-sm text-start">
                                <i class="ri-add-line me-1"></i><?= __('Tạo đơn mới') ?>
                            </a>
                            <a href="<?= base_url('admin/packages-list') ?>" class="btn btn-soft-info btn-sm text-start">
                                <i class="ri-archive-line me-1"></i><?= __('Quản lý kiện hàng') ?>
                            </a>
                            <a href="<?= base_url('admin/finance-summary') ?>" class="btn btn-soft-success btn-sm text-start">
                                <i class="ri-money-cny-circle-line me-1"></i><?= __('Tổng quan tài chính') ?>
                            </a>
                            <a href="<?= base_url('admin/customers-list') ?>" class="btn btn-soft-warning btn-sm text-start">
                                <i class="ri-user-heart-line me-1"></i><?= __('Quản lý khách hàng') ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>

<!-- ApexCharts CDN -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
$(document).ready(function(){
    var csrfName = '<?= $csrf->get_token_name() ?>';
    var csrfToken = '<?= $csrf->get_token_value() ?>';
    var ajaxUrl = '<?= base_url("ajaxs/admin/dashboard.php") ?>';

    function formatVND(n) {
        return new Intl.NumberFormat('vi-VN').format(Math.round(n)) + 'đ';
    }
    function shortVND(val) {
        if (val >= 1000000000) return (val/1000000000).toFixed(1) + 'B';
        if (val >= 1000000) return (val/1000000).toFixed(1) + 'M';
        if (val >= 1000) return (val/1000).toFixed(0) + 'K';
        return val;
    }

    // ===== 1. Revenue Area Chart =====
    var revenueChartData = <?= json_encode($revenueChartData ?: []) ?>;
    var revenueChart = null;

    function renderRevenueChart(data) {
        var dates = data.map(function(d){ return d.date; });
        var revenues = data.map(function(d){ return parseFloat(d.revenue); });
        var orders = data.map(function(d){ return parseInt(d.orders_count); });

        var options = {
            series: [{
                name: '<?= __("Doanh thu") ?>',
                type: 'area',
                data: revenues
            }, {
                name: '<?= __("Số đơn") ?>',
                type: 'line',
                data: orders
            }],
            chart: { height: 350, type: 'line', toolbar: { show: false }, zoom: { enabled: false } },
            colors: ['#0ab39c', '#405189'],
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: [2, 2] },
            fill: {
                type: ['gradient', 'solid'],
                gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 }
            },
            xaxis: {
                categories: dates,
                labels: { rotate: -45, rotateAlways: false, style: { fontSize: '11px' } }
            },
            yaxis: [{
                title: { text: '<?= __("Doanh thu") ?> (VND)' },
                labels: { formatter: function(val){ return shortVND(val); } }
            }, {
                opposite: true,
                title: { text: '<?= __("Số đơn") ?>' },
                labels: { formatter: function(val){ return Math.round(val); } }
            }],
            tooltip: {
                shared: true,
                y: {
                    formatter: function(val, opts) {
                        if (opts.seriesIndex === 0) return formatVND(val);
                        return val + ' <?= __("đơn") ?>';
                    }
                }
            },
            grid: { borderColor: '#f1f1f1' },
            noData: { text: '<?= __("Chưa có dữ liệu") ?>', style: { fontSize: '14px' } }
        };

        if (revenueChart) revenueChart.destroy();
        revenueChart = new ApexCharts(document.querySelector('#revenue-area-chart'), options);
        revenueChart.render();
    }

    renderRevenueChart(revenueChartData);

    // Period selector
    $('.period-btn').on('click', function(){
        var $btn = $(this);
        if ($btn.hasClass('active')) return;
        $('.period-btn').removeClass('active');
        $btn.addClass('active').prop('disabled', true);

        var postData = { request_name: 'revenue_chart', period: $btn.data('period') };
        postData[csrfName] = csrfToken;

        $.post(ajaxUrl, postData, function(res){
            if (res.status === 'success') {
                renderRevenueChart(res.data);
            }
        }, 'json').always(function(){
            $btn.prop('disabled', false);
        });
    });

    // ===== 2. Order Status Donut =====
    var orderStatusData = <?= json_encode($orderStatusData ?: []) ?>;
    var statusLabels = {
        'cn_warehouse': '<?= __("Đã về kho Trung Quốc") ?>',
        'packed': '<?= __("Đã đóng bao") ?>',
        'loading': '<?= __("Đang xếp xe") ?>',
        'shipping': '<?= __("Đang vận chuyển") ?>',
        'vn_warehouse': '<?= __("Đã về kho Việt Nam") ?>',
        'delivered': '<?= __("Đã giao hàng") ?>'
    };
    var statusColors = {
        'cn_warehouse': '#f7b84b', 'packed': '#405189', 'loading': '#e9a032', 'shipping': '#6f42c1', 'vn_warehouse': '#0ab39c', 'delivered': '#2a9d50'
    };

    (function(){
        if (!orderStatusData.length) {
            $('#order-status-donut').html('<div class="text-center text-muted py-5"><?= __("Chưa có dữ liệu") ?></div>');
            return;
        }
        var labels = orderStatusData.map(function(d){ return statusLabels[d.status] || d.status; });
        var series = orderStatusData.map(function(d){ return parseInt(d.count); });
        var colors = orderStatusData.map(function(d){ return statusColors[d.status] || '#878a99'; });

        new ApexCharts(document.querySelector('#order-status-donut'), {
            series: series,
            chart: { type: 'donut', height: 350 },
            labels: labels,
            colors: colors,
            legend: { position: 'bottom', fontSize: '12px' },
            plotOptions: {
                pie: {
                    donut: {
                        size: '65%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: '<?= __("Tổng đơn") ?>',
                                formatter: function(w){ return w.globals.seriesTotals.reduce(function(a,b){ return a+b; }, 0); }
                            }
                        }
                    }
                }
            },
            dataLabels: { dropShadow: { enabled: false } }
        }).render();
    })();

    // ===== 3. Package Pipeline =====
    var packageData = <?= json_encode($packagePipelineData ?: []) ?>;
    var pkgLabels = {
        'cn_warehouse': '<?= __("Kho Trung Quốc") ?>',
        'packed': '<?= __("Đã đóng bao") ?>',
        'shipping': '<?= __("Vận chuyển") ?>', 'vn_warehouse': '<?= __("Kho Việt Nam") ?>',
        'delivered': '<?= __("Đã giao") ?>'
    };
    var pkgColors = {
        'cn_warehouse': '#f7b84b', 'packed': '#405189',
        'shipping': '#6f42c1', 'vn_warehouse': '#0ab39c', 'delivered': '#2a9d50'
    };

    (function(){
        if (!packageData.length) {
            $('#package-pipeline-chart').html('<div class="text-center text-muted py-5"><?= __("Chưa có dữ liệu") ?></div>');
            return;
        }
        var categories = packageData.map(function(d){ return pkgLabels[d.status] || d.status; });
        var data = packageData.map(function(d){ return parseInt(d.count); });
        var colors = packageData.map(function(d){ return pkgColors[d.status] || '#878a99'; });

        new ApexCharts(document.querySelector('#package-pipeline-chart'), {
            series: [{ data: data }],
            chart: { type: 'bar', height: 280, toolbar: { show: false } },
            plotOptions: { bar: { horizontal: true, barHeight: '50%', distributed: true } },
            colors: colors,
            dataLabels: { enabled: true, offsetX: 20, style: { fontSize: '13px', fontWeight: 600 } },
            xaxis: { categories: categories },
            yaxis: { labels: { style: { fontSize: '13px' } } },
            legend: { show: false },
            grid: { borderColor: '#f1f1f1' },
            tooltip: { y: { formatter: function(val){ return val + ' <?= __("kiện") ?>'; } } }
        }).render();
    })();

    // ===== 4. Top 5 Customers =====
    var topCustomers = <?= json_encode($topCustomersData ?: []) ?>;

    (function(){
        if (!topCustomers.length) {
            $('#top-customers-chart').html('<div class="text-center text-muted py-5"><?= __("Chưa có dữ liệu") ?></div>');
            return;
        }
        var names = topCustomers.map(function(c){ return c.customer_code; });
        var spent = topCustomers.map(function(c){ return parseFloat(c.total_spent); });

        new ApexCharts(document.querySelector('#top-customers-chart'), {
            series: [{ name: '<?= __("Tổng chi tiêu") ?>', data: spent }],
            chart: { type: 'bar', height: 280, toolbar: { show: false } },
            plotOptions: { bar: { horizontal: true, barHeight: '45%' } },
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
});
</script>
