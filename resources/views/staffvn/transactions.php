<?php
require_once(__DIR__.'/../../../models/is_staffvn.php');
if ($getUser['role'] !== 'finance_vn') { redirect(base_url('staffvn/home')); }
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

$transactions = $ToryHub->get_list_safe("SELECT t.*, c.fullname as customer_name, u.username as created_by_name
    FROM `transactions` t
    LEFT JOIN `customers` c ON t.customer_id = c.id
    LEFT JOIN `users` u ON t.created_by = u.id
    WHERE $where ORDER BY t.create_date DESC LIMIT 500", $params);

$customers = $ToryHub->get_list_safe("SELECT `id`, `fullname` FROM `customers` ORDER BY `fullname` ASC", []);

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
                    <div class="d-flex gap-2">
                        <a href="<?= base_url('ajaxs/staffvn/transactions-export.php') ?>?type=<?= urlencode($filterType) ?>&customer_id=<?= urlencode($filterCustomer) ?>&date_from=<?= urlencode($filterDateFrom) ?>&date_to=<?= urlencode($filterDateTo) ?>" class="btn btn-success">
                            <i class="ri-file-excel-2-line"></i> <?= __('Xuất Excel') ?>
                        </a>
                        <a href="<?= base_url('staffvn/transactions-add') ?>" class="btn btn-primary">
                            <i class="ri-add-line"></i> <?= __('Tạo giao dịch') ?>
                        </a>
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
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-danger"><?= format_vnd($totalRefund['total']) ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning rounded fs-3"><i class="ri-refund-2-line text-dark"></i></span>
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
                        <form method="GET" action="<?= base_url('staffvn/transactions') ?>" class="row g-3 align-items-end">
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
                                    <option value="<?= $c['id'] ?>" <?= $filterCustomer == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['fullname']) ?></option>
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
                                <a href="<?= base_url('staffvn/transactions') ?>" class="btn btn-secondary"><?= __('Reset') ?></a>
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
                                        <th>#</th>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th><?= __('Loại') ?></th>
                                        <th><?= __('Số tiền') ?></th>
                                        <th><?= __('Mã đơn') ?></th>
                                        <th><?= __('Mô tả') ?></th>
                                        <th><?= __('Người tạo') ?></th>
                                        <th><?= __('Ngày') ?></th>
                                        <th><?= __('Hành động') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $txnBadge = ['deposit' => 'success', 'payment' => 'primary', 'refund' => 'warning', 'adjustment' => 'info'];
                                    $txnLabel = ['deposit' => __('Nạp tiền'), 'payment' => __('Thanh toán'), 'refund' => __('Hoàn tiền'), 'adjustment' => __('Điều chỉnh')];
                                    ?>
                                    <?php $txnNum = 0; foreach ($transactions as $txn): $txnNum++; ?>
                                    <tr>
                                        <td class="text-muted"><?= $txnNum ?></td>
                                        <td>
                                            <a href="<?= base_url('staffvn/customers-detail?id=' . $txn['customer_id']) ?>"><?= htmlspecialchars($txn['customer_name'] ?? '') ?></a>
                                        </td>
                                        <td><span class="badge bg-<?= $txnBadge[$txn['type']] ?? 'secondary' ?>-subtle text-<?= $txnBadge[$txn['type']] ?? 'secondary' ?> fs-12 px-2 py-1"><?= $txnLabel[$txn['type']] ?? $txn['type'] ?></span></td>
                                        <td class="<?= $txn['amount'] >= 0 ? 'text-success' : 'text-danger' ?> fw-bold"><?= format_vnd($txn['amount']) ?></td>
                                        <td>
                                            <?php if ($txn['order_id']): ?>
                                            <?php $txnOrder = $ToryHub->get_row_safe("SELECT order_code FROM orders WHERE id = ?", [$txn['order_id']]); ?>
                                            <a href="<?= base_url('staffvn/orders-detail?id=' . $txn['order_id']) ?>"><?= $txnOrder['order_code'] ?? '' ?></a>
                                            <?php else: ?>-<?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($txn['description'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($txn['created_by_name'] ?? '') ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($txn['create_date'])) ?></td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <button class="btn btn-sm btn-soft-info btn-edit-txn"
                                                    data-id="<?= $txn['id'] ?>"
                                                    data-amount="<?= abs(floatval($txn['amount'])) ?>"
                                                    data-description="<?= htmlspecialchars($txn['description'] ?? '') ?>"
                                                    data-type="<?= $txn['type'] ?>"
                                                    title="<?= __('Sửa') ?>">
                                                    <i class="ri-pencil-line"></i>
                                                </button>
                                                <button class="btn btn-sm btn-soft-danger btn-delete-txn" data-id="<?= $txn['id'] ?>" title="<?= __('Xóa') ?>">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
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

<!-- Modal Edit Transaction -->
<div class="modal fade" id="modalEditTxn" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('Sửa giao dịch') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editTxnId">
                <div class="mb-3">
                    <label class="form-label"><?= __('Loại giao dịch') ?></label>
                    <input type="text" class="form-control" id="editTxnType" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= __('Số tiền') ?></label>
                    <input type="number" class="form-control" id="editTxnAmount" min="1" step="any" required>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= __('Mô tả') ?></label>
                    <textarea class="form-control" id="editTxnDesc" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Hủy') ?></button>
                <button type="button" class="btn btn-primary" id="btnSaveTxn"><?= __('Lưu') ?></button>
            </div>
        </div>
    </div>
</div>

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
var txnTypeLabels = {deposit: '<?= __('Nạp tiền') ?>', payment: '<?= __('Thanh toán') ?>', refund: '<?= __('Hoàn tiền') ?>', adjustment: '<?= __('Điều chỉnh') ?>'};

$(document).on('click', '.btn-edit-txn', function(){
    $('#editTxnId').val($(this).data('id'));
    $('#editTxnType').val(txnTypeLabels[$(this).data('type')] || $(this).data('type'));
    $('#editTxnAmount').val($(this).data('amount'));
    $('#editTxnDesc').val($(this).data('description'));
    new bootstrap.Modal('#modalEditTxn').show();
});

$('#btnSaveTxn').on('click', function(){
    var btn = $(this);
    btn.prop('disabled', true);
    $.post('<?= base_url('ajaxs/staffvn/transactions.php') ?>', {
        request_name: 'edit',
        id: $('#editTxnId').val(),
        amount: $('#editTxnAmount').val(),
        description: $('#editTxnDesc').val(),
        csrf_token: '<?= $csrf->get_token_value() ?>'
    }, function(res){
        btn.prop('disabled', false);
        if(res.status == 'success'){
            Swal.fire({icon:'success', title:res.msg, timer:1500, showConfirmButton:false}).then(function(){ location.reload(); });
        } else {
            Swal.fire({icon:'error', title:'Error', text:res.msg});
        }
    }, 'json');
});

$(document).on('click', '.btn-delete-txn', function(){
    var id = $(this).data('id');
    Swal.fire({
        title: '<?= __('Bạn có chắc chắn?') ?>',
        text: '<?= __('Giao dịch sẽ bị xóa!') ?>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: '<?= __('Xóa') ?>',
        cancelButtonText: '<?= __('Hủy') ?>'
    }).then(function(result){
        if(result.isConfirmed){
            $.post('<?= base_url('ajaxs/staffvn/transactions.php') ?>', {
                request_name: 'delete',
                id: id,
                csrf_token: '<?= $csrf->get_token_value() ?>'
            }, function(res){
                if(res.status == 'success'){
                    Swal.fire({icon:'success', title:res.msg, timer:1500, showConfirmButton:false}).then(function(){ location.reload(); });
                } else {
                    Swal.fire({icon:'error', title:'Error', text:res.msg});
                }
            }, 'json');
        }
    });
});
</script>
