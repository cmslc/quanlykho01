<?php
require_once(__DIR__.'/../../../models/is_admin.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Tạo đơn hàng mới');

$customers = $ToryHub->get_list_safe("SELECT `id`, `fullname` FROM `customers` ORDER BY `fullname` ASC", []);
$exchange_rate = get_exchange_rate();
$preselect_customer = input_get('customer_id') ?: '';

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Tạo đơn hàng mới') ?></h4>
                </div>
            </div>
        </div>

        <form id="form-add-order">
            <input type="hidden" name="<?= $csrf->get_token_name() ?>" value="<?= $csrf->get_token_value() ?>">
            <input type="hidden" name="request_name" value="add">
            <input type="hidden" name="order_type" value="shipping">

            <div id="alert-box"></div>

            <!-- Thông tin đơn hàng -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><?= __('Thông tin đơn hàng') ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label"><?= __('Loại hàng') ?></label>
                                <select class="form-select" name="product_type">
                                    <option value="retail"><?= __('Hàng lẻ') ?></option>
                                    <option value="wholesale"><?= __('Hàng lô') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 wholesale-only">
                            <div class="mb-3">
                                <label class="form-label"><?= __('Phân loại vận chuyển') ?></label>
                                <select class="form-select" name="cargo_type">
                                    <option value="easy"><?= __('Hàng dễ vận chuyển') ?></option>
                                    <option value="difficult"><?= __('Hàng khó vận chuyển') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 wholesale-only">
                            <div class="mb-3">
                                <label class="form-label"><?= __('Khách hàng') ?> <span class="text-danger">*</span></label>
                                <input type="hidden" name="customer_id" id="select-customer" value="<?= htmlspecialchars($preselect_customer) ?>">
                                <div class="input-group position-relative">
                                    <input type="text" class="form-control" id="customer-search"
                                        placeholder="<?= __('Nhập mã hoặc tên khách hàng...') ?>"
                                        autocomplete="off"
                                        value="<?php
                                            if ($preselect_customer) {
                                                foreach ($customers as $c) {
                                                    if ($c['id'] == $preselect_customer) {
                                                        echo htmlspecialchars($c['fullname']);
                                                        break;
                                                    }
                                                }
                                            }
                                        ?>">
                                    <button type="button" class="btn btn-outline-secondary" id="btn-clear-customer" title="<?= __('Xóa chọn') ?>" style="display:none;"><i class="ri-close-line"></i></button>
                                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalAddCustomer" title="<?= __('Tạo khách hàng mới') ?>"><i class="ri-user-add-line"></i></button>
                                    <div id="customer-dropdown" class="position-absolute top-100 start-0 w-100 bg-white border rounded shadow-sm" style="z-index:1055;max-height:220px;overflow-y:auto;display:none;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label"><?= __('Trạng thái') ?></label>
                                <select class="form-select" name="status">
                                    <option value="cn_warehouse" selected><?= __('Đã về kho Trung Quốc') ?></option>
                                    <option value="shipping"><?= __('Đang vận chuyển') ?></option>
                                    <option value="vn_warehouse"><?= __('Đã về kho Việt Nam') ?></option>
                                    <option value="delivered"><?= __('Đã giao hàng') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 wholesale-only">
                            <div class="mb-3">
                                <label class="form-label"><?= __('Mã hàng') ?></label>
                                <input type="text" class="form-control" name="product_code" placeholder="<?= __('Nhập mã hàng') ?>">
                            </div>
                        </div>
                        <div class="col-md-4 wholesale-only">
                            <div class="mb-3">
                                <label class="form-label"><?= __('Mã vận đơn') ?></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="tracking_number" id="tracking-number-input" placeholder="<?= __('Quét hoặc nhập mã vận đơn') ?>" style="text-transform:uppercase">
                                    <button type="button" class="btn btn-outline-primary" id="btn-scan-tracking" title="<?= __('Quét mã') ?>"><i class="ri-barcode-line"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label"><?= __('Tên sản phẩm') ?></label>
                                <input type="text" class="form-control" name="product_name">
                            </div>
                        </div>
                        <div class="col-md-4 wholesale-only">
                            <div class="mb-3">
                                <label class="form-label"><?= __('Tổng cân nặng mã hàng') ?> (kg)</label>
                                <input type="number" class="form-control" name="weight_actual" value="0" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-4 wholesale-only">
                            <div class="mb-3">
                                <label class="form-label"><?= __('Tổng khối hàng') ?> (m³)</label>
                                <input type="number" class="form-control" name="volume_actual" value="0" step="0.0001" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('Ảnh sản phẩm') ?></label>
                        <input type="file" class="form-control" name="product_images[]" id="product_image_input" accept="image/*" multiple>
                        <div id="image-preview" class="mt-2 d-flex flex-wrap gap-2"></div>
                    </div>
                </div>
            </div>

            <!-- Quét mã vận đơn (hàng lẻ) -->
            <div class="card retail-scan-card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="ri-barcode-line"></i> <?= __('Quét mã vận đơn') ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="input-group input-group-lg">
                                <input type="text" class="form-control" id="retail-scan-input" placeholder="<?= __('Quét hoặc nhập mã vận đơn rồi nhấn Enter') ?>" autofocus>
                                <button type="button" class="btn btn-primary" id="btn-scan-submit"><i class="ri-send-plane-line"></i></button>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="alert alert-success mb-0 py-2 text-center h-100 d-flex align-items-center justify-content-center">
                                <span><?= __('Đã quét') ?>: <strong id="scan-count" class="fs-4">0</strong> <?= __('đơn') ?></span>
                            </div>
                        </div>
                    </div>
                    <div id="scan-log" class="mt-3" style="max-height:300px;overflow-y:auto;"></div>
                </div>
            </div>

            <!-- Kiện hàng (hàng lô) -->
            <div class="card wholesale-only">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0"><?= __('Kiện hàng') ?></h5>
                    <button type="button" class="btn btn-sm btn-primary" id="btn-add-package"><i class="ri-add-line"></i> <?= __('Thêm kiện') ?></button>
                </div>
                <div class="card-body">
                    <div id="packages-container">
                        <div class="package-item card card-body bg-light mb-3" data-index="0">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong class="package-label"><?= __('Kiện') ?> #1</strong>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-package" style="display:none;" title="<?= __('Xóa kiện') ?>"><i class="ri-delete-bin-line"></i></button>
                            </div>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="mb-2">
                                        <label class="form-label"><?= __('Số kiện') ?></label>
                                        <input type="number" class="form-control pkg-calc" name="packages[0][qty]" value="1" min="1">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-2">
                                        <label class="form-label"><?= __('Cân nặng/kiện') ?> (kg)</label>
                                        <input type="number" class="form-control pkg-calc" name="packages[0][weight]" value="0" step="0.01" min="0">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="mb-2">
                                        <label class="form-label"><?= __('Dài') ?> (cm)</label>
                                        <input type="number" class="form-control pkg-calc" name="packages[0][length_cm]" value="0" step="0.1" min="0">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="mb-2">
                                        <label class="form-label"><?= __('Rộng') ?> (cm)</label>
                                        <input type="number" class="form-control pkg-calc" name="packages[0][width_cm]" value="0" step="0.1" min="0">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="mb-2">
                                        <label class="form-label"><?= __('Cao') ?> (cm)</label>
                                        <input type="number" class="form-control pkg-calc" name="packages[0][height_cm]" value="0" step="0.1" min="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row g-2 pkg-summary-row">
                        <div class="col-md-4">
                            <div class="alert alert-info mb-0 py-2">
                                <small class="text-muted"><?= __('Tổng kiện') ?>:</small> <strong id="sum-pkg-count">0</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="alert alert-info mb-0 py-2">
                                <small class="text-muted"><?= __('Tổng cân nặng') ?>:</small> <strong id="sum-pkg-weight">0 kg</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="alert alert-info mb-0 py-2">
                                <small class="text-muted"><?= __('Tổng số khối') ?>:</small> <strong id="sum-pkg-cbm">0 m³</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ghi chú -->
            <div class="card wholesale-only">
                <div class="card-header">
                    <h5 class="card-title mb-0"><?= __('Ghi chú') ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><?= __('Ghi chú khách hàng') ?></label>
                                <textarea class="form-control" name="note" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><?= __('Ghi chú nội bộ') ?></label>
                                <textarea class="form-control" name="note_internal" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions (wholesale only) -->
            <div class="d-flex gap-2 mb-4 wholesale-only">
                <button type="submit" class="btn btn-primary"><i class="ri-save-line"></i> <?= __('Tạo đơn hàng') ?></button>
                <a href="<?= base_url('admin/orders-list') ?>" class="btn btn-secondary"><?= __('Hủy') ?></a>
            </div>
        </form>

