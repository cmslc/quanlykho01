<?php
require_once(__DIR__.'/../../../models/is_admin.php');
require_once(__DIR__.'/../../../libs/csrf.php');
require_once(__DIR__.'/../../../libs/database/packages.php');

$id = intval(input_get('id'));
$package = $ToryHub->get_row_safe("SELECT * FROM `packages` WHERE `id` = ?", [$id]);
if (!$package) { redirect(base_url('admin/packages-list')); }

$Packages = new Packages();
$linked_orders = $Packages->getOrdersByPackage($id);
$status_history = $ToryHub->get_list_safe(
    "SELECT h.*, u.username as changed_by_name FROM `package_status_history` h
     LEFT JOIN `users` u ON h.changed_by = u.id
     WHERE h.package_id = ? ORDER BY h.create_date DESC", [$id]
);

$page_title = __('Chi tiết kiện') . ' ' . $package['package_code'];

$pkgStatuses = ['cn_warehouse', 'shipping', 'vn_warehouse', 'delivered'];
$statusFlow = $pkgStatuses;

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><i class="ri-archive-line me-2"></i><?= $package['package_code'] ?></h4>
                    <a href="<?= base_url('admin/packages-list') ?>" class="btn btn-secondary btn-sm"><i class="ri-arrow-left-line"></i> <?= __('Quay lại') ?></a>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-8">
                <!-- Status Timeline -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <?php
                            $currentIdx = array_search($package['status'], $statusFlow);
                            foreach ($statusFlow as $i => $s):
                                $done = ($i <= $currentIdx);
                                $active = ($i === $currentIdx);
                                $color = $done ? 'success' : 'secondary';
                                if ($active) $color = 'primary';
                            ?>
                            <div class="text-center flex-fill">
                                <div class="avatar-xs mx-auto mb-1">
                                    <div class="avatar-title rounded-circle bg-<?= $color ?>">
                                        <?php if ($done && !$active): ?><i class="ri-check-line"></i>
                                        <?php elseif ($active): ?><i class="ri-loader-4-line"></i>
                                        <?php else: ?><i class="ri-time-line"></i><?php endif; ?>
                                    </div>
                                </div>
                                <small class="<?= $active ? 'fw-bold text-primary' : ($done ? 'text-success' : 'text-muted') ?>"><?= strip_tags(display_package_status($s)) ?></small>
                            </div>
                            <?php if ($i < count($statusFlow) - 1): ?>
                                <div class="flex-fill" style="height:2px;background:<?= $done ? '#0ab39c' : '#ddd' ?>;margin-top:-20px;"></div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Update Status -->
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-end">
                            <div class="col-md-4">
                                <label class="form-label"><?= __('Cập nhật trạng thái') ?></label>
                                <select class="form-select" id="new-status">
                                    <?php foreach ($pkgStatuses as $s): ?>
                                    <option value="<?= $s ?>" <?= $package['status'] === $s ? 'selected' : '' ?>><?= strip_tags(display_package_status($s)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label"><?= __('Ghi chú') ?></label>
                                <input type="text" class="form-control" id="status-note" placeholder="<?= __('Ghi chú (không bắt buộc)') ?>">
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-primary w-100" id="btn-update-status"><?= __('Cập nhật') ?></button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Package Info -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <h5 class="card-title mb-0"><?= __('Thông tin kiện hàng') ?></h5>
                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalEditPackage"><i class="ri-pencil-line"></i> <?= __('Sửa') ?></button>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="text-muted"><?= __('Tracking TQ') ?></label>
                                <p class="fw-bold mb-0"><?= htmlspecialchars($package['tracking_cn'] ?: '-') ?></p>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="text-muted"><?= __('Tracking QT') ?></label>
                                <p class="fw-bold mb-0"><?= htmlspecialchars($package['tracking_intl'] ?: '-') ?></p>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="text-muted"><?= __('Tracking VN') ?></label>
                                <p class="fw-bold mb-0"><?= htmlspecialchars($package['tracking_vn'] ?: '-') ?></p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="text-muted"><?= __('Cân nặng') ?></label>
                                <p class="fw-bold mb-0"><?= $package['weight_actual'] ?> kg</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="text-muted"><?= __('Cân quy đổi') ?></label>
                                <p class="fw-bold mb-0"><?= $package['weight_volume'] ?> kg</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="text-muted"><?= __('Cân tính phí') ?></label>
                                <p class="fw-bold text-danger mb-0"><?= $package['weight_charged'] ?> kg</p>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="text-muted"><?= __('Kích thước') ?></label>
                                <p class="fw-bold mb-0"><?= $package['length_cm'] ?>×<?= $package['width_cm'] ?>×<?= $package['height_cm'] ?> cm</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <label class="text-muted"><?= __('Ghi chú') ?></label>
                                <p class="mb-0"><?= htmlspecialchars($package['note'] ?: '-') ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Linked Orders -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <h5 class="card-title mb-0"><i class="ri-links-line me-1"></i><?= __('Đơn hàng liên kết') ?> (<?= count($linked_orders) ?>)</h5>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalLinkOrder"><i class="ri-add-line"></i> <?= __('Liên kết đơn') ?></button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($linked_orders)): ?>
                            <p class="text-center text-muted"><?= __('Chưa có đơn hàng liên kết') ?></p>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th><?= __('Mã đơn') ?></th>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th><?= __('Sản phẩm') ?></th>
                                        <th><?= __('Trạng thái') ?></th>
                                        <th><?= __('Hành động') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($linked_orders as $ord): ?>
                                    <tr>
                                        <td><a href="<?= base_url('admin/orders-detail&id=' . $ord['id']) ?>"><strong><?= $ord['order_code'] ?></strong></a></td>
                                        <td><?= htmlspecialchars($ord['customer_code'] ?? '') ?> <small class="text-muted"><?= htmlspecialchars($ord['customer_name'] ?? '') ?></small></td>
                                        <td><?= htmlspecialchars(mb_strimwidth($ord['product_name'] ?? '', 0, 30, '...')) ?></td>
                                        <td><?= display_order_status($ord['status']) ?></td>
                                        <td>
                                            <a href="<?= base_url('admin/orders-detail&id=' . $ord['id']) ?>" class="btn btn-sm btn-info"><i class="ri-eye-line"></i></a>
                                            <button class="btn btn-sm btn-outline-danger btn-unlink" data-order-id="<?= $ord['id'] ?>" data-code="<?= $ord['order_code'] ?>"><i class="ri-link-unlink"></i></button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Merge / Split -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="ri-git-merge-line me-1"></i><?= __('Gộp / Tách kiện') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalMerge">
                                <i class="ri-git-merge-line"></i> <?= __('Gộp kiện') ?>
                            </button>
                            <?php if (count($linked_orders) >= 2): ?>
                            <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalSplit">
                                <i class="ri-git-branch-line"></i> <?= __('Tách kiện') ?>
                            </button>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted mt-2 d-block"><?= __('Gộp: chọn kiện khác để gộp chung. Tách: phân đơn hàng vào kiện mới.') ?></small>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-lg-4">
                <!-- Status History -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Lịch sử trạng thái') ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($status_history)): ?>
                            <p class="text-muted"><?= __('Chưa có lịch sử') ?></p>
                        <?php else: ?>
                        <div class="acitivity-timeline">
                            <?php foreach ($status_history as $h): ?>
                            <div class="acitivity-item d-flex pb-3">
                                <div class="flex-shrink-0">
                                    <div class="avatar-xs"><div class="avatar-title rounded-circle bg-soft-primary text-primary"><i class="ri-refresh-line"></i></div></div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <p class="mb-1">
                                        <?php if ($h['old_status']): ?>
                                            <?= display_package_status($h['old_status']) ?> → <?= display_package_status($h['new_status']) ?>
                                        <?php else: ?>
                                            <?= display_package_status($h['new_status']) ?>
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($h['note']): ?><small class="text-muted"><?= htmlspecialchars($h['note']) ?></small><br><?php endif; ?>
                                    <small class="text-muted"><?= $h['changed_by_name'] ?? 'System' ?> - <?= $h['create_date'] ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

<!-- Modal Edit Package -->
<div class="modal fade" id="modalEditPackage" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><?= __('Sửa kiện hàng') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form id="form-edit-package">
                <input type="hidden" name="<?= $csrf->get_token_name() ?>" value="<?= $csrf->get_token_value() ?>">
                <input type="hidden" name="request_name" value="edit">
                <input type="hidden" name="id" value="<?= $package['id'] ?>">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label"><?= __('Tracking TQ') ?></label><input type="text" class="form-control" name="tracking_cn" value="<?= htmlspecialchars($package['tracking_cn'] ?? '') ?>"></div>
                    <div class="mb-3"><label class="form-label"><?= __('Tracking QT') ?></label><input type="text" class="form-control" name="tracking_intl" value="<?= htmlspecialchars($package['tracking_intl'] ?? '') ?>"></div>
                    <div class="mb-3"><label class="form-label"><?= __('Tracking VN') ?></label><input type="text" class="form-control" name="tracking_vn" value="<?= htmlspecialchars($package['tracking_vn'] ?? '') ?>"></div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label"><?= __('Cân nặng') ?> (kg)</label><input type="number" step="0.01" class="form-control" name="weight_actual" value="<?= $package['weight_actual'] ?>"></div>
                        <div class="col-md-6 mb-3">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-4 mb-3"><label class="form-label">L (cm)</label><input type="number" step="0.1" class="form-control" name="length_cm" value="<?= $package['length_cm'] ?>"></div>
                        <div class="col-4 mb-3"><label class="form-label">W (cm)</label><input type="number" step="0.1" class="form-control" name="width_cm" value="<?= $package['width_cm'] ?>"></div>
                        <div class="col-4 mb-3"><label class="form-label">H (cm)</label><input type="number" step="0.1" class="form-control" name="height_cm" value="<?= $package['height_cm'] ?>"></div>
                    </div>
                    <div class="mb-3"><label class="form-label"><?= __('Ghi chú') ?></label><textarea class="form-control" name="note" rows="2"><?= htmlspecialchars($package['note'] ?? '') ?></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Hủy') ?></button>
                    <button type="submit" class="btn btn-primary"><?= __('Lưu') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Link Order -->
<div class="modal fade" id="modalLinkOrder" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><?= __('Liên kết đơn hàng') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="text" class="form-control" id="link-search-input" placeholder="<?= __('Tìm mã đơn / mã khách hàng...') ?>">
                <div id="link-search-results" class="list-group mt-2"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Merge -->
<div class="modal fade" id="modalMerge" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><?= __('Gộp kiện') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form id="form-merge">
                <div class="modal-body">
                    <p class="text-muted"><?= __('Nhập mã kiện muốn gộp chung với kiện này:') ?></p>
                    <div class="mb-3">
                        <label class="form-label"><?= __('Mã kiện khác') ?></label>
                        <input type="text" class="form-control" id="merge-pkg-code" placeholder="PKG...">
                    </div>
                    <div id="merge-pkg-list">
                        <span class="badge bg-primary me-1"><?= $package['package_code'] ?></span>
                    </div>
                    <hr>
                    <div class="mb-3"><label class="form-label"><?= __('Tracking QT cho kiện mới') ?></label><input type="text" class="form-control" name="tracking_intl"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Hủy') ?></button>
                    <button type="submit" class="btn btn-primary"><?= __('Gộp kiện') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
var packageId = <?= $package['id'] ?>;
var csrfName = '<?= $csrf->get_token_name() ?>';
var csrfValue = '<?= $csrf->get_token_value() ?>';
var ajaxUrl = '<?= base_url('ajaxs/admin/packages.php') ?>';

// Update Status
$('#btn-update-status').on('click', function(){
    var $btn = $(this).prop('disabled', true);
    $.post(ajaxUrl, {
        request_name: 'update_status', package_id: packageId,
        new_status: $('#new-status').val(), note: $('#status-note').val(),
        [csrfName]: csrfValue
    }, function(res){
        if (res.status === 'success') {
            Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
        } else {
            Swal.fire({icon: 'error', text: res.msg}); $btn.prop('disabled', false);
        }
    }, 'json').fail(function(){ $btn.prop('disabled', false); });
});

// Edit Package
$('#form-edit-package').on('submit', function(e){
    e.preventDefault();
    $.post(ajaxUrl, $(this).serialize(), function(res){
        if (res.status === 'success') {
            Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
        } else { Swal.fire({icon: 'error', text: res.msg}); }
    }, 'json');
});

// Unlink Order
$('.btn-unlink').on('click', function(){
    var orderId = $(this).data('order-id');
    var code = $(this).data('code');
    Swal.fire({
        title: '<?= __('Gỡ liên kết') ?>?', text: code,
        icon: 'question', showCancelButton: true, confirmButtonText: '<?= __('Gỡ') ?>', cancelButtonText: '<?= __('Hủy') ?>'
    }).then(function(result){
        if (result.isConfirmed) {
            $.post(ajaxUrl, {
                request_name: 'unlink_order', package_id: packageId, order_id: orderId,
                [csrfName]: csrfValue
            }, function(res){
                if (res.status === 'success') location.reload();
                else Swal.fire({icon: 'error', text: res.msg});
            }, 'json');
        }
    });
});

// Link Order Search
var linkTimer;
$('#link-search-input').on('input', function(){
    clearTimeout(linkTimer);
    var kw = $(this).val().trim();
    if (kw.length < 2) { $('#link-search-results').html(''); return; }
    linkTimer = setTimeout(function(){
        $.post(ajaxUrl, { request_name: 'search_orders', keyword: kw, [csrfName]: csrfValue }, function(res){
            if (res.status === 'success') {
                var html = '';
                res.orders.forEach(function(o){
                    html += '<a href="#" class="list-group-item list-group-item-action link-order-item" data-id="' + o.id + '">'
                        + '<strong>' + o.order_code + '</strong> - ' + (o.customer_name || '') + '</a>';
                });
                $('#link-search-results').html(html || '<div class="list-group-item text-muted"><?= __('Không có kết quả') ?></div>');
            }
        }, 'json');
    }, 300);
});

$(document).on('click', '.link-order-item', function(e){
    e.preventDefault();
    var orderId = $(this).data('id');
    $.post(ajaxUrl, {
        request_name: 'link_order', package_id: packageId, order_id: orderId,
        [csrfName]: csrfValue
    }, function(res){
        if (res.status === 'success') location.reload();
        else Swal.fire({icon: 'error', text: res.msg});
    }, 'json');
});

// Merge
var mergeIds = [<?= $package['id'] ?>];
$('#merge-pkg-code').on('keypress', function(e){
    if (e.which === 13) {
        e.preventDefault();
        var code = $(this).val().trim();
        if (!code) return;
        // Find package by code
        $.post(ajaxUrl, { request_name: 'search_orders', keyword: code, [csrfName]: csrfValue }, function(){
            // For now, we'll search packages directly
        }, 'json');
        // Simple approach: add code to list, resolve ID server-side
        $('#merge-pkg-list').append('<span class="badge bg-info me-1">' + code + '</span>');
        $(this).val('');
    }
});

$('#form-merge').on('submit', function(e){
    e.preventDefault();
    // Collect all package codes from merge-pkg-list badges
    var codes = [];
    $('#merge-pkg-list .badge').each(function(){ codes.push($(this).text().trim()); });
    // For now, use IDs directly - need server-side lookup
    $.post(ajaxUrl, {
        request_name: 'merge',
        source_package_ids: JSON.stringify(mergeIds),
        tracking_intl: $(this).find('[name=tracking_intl]').val(),
        [csrfName]: csrfValue
    }, function(res){
        if (res.status === 'success') {
            Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){
                window.location.href = '<?= base_url('admin/packages-detail&id=') ?>' + res.package_id;
            });
        } else { Swal.fire({icon: 'error', text: res.msg}); }
    }, 'json');
});
</script>
