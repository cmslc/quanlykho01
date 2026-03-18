<?php
require_once(__DIR__.'/../../../models/is_staffcn.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$bagId = intval(input_get('id') ?: 0);
$bag = null;
$bagPackages = [];

if ($bagId) {
    $bag = $ToryHub->get_row_safe("SELECT * FROM `bags` WHERE `id` = ?", [$bagId]);
    if (!$bag) {
        redirect(base_url('staffcn/bags-list'));
    }
    // Get packages in this bag
    $bagPackages = $ToryHub->get_list_safe(
        "SELECT p.*, bp.scanned_at,
                o.product_name as order_product, o.product_code as order_code,
                o.product_type, c.fullname as customer_name
         FROM `bag_packages` bp
         JOIN `packages` p ON bp.package_id = p.id
         LEFT JOIN `package_orders` po ON p.id = po.package_id
         LEFT JOIN `orders` o ON po.order_id = o.id
         LEFT JOIN `customers` c ON o.customer_id = c.id
         WHERE bp.bag_id = ?
         ORDER BY bp.scanned_at DESC", [$bagId]
    );
}

$page_title = $bag ? __('Đóng bao') . ' - ' . $bag['bag_code'] : __('Tạo bao hàng mới');

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= $page_title ?></h4>
                    <a href="<?= base_url('staffcn/bags-list') ?>" class="btn btn-secondary">
                        <i class="ri-arrow-left-line"></i> <?= __('Danh sách bao') ?>
                    </a>
                </div>
            </div>
        </div>

        <?php if (!$bag): ?>
        <!-- Create new bag -->
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Tạo bao hàng mới') ?></h5>
                    </div>
                    <div class="card-body">
                        <form id="form-create-bag">
                            <div class="mb-3">
                                <label class="form-label"><?= __('Mã bao') ?></label>
                                <input type="text" class="form-control" name="bag_code" placeholder="<?= __('Để trống để tạo tự động') ?>" style="text-transform:uppercase">
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= __('Ghi chú') ?></label>
                                <textarea class="form-control" name="note" rows="2"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100"><i class="ri-archive-drawer-line me-1"></i><?= __('Tạo bao & bắt đầu quét') ?></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Packing interface -->
        <div class="row">
            <!-- Left column: Bag info + Images -->
            <div class="col-lg-4">
                <!-- Bag info -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Thông tin bao') ?></h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tr>
                                <td class="text-muted"><?= __('Mã bao') ?></td>
                                <td><strong><?= htmlspecialchars($bag['bag_code']) ?></strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted"><?= __('Trạng thái') ?></td>
                                <td id="bag-status">
                                    <?php
                                    $bagStatusLabels = [
                                        'open' => ['label' => 'Đang mở', 'bg' => 'info', 'icon' => 'ri-lock-unlock-line'],
                                        'sealed' => ['label' => 'Đã đóng', 'bg' => 'dark', 'icon' => 'ri-lock-line'],
                                        'shipping' => ['label' => 'Đang vận chuyển', 'bg' => 'primary', 'icon' => 'ri-ship-line'],
                                        'arrived' => ['label' => 'Đã đến kho Việt Nam', 'bg' => 'success', 'icon' => 'ri-check-double-line'],
                                    ];
                                    $sl = $bagStatusLabels[$bag['status']] ?? $bagStatusLabels['open'];
                                    ?>
                                    <span class="badge bg-<?= $sl['bg'] ?>"><i class="<?= $sl['icon'] ?> me-1"></i><?= __($sl['label']) ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted"><?= __('Số kiện') ?></td>
                                <td><strong id="bag-count"><?= $bag['total_packages'] ?></strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted"><?= __('Tổng cân') ?></td>
                                <td><strong id="bag-weight"><?= floatval($bag['total_weight']) ?></strong> kg</td>
                            </tr>
                            <tr>
                                <td class="text-muted"><?= __('Số khối') ?></td>
                                <td><strong id="bag-volume"><?= floatval($bag['weight_volume']) ?></strong> m&sup3;</td>
                            </tr>
                            <tr>
                                <td class="text-muted"><?= __('Ngày tạo') ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($bag['create_date'])) ?></td>
                            </tr>
                        </table>
                        <?php if ($bag['status'] === 'open'): ?>
                        <div class="mt-3">
                            <label class="form-label fw-bold"><?= __('Cân nặng (kg)') ?></label>
                            <input type="number" step="0.01" min="0" class="form-control" id="input-bag-weight" value="<?= floatval($bag['total_weight']) ?>" placeholder="0">
                        </div>
                        <div class="mt-2">
                            <label class="form-label fw-bold"><?= __('Kích thước (cm)') ?></label>
                            <div class="row g-2">
                                <div class="col-4">
                                    <input type="number" step="0.1" min="0" class="form-control" id="input-length" value="<?= floatval($bag['length_cm']) ?>" placeholder="Dài">
                                </div>
                                <div class="col-4">
                                    <input type="number" step="0.1" min="0" class="form-control" id="input-width" value="<?= floatval($bag['width_cm']) ?>" placeholder="Rộng">
                                </div>
                                <div class="col-4">
                                    <input type="number" step="0.1" min="0" class="form-control" id="input-height" value="<?= floatval($bag['height_cm']) ?>" placeholder="Cao">
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="button" class="btn btn-success w-100" id="btn-save-weight"><i class="ri-save-line me-1"></i><?= __('Lưu cân & kích thước') ?></button>
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-dark w-100" id="btn-seal"><i class="ri-lock-line me-1"></i><?= __('Đóng bao') ?></button>
                        </div>
                        <?php elseif ($bag['status'] === 'sealed'): ?>
                        <div class="mt-3">
                            <button type="button" class="btn btn-warning w-100" id="btn-unseal"><i class="ri-lock-unlock-line me-1"></i><?= __('Mở bao') ?></button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Images -->
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0"><i class="ri-image-line me-1"></i><?= __('Ảnh bao hàng') ?></h5>
                        <?php if ($bag['status'] === 'open'): ?>
                        <label class="btn btn-sm btn-primary mb-0" for="bag-images-input">
                            <i class="ri-upload-2-line me-1"></i><?= __('Tải ảnh lên') ?>
                        </label>
                        <input type="file" id="bag-images-input" accept="image/*" multiple class="d-none">
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div id="bag-images-container" class="d-flex flex-wrap gap-2">
                            <?php if (!empty($bag['images'])):
                                $bagImages = array_filter(array_map('trim', explode(',', $bag['images'])));
                                foreach ($bagImages as $img): ?>
                            <div class="position-relative d-inline-block bag-img-wrap" data-path="<?= htmlspecialchars($img) ?>">
                                <img src="<?= get_upload_url($img) ?>" class="img-thumbnail bag-img-preview" style="max-height:120px;cursor:pointer;">
                                <?php if ($bag['status'] === 'open'): ?>
                                <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 btn-delete-bag-img" style="padding:0 4px;line-height:1.4;font-size:11px;">&times;</button>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; endif; ?>
                        </div>
                        <?php if (empty($bag['images'])): ?>
                        <p class="text-muted mb-0" id="no-images-text"><?= __('Chưa có ảnh nào') ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right column: Scanner + Packages -->
            <div class="col-lg-8">
                <?php if ($bag['status'] === 'open'): ?>
                <!-- Scanner -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="ri-barcode-line"></i> <?= __('Quét mã vận đơn') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="input-group input-group-lg">
                            <input type="text" class="form-control" id="scan-input" placeholder="<?= __('Quét hoặc nhập mã vận đơn rồi nhấn Enter') ?>" autofocus>
                            <button type="button" class="btn btn-primary" id="btn-scan"><i class="ri-send-plane-line"></i></button>
                        </div>
                        <div id="scan-message" class="mt-2"></div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Packages in bag -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Kiện hàng trong bao') ?> (<span id="pkg-count-header"><?= count($bagPackages) ?></span>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?= __('Mã vận đơn') ?></th>
                                        <th><?= __('Sản phẩm') ?></th>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th><?= __('Cân (kg)') ?></th>
                                        <th><?= __('Quét lúc') ?></th>
                                        <?php if ($bag['status'] === 'open'): ?>
                                        <th></th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody id="packages-tbody">
                                    <?php if (empty($bagPackages)): ?>
                                    <tr id="empty-row"><td colspan="<?= $bag['status'] === 'open' ? 7 : 6 ?>" class="text-center text-muted py-4"><?= __('Chưa có kiện nào. Hãy quét mã vận đơn để thêm.') ?></td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($bagPackages as $i => $pkg): ?>
                                    <tr data-package-id="<?= $pkg['id'] ?>">
                                        <td><?= $i + 1 ?></td>
                                        <td><strong><?= htmlspecialchars($pkg['tracking_cn']) ?></strong></td>
                                        <td><?= htmlspecialchars(mb_strimwidth($pkg['order_product'] ?? '', 0, 25, '...')) ?></td>
                                        <td><?= htmlspecialchars($pkg['customer_name'] ?? '-') ?></td>
                                        <td><?= floatval($pkg['weight_charged']) ?></td>
                                        <td><?= date('H:i:s', strtotime($pkg['scanned_at'])) ?></td>
                                        <?php if ($bag['status'] === 'open'): ?>
                                        <td><button type="button" class="btn btn-sm btn-outline-danger btn-unscan" data-id="<?= $pkg['id'] ?>"><i class="ri-close-line"></i></button></td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
