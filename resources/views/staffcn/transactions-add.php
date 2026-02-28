<?php
require_once(__DIR__.'/../../../models/is_staffcn.php');
if (get_user_role() !== 'finance_cn') { redirect(base_url('staffcn/home')); }
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Tạo giao dịch');

$customers = $ToryHub->get_list_safe("SELECT `id`, `customer_code`, `fullname`, `balance` FROM `customers` ORDER BY `fullname` ASC", []);
$preselect_customer = input_get('customer_id') ?: '';
$preselect_order = input_get('order_id') ?: '';
$preselect_type = input_get('type') ?: 'deposit';

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Tạo giao dịch mới') ?></h4>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Thông tin giao dịch') ?></h5>
                    </div>
                    <div class="card-body">
                        <div id="alert-box"></div>
                        <form id="form-add-transaction">
                            <input type="hidden" name="<?= $csrf->get_token_name() ?>" value="<?= $csrf->get_token_value() ?>">
                            <input type="hidden" name="request_name" value="add">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Khách hàng') ?> <span class="text-danger">*</span></label>
                                        <select class="form-select" name="customer_id" id="sel-customer" required>
                                            <option value=""><?= __('-- Chọn khách hàng --') ?></option>
                                            <?php foreach ($customers as $c): ?>
                                            <option value="<?= $c['id'] ?>" data-balance="<?= $c['balance'] ?>" <?= $preselect_customer == $c['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($c['customer_code'] . ' - ' . $c['fullname']) ?> (<?= format_vnd($c['balance']) ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Loại giao dịch') ?> <span class="text-danger">*</span></label>
                                        <select class="form-select" name="type" id="sel-type" required>
                                            <option value="deposit" <?= $preselect_type == 'deposit' ? 'selected' : '' ?>><?= __('Nạp tiền') ?></option>
                                            <option value="payment" <?= $preselect_type == 'payment' ? 'selected' : '' ?>><?= __('Thanh toán') ?></option>
                                            <option value="refund" <?= $preselect_type == 'refund' ? 'selected' : '' ?>><?= __('Hoàn tiền') ?></option>
                                            <option value="adjustment" <?= $preselect_type == 'adjustment' ? 'selected' : '' ?>><?= __('Điều chỉnh') ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Số tiền') ?> (VND) <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="amount" id="inp-amount" step="1000" min="0" required>
                                        <small class="text-muted"><?= __('Nhập số tiền dương. Hệ thống tự xác định cộng/trừ theo loại giao dịch.') ?></small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Mã đơn hàng') ?> <small class="text-muted">(<?= __('tùy chọn') ?>)</small></label>
                                        <input type="text" class="form-control" name="order_code" value="<?= htmlspecialchars($preselect_order) ?>" placeholder="DH20260218001">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><?= __('Mô tả') ?></label>
                                <textarea class="form-control" name="description" rows="2" placeholder="<?= __('VD: Nạp tiền chuyển khoản VCB, Thanh toán đơn DH001...') ?>"></textarea>
                            </div>

                            <div class="alert alert-info" id="preview-box" style="display:none;">
                                <strong><?= __('Xem trước') ?>:</strong>
                                <span id="preview-text"></span>
                            </div>

                            <button type="submit" class="btn btn-primary me-2"><?= __('Tạo giao dịch') ?></button>
                            <a href="<?= base_url('staffcn/transactions') ?>" class="btn btn-secondary"><?= __('Hủy') ?></a>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Hướng dẫn') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <span class="badge bg-success"><?= __('Nạp tiền') ?></span>
                            <p class="text-muted mb-0 mt-1"><?= __('Khách hàng chuyển tiền vào tài khoản. Số dư tăng.') ?></p>
                        </div>
                        <div class="mb-3">
                            <span class="badge bg-primary"><?= __('Thanh toán') ?></span>
                            <p class="text-muted mb-0 mt-1"><?= __('Thanh toán đơn hàng. Số dư giảm.') ?></p>
                        </div>
                        <div class="mb-3">
                            <span class="badge bg-warning"><?= __('Hoàn tiền') ?></span>
                            <p class="text-muted mb-0 mt-1"><?= __('Hoàn tiền khi hủy đơn/lỗi. Số dư tăng.') ?></p>
                        </div>
                        <div class="mb-3">
                            <span class="badge bg-info"><?= __('Điều chỉnh') ?></span>
                            <p class="text-muted mb-0 mt-1"><?= __('Điều chỉnh số dư. Có thể tăng hoặc giảm.') ?></p>
                        </div>
                    </div>
                </div>

                <div class="card" id="customer-info-card" style="display:none;">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title text-white mb-0"><?= __('Thông tin khách hàng') ?></h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-1"><strong id="cust-name"></strong></p>
                        <p class="mb-1"><?= __('Số dư hiện tại') ?>: <span class="fw-bold" id="cust-balance"></span></p>
                        <p class="mb-0"><?= __('Số dư sau giao dịch') ?>: <span class="fw-bold fs-16" id="cust-balance-after"></span></p>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
