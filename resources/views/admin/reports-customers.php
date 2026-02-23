<?php
require_once(__DIR__.'/../../../models/is_admin.php');

$page_title = __('Báo cáo khách hàng');

$filterDateFrom = input_get('date_from') ?: date('Y-m-01');
$filterDateTo = input_get('date_to') ?: date('Y-m-d');

// Customer stats
$totalCustomers = $CMSNT->num_rows_safe("SELECT * FROM `customers`", []) ?: 0;
$newCustomers = $CMSNT->num_rows_safe("SELECT * FROM `customers` WHERE DATE(create_date) >= ? AND DATE(create_date) <= ?", [$filterDateFrom, $filterDateTo]) ?: 0;
$activeCustomers = $CMSNT->get_row_safe("SELECT COUNT(DISTINCT customer_id) as cnt FROM `orders` WHERE DATE(create_date) >= ? AND DATE(create_date) <= ?", [$filterDateFrom, $filterDateTo])['cnt'];

// Customer type breakdown
$typeStats = $CMSNT->get_list_safe("SELECT customer_type, COUNT(*) as cnt, COALESCE(SUM(total_spent),0) as spent, COALESCE(SUM(balance),0) as balance
    FROM `customers` GROUP BY customer_type", []);

// Top customers by spending in period
$topCustomers = $CMSNT->get_list_safe("SELECT c.id, c.customer_code, c.fullname, c.customer_type, c.balance,
    COUNT(o.id) as period_orders, COALESCE(SUM(o.grand_total),0) as period_spent
    FROM `customers` c
    LEFT JOIN `orders` o ON c.id = o.customer_id AND o.status != 'cancelled'
        AND DATE(o.create_date) >= ? AND DATE(o.create_date) <= ?
    GROUP BY c.id HAVING period_orders > 0
    ORDER BY period_spent DESC LIMIT 20", [$filterDateFrom, $filterDateTo]);

// Customers with no orders in period (inactive)
$inactiveCustomers = $CMSNT->get_list_safe("SELECT c.id, c.customer_code, c.fullname, c.customer_type, c.balance, c.total_orders,
    (SELECT MAX(o.create_date) FROM orders o WHERE o.customer_id = c.id) as last_order_date
    FROM `customers` c
    WHERE c.id NOT IN (SELECT DISTINCT customer_id FROM orders WHERE DATE(create_date) >= ? AND DATE(create_date) <= ? AND status != 'cancelled')
    AND c.total_orders > 0
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
                            <input type="hidden" name="module" value="admin">
                            <input type="hidden" name="action" value="reports-customers">
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
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary -->
        <div class="row">
            <div class="col-xl-4 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Tổng khách hàng') ?></p>
                        <h4 class="fs-22 fw-semibold mt-4 mb-0"><?= $totalCustomers ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Khách hàng mới trong kỳ') ?></p>
                        <h4 class="fs-22 fw-semibold mt-4 mb-0 text-success"><?= $newCustomers ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Khách hàng có đơn trong kỳ') ?></p>
                        <h4 class="fs-22 fw-semibold mt-4 mb-0 text-primary"><?= $activeCustomers ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Top Customers -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Top khách hàng trong kỳ') ?></h5>
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
                                        <th class="text-end"><?= __('Số dư') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topCustomers as $idx => $tc): ?>
                                    <tr>
                                        <td><?= $idx + 1 ?></td>
                                        <td>
                                            <a href="<?= base_url('admin/customers-detail&id=' . $tc['id']) ?>">
                                                <strong><?= htmlspecialchars($tc['customer_code']) ?></strong>
                                            </a>
                                            <br><small><?= htmlspecialchars($tc['fullname']) ?></small>
                                        </td>
                                        <td><?= display_customer_type($tc['customer_type']) ?></td>
                                        <td class="text-end"><?= $tc['period_orders'] ?></td>
                                        <td class="text-end text-success fw-bold"><?= format_vnd($tc['period_spent']) ?></td>
                                        <td class="text-end <?= $tc['balance'] < 0 ? 'text-danger' : '' ?>"><?= format_vnd($tc['balance']) ?></td>
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
                                        <th class="text-end"><?= __('Tổng đơn') ?></th>
                                        <th class="text-end"><?= __('Số dư') ?></th>
                                        <th><?= __('Đơn cuối') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inactiveCustomers as $ic): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= base_url('admin/customers-detail&id=' . $ic['id']) ?>">
                                                <strong><?= htmlspecialchars($ic['customer_code']) ?></strong> - <?= htmlspecialchars($ic['fullname']) ?>
                                            </a>
                                        </td>
                                        <td><?= display_customer_type($ic['customer_type']) ?></td>
                                        <td class="text-end"><?= $ic['total_orders'] ?></td>
                                        <td class="text-end <?= $ic['balance'] < 0 ? 'text-danger' : '' ?>"><?= format_vnd($ic['balance']) ?></td>
                                        <td><?= $ic['last_order_date'] ?? '-' ?></td>
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
                                <span class="fw-bold"><?= $ts['cnt'] ?> <?= __('Khách hàng') ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small class="text-muted"><?= __('Tổng chi tiêu') ?>: <?= format_vnd($ts['spent']) ?></small>
                                <small class="<?= $ts['balance'] < 0 ? 'text-danger' : 'text-success' ?>"><?= __('Số dư') ?>: <?= format_vnd($ts['balance']) ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>