<!-- Modal: Tạo khách hàng nhanh -->
<div class="modal fade" id="modalAddCustomer" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('Tạo khách hàng mới') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-quick-customer">
                <input type="hidden" name="<?= $csrf->get_token_name() ?>" value="<?= $csrf->get_token_value() ?>">
                <input type="hidden" name="request_name" value="add">
                <div class="modal-body">
                    <div id="modal-alert-box"></div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('Họ tên') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="fullname" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('Số điện thoại') ?></label>
                        <input type="text" class="form-control" name="phone">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">WeChat</label>
                                <input type="text" class="form-control" name="wechat">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Zalo</label>
                                <input type="text" class="form-control" name="zalo">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('Địa chỉ Việt Nam') ?></label>
                        <input type="text" class="form-control" name="address_vn">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= __('Hủy') ?></button>
                    <button type="submit" class="btn btn-success" id="btn-quick-customer">
                        <i class="ri-user-add-line"></i> <?= __('Tạo khách hàng') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once(__DIR__.'/footer.php'); ?>

<?php
$body['footer'] = '';
?>
<script>
// ===== Retail scan auto-save =====
var scanCount = 0;
var scanBusy = false;

function submitRetailScan() {
    var $input = $('#retail-scan-input');
    var tracking = $.trim($input.val());
    if(!tracking || scanBusy) return;

    scanBusy = true;
    $input.prop('disabled', true);
    var $scanBtn = $('#btn-scan-submit');
    $scanBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status"></span>');

    var formData = new FormData($('#form-add-order')[0]);
    formData.set('product_type', 'retail');
    formData.delete('packages[0][qty]');
    formData.delete('packages[0][weight]');
    formData.delete('packages[0][length_cm]');
    formData.delete('packages[0][width_cm]');
    formData.delete('packages[0][height_cm]');
    formData.append('packages[0][tracking_cn]', tracking);
    formData.append('packages[0][qty]', '1');

    $.ajax({
        url: '<?= base_url('ajaxs/admin/orders.php') ?>',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(res){
            if(res.status == 'success'){
                scanCount++;
                $('#scan-count').text(scanCount);
                var time = new Date().toLocaleTimeString();
                $('#scan-log').prepend(
                    '<div class="d-flex align-items-center gap-2 py-1 border-bottom">'
                    + '<span class="badge bg-success">' + scanCount + '</span>'
                    + '<code class="flex-grow-1">' + $('<span>').text(tracking).html() + '</code>'
                    + '<small class="text-muted">' + (res.order_code || '') + '</small>'
                    + '<small class="text-muted">' + time + '</small>'
                    + '<i class="ri-check-line text-success"></i>'
                    + '</div>'
                );
                $input.val('').prop('disabled', false).focus();
            } else {
                $('#scan-log').prepend(
                    '<div class="d-flex align-items-center gap-2 py-1 border-bottom text-danger">'
                    + '<span class="badge bg-danger"><i class="ri-close-line"></i></span>'
                    + '<code>' + $('<span>').text(tracking).html() + '</code>'
                    + '<small>' + res.msg + '</small>'
                    + '</div>'
                );
                $input.prop('disabled', false).select();
            }
        },
        error: function(xhr){
            var errMsg = '<?= __('Lỗi kết nối server') ?>';
            try { var r = JSON.parse(xhr.responseText); if(r.msg) errMsg = r.msg; } catch(e){}
            $('#scan-log').prepend(
                '<div class="d-flex align-items-center gap-2 py-1 border-bottom text-danger">'
                + '<span class="badge bg-danger"><i class="ri-close-line"></i></span>'
                + '<code>' + $('<span>').text(tracking).html() + '</code>'
                + '<small>' + errMsg + '</small>'
                + '</div>'
            );
            $input.prop('disabled', false).select();
        },
        complete: function(){
            scanBusy = false;
            $scanBtn.prop('disabled', false).html('<i class="ri-send-plane-line"></i>');
        }
    });
}

