<?php
require_once(__DIR__.'/../../../models/is_staffvn.php');
if ($getUser['role'] !== 'finance_vn') { redirect(base_url('staffvn/home')); }
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Tạo giao dịch');

$exchangeRate = get_exchange_rate();
$customers = $ToryHub->get_list_safe("SELECT `id`, `fullname`, `total_spent` FROM `customers` ORDER BY `fullname` ASC", []);
$preselect_customer = input_get('customer_id') ?: '';
$preselect_order = input_get('order_id') ?: '';
$preselect_type = input_get('type') ?: 'payment';

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
                                            <option value="payment" <?= $preselect_type == 'payment' ? 'selected' : '' ?>><?= __('Thanh toán') ?></option>
                                            <option value="refund" <?= $preselect_type == 'refund' ? 'selected' : '' ?>><?= __('Hoàn tiền') ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Loại tiền') ?></label>
                                        <select class="form-select" name="currency" id="sel-currency">
                                            <option value="VND">VND</option>
                                            <option value="CNY">CNY (¥)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Số tiền') ?> <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="amount" id="inp-amount" step="1" min="0" required>
                                        <div class="form-text" id="amount-vnd-preview"></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
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
                            <a href="<?= base_url('staffvn/transactions') ?>" class="btn btn-secondary"><?= __('Hủy') ?></a>
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
                            <span class="badge bg-success"><?= __('Thanh toán') ?></span>
                            <p class="text-muted mb-0 mt-1"><?= __('Khách thanh toán cước. Đã TT tăng, nợ giảm.') ?></p>
                        </div>
                        <div class="mb-3">
                            <span class="badge bg-warning"><?= __('Hoàn tiền') ?></span>
                            <p class="text-muted mb-0 mt-1"><?= __('Hoàn tiền khi hủy đơn/lỗi. Đã TT giảm, nợ tăng.') ?></p>
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
{id:<?= $c['id'] ?>, name:'<?= addslashes($c['fullname']) ?>', totalSpent:<?= floatval($c['total_spent']) ?>},
<?php endforeach; ?>
];
var customersData = {};
customersList.forEach(function(c){ customersData[c.id] = {name: c.name, totalSpent: c.totalSpent}; });

var exchangeRate = <?= $exchangeRate ?>;
var formatVND = function(n) { return new Intl.NumberFormat('vi-VN').format(Math.round(n)) + 'đ'; };
var formatCNY = function(n) { return '¥' + new Intl.NumberFormat('zh-CN', {minimumFractionDigits:2, maximumFractionDigits:2}).format(n); };

function getAmountVnd() {
    var amount = parseFloat($('#inp-amount').val()) || 0;
    var currency = $('#sel-currency').val();
    return currency === 'CNY' ? Math.round(amount * exchangeRate) : amount;
}

// Live currency preview
function updateCurrencyPreview() {
    var amount = parseFloat($('#inp-amount').val()) || 0;
    var currency = $('#sel-currency').val();
    var $preview = $('#amount-vnd-preview');
    if (currency === 'CNY' && amount > 0) {
        $preview.html('≈ <strong class="text-danger">' + formatVND(amount * exchangeRate) + '</strong> <small class="text-muted">(<?= __('tỉ giá') ?>: ' + new Intl.NumberFormat('vi-VN').format(exchangeRate) + ')</small>');
    } else {
        $preview.html('');
    }
}
$('#sel-currency, #inp-amount').on('input change', updateCurrencyPreview);

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
        dd.append('<a href="#" class="dropdown-item customer-option" data-id="'+c.id+'"><strong>'+c.code+'</strong> - '+c.name+'</a>');
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
    var rawAmount = parseFloat($('#inp-amount').val()) || 0;
    var amountVnd = getAmountVnd();

    if (!custId || !rawAmount) {
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
        paidAfter = paidBefore + amountVnd;
        sign = '+';
    } else if (type === 'refund') {
        paidAfter = Math.max(0, paidBefore - amountVnd);
        sign = '-';
    }

    $('#cust-name').text(cust.name);
    $('#cust-paid').text(formatVND(paidBefore)).removeClass('text-success text-danger').addClass('text-success');
    $('#cust-paid-after').text(formatVND(paidAfter)).removeClass('text-success text-danger').addClass('text-success');
    $('#customer-info-card').show();

    var amountLabel = formatVND(amountVnd);
    if ($('#sel-currency').val() === 'CNY') amountLabel = formatCNY(rawAmount) + ' (' + formatVND(amountVnd) + ')';
    $('#preview-text').text(cust.name + ': <?= __('Đã TT') ?> ' + formatVND(paidBefore) + ' ' + sign + ' ' + amountLabel + ' = ' + formatVND(paidAfter));
    $('#preview-box').show();
}

$('#sel-customer, #sel-type, #inp-amount, #sel-currency').on('input change', updatePreview);
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
            $.post('<?= base_url('ajaxs/staffvn/transactions.php') ?>', $('#form-add-transaction').serialize(), function(res){
                if(res.status == 'success'){
                    Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){
                        window.location.href = '<?= base_url('staffvn/transactions') ?>';
                    });
                } else {
                    $('#alert-box').html('<div class="alert alert-danger">' + res.msg + '</div>');
                }
            }, 'json');
        }
    });
});
</script>
