<?php
require_once(__DIR__.'/../../../models/is_admin.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Tạo giao dịch');

$customers = $ToryHub->get_list_safe("SELECT `id`, `customer_code`, `fullname`, `balance`, `total_spent` FROM `customers` ORDER BY `fullname` ASC", []);
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
                                        <input type="hidden" name="customer_id" id="sel-customer" value="<?= htmlspecialchars($preselect_customer) ?>">
                                        <div class="position-relative" id="customer-search-wrap">
                                            <input type="text" class="form-control" id="customer-search" placeholder="<?= __('Gõ mã KH hoặc tên để tìm...') ?>" autocomplete="off">
                                            <div class="dropdown-menu w-100 shadow" id="customer-dropdown" style="max-height:250px;overflow-y:auto;"></div>
                                        </div>
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
                            <a href="<?= base_url('admin/transactions') ?>" class="btn btn-secondary"><?= __('Hủy') ?></a>
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
                            <p class="text-muted mb-0 mt-1"><?= __('Khách chuyển tiền vào. Đã TT tăng, nợ giảm.') ?></p>
                        </div>
                        <div class="mb-3">
                            <span class="badge bg-primary"><?= __('Thanh toán') ?></span>
                            <p class="text-muted mb-0 mt-1"><?= __('Thanh toán cước vận chuyển. Đã TT tăng, nợ giảm.') ?></p>
                        </div>
                        <div class="mb-3">
                            <span class="badge bg-warning"><?= __('Hoàn tiền') ?></span>
                            <p class="text-muted mb-0 mt-1"><?= __('Hoàn tiền khi hủy đơn/lỗi. Đã TT giảm, nợ tăng.') ?></p>
                        </div>
                        <div class="mb-3">
                            <span class="badge bg-info"><?= __('Điều chỉnh') ?></span>
                            <p class="text-muted mb-0 mt-1"><?= __('Điều chỉnh số đã thanh toán.') ?></p>
                        </div>
                    </div>
                </div>

                <div class="card" id="customer-info-card" style="display:none;">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title text-white mb-0"><?= __('Thông tin khách hàng') ?></h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-1"><strong id="cust-name"></strong></p>
                        <p class="mb-1"><?= __('Đã thanh toán') ?>: <span class="fw-bold" id="cust-paid"></span></p>
                        <p class="mb-0"><?= __('Sau giao dịch') ?>: <span class="fw-bold fs-16" id="cust-paid-after"></span></p>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
var customersList = [
<?php foreach ($customers as $c): ?>
{id:<?= $c['id'] ?>, code:'<?= addslashes($c['customer_code']) ?>', name:'<?= addslashes($c['fullname']) ?>', balance:<?= $c['balance'] ?>, totalSpent:<?= floatval($c['total_spent']) ?>},
<?php endforeach; ?>
];
var customersData = {};
customersList.forEach(function(c){ customersData[c.id] = {name: c.code + ' - ' + c.name, balance: c.balance, totalSpent: c.totalSpent}; });

var formatVND = function(n) { return new Intl.NumberFormat('vi-VN').format(Math.round(n)) + 'đ'; };

// Customer search
function renderCustomerDropdown(keyword) {
    var dd = $('#customer-dropdown');
    dd.empty();
    var kw = (keyword || '').toLowerCase();
    var filtered = customersList.filter(function(c){
        return !kw || c.code.toLowerCase().indexOf(kw) !== -1 || c.name.toLowerCase().indexOf(kw) !== -1;
    }).slice(0, 20);
    if (!filtered.length) { dd.hide(); return; }
    filtered.forEach(function(c){
        var balCls = c.balance < 0 ? 'text-danger' : 'text-success';
        dd.append('<a href="#" class="dropdown-item customer-option" data-id="'+c.id+'"><strong>'+c.code+'</strong> - '+c.name+' <span class="'+balCls+'">('+formatVND(c.balance)+')</span></a>');
    });
    dd.show();
}
function selectCustomer(id) {
    var c = customersData[id];
    if (!c) return;
    $('#sel-customer').val(id);
    $('#customer-search').val(c.name);
    $('#customer-dropdown').hide();
    updatePreview();
}
$('#customer-search').on('input', function(){ renderCustomerDropdown($(this).val()); });
$('#customer-search').on('focus', function(){ renderCustomerDropdown($(this).val()); });
$(document).on('click', '.customer-option', function(e){ e.preventDefault(); selectCustomer($(this).data('id')); });
$(document).on('click', function(e){ if (!$(e.target).closest('#customer-search-wrap').length) $('#customer-dropdown').hide(); });

// Preselect
<?php if ($preselect_customer): ?>
selectCustomer(<?= intval($preselect_customer) ?>);
<?php endif; ?>

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

    var paidBefore = cust.totalSpent;
    var paidAfter = paidBefore;
    var sign = '';

    if (type === 'deposit' || type === 'payment' || type === 'adjustment') {
        paidAfter = paidBefore + amount;
        sign = '+';
    } else if (type === 'refund') {
        paidAfter = Math.max(0, paidBefore - amount);
        sign = '-';
    }

    $('#cust-name').text(cust.name);
    $('#cust-paid').text(formatVND(paidBefore)).removeClass('text-success text-danger').addClass('text-success');
    $('#cust-paid-after').text(formatVND(paidAfter)).removeClass('text-success text-danger').addClass('text-success');
    $('#customer-info-card').show();

    $('#preview-text').text(cust.name + ': <?= __('Đã TT') ?> ' + formatVND(paidBefore) + ' ' + sign + ' ' + formatVND(amount) + ' = ' + formatVND(paidAfter));
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
            $.post('<?= base_url('ajaxs/admin/transactions.php') ?>', $('#form-add-transaction').serialize(), function(res){
                if(res.status == 'success'){
                    Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){
                        window.location.href = '<?= base_url('admin/transactions') ?>';
                    });
                } else {
                    $('#alert-box').html('<div class="alert alert-danger">' + res.msg + '</div>');
                }
            }, 'json');
        }
    });
});
</script>