$('#retail-scan-input').on('keydown', function(e){
    if(e.key === 'Enter'){ e.preventDefault(); submitRetailScan(); }
});
$('#btn-scan-submit').on('click', submitRetailScan);

// ===== Wholesale package management =====
var packageIndex = 1;
$('#btn-add-package').on('click', function(){
    var i = packageIndex++;
    var html = '<div class="package-item card card-body bg-light mb-3" data-index="' + i + '">'
        + '<div class="d-flex justify-content-between align-items-center mb-2">'
        + '<strong class="package-label"><?= __('Kiện') ?> #' + (i + 1) + '</strong>'
        + '<button type="button" class="btn btn-sm btn-outline-danger btn-remove-package" title="<?= __('Xóa kiện') ?>"><i class="ri-delete-bin-line"></i></button>'
        + '</div>'
        + '<div class="row">'
        + '<div class="col-md-3"><div class="mb-2"><label class="form-label"><?= __('Số kiện') ?></label><input type="number" class="form-control pkg-calc" name="packages[' + i + '][qty]" value="1" min="1"></div></div>'
        + '<div class="col-md-3"><div class="mb-2"><label class="form-label"><?= __('Cân nặng/kiện') ?> (kg)</label><input type="number" class="form-control pkg-calc" name="packages[' + i + '][weight]" value="0" step="0.01" min="0"></div></div>'
        + '<div class="col-md-2"><div class="mb-2"><label class="form-label"><?= __('Dài') ?> (cm)</label><input type="number" class="form-control pkg-calc" name="packages[' + i + '][length_cm]" value="0" step="0.1" min="0"></div></div>'
        + '<div class="col-md-2"><div class="mb-2"><label class="form-label"><?= __('Rộng') ?> (cm)</label><input type="number" class="form-control pkg-calc" name="packages[' + i + '][width_cm]" value="0" step="0.1" min="0"></div></div>'
        + '<div class="col-md-2"><div class="mb-2"><label class="form-label"><?= __('Cao') ?> (cm)</label><input type="number" class="form-control pkg-calc" name="packages[' + i + '][height_cm]" value="0" step="0.1" min="0"></div></div>'
        + '</div></div>';
    $('#packages-container').append(html);
    updatePackageButtons();
    toggleWeightExclusive();
});

