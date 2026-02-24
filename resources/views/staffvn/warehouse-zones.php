<?php
require_once(__DIR__.'/../../../models/is_staffvn.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Vị trí kho');

// Get zones for initial render
$zones = $ToryHub->get_list_safe(
    "SELECT z.*, (SELECT COUNT(*) FROM `packages` p WHERE p.zone_id = z.id AND p.status = 'vn_warehouse') as package_count
     FROM `warehouse_zones` z WHERE z.is_active = 1 ORDER BY z.zone_code ASC", []
);

// Count unassigned
$unassignedCount = $ToryHub->num_rows_safe(
    "SELECT id FROM `packages` WHERE `status` = 'vn_warehouse' AND (`zone_id` IS NULL OR `zone_id` = 0)", []
);

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>

        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><i class="ri-layout-grid-line me-2"></i><?= __('Vị trí kho') ?></h4>
                    <div class="page-title-right">
                        <button class="btn btn-sm btn-primary" id="btn-add-zone">
                            <i class="ri-add-line me-1"></i><?= __('Thêm vùng kho') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Zone Cards -->
        <div class="row mb-3" id="zone-cards">
            <!-- Unassigned -->
            <div class="col-auto mb-2">
                <div class="card mb-0 border zone-card <?= !$zones ? 'border-primary' : '' ?>" data-zone-id="0" role="button">
                    <div class="card-body py-2 px-3">
                        <span class="fw-semibold text-muted"><?= __('Chưa gán') ?></span>
                        <span class="badge bg-warning text-dark ms-2" id="count-unassigned"><?= $unassignedCount ?></span>
                    </div>
                </div>
            </div>
            <?php foreach ($zones as $z): ?>
            <div class="col-auto mb-2">
                <div class="card mb-0 border zone-card" data-zone-id="<?= $z['id'] ?>" role="button">
                    <div class="card-body py-2 px-3">
                        <span class="badge bg-primary me-1"><?= htmlspecialchars($z['zone_code']) ?></span>
                        <span class="fw-semibold"><?= htmlspecialchars($z['zone_name']) ?></span>
                        <span class="badge bg-soft-success text-success ms-2 zone-count"><?= $z['package_count'] ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Scan Assign Section -->
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card border-success shadow">
                    <div class="card-body p-4">
                        <div class="row mb-3">
                            <div class="col-md-5">
                                <label class="form-label fw-semibold"><?= __('Vùng đích') ?></label>
                                <select id="target-zone" class="form-select form-select-lg">
                                    <option value=""><?= __('-- Chọn vùng kho --') ?></option>
                                    <?php foreach ($zones as $z): ?>
                                    <option value="<?= $z['id'] ?>"><?= htmlspecialchars($z['zone_code'] . ' - ' . $z['zone_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold"><?= __('Vị trí kệ') ?> <small class="text-muted">(<?= __('tùy chọn') ?>)</small></label>
                                <input type="text" class="form-control form-control-lg" id="shelf-position" placeholder="VD: Tầng 2">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="sound-toggle" checked>
                                    <label class="form-check-label" for="sound-toggle">
                                        <i class="ri-volume-up-line me-1"></i><?= __('Âm thanh') ?>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <form id="form-scan" autocomplete="off">
                            <input type="hidden" name="<?= $csrf->get_token_name() ?>" id="csrf-token" value="<?= $csrf->get_token_value() ?>">

                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-success text-white">
                                    <i class="ri-barcode-line fs-20"></i>
                                </span>
                                <input type="text" class="form-control" id="scan-input" name="barcode"
                                    placeholder="<?= __('Quét mã kiện hàng để gán vùng kho...') ?>"
                                    autofocus
                                    style="height: 65px; font-size: 22px; letter-spacing: 1px;">
                                <button type="submit" class="btn btn-success px-4">
                                    <i class="ri-map-pin-add-line fs-20"></i>
                                </button>
                            </div>
                            <div class="text-muted mt-2 fs-12">
                                <i class="ri-information-line me-1"></i>
                                <?= __('Chọn vùng đích trước, sau đó quét mã kiện để gán. Auto-submit sau 500ms.') ?>
                            </div>
                        </form>

                        <div id="last-result" class="mt-3" style="display:none;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Bar -->
        <div class="row" id="stats-bar">
            <div class="col-12">
                <div class="card shadow-sm mb-3">
                    <div class="card-body py-2">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <span class="badge bg-success fs-14 px-3 py-2">
                                    <?= __('Đã gán') ?>: <span id="stat-assigned" class="fw-bold">0</span>
                                </span>
                            </div>
                            <div class="col-auto">
                                <span class="badge bg-warning text-dark fs-14 px-3 py-2">
                                    <?= __('Trùng') ?>: <span id="stat-duplicate" class="fw-bold">0</span>
                                </span>
                            </div>
                            <div class="col-auto">
                                <span class="badge bg-danger fs-14 px-3 py-2">
                                    <?= __('Lỗi') ?>: <span id="stat-error" class="fw-bold">0</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Packages Table (loaded via zone card click) -->
        <div class="row" id="zone-packages-panel" style="display:none;">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0">
                            <i class="ri-archive-line me-1"></i><span id="zone-packages-title"></span>
                        </h5>
                        <span class="badge bg-primary" id="zone-packages-count"></span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50">#</th>
                                        <th><?= __('Mã kiện') ?></th>
                                        <th><?= __('Tracking') ?></th>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th><?= __('Cân nặng') ?></th>
                                        <th><?= __('Vị trí kệ') ?></th>
                                        <th><?= __('Ngày nhập kho') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="zone-packages-tbody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assign Log Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="ri-list-check-2 me-1"></i><?= __('Lịch sử gán vị trí phiên này') ?>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50"><?= __('STT') ?></th>
                                        <th><?= __('Mã kiện') ?></th>
                                        <th><?= __('Vùng kho') ?></th>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th><?= __('Kết quả') ?></th>
                                        <th width="120"><?= __('Thời gian') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="log-tbody">
                                    <tr id="empty-row">
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="ri-map-pin-line fs-24 d-block mb-2"></i>
                                            <?= __('Chưa gán kiện nào. Chọn vùng và quét mã kiện ở trên.') ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<?php
$_ajaxUrl = base_url('ajaxs/staffvn/warehouse-zones.php');
$_csrfName = $csrf->get_token_name();

$body['footer'] = <<<'SCRIPT'
<script>
$(document).ready(function(){
    // ===== Sound =====
    var audioCtx = null;
    function getAudioCtx() { if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)(); return audioCtx; }
    function playBeep(freq, dur, type) {
        if (!$('#sound-toggle').is(':checked')) return;
        try { var c=getAudioCtx(),o=c.createOscillator(),g=c.createGain();o.connect(g);g.connect(c.destination);o.type=type||'sine';o.frequency.value=freq;g.gain.value=0.3;o.start(c.currentTime);g.gain.exponentialRampToValueAtTime(0.001,c.currentTime+dur);o.stop(c.currentTime+dur); } catch(e){}
    }
    function soundSuccess() { playBeep(880,0.15,'sine'); setTimeout(function(){playBeep(1100,0.15,'sine');},150); }
    function soundError() { playBeep(200,0.4,'square'); }
    function soundDuplicate() { playBeep(440,0.1,'triangle'); setTimeout(function(){playBeep(440,0.1,'triangle');},200); }

    var stats = { assigned: 0, duplicate: 0, error: 0 };
    var logCounter = 0;
    var isSubmitting = false;
    var scanInput = $('#scan-input');
    var autoSubmitTimer = null;

    function updateStats() {
        $('#stat-assigned').text(stats.assigned);
        $('#stat-duplicate').text(stats.duplicate);
        $('#stat-error').text(stats.error);
    }

    // ===== Auto-submit =====
    scanInput.on('input', function(){
        clearTimeout(autoSubmitTimer);
        var val = $(this).val().trim();
        if (val.length >= 6 && !isSubmitting) {
            autoSubmitTimer = setTimeout(function(){ $('#form-scan').submit(); }, 500);
        }
    });

    // ===== Assign zone on scan =====
    $('#form-scan').on('submit', function(e){
        e.preventDefault();
        clearTimeout(autoSubmitTimer);
        var barcode = scanInput.val().trim();
        var zoneId = $('#target-zone').val();

        if (!barcode || isSubmitting) return;

        if (!zoneId) {
            soundError();
            showFlash('danger', '<i class="ri-error-warning-line me-1"></i> {$_selectZoneFirst}');
            scanInput.val('').focus();
            return;
        }

        isSubmitting = true;
        scanInput.prop('readonly', true);

        $.ajax({
            url: '{$_ajaxUrl}',
            type: 'POST',
            data: {
                '{$_csrfName}': $('#csrf-token').val(),
                request_name: 'assign_zone',
                barcode: barcode,
                zone_id: zoneId,
                shelf_position: $('#shelf-position').val().trim()
            },
            dataType: 'json',
            timeout: 10000,
            success: function(res){
                if (res.csrf_token) $('#csrf-token').val(res.csrf_token);

                if (res.status === 'success') {
                    stats.assigned++;
                    soundSuccess();
                    showFlash('success', '<i class="ri-checkbox-circle-line me-1"></i> ' + res.msg);
                    addLogRow(res.package, 'success', res.msg);
                } else if (res.status === 'duplicate') {
                    stats.duplicate++;
                    soundDuplicate();
                    showFlash('warning', '<i class="ri-repeat-line me-1"></i> ' + res.msg);
                    addLogRow({ package_code: res.package_code || barcode }, 'duplicate', res.msg);
                } else {
                    stats.error++;
                    soundError();
                    showFlash('danger', '<i class="ri-error-warning-line me-1"></i> ' + res.msg);
                    addLogRow({ package_code: barcode }, 'error', res.msg);
                }
                updateStats();
            },
            error: function(){
                stats.error++;
                updateStats();
                soundError();
                showFlash('danger', '<i class="ri-wifi-off-line me-1"></i> {$_connectionError}');
            },
            complete: function(){
                isSubmitting = false;
                scanInput.prop('readonly', false).val('').focus();
            }
        });
    });

    // ===== Add zone =====
    $('#btn-add-zone').on('click', function(){
        Swal.fire({
            title: '{$_addZoneTitle}',
            html: '<div class="text-start">' +
                '<div class="mb-3"><label class="form-label">{$_lblZoneCode}</label><input id="swal-zone-code" class="form-control" placeholder="VD: A1, KE-01"></div>' +
                '<div class="mb-3"><label class="form-label">{$_lblZoneName}</label><input id="swal-zone-name" class="form-control" placeholder="VD: Kệ A1"></div>' +
                '<div><label class="form-label">{$_lblDescription}</label><input id="swal-zone-desc" class="form-control" placeholder="' + '{$_lblOptional}' + '"></div>' +
                '</div>',
            showCancelButton: true,
            confirmButtonText: '{$_lblCreate}',
            cancelButtonText: '{$_lblCancel}',
            preConfirm: function(){
                var code = $('#swal-zone-code').val().trim();
                var name = $('#swal-zone-name').val().trim();
                if (!code || !name) { Swal.showValidationMessage('{$_fillRequired}'); return false; }
                return { zone_code: code, zone_name: name, description: $('#swal-zone-desc').val().trim() };
            }
        }).then(function(result){
            if (!result.isConfirmed) return;
            $.ajax({
                url: '{$_ajaxUrl}',
                type: 'POST',
                data: {
                    '{$_csrfName}': $('#csrf-token').val(),
                    request_name: 'create_zone',
                    zone_code: result.value.zone_code,
                    zone_name: result.value.zone_name,
                    description: result.value.description
                },
                dataType: 'json',
                success: function(res){
                    if (res.csrf_token) $('#csrf-token').val(res.csrf_token);
                    if (res.status === 'success') {
                        Swal.fire({ icon: 'success', title: res.msg, showConfirmButton: false, timer: 1500 });
                        // Add zone card + select option
                        var z = res.zone;
                        var card = '<div class="col-auto mb-2"><div class="card mb-0 border zone-card" data-zone-id="' + z.id + '" role="button">' +
                            '<div class="card-body py-2 px-3"><span class="badge bg-primary me-1">' + escHtml(z.zone_code) + '</span>' +
                            '<span class="fw-semibold">' + escHtml(z.zone_name) + '</span>' +
                            '<span class="badge bg-soft-success text-success ms-2 zone-count">0</span></div></div></div>';
                        $('#zone-cards').append(card);
                        $('#target-zone').append('<option value="' + z.id + '">' + escHtml(z.zone_code + ' - ' + z.zone_name) + '</option>');
                    } else {
                        Swal.fire({ icon: 'error', title: res.msg });
                    }
                }
            });
        });
    });

    // ===== Zone card click → load packages =====
    $(document).on('click', '.zone-card', function(){
        $('.zone-card').removeClass('border-primary');
        $(this).addClass('border-primary');

        var zoneId = $(this).data('zone-id');
        var zoneName = $(this).find('.fw-semibold').text();

        $.ajax({
            url: '{$_ajaxUrl}',
            type: 'POST',
            data: {
                '{$_csrfName}': $('#csrf-token').val(),
                request_name: 'get_zone_packages',
                zone_id: zoneId
            },
            dataType: 'json',
            success: function(res){
                if (res.csrf_token) $('#csrf-token').val(res.csrf_token);
                if (res.status !== 'success') return;

                var title = zoneId == 0 ? '{$_lblUnassigned}' : zoneName;
                $('#zone-packages-title').text(title);
                $('#zone-packages-count').text(res.packages.length + ' {$_lblPkgs}');

                var tbody = $('#zone-packages-tbody');
                tbody.empty();

                if (res.packages.length === 0) {
                    tbody.html('<tr><td colspan="7" class="text-center text-muted py-3">{$_noPackages}</td></tr>');
                } else {
                    $.each(res.packages, function(i, p){
                        tbody.append('<tr>' +
                            '<td>' + (i+1) + '</td>' +
                            '<td><code>' + escHtml(p.package_code) + '</code></td>' +
                            '<td class="text-muted fs-12">' + escHtml(p.tracking_cn || p.tracking_intl || '-') + '</td>' +
                            '<td>' + escHtml(p.customer_names || '-') + '</td>' +
                            '<td>' + (p.weight_charged || 0) + ' kg</td>' +
                            '<td>' + escHtml(p.shelf_position || '-') + '</td>' +
                            '<td class="text-muted fs-12">' + escHtml(p.vn_warehouse_date || '-') + '</td>' +
                            '</tr>');
                    });
                }

                $('#zone-packages-panel').slideDown(200);
            }
        });
    });

    // ===== Log =====
    function addLogRow(pkg, resultType, message) {
        logCounter++;
        $('#empty-row').remove();
        var time = new Date().toLocaleTimeString('vi-VN');
        var rowClass = resultType === 'success' ? 'table-success' : (resultType === 'duplicate' ? 'table-warning' : 'table-danger');
        var badge = resultType === 'success' ? '<span class="badge bg-success">OK</span>' :
                    (resultType === 'duplicate' ? '<span class="badge bg-warning text-dark">{$_lblDup}</span>' :
                    '<span class="badge bg-danger">{$_lblErr}</span>');

        var row = '<tr class="' + rowClass + '">' +
            '<td class="fw-semibold">' + logCounter + '</td>' +
            '<td><code>' + escHtml(pkg.package_code || '-') + '</code></td>' +
            '<td>' + escHtml(pkg.zone_code || '-') + '</td>' +
            '<td>' + escHtml(pkg.customer_name || '-') + '</td>' +
            '<td>' + badge + '</td>' +
            '<td class="text-muted fs-12">' + time + '</td>' +
            '</tr>';
        $('#log-tbody').prepend(row);
    }

    function showFlash(type, html) {
        var el = $('#last-result');
        el.stop(true).hide().html(
            '<div class="alert alert-' + type + ' alert-dismissible fade show mb-0 py-2">' +
            html + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>'
        ).fadeIn(100);
        setTimeout(function(){ el.fadeOut(500); }, 3000);
    }

    function escHtml(str) { return str ? $('<div>').text(str).html() : ''; }

    // ===== Focus =====
    $(document).on('click', function(e){
        if (!$(e.target).closest('select, button, .swal2-container, .btn-close, input, .zone-card').length) {
            scanInput.focus();
        }
    });
});
</script>
SCRIPT;

$_connectionError = __('Lỗi kết nối. Vui lòng thử lại.');
$_selectZoneFirst = __('Vui lòng chọn vùng kho trước');
$_addZoneTitle = __('Thêm vùng kho mới');
$_lblZoneCode = __('Mã vùng');
$_lblZoneName = __('Tên vùng');
$_lblDescription = __('Mô tả');
$_lblOptional = __('Tùy chọn');
$_lblCreate = __('Tạo mới');
$_lblCancel = __('Hủy');
$_fillRequired = __('Vui lòng nhập mã vùng và tên vùng');
$_lblUnassigned = __('Chưa gán vùng');
$_lblPkgs = __('kiện');
$_noPackages = __('Không có kiện nào trong vùng này');
$_lblDup = __('Trùng');
$_lblErr = __('Lỗi');

$body['footer'] = str_replace(
    ['{$_ajaxUrl}', '{$_csrfName}', '{$_connectionError}', '{$_selectZoneFirst}',
     '{$_addZoneTitle}', '{$_lblZoneCode}', '{$_lblZoneName}', '{$_lblDescription}',
     '{$_lblOptional}', '{$_lblCreate}', '{$_lblCancel}', '{$_fillRequired}',
     '{$_lblUnassigned}', '{$_lblPkgs}', '{$_noPackages}', '{$_lblDup}', '{$_lblErr}'],
    [$_ajaxUrl, $_csrfName, $_connectionError, $_selectZoneFirst,
     $_addZoneTitle, $_lblZoneCode, $_lblZoneName, $_lblDescription,
     $_lblOptional, $_lblCreate, $_lblCancel, $_fillRequired,
     $_lblUnassigned, $_lblPkgs, $_noPackages, $_lblDup, $_lblErr],
    $body['footer']
);

require_once(__DIR__.'/footer.php');
?>