$(function(){
    var csrfToken = '<?= (new Csrf())->get_token_value() ?>';
    var ajaxUrl = '<?= base_url('ajaxs/staffcn/bags.php') ?>';
    <?php if ($bag): ?>
    var bagId = <?= $bag['id'] ?>;
    var rowIndex = <?= count($bagPackages) ?>;
    <?php endif; ?>

    <?php if (!$bag): ?>
    // === CREATE BAG ===
    $('#form-create-bag').on('submit', function(e){
        e.preventDefault();
        var formData = $(this).serializeArray();
        formData.push({name: 'request_name', value: 'create'});
        formData.push({name: 'csrf_token', value: csrfToken});
        $.post(ajaxUrl, $.param(formData), function(res){
            if(res.status == 'success'){
                window.location.href = '<?= base_url('staffcn/bags-packing') ?>?id=' + res.bag_id;
            } else {
                Swal.fire({icon: 'error', title: 'Error', text: res.msg});
            }
        }, 'json');
    });
    <?php endif; ?>

    <?php if ($bag && $bag['status'] === 'open'): ?>
    // === SCAN ===
    var scanBusy = false;
    function doScan() {
        var $input = $('#scan-input');
        var tracking = $.trim($input.val());
        if (!tracking || scanBusy) return;
        scanBusy = true;
        $input.prop('disabled', true);
        $('#scan-message').html('<div class="text-muted"><i class="ri-loader-4-line ri-spin me-1"></i><?= __('Đang xử lý...') ?></div>');

        $.post(ajaxUrl, {
            request_name: 'scan',
            bag_id: bagId,
            tracking_cn: tracking,
            csrf_token: csrfToken
        }, function(res){
            if (res.status == 'success') {
                $('#scan-message').html('<div class="alert alert-success py-1 mb-0"><i class="ri-check-line me-1"></i>' + res.msg + ' - <strong>' + res.package.tracking_cn + '</strong></div>');
                // Remove empty row
                $('#empty-row').remove();
                // Add row to top of table
                rowIndex++;
                var p = res.package;
                var now = new Date().toLocaleTimeString('vi-VN', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
                var row = '<tr data-package-id="' + p.id + '" class="table-success">'
                    + '<td>' + rowIndex + '</td>'
                    + '<td><strong>' + $('<span>').text(p.tracking_cn).html() + '</strong></td>'
                    + '<td>' + $('<span>').text(p.order_product || '').html() + '</td>'
                    + '<td>' + $('<span>').text(p.customer_name || '-').html() + '</td>'
                    + '<td>' + parseFloat(p.weight_charged) + '</td>'
                    + '<td>' + now + '</td>'
                    + '<td><button type="button" class="btn btn-sm btn-outline-danger btn-unscan" data-id="' + p.id + '"><i class="ri-close-line"></i></button></td>'
                    + '</tr>';
                $('#packages-tbody').prepend(row);
                // Flash green then remove highlight
                setTimeout(function(){ $('tr[data-package-id="' + p.id + '"]').removeClass('table-success'); }, 1500);
                // Update totals
                $('#bag-count').text(res.bag_total_packages);
                $('#bag-weight').text(parseFloat(res.bag_total_weight));
                $('#pkg-count-header').text(res.bag_total_packages);
                $input.val('').prop('disabled', false).focus();
            } else {
                $('#scan-message').html('<div class="alert alert-danger py-1 mb-0"><i class="ri-error-warning-line me-1"></i>' + res.msg + '</div>');
                $input.prop('disabled', false).select();
            }
        }, 'json').fail(function(){
            $('#scan-message').html('<div class="alert alert-danger py-1 mb-0"><?= __('Lỗi kết nối') ?></div>');
            $input.prop('disabled', false).select();
        }).always(function(){ scanBusy = false; });
    }

    $('#scan-input').on('keydown', function(e){
        if (e.key === 'Enter') { e.preventDefault(); doScan(); }
    });
    $('#btn-scan').on('click', doScan);

    // === UNSCAN ===
    $(document).on('click', '.btn-unscan', function(){
        var btn = $(this);
        var pkgId = btn.data('id');
        $.post(ajaxUrl, {
            request_name: 'unscan',
            bag_id: bagId,
            package_id: pkgId,
            csrf_token: csrfToken
        }, function(res){
            if (res.status == 'success') {
                btn.closest('tr').fadeOut(300, function(){ $(this).remove(); });
                $('#bag-count').text(res.bag_total_packages);
                $('#bag-weight').text(parseFloat(res.bag_total_weight));
                $('#pkg-count-header').text(res.bag_total_packages);
            } else {
                Swal.fire({icon: 'error', text: res.msg});
            }
        }, 'json');
    });

    // === AUTO CALC VOLUME ===
    function calcVolume() {
        var l = parseFloat($('#input-length').val()) || 0;
        var w = parseFloat($('#input-width').val()) || 0;
        var h = parseFloat($('#input-height').val()) || 0;
        var vol = (l * w * h) / 1000000;
        $('#volume-display').text(parseFloat(vol.toFixed(4)));
    }
    $('#input-length, #input-width, #input-height').on('input', calcVolume);

    // === SAVE WEIGHT & DIMENSIONS ===
    $('#btn-save-weight').on('click', function(){
        var weight = parseFloat($('#input-bag-weight').val()) || 0;
        var length_cm = parseFloat($('#input-length').val()) || 0;
        var width_cm = parseFloat($('#input-width').val()) || 0;
        var height_cm = parseFloat($('#input-height').val()) || 0;
        var $btn = $(this).prop('disabled', true);
        $.post(ajaxUrl, {
            request_name: 'update_bag_weight',
            bag_id: bagId,
            weight: weight,
            length_cm: length_cm,
            width_cm: width_cm,
            height_cm: height_cm,
            csrf_token: csrfToken
        }, function(res){
            if (res.status == 'success') {
                $('#bag-weight').text(parseFloat(res.bag_total_weight));
                Swal.fire({icon: 'success', title: res.msg, timer: 1000, showConfirmButton: false});
            } else {
                Swal.fire({icon: 'error', text: res.msg});
            }
            $btn.prop('disabled', false);
        }, 'json').fail(function(){ $btn.prop('disabled', false); });
    });

    // === SEAL ===
    $('#btn-seal').on('click', function(){
        var count = parseInt($('#bag-count').text());
        if (count < 1) {
            Swal.fire({icon: 'warning', title: '<?= __('Bao hàng chưa có kiện nào') ?>'});
            return;
        }
        Swal.fire({
            title: '<?= __('Đóng bao?') ?>',
            html: '<?= __('Sau khi đóng sẽ không thể thêm/gỡ kiện.') ?><br><?= __('Tổng') ?>: <strong>' + count + '</strong> <?= __('kiện') ?>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<?= __('Đóng bao') ?>',
            cancelButtonText: '<?= __('Hủy') ?>'
        }).then(function(result){
            if (result.isConfirmed) {
                $.post(ajaxUrl, { request_name: 'seal', bag_id: bagId, csrf_token: csrfToken }, function(res){
                    if (res.status == 'success') {
                        Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
                    } else {
                        Swal.fire({icon: 'error', text: res.msg});
                    }
                }, 'json');
            }
        });
    });
    <?php endif; ?>

    <?php if ($bag && $bag['status'] === 'sealed'): ?>
    // === UNSEAL ===
    $('#btn-unseal').on('click', function(){
        Swal.fire({
            title: '<?= __('Mở lại bao?') ?>',
            html: '<?= __('Bao sẽ được mở lại để thêm/gỡ kiện') ?>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<?= __('Mở bao') ?>',
            cancelButtonText: '<?= __('Hủy') ?>'
        }).then(function(result){
            if (result.isConfirmed) {
                $.post(ajaxUrl, { request_name: 'unseal', bag_id: bagId, csrf_token: csrfToken }, function(res){
                    if (res.status == 'success') {
                        Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
                    } else {
                        Swal.fire({icon: 'error', text: res.msg});
                    }
                }, 'json');
            }
        });
    });

    <?php endif; ?>

    <?php if ($bag): ?>
    // === UPLOAD IMAGES ===
    $('#bag-images-input').on('change', function(){
        var files = this.files;
        if (!files.length) return;
        var formData = new FormData();
        formData.append('request_name', 'upload_images');
        formData.append('bag_id', bagId);
        formData.append('csrf_token', csrfToken);
        for (var i = 0; i < files.length; i++) {
            formData.append('images[]', files[i]);
        }
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res){
                if (res.status == 'success') {
                    $('#no-images-text').remove();
                    var $container = $('#bag-images-container').empty();
                    res.images.forEach(function(img){
                        var isOpen = <?= $bag['status'] === 'open' ? 'true' : 'false' ?>;
                        var delBtn = isOpen ? '<button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 btn-delete-bag-img" style="padding:0 4px;line-height:1.4;font-size:11px;">&times;</button>' : '';
                        $container.append(
                            '<div class="position-relative d-inline-block bag-img-wrap" data-path="' + img.path + '">'
                            + '<img src="' + img.url + '" class="img-thumbnail bag-img-preview" style="max-height:120px;cursor:pointer;">'
                            + delBtn
                            + '</div>'
                        );
                    });
                    Swal.fire({icon: 'success', title: res.msg, timer: 1000, showConfirmButton: false});
                } else {
                    Swal.fire({icon: 'error', text: res.msg});
                }
            }
        });
        $(this).val('');
    });

    // === DELETE IMAGE ===
    $(document).on('click', '.btn-delete-bag-img', function(e){
        e.stopPropagation();
        var $wrap = $(this).closest('.bag-img-wrap');
        var path = $wrap.data('path');
        Swal.fire({
            title: '<?= __('Xóa ảnh này?') ?>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<?= __('Xóa') ?>',
            cancelButtonText: '<?= __('Hủy') ?>'
        }).then(function(result){
            if (result.isConfirmed) {
                $.post(ajaxUrl, {
                    request_name: 'delete_image',
                    bag_id: bagId,
                    image_path: path,
                    csrf_token: csrfToken
                }, function(res){
                    if (res.status == 'success') {
                        $wrap.fadeOut(300, function(){ $(this).remove(); });
                    } else {
                        Swal.fire({icon: 'error', text: res.msg});
                    }
                }, 'json');
            }
        });
    });

    // === PREVIEW IMAGE (lightbox) ===
    $(document).on('click', '.bag-img-preview', function(){
        var src = $(this).attr('src');
        Swal.fire({
            imageUrl: src,
            imageAlt: '<?= __('Ảnh bao hàng') ?>',
            showConfirmButton: false,
            showCloseButton: true,
            width: 'auto',
            padding: '1rem'
        });
    });
    <?php endif; ?>
});
</script>