$(document).on('click', '.btn-remove-package', function(){
    $(this).closest('.package-item').remove();
    updatePackageButtons();
});

function updatePackageButtons() {
    var items = $('.package-item');
    items.each(function(idx){
        $(this).find('.package-label').text('<?= __('Kiện') ?> #' + (idx + 1));
        $(this).find('.btn-remove-package').toggle(items.length > 1);
    });
    calcPackageSummary();
}

function calcPackageSummary() {
    var totalCount = 0, totalWeight = 0, totalCbm = 0;
    $('.package-item').each(function(){
        var qty = parseInt($(this).find('[name$="[qty]"]').val()) || 1;
        var w = parseFloat($(this).find('[name$="[weight]"]').val()) || 0;
        var l = parseFloat($(this).find('[name$="[length_cm]"]').val()) || 0;
        var r = parseFloat($(this).find('[name$="[width_cm]"]').val()) || 0;
        var h = parseFloat($(this).find('[name$="[height_cm]"]').val()) || 0;
        var cbm = (l * r * h) / 1000000;
        totalCount += qty;
        totalWeight += w * qty;
        totalCbm += qty * cbm;
    });
    var orderW = parseFloat($('input[name="weight_actual"]').val()) || 0;
    var orderV = parseFloat($('input[name="volume_actual"]').val()) || 0;
    $('#sum-pkg-count').text(totalCount);
    $('#sum-pkg-weight').text((orderW > 0 ? orderW : totalWeight).toFixed(2) + ' kg');
    $('#sum-pkg-cbm').text((orderV > 0 ? orderV : totalCbm) > 0 ? parseFloat((orderV > 0 ? orderV : totalCbm).toFixed(4)) + ' m³' : '0 m³');
}

$(document).on('input change', '.pkg-calc', calcPackageSummary);
$('input[name="weight_actual"]').on('input change', calcPackageSummary);
$('input[name="volume_actual"]').on('input change', calcPackageSummary);
calcPackageSummary();

