<?php
require_once(__DIR__.'/../../../models/is_staffvn.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Tách bao hàng');

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>

        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><i class="ri-inbox-unarchive-line me-2"></i><?= __('Tách bao hàng') ?></h4>
                    <div class="page-title-right">
                        <span class="badge bg-soft-success text-success fs-13 p-2">
                            <i class="ri-calendar-line me-1"></i><?= date('d/m/Y H:i') ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Bar (Sticky) -->
        <div class="row" id="stats-bar" style="position: sticky; top: 70px; z-index: 100;">
            <div class="col-12">
                <div class="card shadow-sm mb-3">
                    <div class="card-body py-2">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <span class="badge bg-info fs-14 px-3 py-2">
                                    <?= __('Bao đã xử lý') ?>: <span id="stat-bags" class="fw-bold">0</span>
                                </span>
                            </div>
                            <div class="col-auto">
                                <span class="badge bg-success fs-14 px-3 py-2">
                                    <?= __('Kiện đã tách') ?>: <span id="stat-unpacked" class="fw-bold">0</span>
                                </span>
                            </div>
                            <div class="col">
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" id="progress-bar" role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="sound-toggle" checked>
                                    <label class="form-check-label" for="sound-toggle">
                                        <i class="ri-volume-up-line"></i>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scan Input -->
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card border-info shadow">
                    <div class="card-body p-4">
                        <form id="form-scan" autocomplete="off">
                            <input type="hidden" name="<?= $csrf->get_token_name() ?>" id="csrf-token" value="<?= $csrf->get_token_value() ?>">
                            <input type="hidden" name="request_name" value="load_bag">

                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-info text-white">
                                    <i class="ri-barcode-line fs-20"></i>
                                </span>
                                <input type="text" class="form-control" id="scan-input" name="bag_code"
                                    placeholder="<?= __('Quét mã bao hàng...') ?>"
                                    autofocus required
                                    style="height: 65px; font-size: 22px; letter-spacing: 1px;">
                                <button type="submit" class="btn btn-info px-4" id="btn-scan">
                                    <i class="ri-search-line fs-20"></i>
                                </button>
                            </div>
                            <div class="text-muted mt-2 fs-12">
                                <i class="ri-information-line me-1"></i>
                                <?= __('Quét mã bao (BAO-xxx) để xem danh sách kiện bên trong. Auto-submit sau 500ms.') ?>
                            </div>
                        </form>

                        <!-- Last result flash -->
                        <div id="last-result" class="mt-3" style="display:none;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bag Detail Panel (hidden until bag is loaded) -->
        <div class="row" id="bag-panel" style="display:none;">
            <div class="col-12">
                <div class="card border-primary shadow">
                    <!-- Bag Header -->
                    <div class="card-header bg-primary-subtle">
                        <div class="row align-items-center">
                            <div class="col">
                                <h5 class="mb-0">
                                    <i class="ri-archive-line me-2"></i>
                                    <span id="bag-title"></span>
                                </h5>
                                <div class="text-muted mt-1 fs-13" id="bag-info"></div>
                            </div>
                            <div class="col-auto">
                                <span class="badge bg-primary fs-14 px-3 py-2" id="bag-pkg-count"></span>
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-sm btn-outline-danger" id="btn-close-bag" title="<?= __('Đóng') ?>">
                                    <i class="ri-close-line"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Package Table -->
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="pkg-table">
                                <thead class="table-light">
                                    <tr>
                                        <th width="40">
                                            <input type="checkbox" class="form-check-input" id="check-all">
                                        </th>
                                        <th><?= __('Mã kiện') ?></th>
                                        <th><?= __('Tracking') ?></th>
                                        <th><?= __('Sản phẩm') ?></th>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th><?= __('Cân nặng') ?></th>
                                        <th><?= __('Trạng thái') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="pkg-tbody"></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="card-footer bg-light">
                        <div class="d-flex gap-2">
                            <button class="btn btn-warning" id="btn-unpack-selected" disabled>
                                <i class="ri-scissors-line me-1"></i>
                                <?= __('Tách kiện đã chọn') ?> (<span id="selected-count">0</span>)
                            </button>
                            <button class="btn btn-danger" id="btn-unpack-all">
                                <i class="ri-scissors-2-line me-1"></i>
                                <?= __('Tách toàn bộ') ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unpack Log Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="ri-list-check-2 me-1"></i><?= __('Lịch sử tách bao phiên này') ?>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50"><?= __('STT') ?></th>
                                        <th><?= __('Mã bao') ?></th>
                                        <th><?= __('Kiện đã tách') ?></th>
                                        <th><?= __('Chi tiết') ?></th>
                                        <th width="120"><?= __('Thời gian') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="log-tbody">
                                    <tr id="empty-row">
                                        <td colspan="5" class="text-center text-muted py-4">
                                            <i class="ri-inbox-unarchive-line fs-24 d-block mb-2"></i>
                                            <?= __('Chưa có bao nào được tách. Quét mã bao ở trên để bắt đầu.') ?>
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
$_ajaxUrl = base_url('ajaxs/staffvn/bag-unpack.php');
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

    // ===== State =====
    var sessionStats = { bags: 0, unpacked: 0 };
    var logCounter = 0;
    var currentBag = null;
    var isSubmitting = false;

    function updateStats() {
        $('#stat-bags').text(sessionStats.bags);
        $('#stat-unpacked').text(sessionStats.unpacked);
    }

    // ===== Auto-submit =====
    var scanInput = $('#scan-input');
    var autoSubmitTimer = null;

    scanInput.on('input', function(){
        clearTimeout(autoSubmitTimer);
        var val = $(this).val().trim();
        if (val.length >= 6 && !isSubmitting) {
            autoSubmitTimer = setTimeout(function(){ $('#form-scan').submit(); }, 500);
        }
    });

    // ===== Load Bag =====
    $('#form-scan').on('submit', function(e){
        e.preventDefault();
        clearTimeout(autoSubmitTimer);
        var code = scanInput.val().trim();
        if (!code || isSubmitting) return;

        isSubmitting = true;
        scanInput.prop('readonly', true);

        $.ajax({
            url: '{$_ajaxUrl}',
            type: 'POST',
            data: {
                '{$_csrfName}': $('#csrf-token').val(),
                request_name: 'load_bag',
                bag_code: code
            },
            dataType: 'json',
            timeout: 10000,
            success: function(res){
                if (res.csrf_token) $('#csrf-token').val(res.csrf_token);

                if (res.status === 'success' || res.status === 'warning') {
                    currentBag = res.bag;
                    renderBagPanel(res.bag, res.packages);
                    soundSuccess();
                    showFlash('success', '<i class="ri-checkbox-circle-line me-1"></i> ' + res.msg);
                } else {
                    soundError();
                    showFlash('danger', '<i class="ri-error-warning-line me-1"></i> ' + res.msg);
                }
            },
            error: function(){
                soundError();
                showFlash('danger', '<i class="ri-wifi-off-line me-1"></i> {$_connectionError}');
            },
            complete: function(){
                isSubmitting = false;
                scanInput.prop('readonly', false).val('').focus();
            }
        });
    });

    // ===== Render bag panel =====
    function renderBagPanel(bag, packages) {
        $('#bag-title').text(bag.bag_code);
        var info = [];
        if (bag.total_weight) info.push(bag.total_weight + ' kg');
        if (bag.create_date) info.push(bag.create_date);
        if (bag.note) info.push(bag.note);
        $('#bag-info').html(info.join(' · '));
        $('#bag-pkg-count').text(packages.length + ' ' + '{$_lblPackages}');

        var tbody = $('#pkg-tbody');
        tbody.empty();

        if (packages.length === 0) {
            tbody.html('<tr><td colspan="7" class="text-center text-muted py-3">{$_noPkgs}</td></tr>');
            $('#btn-unpack-all').prop('disabled', true);
        } else {
            $.each(packages, function(i, p){
                var row = '<tr data-id="' + p.id + '">' +
                    '<td><input type="checkbox" class="form-check-input pkg-check" value="' + p.id + '"></td>' +
                    '<td><code class="fs-13">' + escHtml(p.package_code) + '</code></td>' +
                    '<td class="text-muted fs-12">' + escHtml(p.tracking_cn || p.tracking_intl || '-') + '</td>' +
                    '<td>' + escHtml(p.product_names || '-') + '</td>' +
                    '<td>' + escHtml(p.customer_names || '-') + '</td>' +
                    '<td>' + p.weight_charged + ' kg</td>' +
                    '<td>' + p.status_html + '</td>' +
                    '</tr>';
                tbody.append(row);
            });
            $('#btn-unpack-all').prop('disabled', false);
        }

        $('#bag-panel').slideDown(200);
        updateSelectedCount();
    }

    // ===== Close bag panel =====
    $('#btn-close-bag').on('click', function(){
        $('#bag-panel').slideUp(200);
        currentBag = null;
        scanInput.focus();
    });

    // ===== Checkbox handling =====
    $('#check-all').on('change', function(){
        var checked = $(this).is(':checked');
        $('.pkg-check').prop('checked', checked);
        updateSelectedCount();
    });

    $(document).on('change', '.pkg-check', function(){
        updateSelectedCount();
        var total = $('.pkg-check').length;
        var checked = $('.pkg-check:checked').length;
        $('#check-all').prop('checked', total > 0 && checked === total);
    });

    function updateSelectedCount() {
        var count = $('.pkg-check:checked').length;
        $('#selected-count').text(count);
        $('#btn-unpack-selected').prop('disabled', count === 0);
    }

    // ===== Unpack selected =====
    $('#btn-unpack-selected').on('click', function(){
        var ids = [];
        $('.pkg-check:checked').each(function(){ ids.push($(this).val()); });
        if (ids.length === 0 || !currentBag) return;

        Swal.fire({
            title: '{$_confirmUnpackTitle}',
            html: '{$_confirmUnpackText}'.replace('{count}', ids.length).replace('{bag}', currentBag.bag_code),
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '{$_lblConfirm}',
            cancelButtonText: '{$_lblCancel}'
        }).then(function(result){
            if (!result.isConfirmed) return;
            doUnpack('unpack_packages', { bag_id: currentBag.id, package_ids: JSON.stringify(ids) });
        });
    });

    // ===== Unpack all =====
    $('#btn-unpack-all').on('click', function(){
        if (!currentBag) return;
        var total = $('.pkg-check').length;

        Swal.fire({
            title: '{$_confirmUnpackAllTitle}',
            html: '{$_confirmUnpackAllText}'.replace('{count}', total).replace('{bag}', currentBag.bag_code),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '{$_lblConfirm}',
            cancelButtonText: '{$_lblCancel}'
        }).then(function(result){
            if (!result.isConfirmed) return;
            doUnpack('unpack_all', { bag_id: currentBag.id });
        });
    });

    function doUnpack(action, data) {
        data['{$_csrfName}'] = $('#csrf-token').val();
        data['request_name'] = action;

        $.ajax({
            url: '{$_ajaxUrl}',
            type: 'POST',
            data: data,
            dataType: 'json',
            timeout: 15000,
            success: function(res){
                if (res.csrf_token) $('#csrf-token').val(res.csrf_token);

                if (res.status === 'success') {
                    soundSuccess();
                    showFlash('success', '<i class="ri-checkbox-circle-line me-1"></i> ' + res.msg);
                    sessionStats.bags++;
                    sessionStats.unpacked += (res.unpacked_count || 0);
                    updateStats();

                    // Add log
                    addLogRow(currentBag.bag_code, res.unpacked_count, res.unpacked_codes || []);

                    // Remove unpacked rows or close panel
                    if (res.bag_remaining === 0 || action === 'unpack_all') {
                        $('#bag-panel').slideUp(200);
                        currentBag = null;
                    } else {
                        // Remove unpacked rows from table
                        if (res.unpacked_codes) {
                            // Reload bag
                            scanInput.val(currentBag.bag_code);
                            $('#form-scan').submit();
                        }
                    }
                } else {
                    soundError();
                    showFlash('danger', '<i class="ri-error-warning-line me-1"></i> ' + res.msg);
                }
            },
            error: function(){
                soundError();
                showFlash('danger', '<i class="ri-wifi-off-line me-1"></i> {$_connectionError}');
            }
        });
    }

    // ===== Log =====
    function addLogRow(bagCode, count, codes) {
        logCounter++;
        $('#empty-row').remove();
        var time = new Date().toLocaleTimeString('vi-VN');
        var detail = codes.length > 0 ? codes.join(', ') : '-';
        var row = '<tr class="table-success">' +
            '<td class="fw-semibold">' + logCounter + '</td>' +
            '<td><code>' + escHtml(bagCode) + '</code></td>' +
            '<td><span class="badge bg-success">' + count + ' ' + '{$_lblPackages}' + '</span></td>' +
            '<td class="text-muted fs-12">' + escHtml(detail) + '</td>' +
            '<td class="text-muted fs-12">' + time + '</td>' +
            '</tr>';
        $('#log-tbody').prepend(row);
    }

    // ===== Flash =====
    function showFlash(type, html) {
        var el = $('#last-result');
        el.stop(true).hide().html(
            '<div class="alert alert-' + type + ' alert-dismissible fade show mb-0 py-2">' +
            html + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>'
        ).fadeIn(100);
        setTimeout(function(){ el.fadeOut(500); }, 3000);
    }

    function escHtml(str) {
        if (!str) return '';
        return $('<div>').text(str).html();
    }

    // ===== Focus management =====
    $(document).on('click', function(e){
        if (!$(e.target).closest('select, button, .swal2-container, .btn-close, #bag-panel, input').length) {
            scanInput.focus();
        }
    });
});
</script>
SCRIPT;

