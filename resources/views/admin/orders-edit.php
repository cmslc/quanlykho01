<?php
require_once(__DIR__.'/../../../models/is_admin.php');
require_once(__DIR__.'/../../../libs/csrf.php');
require_once(__DIR__.'/../../../libs/database/packages.php');

$id = intval(input_get('id'));
$order = $ToryHub->get_row_safe("SELECT * FROM `orders` WHERE `id` = ?", [$id]);
if (!$order) {
    redirect(base_url('admin/orders-list'));
}

$page_title = __('Sửa đơn hàng') . ' #' . $order['id'];
$customers = $ToryHub->get_list_safe("SELECT `id`, `customer_code`, `fullname` FROM `customers` ORDER BY `fullname` ASC", []);

// Get packages for this order
$packages = $ToryHub->get_list_safe(
    "SELECT p.* FROM `packages` p
     JOIN `package_orders` po ON p.id = po.package_id
     WHERE po.order_id = ? ORDER BY p.id ASC", [$id]
);

$productType = $order['product_type'] ?? 'retail';

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Sửa đơn hàng') ?> #<?= $order['id'] ?></h4>
                </div>
            </div>
        </div>

        <form id="form-edit-order">
            <input type="hidden" name="<?= $csrf->get_token_name() ?>" value="<?= $csrf->get_token_value() ?>">
            <input type="hidden" name="request_name" value="edit">
            <input type="hidden" name="id" value="<?= $order['id'] ?>">
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
                                    <option value="retail" <?= $productType === 'retail' ? 'selected' : '' ?>><?= __('Hàng lẻ') ?></option>
                                    <option value="wholesale" <?= $productType === 'wholesale' ? 'selected' : '' ?>><?= __('Hàng lô') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 wholesale-only">
                            <div class="mb-3">
                                <label class="form-label"><?= __('Phân loại vận chuyển') ?></label>
                                <select class="form-select" name="cargo_type">
                                    <option value="easy" <?= ($order['cargo_type'] ?? '') === 'easy' ? 'selected' : '' ?>><?= __('Hàng dễ vận chuyển') ?></option>
                                    <option value="difficult" <?= ($order['cargo_type'] ?? '') === 'difficult' ? 'selected' : '' ?>><?= __('Hàng khó vận chuyển') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 wholesale-only">
                            <div class="mb-3">
                                <label class="form-label"><?= __('Khách hàng') ?> <span class="text-danger">*</span></label>
                                <select class="form-select" name="customer_id" id="select-customer">
                                    <option value=""><?= __('-- Chọn khách hàng --') ?></option>
                                    <?php foreach ($customers as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $order['customer_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['customer_code'] . ' - ' . $c['fullname']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label"><?= __('Trạng thái') ?></label>
                                <select class="form-select" name="status">
                                    <option value="cn_warehouse" <?= $order['status'] === 'cn_warehouse' ? 'selected' : '' ?>><?= __('Đã về kho Trung Quốc') ?></option>
                                    <option value="packed" <?= $order['status'] === 'packed' ? 'selected' : '' ?>><?= __('Đã đóng bao') ?></option>
                                    <option value="shipping" <?= $order['status'] === 'shipping' ? 'selected' : '' ?>><?= __('Đang vận chuyển') ?></option>
                                    <option value="vn_warehouse" <?= $order['status'] === 'vn_warehouse' ? 'selected' : '' ?>><?= __('Đã về kho Việt Nam') ?></option>
                                    <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>><?= __('Đã giao hàng') ?></option>
                                    <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>><?= __('Đã hủy') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 wholesale-only">
                            <div class="mb-3">
                                <label class="form-label"><?= __('Mã hàng') ?></label>
                                <input type="text" class="form-control" name="product_code" value="<?= htmlspecialchars($order['product_code'] ?? '') ?>" placeholder="<?= __('Nhập mã hàng') ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label"><?= __('Tên sản phẩm') ?></label>
                                <input type="text" class="form-control" name="product_name" value="<?= htmlspecialchars($order['product_name'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-4 wholesale-only">
                            <div class="mb-3">
                                <label class="form-label"><?= __('Tổng cân nặng mã hàng') ?> (kg)</label>
                                <input type="number" class="form-control" name="weight_actual" value="<?= floatval($order['weight_actual'] ?? 0) ?>" step="0.01" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('Ảnh sản phẩm') ?></label>
                        <input type="file" class="form-control" name="product_images[]" id="product_image_input" accept="image/*" multiple>
                        <input type="hidden" name="current_images" value="<?= htmlspecialchars($order['product_image'] ?? '') ?>">
                        <?php if (!empty($order['product_image'])): ?>
                        <div id="current-images" class="mt-2 d-flex flex-wrap gap-2">
                            <?php foreach (explode(',', $order['product_image']) as $img): ?>
                            <?php if (trim($img)): ?>
                            <div class="position-relative d-inline-block current-img-wrap">
                                <img src="<?= get_upload_url(trim($img)) ?>" class="img-thumbnail" style="max-height:100px;">
                                <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 btn-remove-current-img" data-path="<?= htmlspecialchars(trim($img)) ?>" style="padding:1px 5px;font-size:10px;"><i class="ri-close-line"></i></button>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div id="image-preview" class="mt-2 d-flex flex-wrap gap-2"></div>
                    </div>
                </div>
            </div>

            <!-- Kiện hàng -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="card-title mb-0"><i class="ri-archive-line me-1"></i><?= __('Kiện hàng') ?> (<?= count($packages) ?>)</h5>
                        <?php if ($productType !== 'retail' && !empty($order['product_code'])): ?>
                        <span class="text-muted"><i class="ri-barcode-line me-1"></i><?= __('Mã hàng') ?>: <strong><?= htmlspecialchars($order['product_code']) ?></strong></span>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddPackage"><i class="ri-add-line"></i> <?= __('Tạo kiện') ?></button>
                </div>
                <div class="card-body">
                    <?php if (!empty($packages)): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th><?= __('Mã kiện') ?></th>
                                    <?php if ($productType === 'retail'): ?>
                                    <th><?= __('Mã vận đơn') ?></th>
                                    <?php endif; ?>
                                    <th><?= __('Cân nặng (kg)') ?></th>
                                    <th><?= __('Kích thước (cm)') ?></th>
                                    <th><?= __('Số khối (m³)') ?></th>
                                    <th><?= __('Trạng thái') ?></th>
                                    <th class="text-center"><?= __('Thao tác') ?></th>
                                </tr>
                            </thead>
                            <?php
                            // Group packages by weight + dims
                            $pkgGroups = []; $gIdx = 0;
                            foreach ($packages as $pkg) {
                                $gKey = floatval($pkg['weight_actual']).'|'.floatval($pkg['length_cm']).'|'.floatval($pkg['width_cm']).'|'.floatval($pkg['height_cm']);
                                if (!isset($pkgGroups[$gKey])) $pkgGroups[$gKey] = ['gid' => 'g'.$gIdx++, 'pkgs' => []];
                                $pkgGroups[$gKey]['pkgs'][] = $pkg;
                            }
                            ?>
                            <tbody>
                                <?php foreach ($pkgGroups as $grp):
                                    $gpkgs  = $grp['pkgs'];
                                    $gid    = $grp['gid'];
                                    $first  = $gpkgs[0];
                                    $gCount = count($gpkgs);
                                    $allIds = array_column($gpkgs, 'id');
                                    $pkgVolume = ($first['length_cm'] * $first['width_cm'] * $first['height_cm']) / 1000000;
                                    $uniqueStatuses = array_unique(array_column($gpkgs, 'status'));
                                ?>
                                <tr>
                                    <td>
                                        <?php if ($gCount > 1): ?>
                                        <strong><?= htmlspecialchars($gpkgs[0]['package_code']) ?> ~ <?= htmlspecialchars($gpkgs[$gCount-1]['package_code']) ?></strong>
                                        <span class="badge bg-primary-subtle text-primary ms-1"><?= $gCount ?> <?= __('kiện') ?></span>
                                        <?php else: ?>
                                        <strong><?= htmlspecialchars($first['package_code'] ?? '') ?></strong>
                                        <?php endif; ?>
                                        <?php // Hidden sync inputs for packages 2..N (weight + dims) ?>
                                        <?php foreach (array_slice($gpkgs, 1) as $sp): ?>
                                        <input type="hidden" class="grp-sync-w" data-gid="<?= $gid ?>" name="edit_packages[<?= $sp['id'] ?>][weight_actual]" value="<?= floatval($sp['weight_actual']) ?>">
                                        <input type="hidden" class="grp-sync-l" data-gid="<?= $gid ?>" name="edit_packages[<?= $sp['id'] ?>][length_cm]"    value="<?= floatval($sp['length_cm']) ?>">
                                        <input type="hidden" class="grp-sync-r" data-gid="<?= $gid ?>" name="edit_packages[<?= $sp['id'] ?>][width_cm]"     value="<?= floatval($sp['width_cm']) ?>">
                                        <input type="hidden" class="grp-sync-h" data-gid="<?= $gid ?>" name="edit_packages[<?= $sp['id'] ?>][height_cm]"    value="<?= floatval($sp['height_cm']) ?>">
                                        <?php endforeach; ?>
                                    </td>
                                    <?php if ($productType === 'retail'): ?>
                                    <td>
                                        <?php foreach ($gpkgs as $p): ?>
                                        <input type="text" class="form-control form-control-sm <?= $gCount > 1 ? 'mb-1' : '' ?>" name="edit_packages[<?= $p['id'] ?>][tracking_cn]" value="<?= htmlspecialchars($p['tracking_cn'] ?? '') ?>" style="min-width:120px;text-transform:uppercase"<?= $gCount > 1 ? ' title="' . htmlspecialchars($p['package_code'] ?? '') . '"' : '' ?>>
                                        <?php endforeach; ?>
                                    </td>
                                    <?php endif; ?>
                                    <td>
                                        <input type="number" class="form-control form-control-sm pkg-w-input grp-master-w" name="edit_packages[<?= $first['id'] ?>][weight_actual]" value="<?= floatval($first['weight_actual']) ?>" step="0.01" min="0" style="width:80px" data-gid="<?= $gid ?>">
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <input type="number" class="form-control form-control-sm pkg-dim-input grp-master-l" name="edit_packages[<?= $first['id'] ?>][length_cm]" value="<?= floatval($first['length_cm']) ?>" step="0.1" min="0" placeholder="D" style="width:58px" data-gid="<?= $gid ?>" title="<?= __('Dài') ?>">
                                            <input type="number" class="form-control form-control-sm pkg-dim-input grp-master-r" name="edit_packages[<?= $first['id'] ?>][width_cm]"  value="<?= floatval($first['width_cm']) ?>"  step="0.1" min="0" placeholder="R" style="width:58px" data-gid="<?= $gid ?>" title="<?= __('Rộng') ?>">
                                            <input type="number" class="form-control form-control-sm pkg-dim-input grp-master-h" name="edit_packages[<?= $first['id'] ?>][height_cm]" value="<?= floatval($first['height_cm']) ?>" step="0.1" min="0" placeholder="C" style="width:58px" data-gid="<?= $gid ?>" title="<?= __('Cao') ?>">
                                        </div>
                                    </td>
                                    <td class="pkg-cbm-cell text-nowrap">
                                        <?= $pkgVolume > 0 ? number_format($pkgVolume, 4, '.', '') : '<span class="text-muted">-</span>' ?>
                                    </td>
                                    <td>
                                        <?php foreach ($uniqueStatuses as $st): ?>
                                        <?= display_package_status($st) ?><?= count($uniqueStatuses) > 1 ? '<br>' : '' ?>
                                        <?php endforeach; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($gCount === 1): ?>
                                        <button type="button" class="btn btn-sm btn-soft-danger btn-delete-pkg" data-id="<?= $first['id'] ?>" data-code="<?= htmlspecialchars($first['package_code'] ?? '') ?>" title="<?= __('Xóa') ?>"><i class="ri-delete-bin-line"></i></button>
                                        <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-soft-danger btn-delete-group" data-ids="<?= implode(',', $allIds) ?>" data-count="<?= $gCount ?>" title="<?= __('Xóa nhóm') ?>"><i class="ri-delete-bin-line"></i> <?= $gCount ?></button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php else: ?>
                    <p class="text-muted mb-0"><?= __('Chưa có kiện hàng liên kết.') ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Ghi chú -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><?= __('Ghi chú') ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><?= __('Ghi chú khách hàng') ?></label>
                                <textarea class="form-control" name="note" rows="2"><?= htmlspecialchars($order['note'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><?= __('Ghi chú nội bộ') ?></label>
                                <textarea class="form-control" name="note_internal" rows="2"><?= htmlspecialchars($order['note_internal'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="d-flex gap-2 mb-4">
                <button type="submit" class="btn btn-primary"><i class="ri-save-line"></i> <?= __('Cập nhật') ?></button>
                <a href="<?= base_url('admin/orders-detail&id=' . $order['id']) ?>" class="btn btn-secondary"><?= __('Quay lại') ?></a>
            </div>
        </form>

<!-- Modal Tạo kiện -->
<div class="modal fade" id="modalAddPackage" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ri-archive-line me-1"></i><?= __('Tạo kiện hàng') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if ($productType === 'retail'): ?>
                <div class="mb-3">
                    <label class="form-label"><?= __('Mã vận đơn') ?></label>
                    <input type="text" class="form-control" id="pkg-tracking-cn" placeholder="<?= __('Nhập mã tracking') ?>" style="text-transform:uppercase">
                </div>
                <?php endif; ?>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><?= __('Số kiện') ?></label>
                        <input type="number" class="form-control" id="pkg-qty" value="1" min="1" max="999">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><?= __('Cân nặng/kiện (kg)') ?></label>
                        <input type="number" class="form-control" id="pkg-weight" step="0.01" min="0" placeholder="0.00">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><?= __('Dài (cm)') ?></label>
                        <input type="number" class="form-control pkg-dim" id="pkg-length" step="0.1" min="0" placeholder="0">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><?= __('Rộng (cm)') ?></label>
                        <input type="number" class="form-control pkg-dim" id="pkg-width" step="0.1" min="0" placeholder="0">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><?= __('Cao (cm)') ?></label>
                        <input type="number" class="form-control pkg-dim" id="pkg-height" step="0.1" min="0" placeholder="0">
                    </div>
                </div>
                <div class="mb-3" id="pkg-volume-display" style="display:none;">
                    <div class="p-2 bg-light rounded text-center">
                        <span class="text-muted"><?= __('Số khối') ?>:</span> <strong id="pkg-volume-value">0</strong> m³
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= __('Ghi chú') ?></label>
                    <textarea class="form-control" id="pkg-note" rows="2"></textarea>
                </div>
                <div class="alert alert-info mb-0 py-2">
                    <small><i class="ri-link me-1"></i><?= __('Kiện sẽ tự động liên kết với đơn này') ?></small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= __('Đóng') ?></button>
                <button type="button" class="btn btn-primary" id="btn-create-package"><i class="ri-save-line me-1"></i><?= __('Tạo kiện') ?></button>
            </div>
        </div>
    </div>
</div>

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
// Mutual exclusivity: order-level weight vs package weights
function toggleWeightExclusive() {
    var orderW = parseFloat($('input[name="weight_actual"]').val()) || 0;
    var hasPkgW = false;
    $('.pkg-w-input').each(function(){ if ((parseFloat($(this).val()) || 0) > 0) hasPkgW = true; });

    if (orderW > 0) {
        $('.pkg-w-input').prop('readonly', true).css({opacity: 0.5, pointerEvents: 'none', background: '#f8f9fa'});
        $('input[name="weight_actual"]').prop('readonly', false).css({opacity: 1, pointerEvents: '', background: ''});
    } else if (hasPkgW) {
        $('input[name="weight_actual"]').prop('readonly', true).css({opacity: 0.5, pointerEvents: 'none', background: '#f8f9fa'});
        $('.pkg-w-input').prop('readonly', false).css({opacity: 1, pointerEvents: '', background: ''});
    } else {
        $('.pkg-w-input').prop('readonly', false).css({opacity: 1, pointerEvents: '', background: ''});
        $('input[name="weight_actual"]').prop('readonly', false).css({opacity: 1, pointerEvents: '', background: ''});
    }
}
$('input[name="weight_actual"]').on('input change', toggleWeightExclusive);
$(document).on('input change', '.pkg-w-input', toggleWeightExclusive);
toggleWeightExclusive();

// Sync hidden inputs for grouped packages
$(document).on('input', '.grp-master-w', function(){ $('.grp-sync-w[data-gid="' + $(this).data('gid') + '"]').val($(this).val()); });
$(document).on('input', '.grp-master-l', function(){ $('.grp-sync-l[data-gid="' + $(this).data('gid') + '"]').val($(this).val()); });
$(document).on('input', '.grp-master-r', function(){ $('.grp-sync-r[data-gid="' + $(this).data('gid') + '"]').val($(this).val()); });
$(document).on('input', '.grp-master-h', function(){ $('.grp-sync-h[data-gid="' + $(this).data('gid') + '"]').val($(this).val()); });

// Delete group
$(document).on('click', '.btn-delete-group', function(){
    var ids = $(this).data('ids').toString().split(',');
    var count = $(this).data('count');
    Swal.fire({
        title: '<?= __('Xóa') ?> ' + count + ' <?= __('kiện hàng') ?>?',
        icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: '<?= __('Xóa tất cả') ?>',
        cancelButtonText: '<?= __('Hủy') ?>'
    }).then(function(r){
        if (!r.isConfirmed) return;
        var deleted = 0;
        Swal.fire({title: '<?= __('Đang xóa...') ?>', text: '0/' + count, allowOutsideClick: false, didOpen: function(){ Swal.showLoading(); }});
        function next(i){
            if (i >= ids.length){
                Swal.fire({icon: 'success', title: '<?= __('Đã xóa') ?> ' + deleted + ' <?= __('kiện') ?>', timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
                return;
            }
            $.post('<?= base_url('ajaxs/admin/packages.php') ?>', {request_name:'delete', id: ids[i], csrf_token:'<?= $csrf->get_token_value() ?>'}, function(){ deleted++; Swal.update({text: deleted+'/'+count}); next(i+1); }, 'json').fail(function(){ next(i+1); });
        }
        next(0);
    });
});

// Toggle retail/wholesale mode
function toggleProductType() {
    var isRetail = $('select[name="product_type"]').val() === 'retail';
    if(isRetail){
        $('.wholesale-only').hide();
        $('#select-customer').prop('required', false);
    } else {
        $('.wholesale-only').show();
        $('#select-customer').prop('required', true);
    }
}
$('select[name="product_type"]').on('change', toggleProductType);
toggleProductType();

// Image preview (new uploads)
$('#product_image_input').on('change', function(){
    var $preview = $('#image-preview').empty();
    Array.from(this.files).forEach(function(file, idx){
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

// Remove existing images
$(document).on('click', '.btn-remove-current-img', function(){
    var path = $(this).data('path');
    $(this).closest('.current-img-wrap').remove();
    var current = $('[name=current_images]').val().split(',').filter(function(p){ return p.trim() !== path; });
    $('[name=current_images]').val(current.join(','));
});

// Submit
$('#form-edit-order').on('submit', function(e){
    e.preventDefault();
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
                Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){
                    window.location.href = '<?= base_url('admin/orders-detail&id=' . $order['id']) ?>';
                });
            } else {
                $('#alert-box').html('<div class="alert alert-danger">' + res.msg + '</div>');
                $('html, body').animate({scrollTop: 0}, 300);
            }
        }
    });
});


// Auto-calculate volume (CBM) for "Tạo kiện" modal
$('.pkg-dim, #pkg-qty').on('input', function(){
    var l = parseFloat($('#pkg-length').val()) || 0;
    var w = parseFloat($('#pkg-width').val()) || 0;
    var h = parseFloat($('#pkg-height').val()) || 0;
    var qty = Math.max(1, parseInt($('#pkg-qty').val()) || 1);
    if (l > 0 && w > 0 && h > 0) {
        var vol = (l * w * h) / 1000000 * qty;
        $('#pkg-volume-value').text(parseFloat(vol.toFixed(4)));
        $('#pkg-volume-display').show();
    } else {
        $('#pkg-volume-display').hide();
    }
});

// Auto-calculate CBM inline per row
$(document).on('input', '.pkg-dim-input', function(){
    var $row = $(this).closest('tr');
    var l = parseFloat($row.find('[name$="[length_cm]"]').val()) || 0;
    var w = parseFloat($row.find('[name$="[width_cm]"]').val()) || 0;
    var h = parseFloat($row.find('[name$="[height_cm]"]').val()) || 0;
    var $cell = $row.find('.pkg-cbm-cell');
    if (l > 0 && w > 0 && h > 0) {
        $cell.text(parseFloat(((l * w * h) / 1000000).toFixed(4)));
    } else {
        $cell.html('<span class="text-muted">-</span>');
    }
});

// Delete package
$(document).on('click', '.btn-delete-pkg', function(){
    var pkgId = $(this).data('id');
    var pkgCode = $(this).data('code');
    Swal.fire({
        title: '<?= __('Xóa kiện hàng') ?>?',
        text: pkgCode,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: '<?= __('Xóa') ?>',
        cancelButtonText: '<?= __('Hủy') ?>'
    }).then(function(result){
        if(result.isConfirmed){
            $.post('<?= base_url('ajaxs/admin/packages.php') ?>', {
                request_name: 'delete',
                id: pkgId,
                csrf_token: '<?= $csrf->get_token_value() ?>'
            }, function(res){
                if(res.status == 'success'){
                    Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
                } else {
                    Swal.fire({icon: 'error', title: 'Error', text: res.msg});
                }
            }, 'json').fail(function(){
                Swal.fire({icon: 'error', text: '<?= __('Lỗi kết nối') ?>'});
            });
        }
    });
});

// Create package (supports qty > 1)
$('#btn-create-package').on('click', function(){
    var btn = $(this).prop('disabled', true).html('<i class="ri-loader-4-line ri-spin me-1"></i><?= __('Đang tạo...') ?>');
    var qty = Math.max(1, parseInt($('#pkg-qty').val()) || 1);
    var created = 0, errors = 0, total = qty;

    function createOne(remaining){
        if(remaining <= 0){
            if(created > 0){
                Swal.fire({icon: 'success', title: '<?= __('Đã tạo') ?> ' + created + ' <?= __('kiện') ?>', timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
            } else {
                btn.prop('disabled', false).html('<i class="ri-save-line me-1"></i><?= __('Tạo kiện') ?>');
            }
            return;
        }
        var postData = {
            request_name: 'add',
            weight_actual: $('#pkg-weight').val(),
            length_cm: $('#pkg-length').val() || 0,
            width_cm: $('#pkg-width').val() || 0,
            height_cm: $('#pkg-height').val() || 0,
            note: $('#pkg-note').val(),
            'order_ids[]': [<?= $order['id'] ?>],
            csrf_token: '<?= $csrf->get_token_value() ?>'
        };
        <?php if ($productType === 'retail'): ?>
        postData.tracking_cn = $('#pkg-tracking-cn').val();
        <?php endif; ?>
        btn.html('<i class="ri-loader-4-line ri-spin me-1"></i>' + (total - remaining + 1) + '/' + total);
        $.post('<?= base_url('ajaxs/admin/packages.php') ?>', postData, function(res){
            if(res.status == 'success'){ created++; } else { errors++; }
            createOne(remaining - 1);
        }, 'json').fail(function(){
            errors++;
            createOne(remaining - 1);
        });
    }
    createOne(qty);
});
</script>