// Hai cách nhập cân nặng: tổng mã hàng vs cân/kiện — loại trừ lẫn nhau
function toggleWeightExclusive(source) {
    var totalW = parseFloat($('input[name="weight_actual"]').val()) || 0;
    var hasPkgW = false;
    $('[name$="[weight]"]').each(function(){ if ((parseFloat($(this).val()) || 0) > 0) hasPkgW = true; });

    if (source === 'total' || (!source && totalW > 0)) {
        // Tổng cân nặng đã nhập → disable Cân nặng/kiện
        $('[name$="[weight]"]').prop('disabled', totalW > 0).closest('.mb-2').css('opacity', totalW > 0 ? 0.5 : 1);
        $('input[name="weight_actual"]').prop('disabled', false).closest('.mb-3').css('opacity', 1);
    } else if (source === 'pkg' || (!source && hasPkgW)) {
        // Cân nặng/kiện đã nhập → disable Tổng cân nặng
        $('input[name="weight_actual"]').prop('disabled', hasPkgW).closest('.mb-3').css('opacity', hasPkgW ? 0.5 : 1);
        $('[name$="[weight]"]').prop('disabled', false).closest('.mb-2').css('opacity', 1);
    } else {
        // Cả hai trống → bật lại hết
        $('[name$="[weight]"]').prop('disabled', false).closest('.mb-2').css('opacity', 1);
        $('input[name="weight_actual"]').prop('disabled', false).closest('.mb-3').css('opacity', 1);
    }
}
$('input[name="weight_actual"]').on('input change', function(){ toggleWeightExclusive('total'); });
$(document).on('input change', '[name$="[weight]"]', function(){ toggleWeightExclusive('pkg'); });
toggleWeightExclusive();

// Hai cách nhập khối: tổng khối mã hàng vs kích thước/kiện — loại trừ lẫn nhau
function toggleVolumeExclusive(source) {
    var totalV = parseFloat($('input[name="volume_actual"]').val()) || 0;
    var hasPkgDim = false;
    $('.package-item').each(function(){
        var l = parseFloat($(this).find('[name$="[length_cm]"]').val()) || 0;
        var r = parseFloat($(this).find('[name$="[width_cm]"]').val()) || 0;
        var h = parseFloat($(this).find('[name$="[height_cm]"]').val()) || 0;
        if (l > 0 || r > 0 || h > 0) hasPkgDim = true;
    });
    if (source === 'total' || (!source && totalV > 0)) {
        $('[name$="[length_cm]"], [name$="[width_cm]"], [name$="[height_cm]"]').prop('disabled', totalV > 0).closest('.mb-2').css('opacity', totalV > 0 ? 0.5 : 1);
        $('input[name="volume_actual"]').prop('disabled', false).closest('.mb-3').css('opacity', 1);
    } else if (source === 'pkg' || (!source && hasPkgDim)) {
        $('input[name="volume_actual"]').prop('disabled', hasPkgDim).closest('.mb-3').css('opacity', hasPkgDim ? 0.5 : 1);
        $('[name$="[length_cm]"], [name$="[width_cm]"], [name$="[height_cm]"]').prop('disabled', false).closest('.mb-2').css('opacity', 1);
    } else {
        $('[name$="[length_cm]"], [name$="[width_cm]"], [name$="[height_cm]"]').prop('disabled', false).closest('.mb-2').css('opacity', 1);
        $('input[name="volume_actual"]').prop('disabled', false).closest('.mb-3').css('opacity', 1);
    }
}
$('input[name="volume_actual"]').on('input change', function(){ toggleVolumeExclusive('total'); });
$(document).on('input change', '[name$="[length_cm]"], [name$="[width_cm]"], [name$="[height_cm]"]', function(){ toggleVolumeExclusive('pkg'); });
toggleVolumeExclusive();

// Toggle retail/wholesale mode
function toggleProductType() {
    var isRetail = $('select[name="product_type"]').val() === 'retail';
    if(isRetail){
        $('.wholesale-only').hide();
        $('.retail-scan-card').show();
        $('#retail-scan-input').focus();
    } else {
        $('.wholesale-only').show();
        $('.retail-scan-card').hide();
        calcPackageSummary();
    }
}
$('select[name="product_type"]').on('change', toggleProductType);

