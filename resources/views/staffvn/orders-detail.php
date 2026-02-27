<?php
require_once(__DIR__.'/../../../models/is_staffvn.php');
require_once(__DIR__.'/../../../libs/csrf.php');
require_once(__DIR__.'/../../../libs/database/shipments.php');

$id = intval(input_get('id'));
$shipment = $ToryHub->get_row_safe("SELECT s.*, u.fullname as creator_name, uc.fullname as checker_name FROM `shipments` s LEFT JOIN `users` u ON s.created_by = u.id LEFT JOIN `users` uc ON s.checked_by = uc.id WHERE s.id = ?", [$id]);
if (!$shipment) {
    echo '<script>alert("' . __('Chuyến xe không tồn tại') . '");window.location.href="' . base_url('staffvn/orders-list') . '";</script>';
    exit;
}

$page_title = __('Chi tiết chuyến xe') . ' - ' . $shipment['shipment_code'];

$ShipmentsDB = new Shipments();
$packages = $ShipmentsDB->getPackages($id);

// Get check statuses
$checkStatuses = [];
$spRows = $ToryHub->get_list_safe("SELECT package_id, check_status FROM `shipment_packages` WHERE `shipment_id` = ?", [$id]);
foreach ($spRows as $sp) { $checkStatuses[$sp['package_id']] = $sp['check_status']; }

$isChecked = !empty($shipment['checked_date']);

$statusLabels = [
    'preparing'  => ['label' => 'Đang chuẩn bị', 'bg' => 'info-subtle', 'text' => 'info', 'icon' => 'ri-loader-4-line'],
    'in_transit' => ['label' => 'Đang vận chuyển', 'bg' => 'primary-subtle', 'text' => 'primary', 'icon' => 'ri-truck-line'],
    'arrived'    => ['label' => 'Đã đến', 'bg' => 'success-subtle', 'text' => 'success', 'icon' => 'ri-map-pin-2-line'],
    'completed'  => ['label' => 'Hoàn thành', 'bg' => 'secondary-subtle', 'text' => 'secondary', 'icon' => 'ri-check-double-line'],
];
$cfg = $statusLabels[$shipment['status']] ?? $statusLabels['in_transit'];

// Group packages by mã hàng
$grouped = [];
foreach ($packages as $pkg) {
    $key = $pkg['bag_code'] ?: ($pkg['product_code'] ?: ($pkg['order_code'] ?: __('Không xác định')));
    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'pkgs' => [],
            'customer' => $pkg['customer_name'] ?: '',
            'customer_code' => $pkg['customer_code'] ?: '',
            'is_bag' => !empty($pkg['bag_code']),
            'bag_weight' => $pkg['bag_weight'] ?? 0,
            'bag_cbm' => (($pkg['bag_length'] ?? 0) * ($pkg['bag_width'] ?? 0) * ($pkg['bag_height'] ?? 0)) / 1000000,
        ];
    }
    $grouped[$key]['pkgs'][] = $pkg;
}