$_connectionError = __('Lỗi kết nối. Vui lòng thử lại.');
$_lblPackages = __('kiện');
$_noPkgs = __('Bao hàng trống, không có kiện nào');
$_confirmUnpackTitle = __('Xác nhận tách kiện?');
$_confirmUnpackText = __('Tách {count} kiện khỏi bao {bag}?');
$_confirmUnpackAllTitle = __('Tách toàn bộ?');
$_confirmUnpackAllText = __('Tách toàn bộ {count} kiện khỏi bao {bag}? Hành động này không thể hoàn tác.');
$_lblConfirm = __('Xác nhận');
$_lblCancel = __('Hủy');

$body['footer'] = str_replace(
    ['{$_ajaxUrl}', '{$_csrfName}', '{$_connectionError}',
     '{$_lblPackages}', '{$_noPkgs}',
     '{$_confirmUnpackTitle}', '{$_confirmUnpackText}',
     '{$_confirmUnpackAllTitle}', '{$_confirmUnpackAllText}',
     '{$_lblConfirm}', '{$_lblCancel}'],
    [$_ajaxUrl, $_csrfName, $_connectionError,
     $_lblPackages, $_noPkgs,
     $_confirmUnpackTitle, $_confirmUnpackText,
     $_confirmUnpackAllTitle, $_confirmUnpackAllText,
     $_lblConfirm, $_lblCancel],
    $body['footer']
);

require_once(__DIR__.'/footer.php');
?>