// Popup chọn loại hàng khi mở trang
$('#form-add-order').hide();
Swal.fire({
    title: '<?= __('Chọn loại hàng') ?>',
    html: '<div class="d-flex gap-3 justify-content-center mt-2">'
        + '<button type="button" class="btn btn-lg btn-outline-primary px-4 py-3 swal-type-btn" data-type="retail">'
        + '<i class="ri-shopping-bag-line d-block fs-1 mb-2"></i><?= __('Hàng lẻ') ?></button>'
        + '<button type="button" class="btn btn-lg btn-outline-success px-4 py-3 swal-type-btn" data-type="wholesale">'
        + '<i class="ri-stack-line d-block fs-1 mb-2"></i><?= __('Hàng lô') ?></button>'
        + '</div>',
    showConfirmButton: false,
    allowOutsideClick: false,
    allowEscapeKey: false,
    didOpen: function(){
        $('.swal-type-btn').on('click', function(){
            var type = $(this).data('type');
            $('select[name="product_type"]').val(type);
            toggleProductType();
            $('#form-add-order').show();
            Swal.close();
        });
    }
});


// Compress image using canvas
function compressImage(file, maxWidth, quality) {
    maxWidth = maxWidth || 1920;
    quality = quality || 0.7;
    return new Promise(function(resolve) {
        if (!file.type.match(/image\/(jpeg|png|webp|bmp)/)) { resolve(file); return; }
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = new Image();
            img.onload = function() {
                var w = img.width, h = img.height;
                if (w > maxWidth) { h = Math.round(h * maxWidth / w); w = maxWidth; }
                var canvas = document.createElement('canvas');
                canvas.width = w; canvas.height = h;
                canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                canvas.toBlob(function(blob) {
                    if (!blob || blob.size >= file.size) { resolve(file); return; }
                    var compressed = new File([blob], file.name.replace(/\.\w+$/, '.jpg'), {type: 'image/jpeg', lastModified: Date.now()});
                    resolve(compressed);
                }, 'image/jpeg', quality);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
}

function compressAndSetFiles(input) {
    var files = Array.from(input.files);
    if (!files.length) return Promise.resolve();
    return Promise.all(files.map(function(f) { return compressImage(f); })).then(function(compressed) {
        var dt = new DataTransfer();
        compressed.forEach(function(f) { dt.items.add(f); });
        input.files = dt.files;
    });
}

// Image preview (multiple)
$('#product_image_input').on('change', function(){
    var input = this;
    var $preview = $('#image-preview').empty();
    $preview.html('<small class="text-muted"><?= __('Đang nén ảnh...') ?></small>');
    compressAndSetFiles(input).then(function() {
        $preview.empty();
        Array.from(input.files).forEach(function(file, idx){
            var reader = new FileReader();
            reader.onload = function(e){
                var $wrap = $('<div class="position-relative d-inline-block">');
                $wrap.append('<img src="' + e.target.result + '" class="img-thumbnail" style="max-height:100px;">');
                $wrap.append('<button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 btn-remove-img" data-idx="' + idx + '" style="padding:1px 5px;font-size:10px;"><i class="ri-close-line"></i></button>');
                $preview.append($wrap);
            };
            reader.readAsDataURL(file);
        });
    });
});

$(document).on('click', '.btn-remove-img', function(){
    var input = $('#product_image_input')[0];
    var dt = new DataTransfer();
    var idx = $(this).data('idx');
    Array.from(input.files).forEach(function(file, i){
        if(i !== idx) dt.items.add(file);
    });
    input.files = dt.files;
    $(input).trigger('change');
});

// ===== Customer autocomplete =====
var customerList = <?= json_encode(array_values(array_map(function($c) {
    return ['id' => $c['id'], 'label' => $c['fullname']];
}, $customers)), JSON_UNESCAPED_UNICODE) ?>;

function renderCustomerDropdown(q) {
    var $dd = $('#customer-dropdown');
    var rawQ = q.trim();
    q = rawQ.toLowerCase();
    var results = q === '' ? customerList : customerList.filter(function(c){
        return c.label.toLowerCase().indexOf(q) !== -1;
    });
    if (!results.length) {
        var createHtml = rawQ
            ? '<div class="customer-option-create px-3 py-2 d-flex align-items-center gap-2" data-name="' + $('<span>').text(rawQ).html() + '" style="cursor:pointer;">'
                + '<i class="ri-user-add-line text-success"></i>'
                + '<span><?= __('Tạo khách hàng') ?>: <strong>' + $('<span>').text(rawQ).html() + '</strong></span>'
                + '</div>'
            : '<div class="px-3 py-2 text-muted small"><?= __('Không tìm thấy khách hàng') ?></div>';
        $dd.html(createHtml).show();
        return;
    }
    var html = '';
    results.slice(0, 50).forEach(function(c){
        html += '<div class="customer-option px-3 py-2 border-bottom" data-id="' + c.id + '" data-label="' + $('<span>').text(c.label).html() + '" style="cursor:pointer;">'
            + $('<span>').text(c.label).html()
            + '</div>';
    });
    $dd.html(html).show();
}

function selectCustomer(id, label) {
    $('#select-customer').val(id);
    $('#customer-search').val(label);
    $('#customer-dropdown').hide();
    $('#btn-clear-customer').show();
}

function clearCustomer() {
    $('#select-customer').val('');
    $('#customer-search').val('').focus();
    $('#customer-dropdown').hide();
    $('#btn-clear-customer').hide();
}

$('#customer-search').on('input', function(){
    var val = $(this).val();
    if (!val) { clearCustomer(); return; }
    renderCustomerDropdown(val);
    $('#select-customer').val('');
    $('#btn-clear-customer').hide();
}).on('focus', function(){
    if (!$('#select-customer').val()) renderCustomerDropdown($(this).val());
}).on('keydown', function(e){
    var $items = $('#customer-dropdown .customer-option:visible');
    if (!$items.length) return;
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        var $active = $items.filter('.active');
        var $next = $active.length ? $active.removeClass('active').next() : $items.first();
        $next.addClass('active bg-primary text-white');
        $next[0] && $next[0].scrollIntoView({block:'nearest'});
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        var $active = $items.filter('.active');
        var $prev = $active.length ? $active.removeClass('active').prev() : $items.last();
        $prev.addClass('active bg-primary text-white');
        $prev[0] && $prev[0].scrollIntoView({block:'nearest'});
    } else if (e.key === 'Enter') {
        var $active = $items.filter('.active');
        if ($active.length) { e.preventDefault(); selectCustomer($active.data('id'), $active.data('label')); }
    } else if (e.key === 'Escape') {
        $('#customer-dropdown').hide();
    }
});

$(document).on('click', '#customer-dropdown .customer-option', function(){
    selectCustomer($(this).data('id'), $(this).data('label'));
});

$(document).on('click', '#customer-dropdown .customer-option-create', function(){
    var name = $(this).data('name');
    $('#customer-dropdown').hide();
    $('#modalAddCustomer [name="fullname"]').val(name);
    var modalEl = document.getElementById('modalAddCustomer');
    var m = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    m.show();
    setTimeout(function(){ $('#modalAddCustomer [name="phone"]').focus(); }, 350);
});

$('#btn-clear-customer').on('click', clearCustomer);

$(document).on('click', function(e){
    if (!$(e.target).closest('.input-group').length) $('#customer-dropdown').hide();
});

// Init clear button if preselected
if ($('#select-customer').val()) $('#btn-clear-customer').show();

// Quick add customer
$('#form-quick-customer').on('submit', function(e){
    e.preventDefault();
    var $btn = $('#btn-quick-customer');
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status"></span><?= __('Đang tạo...') ?>');
    $.ajax({
        url: '<?= base_url('ajaxs/admin/customers.php') ?>',
        type: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(res){
            if(res.status == 'success'){
                var label = res.fullname;
                // Add to customerList and select
                customerList.unshift({id: res.customer_id, label: label});
                selectCustomer(res.customer_id, label);
                // Close modal and reset form
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAddCustomer')).hide();
                $('#form-quick-customer')[0].reset();
                $('#modal-alert-box').html('');
                Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false});
            } else {
                $('#modal-alert-box').html('<div class="alert alert-danger">' + res.msg + '</div>');
            }
        },
        complete: function(){ $btn.prop('disabled', false).html('<i class="ri-user-add-line"></i> <?= __('Tạo khách hàng') ?>'); }
    });
});

