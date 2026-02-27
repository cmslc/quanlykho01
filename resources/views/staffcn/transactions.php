<?php
require_once(__DIR__.'/../../../models/is_staffcn.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Quản lý giao dịch');

// Filters
$filterType = input_get('type') ?: '';
$filterCustomer = input_get('customer_id') ?: '';
$filterDateFrom = input_get('date_from') ?: '';
$filterDateTo = input_get('date_to') ?: '';

$where = "1=1";
$params = [];

if ($filterType) {
    $where .= " AND t.type = ?";
    $params[] = $filterType;
}
if ($filterCustomer) {
    $where .= " AND t.customer_id = ?";
    $params[] = intval($filterCustomer);
}
if ($filterDateFrom) {
    $where .= " AND DATE(t.create_date) >= ?";
    $params[] = $filterDateFrom;
}
if ($filterDateTo) {
    $where .= " AND DATE(t.create_date) <= ?";
    $params[] = $filterDateTo;
}

$transactions = $ToryHub->get_list_safe("SELECT t.*, c.fullname as customer_name, c.customer_code, u.username as created_by_name
    FROM `transactions` t
    LEFT JOIN `customers` c ON t.customer_id = c.id
    LEFT JOIN `users` u ON t.created_by = u.id
    WHERE $where ORDER BY t.create_date DESC LIMIT 500", $params);

$customers = $ToryHub->get_list_safe("SELECT `id`, `customer_code`, `fullname` FROM `customers` ORDER BY `fullname` ASC", []);

// Summary
$totalDeposit = $ToryHub->get_row_safe("SELECT COALESCE(SUM(amount),0) as total FROM `transactions` WHERE `type` = 'deposit'", []);
$totalPayment = $ToryHub->get_row_safe("SELECT COALESCE(SUM(ABS(amount)),0) as total FROM `transactions` WHERE `type` = 'payment'", []);
$totalRefund = $ToryHub->get_row_safe("SELECT COALESCE(SUM(amount),0) as total FROM `transactions` WHERE `type` = 'refund'", []);

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Quản lý giao dịch') ?></h4>
                    <a href="<?= base_url('staffcn/transactions-add') ?>" class="btn btn-primary">
                        <i class="ri-add-line"></i> <?= __('Tạo giao dịch') ?>
                    </a>
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
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Tổng nạp tiền') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-success"><?= format_vnd($totalDeposit['total']) ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success-subtle rounded fs-3"><i class="ri-arrow-down-circle-line text-success"></i></span>
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
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Tổng thanh toán') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-primary"><?= format_vnd($totalPayment['total']) ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle rounded fs-3"><i class="ri-arrow-up-circle-line text-primary"></i></span>
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
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Tổng hoàn tiền') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-warning"><?= format_vnd($totalRefund['total']) ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning-subtle rounded fs-3"><i class="ri-refund-2-line text-warning"></i></span>
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
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Số giao dịch') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0"><?= count($transactions) ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-info-subtle rounded fs-3"><i class="ri-file-list-3-line text-info"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="<?= base_url('staffcn/transactions') ?>" class="row g-3 align-items-end">
                            <input type="hidden" name="module" value="admin">
                            <input type="hidden" name="action" value="transactions">
                            <div class="col-md-2">
                                <label class="form-label"><?= __('Loại giao dịch') ?></label>
                                <select class="form-select" name="type">
                                    <option value=""><?= __('Tất cả') ?></option>
                                    <option value="deposit" <?= $filterType == 'deposit' ? 'selected' : '' ?>><?= __('Nạp tiền') ?></option>
                                    <option value="payment" <?= $filterType == 'payment' ? 'selected' : '' ?>><?= __('Thanh toán') ?></option>
                                    <option value="refund" <?= $filterType == 'refund' ? 'selected' : '' ?>><?= __('Hoàn tiền') ?></option>
                                    <option value="adjustment" <?= $filterType == 'adjustment' ? 'selected' : '' ?>><?= __('Điều chỉnh') ?></option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><?= __('Khách hàng') ?></label>
                                <select class="form-select" name="customer_id">
                                    <option value=""><?= __('Tất cả') ?></option>
                                    <?php foreach ($customers as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $filterCustomer == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['customer_code'] . ' - ' . $c['fullname']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><?= __('Từ ngày') ?></label>
                                <input type="date" class="form-control" name="date_from" value="<?= $filterDateFrom ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><?= __('Đến ngày') ?></label>
                                <input type="date" class="form-control" name="date_to" value="<?= $filterDateTo ?>">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary"><?= __('Lọc') ?></button>
                                <a href="<?= base_url('staffcn/transactions') ?>" class="btn btn-secondary"><?= __('Reset') ?></a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Danh sách giao dịch') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table data-table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th><?= __('Loại') ?></th>
                                        <th><?= __('Số tiền') ?></th>
                                        <th><?= __('Số dư trước') ?></th>
                                        <th><?= __('Số dư sau') ?></th>
                                        <th><?= __('Mã đơn') ?></th>
                                        <th><?= __('Mô tả') ?></th>
                                        <th><?= __('Người tạo') ?></th>
                                        <th><?= __('Ngày') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $txnBadge = ['deposit' => 'success', 'payment' => 'primary', 'refund' => 'warning', 'adjustment' => 'info'];
                                    $txnLabel = ['deposit' => __('Nạp tiền'), 'payment' => __('Thanh toán'), 'refund' => __('Hoàn tiền'), 'adjustment' => __('Điều chỉnh')];
                                    ?>
                                    <?php foreach ($transactions as $txn): ?>
                                    <tr>
                                        <td><?= $txn['id'] ?></td>
                                        <td>
                                            <a href="<?= base_url('staffcn/customers-detail&id=' . $txn['customer_id']) ?>"><?= htmlspecialchars($txn['customer_code'] ?? '') ?></a>
                                            <br><small class="text-muted"><?= htmlspecialchars($txn['customer_name'] ?? '') ?></small>
                                        </td>
                                        <td><span class="badge bg-<?= $txnBadge[$txn['type']] ?? 'secondary' ?>"><?= $txnLabel[$txn['type']] ?? $txn['type'] ?></span></td>
                                        <td class="<?= $txn['amount'] >= 0 ? 'text-success' : 'text-danger' ?> fw-bold"><?= format_vnd($txn['amount']) ?></td>
                                        <td><?= format_vnd($txn['balance_before']) ?></td>
                                        <td class="fw-bold"><?= format_vnd($txn['balance_after']) ?></td>
                                        <td>
                                            <?php if ($txn['order_id']): ?>
                                            <?php $txnOrder = $ToryHub->get_row_safe("SELECT order_code FROM orders WHERE id = ?", [$txn['order_id']]); ?>
                                            <a href="<?= base_url('staffcn/orders-detail&id=' . $txn['order_id']) ?>"><?= $txnOrder['order_code'] ?? '' ?></a>
                                            <?php else: ?>-<?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($txn['description'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($txn['created_by_name'] ?? '') ?></td>
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
