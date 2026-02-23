<?php
require_once(__DIR__.'/../../../models/is_admin.php');

$page_title = __('Báo cáo đơn hàng');

$filterDateFrom = input_get('date_from') ?: date('Y-m-01');
$filterDateTo = input_get('date_to') ?: date('Y-m-d');

// Order stats by status
$statusStats = $CMSNT->get_list_safe("SELECT status, COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as total
    FROM `orders` WHERE DATE(create_date) >= ? AND DATE(create_date) <= ?
    GROUP BY status ORDER BY cnt DESC", [$filterDateFrom, $filterDateTo]);

$totalOrders = $CMSNT->get_row_safe("SELECT COUNT(*) as cnt FROM `orders` WHERE DATE(create_date) >= ? AND DATE(create_date) <= ?", [$filterDateFrom, $filterDateTo])['cnt'];
$completedOrders = $CMSNT->get_row_safe("SELECT COUNT(*) as cnt FROM `orders` WHERE `status` = 'delivered' AND DATE(create_date) >= ? AND DATE(create_date) <= ?", [$filterDateFrom, $filterDateTo])['cnt'];
$cancelledOrders = $CMSNT->get_row_safe("SELECT COUNT(*) as cnt FROM `orders` WHERE `status` = 'cancelled' AND DATE(create_date) >= ? AND DATE(create_date) <= ?", [$filterDateFrom, $filterDateTo])['cnt'];

// Average order value
$avgOrder = $CMSNT->get_row_safe("SELECT COALESCE(AVG(grand_total),0) as avg_val FROM `orders` WHERE `status` != 'cancelled' AND DATE(create_date) >= ? AND DATE(create_date) <= ?", [$filterDateFrom, $filterDateTo])['avg_val'];

// Daily order count
$dailyOrders = $CMSNT->get_list_safe("SELECT DATE(create_date) as day, COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as total
    FROM `orders` WHERE DATE(create_date) >= ? AND DATE(create_date) <= ?
    GROUP BY DATE(create_date) ORDER BY day DESC", [$filterDateFrom, $filterDateTo]);

// Weight stats
$weightStats = $CMSNT->get_row_safe("SELECT
    COALESCE(SUM(weight_charged),0) as total_weight,
    COALESCE(AVG(weight_charged),0) as avg_weight,
    COALESCE(MAX(weight_charged),0) as max_weight
    FROM `orders` WHERE `status` != 'cancelled' AND weight_charged > 0
    AND DATE(create_date) >= ? AND DATE(create_date) <= ?", [$filterDateFrom, $filterDateTo]);

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Báo cáo đơn hàng') ?></h4>
                    <a href="<?= base_url('ajaxs/admin/export.php') ?>?type=orders&date_from=<?= $filterDateFrom ?>&date_to=<?= $filterDateTo ?>" class="btn btn-success">
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
                        <form method="GET" action="<?= base_url('admin/reports-orders') ?>" class="row g-3 align-items-end">
                            <input type="hidden" name="module" value="admin">
                            <input type="hidden" name="action" value="reports-orders">
                            <div class="col-md-3">
                                <label class="form-label"><?= __('Từ ngày') ?></label>
                                <input type="date" class="form-control" name="date_from" value="<?= $filterDateFrom ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><?= __('Đến ngày') ?></label>
                                <input type="date" class="form-control" name="date_to" value="<?= $filterDateTo ?>">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary"><?= __('Xem') ?></button>
                                <a href="<?= base_url('admin/reports-orders') ?>" class="btn btn-secondary"><?= __('Reset') ?></a>
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
                        <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Tổng đơn') ?></p>
                        <h4 class="fs-22 fw-semibold mt-4 mb-0"><?= $totalOrders ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Đã giao') ?></p>
                        <h4 class="fs-22 fw-semibold mt-4 mb-0 text-success"><?= $completedOrders ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Đã hủy') ?></p>
                        <h4 class="fs-22 fw-semibold mt-4 mb-0 text-danger"><?= $cancelledOrders ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Giá trị TB/đơn') ?></p>
                        <h4 class="fs-22 fw-semibold mt-4 mb-0"><?= format_vnd($avgOrder) ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Status Breakdown -->
            <div class="col-lg-4">
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
                    </div>
                </div>

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
            </div>

            <!-- Daily Orders -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Đơn hàng theo ngày') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><?= __('Ngày') ?></th>
                                        <th class="text-end"><?= __('Số đơn') ?></th>
                                        <th class="text-end"><?= __('Doanh thu') ?></th>
                                        <th style="width: 40%;"><?= __('Biểu đồ') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $maxDaily = max(array_column($dailyOrders, 'total') ?: [1]);
                                    foreach ($dailyOrders as $d):
                                        $pct = $maxDaily > 0 ? round($d['total'] / $maxDaily * 100) : 0;
                                    ?>
                                    <tr>
                                        <td class="fw-bold"><?= $d['day'] ?></td>
                                        <td class="text-end"><?= $d['cnt'] ?></td>
                                        <td class="text-end text-success fw-bold"><?= format_vnd($d['total']) ?></td>
                                        <td>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-primary" style="width: <?= $pct ?>%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>