// Enter key to submit wholesale form
$('#form-add-order').on('keydown', 'input[type="text"], input[type="number"]', function(e){
    if(e.key !== 'Enter') return;
    if($('select[name="product_type"]').val() === 'retail') return; // retail uses scan
    if($(this).attr('id') === 'retail-scan-input') return;
    e.preventDefault();
    $('#form-add-order').submit();
});

$('#form-add-order').on('submit', function(e){
    e.preventDefault();
    // Retail uses scan auto-save, block normal submit
    if($('select[name="product_type"]').val() === 'retail') return;

    var $btn = $('button[type=submit]', this);
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status"></span><?= __('Đang lưu...') ?>');

    var formData = new FormData(this);
    $.ajax({
        url: '<?= base_url('ajaxs/admin/orders.php') ?>',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(res){
            if(res.status == 'success'){
                Swal.fire({icon: 'success', title: res.msg, showConfirmButton: true, confirmButtonText: 'OK'}).then(function(){
                    window.location.href = '<?= base_url('admin/orders-detail') ?>?id=' + res.order_id;
                });
            } else {
                $btn.prop('disabled', false).html('<i class="ri-save-line"></i> <?= __('Tạo đơn hàng') ?>');
                $('#alert-box').html('<div class="alert alert-danger">' + res.msg + '</div>');
                $('html, body').animate({scrollTop: 0}, 300);
            }
        },
        error: function(xhr){
            $btn.prop('disabled', false).html('<i class="ri-save-line"></i> <?= __('Tạo đơn hàng') ?>');
            var msg = '<?= __('Lỗi server') ?>';
            try { msg = xhr.responseText.substring(0, 500); } catch(e){}
            Swal.fire({icon: 'error', title: 'Error', text: msg});
        }
    });
});

