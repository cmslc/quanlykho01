<?php
require_once(__DIR__.'/../../../models/is_customer.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Lịch sử giao dịch');

// Get customer info
$customer = $CMSNT->get_row_safe("SELECT * FROM `customers` WHERE `user_id` = ?", [$getUser['id']]);
$customer_id = $customer ? $customer['id'] : 0;

// Filters
$filterType = input_get('type') ?: '';
$filterFrom = input_get('date_from') ?: '';
$filterTo = input_get('date_to') ?: '';

$where = "`t`.`customer_id` = ?";
$params = [$customer_id];

if ($filterType) {
    $where .= " AND `t`.`type` = ?";
    $params[] = $filterType;
}
if ($filterFrom) {
    $where .= " AND `t`.`create_date` >= ?";
    $params[] = $filterFrom . ' 00:00:00';
}
if ($filterTo) {
    $where .= " AND `t`.`create_date` <= ?";
    $params[] = $filterTo . ' 23:59:59';
}

$transactions = $CMSNT->get_list_safe("SELECT t.*, o.order_code
    FROM `transactions` t
    LEFT JOIN `orders` o ON t.order_id = o.id
    WHERE $where ORDER BY t.create_date DESC", $params);

// Summary stats
$balance = $customer ? $customer['balance'] : 0;
$totalDeposit = $CMSNT->get_row_safe("SELECT COALESCE(SUM(amount),0) as total FROM `transactions` WHERE `customer_id` = ? AND `type` = 'deposit'", [$customer_id]);
$totalPayment = $CMSNT->get_row_safe("SELECT COALESCE(SUM(ABS(amount)),0) as total FROM `transactions` WHERE `customer_id` = ? AND `type` = 'payment'", [$customer_id]);
$totalCount = $CMSNT->num_rows_safe("SELECT * FROM `transactions` WHERE `customer_id` = ?", [$customer_id]) ?: 0;

$txnTypes = ['deposit', 'payment', 'refund', 'adjustment'];
$txnBadge = ['deposit' => 'success', 'payment' => 'primary', 'refund' => 'warning', 'adjustment' => 'info'];
$txnLabel = ['deposit' => __('Nạp tiền'), 'payment' => __('Thanh toán'), 'refund' => __('Hoàn tiền'), 'adjustment' => __('Điều chỉnh')];

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Lịch sử giao dịch') ?></h4>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted text-truncate mb-0"><?= __('Số dư hiện tại') ?></p>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div><h4 class="fs-22 fw-semibold mb-4 text-success"><?= format_vnd($balance) ?></h4></div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success-subtle rounded fs-3"><i class="ri-wallet-3-line text-success"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted text-truncate mb-0"><?= __('Tổng nạp') ?></p>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div><h4 class="fs-22 fw-semibold mb-4"><?= format_vnd($totalDeposit['total']) ?></h4></div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle rounded fs-3"><i class="ri-add-circle-line text-primary"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted text-truncate mb-0"><?= __('Tổng thanh toán') ?></p>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div><h4 class="fs-22 fw-semibold mb-4"><?= format_vnd($totalPayment['total']) ?></h4></div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning-subtle rounded fs-3"><i class="ri-shopping-bag-line text-warning"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted text-truncate mb-0"><?= __('Tổng giao dịch') ?></p>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div><h4 class="fs-22 fw-semibold mb-4"><?= $totalCount ?></h4></div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-info-subtle rounded fs-3"><i class="ri-exchange-funds-line text-info"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="<?= base_url('customer/transactions') ?>" class="row g-3 align-items-end">
                            <input type="hidden" name="module" value="customer">
                            <input type="hidden" name="action" value="transactions">
                            <div class="col-md-3">
                                <label class="form-label"><?= __('Loại giao dịch') ?></label>
                                <select class="form-select" name="type">
                                    <option value=""><?= __('Tất cả') ?></option>
                                    <?php foreach ($txnTypes as $t): ?>
                                    <option value="<?= $t ?>" <?= $filterType == $t ? 'selected' : '' ?>><?= $txnLabel[$t] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><?= __('Từ ngày') ?></label>
                                <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($filterFrom) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><?= __('Đến ngày') ?></label>
                                <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($filterTo) ?>">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary"><?= __('Lọc') ?></button>
                                <a href="<?= base_url('customer/transactions') ?>" class="btn btn-secondary"><?= __('Reset') ?></a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Danh sách giao dịch') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table data-table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th><?= __('Loại') ?></th>
                                        <th><?= __('Số tiền') ?></th>
                                        <th><?= __('Số dư trước') ?></th>
                                        <th><?= __('Số dư sau') ?></th>
                                        <th><?= __('Mã đơn') ?></th>
                                        <th><?= __('Mô tả') ?></th>
                                        <th><?= __('Ngày') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $i => $txn): ?>
                                    <?php
                                        $badge = $txnBadge[$txn['type']] ?? 'secondary';
                                        $label = $txnLabel[$txn['type']] ?? $txn['type'];
                                        $isPositive = in_array($txn['type'], ['deposit', 'refund']);
                                        $amountClass = $isPositive ? 'text-success' : 'text-danger';
                                        $amountPrefix = $isPositive ? '+' : '-';
                                        $amountDisplay = abs($txn['amount']);
                                    ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td><span class="badge bg-<?= $badge ?>"><?= $label ?></span></td>
                                        <td class="fw-bold <?= $amountClass ?>"><?= $amountPrefix ?><?= format_vnd($amountDisplay) ?></td>
                                        <td><?= format_vnd($txn['balance_before']) ?></td>
                                        <td><?= format_vnd($txn['balance_after']) ?></td>
                                        <td>
                                            <?php if (!empty($txn['order_code'])): ?>
                                            <a href="<?= base_url('customer/orders&status=') ?>"><?= htmlspecialchars($txn['order_code']) ?></a>
                                            <?php else: ?>
                                            -
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($txn['description'] ?? '') ?></td>
                                        <td><?= $txn['create_date'] ?></td>
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
