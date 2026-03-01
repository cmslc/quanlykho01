<?php
require_once(__DIR__.'/../../../models/is_admin.php');

$page_title = __('Báo cáo doanh thu');

$filterDateFrom = input_get('date_from') ?: date('Y-m-01');
$filterDateTo = input_get('date_to') ?: date('Y-m-d');
$filterGroup = input_get('group_by') ?: 'day';

// Revenue by period
$groupFormat = $filterGroup === 'month' ? '%Y-%m' : ($filterGroup === 'week' ? '%Y-%u' : '%Y-%m-%d');

$revenueData = $ToryHub->get_list_safe("SELECT DATE_FORMAT(create_date, '$groupFormat') as period,
    COUNT(*) as order_count,
    COALESCE(SUM(total_cny),0) as total_cny,
    COALESCE(SUM(total_vnd),0) as total_vnd,
    COALESCE(SUM(shipping_fee_cn + shipping_fee_intl),0) as shipping_fee,
    COALESCE(SUM(packing_fee + insurance_fee + other_fee),0) as other_fees,
    COALESCE(SUM(total_fee),0) as total_fee,
    COALESCE(SUM(grand_total),0) as grand_total
    FROM `orders` WHERE `status` != 'cancelled'
    AND DATE(create_date) >= ? AND DATE(create_date) <= ?
    GROUP BY period ORDER BY period DESC", [$filterDateFrom, $filterDateTo]);

// Totals
$totals = [
    'order_count' => 0, 'total_cny' => 0, 'total_vnd' => 0,
    'shipping_fee' => 0, 'other_fees' => 0,
    'total_fee' => 0, 'grand_total' => 0
];
foreach ($revenueData as $r) {
    foreach ($totals as $k => &$v) { $v += $r[$k]; }
}
unset($v);

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
                    <h4 class="mb-sm-0"><?= __('Báo cáo doanh thu') ?></h4>
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
                            <input type="hidden" name="module" value="admin">
                            <input type="hidden" name="action" value="reports-revenue">
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
                                <button type="submit" class="btn btn-primary"><?= __('Xem') ?></button>
                                <a href="<?= base_url('admin/reports-revenue') ?>" class="btn btn-secondary"><?= __('Reset') ?></a>
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
                        <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Tổng doanh thu') ?></p>
                        <h4 class="fs-22 fw-semibold mt-4 mb-0 text-success"><?= format_vnd($totals['grand_total']) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Tổng đơn') ?></p>
                        <h4 class="fs-22 fw-semibold mt-4 mb-0"><?= $totals['order_count'] ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Tiền hàng CNY') ?></p>
                        <h4 class="fs-22 fw-semibold mt-4 mb-0">¥<?= number_format($totals['total_cny'], 2) ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
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
                                        <th class="text-end"><?= __('CNY') ?></th>
                                        <th class="text-end"><?= __('Tiền hàng VND') ?></th>
                                        <th class="text-end"><?= __('Phí ship') ?></th>
                                        <th class="text-end"><?= __('Tổng cộng') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($revenueData as $r): ?>
                                    <tr>
                                        <td class="fw-bold"><?= $r['period'] ?></td>
                                        <td class="text-end"><?= $r['order_count'] ?></td>
                                        <td class="text-end">¥<?= number_format($r['total_cny'], 2) ?></td>
                                        <td class="text-end"><?= format_vnd($r['total_vnd']) ?></td>
                                        <td class="text-end"><?= format_vnd($r['shipping_fee']) ?></td>
                                        <td class="text-end fw-bold text-success"><?= format_vnd($r['grand_total']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($revenueData)): ?>
                                    <tr><td colspan="6" class="text-center text-muted"><?= __('Không có dữ liệu') ?></td></tr>
                                    <?php endif; ?>
                                </tbody>
                                <?php if (!empty($revenueData)): ?>
                                <tfoot class="table-light">
                                    <tr class="fw-bold">
                                        <td><?= __('TỔNG') ?></td>
                                        <td class="text-end"><?= $totals['order_count'] ?></td>
                                        <td class="text-end">¥<?= number_format($totals['total_cny'], 2) ?></td>
                                        <td class="text-end"><?= format_vnd($totals['total_vnd']) ?></td>
                                        <td class="text-end"><?= format_vnd($totals['shipping_fee']) ?></td>
                                        <td class="text-end text-success"><?= format_vnd($totals['grand_total']) ?></td>
                                    </tr>
                                </tfoot>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Platform Breakdown -->
            <div class="col-lg-4">
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
                        <?php if (empty($platformData)): ?>
                        <p class="text-muted text-center"><?= __('Không có dữ liệu') ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>