// ===== Barcode Camera Scanner =====
(function(){
    function ensureLib(cb) {
        if (typeof Html5QrcodeScanner !== 'undefined') return cb();
        var s = document.createElement('script');
        s.src = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
        s.onload = cb;
        document.head.appendChild(s);
    }

    $('#btn-scan-tracking').on('click', function(){
        ensureLib(openScanner);
    });

    function openScanner() {
        // Nếu đang mở thì không mở nữa
        if ($('#scan-fullscreen').length) return;

        var $overlay = $('<div id="scan-fullscreen" style="position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;background:#000;">' +
            '<div style="position:absolute;top:0;left:0;right:0;padding:8px 12px;background:rgba(0,0,0,0.7);z-index:10;display:flex;align-items:center;justify-content:space-between;">' +
            '<span style="color:#fff;font-size:14px;"><i class="ri-camera-line"></i> <?= __('Quét mã vận đơn') ?></span>' +
            '<button type="button" id="btn-close-scan" style="background:none;border:none;color:#fff;font-size:24px;cursor:pointer;"><i class="ri-close-line"></i></button>' +
            '</div>' +
            '<div id="scan-reader" style="width:100%;height:100%;"></div>' +
            '</div>');
        $('body').append($overlay);

        var scanner = new Html5QrcodeScanner('scan-reader', {
            fps: 15,
            qrbox: function(vw, vh){ return { width: Math.floor(vw*0.8), height: Math.floor(Math.min(vh*0.3, 150)) }; },
            rememberLastUsedCamera: true,
            showTorchButtonIfSupported: true,
            formatsToSupport: [Html5QrcodeSupportedFormats.CODE_128, Html5QrcodeSupportedFormats.CODE_39, Html5QrcodeSupportedFormats.QR_CODE, Html5QrcodeSupportedFormats.EAN_13]
        }, false);

        scanner.render(function(text){
            var val = text.trim().toUpperCase();
            $('#tracking-number-input').val(val).trigger('change');
            scanner.clear();
            $('#scan-fullscreen').remove();
            Swal.fire({icon:'success', title: val, timer:1500, showConfirmButton:false});
        }, function(){});

        $('#btn-close-scan').on('click', function(){
            scanner.clear();
            $('#scan-fullscreen').remove();
        });
    }
})();
</script>