// Build package_code → group index map for JS
$pkgCodeMap = [];
$bagCodeMap = [];
$gIdx2 = 0;
foreach ($grouped as $maHang => $group) {
    $gIdx2++;
    foreach ($group['pkgs'] as $pkg) {
        $pkgCodeMap[$pkg['package_code']] = $gIdx2;
        if (!empty($pkg['tracking_code'])) $pkgCodeMap[$pkg['tracking_code']] = $gIdx2;
    }
    if ($group['is_bag']) {
        $bagCodeMap[$maHang] = $gIdx2;
    }
}

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Kiểm đếm chuyến xe') ?>: <?= htmlspecialchars($shipment['shipment_code']) ?></h4>
                    <a href="<?= base_url('staffvn/orders-list') ?>" class="btn btn-secondary btn-sm"><i class="ri-arrow-left-line me-1"></i><?= __('Quay lại') ?></a>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left: Info + Scan -->
            <div class="col-lg-4">
                <!-- Shipment Info -->
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0"><?= __('Thông tin chuyến') ?></h5>
                        <span class="badge bg-<?= $cfg['bg'] ?> text-<?= $cfg['text'] ?> fs-12 px-2 py-1"><i class="<?= $cfg['icon'] ?> me-1"></i><?= __($cfg['label']) ?></span>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless table-sm mb-0">
                            <tr><td class="text-muted" style="width:40%"><?= __('Biển số xe') ?></td><td><?= htmlspecialchars($shipment['truck_plate'] ?: '-') ?></td></tr>
                            <tr><td class="text-muted"><?= __('Tài xế') ?></td><td><?= htmlspecialchars($shipment['driver_name'] ?: '-') ?><?= $shipment['driver_phone'] ? ' · <a href="tel:' . htmlspecialchars($shipment['driver_phone']) . '">' . htmlspecialchars($shipment['driver_phone']) . '</a>' : '' ?></td></tr>
                            <tr><td class="text-muted"><?= __('Tuyến đường') ?></td><td><?= htmlspecialchars($shipment['route'] ?: '-') ?></td></tr>
                            <?php if ($shipment['departed_date']): ?>
                            <tr><td class="text-muted"><?= __('Xuất phát') ?></td><td><?= date('d/m/Y H:i', strtotime($shipment['departed_date'])) ?></td></tr>
                            <?php endif; ?>
                            <?php if ($shipment['arrived_date']): ?>
                            <tr><td class="text-muted"><?= __('Đến nơi') ?></td><td><?= date('d/m/Y H:i', strtotime($shipment['arrived_date'])) ?></td></tr>
                            <?php endif; ?>
                        </table>
                        <div class="mt-3 p-3 bg-light rounded">
                            <div class="row text-center">
                                <div class="col-4">
                                    <h5 class="mb-1"><?= count($grouped) ?></h5>
                                    <small class="text-muted"><?= __('Mã hàng') ?></small>
                                </div>
                                <div class="col-4">
                                    <h5 class="mb-1 text-primary"><?= $shipment['total_packages'] ?></h5>
                                    <small class="text-muted"><?= __('Kiện') ?></small>
                                </div>
                                <div class="col-4">
                                    <h5 class="mb-1 text-info"><?= fnum($shipment['total_weight'], 1) ?></h5>
                                    <small class="text-muted">kg</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Scan Input -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="ri-qr-scan-2-line me-1"></i><?= __('Quét mã kiểm đếm') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" id="scan-input" placeholder="<?= __('Quét mã kiện / mã bao...') ?>" autofocus <?= $isChecked ? 'disabled' : '' ?>>
                            <button class="btn btn-primary" id="btn-scan" <?= $isChecked ? 'disabled' : '' ?>><i class="ri-qr-scan-line"></i></button>
                        </div>
                        <!-- Scan log -->
                        <div id="scan-log" style="max-height:250px; overflow-y:auto;"></div>
                    </div>
                </div>

                <!-- Extra Items -->
                <div class="card d-none" id="card-extras">
                    <div class="card-header bg-warning">
                        <h5 class="card-title mb-0 text-dark"><i class="ri-error-warning-line me-1"></i><?= __('Hàng thừa') ?> (<span id="extra-count">0</span>)</h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush" id="extra-list"></ul>
                    </div>
                </div>

                <!-- Actions -->
                <div class="card">
                    <div class="card-body">
                        <?php if ($isChecked): ?>
                        <div class="alert alert-<?= $shipment['check_missing'] > 0 ? 'warning' : 'success' ?> mb-3">
                            <strong><i class="ri-checkbox-circle-line me-1"></i><?= __('Đã kiểm đếm') ?></strong>
                            <br><?= $shipment['checker_name'] ?> · <?= date('d/m/Y H:i', strtotime($shipment['checked_date'])) ?>
                            <div class="mt-2">
                                <span class="badge bg-success"><?= __('Khớp') ?>: <?= $shipment['check_matched'] ?></span>
                                <?php if ($shipment['check_missing'] > 0): ?>
                                <span class="badge bg-danger"><?= __('Thiếu') ?>: <?= $shipment['check_missing'] ?></span>
                                <?php endif; ?>
                                <?php if ($shipment['check_extra'] > 0): ?>
                                <span class="badge bg-warning"><?= __('Thừa') ?>: <?= $shipment['check_extra'] ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($shipment['check_notes']): ?>
                            <div class="mt-2 text-muted"><small><?= nl2br(htmlspecialchars($shipment['check_notes'])) ?></small></div>
                            <?php endif; ?>
                        </div>
                        <button class="btn btn-outline-secondary btn-sm w-100" id="btn-reset"><i class="ri-restart-line me-1"></i><?= __('Kiểm đếm lại') ?></button>
                        <?php else: ?>
                        <div class="mb-3">
                            <label class="form-label"><?= __('Ghi chú') ?></label>
                            <textarea class="form-control" id="check-note" rows="2" placeholder="<?= __('Ghi chú kiểm đếm (nếu có)...') ?>"></textarea>
                        </div>
                        <button class="btn btn-success w-100" id="btn-complete"><i class="ri-check-double-line me-1"></i><?= __('Hoàn thành kiểm đếm') ?></button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right: Checklist -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0"><i class="ri-list-check-2 me-1"></i><?= __('Danh sách kiểm đếm') ?></h5>
                        <div>
                            <span class="badge bg-success-subtle text-success fs-12 me-1" id="stat-matched">0 <?= __('khớp') ?></span>
                            <span class="badge bg-danger-subtle text-danger fs-12 me-1 d-none" id="stat-missing">0 <?= __('thiếu') ?></span>
                            <span class="badge bg-primary-subtle text-primary fs-12" id="check-count">0 / <?= count($grouped) ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:40px"><input type="checkbox" class="form-check-input" id="check-all" title="<?= __('Chọn tất cả') ?>" <?= $isChecked ? 'disabled' : '' ?>></th>
                                        <th>#</th>
                                        <th><?= __('Mã hàng') ?></th>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th><?= __('Tổng cân') ?></th>
                                        <th><?= __('Tổng khối') ?></th>
                                        <th><?= __('Trạng thái') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($grouped)): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4"><?= __('Chưa có kiện hàng nào') ?></td></tr>
                                    <?php endif; ?>
                                    <?php $gIdx = 0; foreach ($grouped as $maHang => $group): $gIdx++;
                                        $pkgList = $group['pkgs'];
                                        if ($group['is_bag']) {
                                            $totalW = floatval($group['bag_weight']);
                                            $totalCbm = floatval($group['bag_cbm']);
                                        } else {
                                            $totalW = array_sum(array_column($pkgList, 'weight_charged'));
                                            $totalCbm = 0;
                                            foreach ($pkgList as $p) { $totalCbm += ($p['length_cm'] * $p['width_cm'] * $p['height_cm']) / 1000000; }
                                        }
                                        $groupId = 'grp-' . $gIdx;
                                        // Check if all packages in group are matched
                                        $allMatched = true;
                                        $anyMissing = false;
                                        foreach ($pkgList as $p) {
                                            $cs = $checkStatuses[$p['id']] ?? 'unchecked';
                                            if ($cs !== 'matched') $allMatched = false;
                                            if ($cs === 'missing') $anyMissing = true;
                                        }
                                    ?>
                                    <tr class="check-row <?= $allMatched ? 'table-success' : ($anyMissing ? 'table-danger' : '') ?>" id="row-<?= $groupId ?>" data-group="<?= $groupId ?>" data-pkg-ids="<?= implode(',', array_column($pkgList, 'id')) ?>">
                                        <td><input type="checkbox" class="form-check-input check-item" data-group="<?= $groupId ?>" <?= $allMatched ? 'checked' : '' ?> <?= $isChecked ? 'disabled' : '' ?>></td>
                                        <td><?= $gIdx ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($maHang) ?></strong>
                                            <div class="mt-1">
                                                <a href="#" class="btn-expand text-muted text-decoration-none" data-group="<?= $groupId ?>">
                                                    <i class="ri-archive-line"></i> <?= count($pkgList) ?> <?= __('kiện') ?> <i class="ri-arrow-down-s-line expand-icon-<?= $groupId ?> fs-14"></i>
                                                </a>
                                            </div>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($group['customer_code']) ?>
                                            <?php if ($group['customer']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($group['customer']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $totalW > 0 ? fnum($totalW, 2) . ' kg' : '' ?></td>
                                        <td><?= $totalCbm > 0 ? fnum($totalCbm, 2) . ' m³' : '' ?></td>
                                        <td><?= display_package_status($pkgList[0]['status']) ?></td>
                                    </tr>
                                    <tr class="d-none" id="expand-<?= $groupId ?>">
                                        <td colspan="7" class="p-0">
                                            <div class="px-4 py-2 bg-light">
                                                <table class="table table-sm table-borderless mb-0 text-muted">
                                                    <thead><tr>
                                                        <th><?= __('Mã kiện') ?></th>
                                                        <th><?= __('Cân nặng') ?></th>
                                                        <th><?= __('Kích thước') ?></th>
                                                        <th><?= __('Số khối') ?></th>
                                                        <th><?= __('Kiểm đếm') ?></th>
                                                    </tr></thead>
                                                    <tbody>
                                                    <?php foreach ($pkgList as $pkg):
                                                        $cbm = ($pkg['length_cm'] * $pkg['width_cm'] * $pkg['height_cm']) / 1000000;
                                                        $cs = $checkStatuses[$pkg['id']] ?? 'unchecked';
                                                    ?>
                                                    <tr class="pkg-row-<?= $pkg['id'] ?> <?= $cs === 'matched' ? 'text-success' : ($cs === 'missing' ? 'text-danger' : '') ?>">
                                                        <td><strong><?= htmlspecialchars($pkg['package_code']) ?></strong></td>
                                                        <td><?= $pkg['weight_charged'] > 0 ? fnum($pkg['weight_charged'], 2) . ' kg' : '' ?></td>
                                                        <td><?= ($pkg['length_cm'] > 0) ? floatval($pkg['length_cm']) . '×' . floatval($pkg['width_cm']) . '×' . floatval($pkg['height_cm']) : '' ?></td>
                                                        <td><?= $cbm > 0 ? fnum($cbm, 2) . ' m³' : '' ?></td>
                                                        <td class="pkg-check-status-<?= $pkg['id'] ?>">
                                                            <?php if ($cs === 'matched'): ?>
                                                            <span class="text-success"><i class="ri-check-line"></i> <?= __('Khớp') ?></span>
                                                            <?php elseif ($cs === 'missing'): ?>
                                                            <span class="text-danger"><i class="ri-close-line"></i> <?= __('Thiếu') ?></span>
                                                            <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                    </tbody>
                                                </table>
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

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
$(function(){
    var csrfToken = '<?= (new Csrf())->get_token_value() ?>';
    var ajaxUrl = '<?= base_url('ajaxs/staffvn/shipment-check.php') ?>';
    var shipmentId = <?= $id ?>;
    var totalGroups = <?= count($grouped) ?>;
    var isChecked = <?= $isChecked ? 'true' : 'false' ?>;

    // Map: package_code/tracking_code → group index
    var pkgCodeMap = <?= json_encode($pkgCodeMap) ?>;
    var bagCodeMap = <?= json_encode($bagCodeMap) ?>;
    var extraCount = 0;

    function updateStats() {
        var checked = $('.check-item:checked').length;
        $('#check-count').text(checked + ' / ' + totalGroups);
        $('#stat-matched').text(checked + ' <?= __('khớp') ?>');
        if (checked === totalGroups && totalGroups > 0) {
            $('#check-count').removeClass('bg-primary-subtle text-primary').addClass('bg-success-subtle text-success');
        } else {
            $('#check-count').removeClass('bg-success-subtle text-success').addClass('bg-primary-subtle text-primary');
        }
    }

    function addScanLog(msg, type) {
        var colors = { matched: 'success', extra: 'warning', not_found: 'danger', duplicate: 'secondary', error: 'danger' };
        var icons = { matched: 'ri-check-line', extra: 'ri-error-warning-line', not_found: 'ri-close-circle-line', duplicate: 'ri-repeat-line', error: 'ri-close-line' };
        var color = colors[type] || 'secondary';
        var icon = icons[type] || 'ri-information-line';
        var html = '<div class="d-flex align-items-start mb-2"><span class="badge bg-' + color + '-subtle text-' + color + ' me-2 mt-1"><i class="' + icon + '"></i></span><small>' + msg + '</small></div>';
        $('#scan-log').prepend(html);
    }

    function addExtra(barcode) {
        extraCount++;
        $('#extra-count').text(extraCount);
        $('#card-extras').removeClass('d-none');
        $('#extra-list').append('<li class="list-group-item py-2"><i class="ri-error-warning-line text-danger me-1"></i>' + $('<span>').text(barcode).html() + '</li>');
    }

    function tickGroup(grpIdx) {
        var $cb = $('[data-group="grp-' + grpIdx + '"].check-item');
        if (!$cb.is(':checked')) {
            $cb.prop('checked', true);
            $('#row-grp-' + grpIdx).addClass('table-success').removeClass('table-danger');
            updateStats();
        }
    }

    // Scan
    function doScan(barcode) {
        if (!barcode) return;

        // Check if it's a known bag code → tick group immediately
        if (bagCodeMap[barcode]) {
            tickGroup(bagCodeMap[barcode]);
        }
        // Check if it's a known package code → tick group
        if (pkgCodeMap[barcode]) {
            tickGroup(pkgCodeMap[barcode]);
        }

        $.post(ajaxUrl, { request_name: 'scan', shipment_id: shipmentId, barcode: barcode, csrf_token: csrfToken }, function(res) {
            addScanLog(res.msg, res.status);

            if (res.status === 'matched') {
                if (res.type === 'bag' && res.bag_code && bagCodeMap[res.bag_code]) {
                    tickGroup(bagCodeMap[res.bag_code]);
                } else if (res.package_code && pkgCodeMap[res.package_code]) {
                    tickGroup(pkgCodeMap[res.package_code]);
                    // Update individual package check status
                    // Find package row and mark as checked
                }
            } else if (res.status === 'extra' || res.status === 'not_found') {
                addExtra(barcode);
            }
        }, 'json').fail(function(){
            addScanLog('<?= __('Lỗi kết nối') ?>', 'error');
        });
    }

    $('#scan-input').on('keypress', function(e){
        if (e.which === 13) {
            e.preventDefault();
            doScan($(this).val().trim());
            $(this).val('').focus();
        }
    });
    $('#btn-scan').on('click', function(){
        doScan($('#scan-input').val().trim());
        $('#scan-input').val('').focus();
    });

    // Manual checkbox
    $(document).on('change', '.check-item', function(){
        var grp = $(this).data('group');
        if ($(this).is(':checked')) {
            $('#row-' + grp).addClass('table-success').removeClass('table-danger');
        } else {
            $('#row-' + grp).removeClass('table-success');
        }
        $('#check-all').prop('checked', $('.check-item:checked').length === totalGroups);
        updateStats();
    });

    $('#check-all').on('change', function(){
        $('.check-item').prop('checked', $(this).is(':checked')).trigger('change');
    });

    // Expand
    $(document).on('click', '.btn-expand', function(e){
        e.preventDefault();
        var grp = $(this).data('group');
        $('#expand-' + grp).toggleClass('d-none');
        var $icon = $('.expand-icon-' + grp);
        if ($('#expand-' + grp).hasClass('d-none')) {
            $icon.removeClass('ri-arrow-up-s-line').addClass('ri-arrow-down-s-line');
        } else {
            $icon.removeClass('ri-arrow-down-s-line').addClass('ri-arrow-up-s-line');
        }
    });

    // Complete check
    $('#btn-complete').on('click', function(){
        var btn = $(this);
        var checked = $('.check-item:checked').length;
        var unchecked = totalGroups - checked;

        var msg = '✅ <?= __('Khớp') ?>: ' + checked + ' <?= __('mã hàng') ?>';
        if (unchecked > 0) msg += '\n❌ <?= __('Thiếu') ?>: ' + unchecked + ' <?= __('mã hàng') ?>';
        if (extraCount > 0) msg += '\n⚠️ <?= __('Thừa') ?>: ' + extraCount + ' <?= __('mã') ?>';
        msg += '\n\n<?= __('Xác nhận hoàn thành kiểm đếm?') ?>';

        Swal.fire({
            title: '<?= __('Hoàn thành kiểm đếm') ?>',
            html: '<pre class="text-start mb-0" style="white-space:pre-wrap">' + msg + '</pre>',
            icon: unchecked > 0 ? 'warning' : 'success',
            showCancelButton: true,
            confirmButtonText: '<?= __('Xác nhận') ?>',
            cancelButtonText: '<?= __('Hủy') ?>'
        }).then(function(result){
            if (!result.isConfirmed) return;
            btn.prop('disabled', true);

            $.post(ajaxUrl, {
                request_name: 'complete',
                shipment_id: shipmentId,
                note: $('#check-note').val(),
                csrf_token: csrfToken
            }, function(res){
                if (res.status === 'success') {
                    var html = '<h4 class="mb-3"><?= __('Kết quả kiểm đếm') ?></h4>';
                    html += '<div class="d-flex gap-3 justify-content-center mb-3">';
                    html += '<span class="badge bg-success fs-14 px-3 py-2">✅ <?= __('Khớp') ?>: ' + res.matched + '</span>';
                    if (res.missing > 0) html += '<span class="badge bg-danger fs-14 px-3 py-2">❌ <?= __('Thiếu') ?>: ' + res.missing + '</span>';
                    if (res.extra > 0) html += '<span class="badge bg-warning fs-14 px-3 py-2">⚠️ <?= __('Thừa') ?>: ' + res.extra + '</span>';
                    html += '</div>';

                    if (res.missing_items && res.missing_items.length > 0) {
                        html += '<div class="text-start mt-3"><strong class="text-danger"><?= __('Danh sách thiếu') ?>:</strong><ul class="mb-0 mt-1">';
                        res.missing_items.forEach(function(item){
                            html += '<li>' + item.package_code + (item.ma_hang ? ' <small class="text-muted">(' + item.ma_hang + ')</small>' : '') + '</li>';
                        });
                        html += '</ul></div>';
                    }

                    if (res.extra_items && res.extra_items.length > 0) {
                        html += '<div class="text-start mt-3"><strong class="text-danger"><?= __('Danh sách thừa') ?>:</strong><ul class="mb-0 mt-1">';
                        res.extra_items.forEach(function(item){
                            html += '<li>' + item.barcode + '</li>';
                        });
                        html += '</ul></div>';
                    }

                    Swal.fire({ html: html, icon: res.missing > 0 ? 'warning' : 'success', confirmButtonText: 'OK' }).then(function(){ location.reload(); });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.msg });
                    btn.prop('disabled', false);
                }
            }, 'json').fail(function(){
                Swal.fire({ icon: 'error', title: 'Error', text: '<?= __('Lỗi kết nối') ?>' });
                btn.prop('disabled', false);
            });
        });
    });

    // Reset
    $('#btn-reset').on('click', function(){
        Swal.fire({
            title: '<?= __('Kiểm đếm lại?') ?>',
            text: '<?= __('Dữ liệu kiểm đếm cũ sẽ bị xóa') ?>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: '<?= __('Xác nhận') ?>',
            cancelButtonText: '<?= __('Hủy') ?>'
        }).then(function(result){
            if (!result.isConfirmed) return;
            $.post(ajaxUrl, { request_name: 'reset', shipment_id: shipmentId, csrf_token: csrfToken }, function(res){
                if (res.status === 'success') {
                    location.reload();
                } else {
                    Swal.fire({ icon: 'error', text: res.msg });
                }
            }, 'json');
        });
    });

    // Init stats
    updateStats();

    // Load existing extras on page load
    <?php
    $existingExtras = $ToryHub->get_list_safe("SELECT * FROM `shipment_check_extras` WHERE `shipment_id` = ?", [$id]);
    if (!empty($existingExtras)):
    ?>
    var existingExtras = <?= json_encode($existingExtras) ?>;
    existingExtras.forEach(function(e){
        extraCount++;
        $('#extra-list').append('<li class="list-group-item py-2"><i class="ri-error-warning-line text-danger me-1"></i>' + $('<span>').text(e.barcode).html() + '</li>');
    });
    $('#extra-count').text(extraCount);
    $('#card-extras').removeClass('d-none');
    <?php endif; ?>

    // Focus scan input
    if (!isChecked) $('#scan-input').focus();
});
</script>