var customersData = {};
<?php foreach ($customers as $c): ?>
customersData[<?= $c['id'] ?>] = {name: '<?= addslashes($c['customer_code'] . ' - ' . $c['fullname']) ?>', balance: <?= $c['balance'] ?>};
<?php endforeach; ?>

function updatePreview() {
    var custId = $('#sel-customer').val();
    var type = $('#sel-type').val();
    var amount = parseFloat($('#inp-amount').val()) || 0;

    if (!custId || !amount) {
        $('#preview-box').hide();
        $('#customer-info-card').hide();
        return;
    }

    var cust = customersData[custId];
    if (!cust) return;

    var balanceBefore = cust.balance;
    var balanceAfter = balanceBefore;
    var sign = '';

    if (type === 'deposit' || type === 'refund') {
        balanceAfter = balanceBefore + amount;
        sign = '+';
    } else if (type === 'payment') {
        balanceAfter = balanceBefore - amount;
        sign = '-';
    } else {
        balanceAfter = balanceBefore + amount;
        sign = '±';
    }

    var formatVND = function(n) { return new Intl.NumberFormat('vi-VN').format(Math.round(n)) + 'đ'; };

    $('#cust-name').text(cust.name);
    $('#cust-balance').text(formatVND(balanceBefore)).removeClass('text-success text-danger').addClass(balanceBefore >= 0 ? 'text-success' : 'text-danger');
    $('#cust-balance-after').text(formatVND(balanceAfter)).removeClass('text-success text-danger').addClass(balanceAfter >= 0 ? 'text-success' : 'text-danger');
    $('#customer-info-card').show();

    $('#preview-text').text(cust.name + ': ' + formatVND(balanceBefore) + ' ' + sign + ' ' + formatVND(amount) + ' = ' + formatVND(balanceAfter));
    $('#preview-box').show();
}

$('#sel-customer, #sel-type, #inp-amount').on('input change', updatePreview);
updatePreview();

$('#form-add-transaction').on('submit', function(e){
    e.preventDefault();
    Swal.fire({
        title: '<?= __('Xác nhận tạo giao dịch?') ?>',
        html: $('#preview-text').text(),
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<?= __('Xác nhận') ?>',
        cancelButtonText: '<?= __('Hủy') ?>'
    }).then(function(result){
        if(result.isConfirmed){
            $.post('<?= base_url('ajaxs/staffcn/transactions.php') ?>', $('#form-add-transaction').serialize(), function(res){
                if(res.status == 'success'){
                    Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){
                        window.location.href = '<?= base_url('staffcn/transactions') ?>';
                    });
                } else {
                    $('#alert-box').html('<div class="alert alert-danger">' + res.msg + '</div>');
                }
            }, 'json');
        }
    });
});
</script>
