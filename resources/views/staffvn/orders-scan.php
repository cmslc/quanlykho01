<?php
require_once(__DIR__.'/../../../models/is_staffvn.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Batch Scan - Quét mã hàng loạt');

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>

        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><i class="ri-qr-scan-2-line me-2"></i><?= __('Batch Scan - Kho Việt Nam') ?></h4>
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
                                <span class="badge bg-primary fs-14 px-3 py-2">
                                    <?= __('Tổng quét') ?>: <span id="stat-total" class="fw-bold">0</span>
                                </span>
                            </div>
                            <div class="col-auto">
                                <span class="badge bg-success fs-14 px-3 py-2">
                                    <?= __('Thành công') ?>: <span id="stat-success" class="fw-bold">0</span>
                                </span>
                            </div>
                            <div class="col-auto">
                                <span class="badge bg-danger fs-14 px-3 py-2">
                                    <?= __('Lỗi') ?>: <span id="stat-error" class="fw-bold">0</span>
                                </span>
                            </div>
                            <div class="col-auto">
                                <span class="badge bg-warning text-dark fs-14 px-3 py-2">
                                    <?= __('Trùng') ?>: <span id="stat-duplicate" class="fw-bold">0</span>
                                </span>
                            </div>
                            <div class="col">
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" id="progress-bar" role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-sm btn-outline-danger" id="btn-end-session" title="<?= __('Kết thúc phiên quét') ?>">
                                    <i class="ri-stop-circle-line me-1"></i><?= __('Kết thúc') ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scan Input + Mode -->
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card border-success shadow">
                    <div class="card-body p-4">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?= __('Chế độ quét') ?></label>
                                <select id="scan-mode" class="form-select form-select-lg">
                                    <option value="vn_warehouse"><?= __('Nhập kho Việt Nam') ?> (→ <?= __('Đã về kho Việt Nam') ?>)</option>
                                    <option value="delivered"><?= __('Giao hàng') ?> (→ <?= __('Đã giao hàng') ?>)</option>
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
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
                            <input type="hidden" name="request_name" value="scan_order">
                            <input type="hidden" name="target_status" id="target-status" value="vn_warehouse">

                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-success text-white">
                                    <i class="ri-barcode-line fs-20"></i>
                                </span>
                                <input type="text" class="form-control" id="scan-input" name="barcode"
                                    placeholder="<?= __('Quét mã vận chuyển QT / mã giao VN / mã đơn hàng...') ?>"
                                    autofocus required
                                    style="height: 65px; font-size: 22px; letter-spacing: 1px;">
                                <button type="submit" class="btn btn-success px-4" id="btn-scan">
                                    <i class="ri-qr-scan-2-line fs-20"></i>
                                </button>
                            </div>
                            <div class="text-muted mt-2 fs-12">
                                <i class="ri-information-line me-1"></i>
                                <?= __('Tìm theo: Mã kiện hàng (PKG), Tracking QT, Tracking VN, Mã đơn hàng. Auto-submit sau 500ms.') ?>
                            </div>
                        </form>

                        <!-- Last scan result flash -->
                        <div id="last-result" class="mt-3" style="display:none;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scan Log Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0">
                            <i class="ri-list-check-2 me-1"></i><?= __('Lịch sử quét phiên này') ?>
                        </h5>
                        <button class="btn btn-sm btn-outline-secondary" id="btn-clear-log">
                            <i class="ri-delete-bin-line me-1"></i><?= __('Xóa log') ?>
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0" id="scan-log-table">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50"><?= __('STT') ?></th>
                                        <th><?= __('Mã vận đơn/Mã đơn') ?></th>
                                        <th><?= __('Tên sản phẩm') ?></th>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th width="120"><?= __('Kết quả') ?></th>
                                        <th><?= __('Chi tiết') ?></th>
                                        <th width="100"><?= __('Thời gian') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="scan-log-tbody">
                                    <tr id="empty-row">
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="ri-scan-2-line fs-24 d-block mb-2"></i>
                                            <?= __('Chưa có lần quét nào. Bắt đầu quét mã vận đơn ở trên.') ?>
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
$_ajaxUrl = base_url('ajaxs/staffvn/orders-scan.php');
$_csrfName = $csrf->get_token_name();

$body['footer'] = <<<'SCRIPT'
<script>
$(document).ready(function(){
    // ===== Web Audio API - Sound Feedback =====
    var audioCtx = null;
    function getAudioCtx() {
        if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        return audioCtx;
    }
    function playBeep(freq, duration, type) {
        if (!$('#sound-toggle').is(':checked')) return;
        try {
            var ctx = getAudioCtx();
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = type || 'sine';
            osc.frequency.value = freq;
            gain.gain.value = 0.3;
            osc.start(ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration);
            osc.stop(ctx.currentTime + duration);
        } catch(e) {}
    }
    function soundSuccess() {
        playBeep(880, 0.15, 'sine');
        setTimeout(function(){ playBeep(1100, 0.15, 'sine'); }, 150);
    }
    function soundError() {
        playBeep(200, 0.4, 'square');
    }
    function soundDuplicate() {
        playBeep(440, 0.1, 'triangle');
        setTimeout(function(){ playBeep(440, 0.1, 'triangle'); }, 200);
        setTimeout(function(){ playBeep(440, 0.1, 'triangle'); }, 400);
    }

    // ===== Stats =====
    var stats = { total: 0, success: 0, error: 0, duplicate: 0 };
    var scanCounter = 0;
    var scannedCodes = {};

    function updateStats() {
        $('#stat-total').text(stats.total);
        $('#stat-success').text(stats.success);
        $('#stat-error').text(stats.error);
        $('#stat-duplicate').text(stats.duplicate);
        var pct = stats.total > 0 ? Math.round((stats.success / stats.total) * 100) : 0;
        $('#progress-bar').css('width', pct + '%');
    }

    // ===== Mode selector =====
    $('#scan-mode').on('change', function(){
        $('#target-status').val($(this).val());
        $('#scan-input').focus();
    });

    // ===== Auto-submit =====
    var scanInput = $('#scan-input');
    var autoSubmitTimer = null;
    var isSubmitting = false;

    scanInput.on('input', function(){
        clearTimeout(autoSubmitTimer);
        var val = $(this).val().trim();
        if (val.length >= 6 && !isSubmitting) {
            autoSubmitTimer = setTimeout(function(){
                $('#form-scan').submit();
            }, 500);
        }
    });

    // ===== Form Submit =====
    $('#form-scan').on('submit', function(e){
        e.preventDefault();
        clearTimeout(autoSubmitTimer);

        var barcode = scanInput.val().trim();
        if (!barcode || isSubmitting) {
            scanInput.focus();
            return;
        }

        isSubmitting = true;
        scanInput.prop('readonly', true);

        // Check client-side duplicate
        var targetStatus = $('#target-status').val();
        var dupKey = barcode + '_' + targetStatus;

        if (scannedCodes[dupKey]) {
            stats.total++;
            stats.duplicate++;
            updateStats();
            soundDuplicate();
            addLogRow(barcode, 'duplicate', '{$_dupMsg}', null);
            showFlash('warning', '<i class="ri-repeat-line me-1"></i> <strong>' + barcode + '</strong> - {$_dupMsg}');
            resetInput();
            return;
        }

        $.ajax({
            url: '{$_ajaxUrl}',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            timeout: 10000,
            success: function(res){
                stats.total++;

                if (res.status == 'success') {
                    stats.success++;
                    soundSuccess();
                    scannedCodes[dupKey] = true;
                    addLogRow(barcode, 'success', res.msg, res.order);
                    showFlash('success', '<i class="ri-checkbox-circle-line me-1"></i> <strong>' + barcode + '</strong> - ' + res.msg);
                } else if (res.error_type == 'duplicate') {
                    stats.duplicate++;
                    soundDuplicate();
                    scannedCodes[dupKey] = true;
                    addLogRow(barcode, 'duplicate', res.msg, res.order || null);
                    showFlash('warning', '<i class="ri-repeat-line me-1"></i> <strong>' + barcode + '</strong> - ' + res.msg);
                } else {
                    stats.error++;
                    soundError();
                    addLogRow(barcode, 'error', res.msg, res.order || null);
                    showFlash('danger', '<i class="ri-error-warning-line me-1"></i> <strong>' + barcode + '</strong> - ' + res.msg);
                }

                updateStats();

                if (res.csrf_token) {
                    $('#csrf-token').val(res.csrf_token);
                }
            },
            error: function(){
                stats.total++;
                stats.error++;
                updateStats();
                soundError();
                addLogRow(barcode, 'error', '{$_connectionError}', null);
                showFlash('danger', '<i class="ri-wifi-off-line me-1"></i> <strong>' + barcode + '</strong> - {$_connectionError}');
            },
            complete: function(){
                resetInput();
            }
        });
    });

    function resetInput() {
        isSubmitting = false;
        scanInput.prop('readonly', false).val('').focus();
    }

    function showFlash(type, html) {
        var el = $('#last-result');
        el.stop(true).hide().html(
            '<div class="alert alert-' + type + ' alert-dismissible fade show mb-0 py-2">' +
            html + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>'
        ).fadeIn(100);
        setTimeout(function(){ el.fadeOut(500); }, 3000);
    }

    function addLogRow(barcode, resultType, message, order) {
        scanCounter++;
        $('#empty-row').remove();

        var rowClass = '', badge = '';
        if (resultType === 'success') {
            rowClass = 'table-success';
            badge = '<span class="badge bg-success">{$_lblSuccess}</span>';
        } else if (resultType === 'duplicate') {
            rowClass = 'table-warning';
            badge = '<span class="badge bg-warning text-dark">{$_lblDuplicate}</span>';
        } else {
            rowClass = 'table-danger';
            badge = '<span class="badge bg-danger">{$_lblError}</span>';
        }

        var productName = order ? (order.product_name || '-') : '-';
        var customerName = order ? (order.customer_name || '-') : '-';
        var time = new Date().toLocaleTimeString('vi-VN');

        var row = '<tr class="' + rowClass + '">' +
            '<td class="fw-semibold">' + scanCounter + '</td>' +
            '<td><code class="fs-13">' + escHtml(barcode) + '</code></td>' +
            '<td>' + escHtml(productName) + '</td>' +
            '<td>' + escHtml(customerName) + '</td>' +
            '<td>' + badge + '</td>' +
            '<td class="text-muted fs-12">' + escHtml(message) + '</td>' +
            '<td class="text-muted fs-12">' + time + '</td>' +
            '</tr>';
        $('#scan-log-tbody').prepend(row);
    }

    function escHtml(str) {
        if (!str) return '';
        return $('<div>').text(str).html();
    }

    // ===== Clear log =====
    $('#btn-clear-log').on('click', function(){
        Swal.fire({
            title: '{$_clearConfirmTitle}',
            text: '{$_clearConfirmText}',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '{$_lblYes}',
            cancelButtonText: '{$_lblNo}'
        }).then(function(result){
            if (result.isConfirmed) {
                scanCounter = 0;
                stats = { total: 0, success: 0, error: 0, duplicate: 0 };
                scannedCodes = {};
                updateStats();
                $('#scan-log-tbody').html(
                    '<tr id="empty-row"><td colspan="7" class="text-center text-muted py-4">' +
                    '<i class="ri-scan-2-line fs-24 d-block mb-2"></i>{$_emptyMsg}</td></tr>'
                );
                $('#last-result').hide();
                scanInput.focus();
            }
        });
    });

    // ===== End session =====
    $('#btn-end-session').on('click', function(){
        if (stats.total === 0) {
            Swal.fire({ icon: 'info', title: '{$_noScanTitle}', text: '{$_noScanText}' });
            return;
        }
        Swal.fire({
            title: '{$_endSessionTitle}',
            html: '<div class="text-start">' +
                '<p><strong>{$_lblTotal}:</strong> ' + stats.total + '</p>' +
                '<p><strong>{$_lblSuccess}:</strong> ' + stats.success + '</p>' +
                '<p><strong>{$_lblError}:</strong> ' + stats.error + '</p>' +
                '<p><strong>{$_lblDuplicate}:</strong> ' + stats.duplicate + '</p>' +
                '</div>',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: '{$_endAndNotify}',
            cancelButtonText: '{$_lblCancel}'
        }).then(function(result){
            if (result.isConfirmed) {
                $.post('{$_ajaxUrl}', {
                    '{$_csrfName}': $('#csrf-token').val(),
                    request_name: 'end_session',
                    total: stats.total,
                    success: stats.success,
                    error: stats.error,
                    duplicate: stats.duplicate,
                    mode: $('#target-status').val()
                }, function(res){
                    if (res.csrf_token) $('#csrf-token').val(res.csrf_token);
                    Swal.fire({
                        icon: 'success',
                        title: '{$_sessionEndedTitle}',
                        showConfirmButton: false,
                        timer: 2000
                    });
                }, 'json');

                scanCounter = 0;
                stats = { total: 0, success: 0, error: 0, duplicate: 0 };
                scannedCodes = {};
                updateStats();
                $('#scan-log-tbody').html(
                    '<tr id="empty-row"><td colspan="7" class="text-center text-muted py-4">' +
                    '<i class="ri-scan-2-line fs-24 d-block mb-2"></i>{$_emptyMsg}</td></tr>'
                );
                scanInput.focus();
            }
        });
    });

    // ===== Keep focus =====
    $(document).on('click', function(e){
        if (!$(e.target).closest('select, button, .swal2-container, .btn-close').length) {
            scanInput.focus();
        }
    });
    $(document).on('click', '.swal2-confirm, .swal2-cancel', function(){
        setTimeout(function(){ scanInput.focus(); }, 300);
    });
});
</script>
SCRIPT;

$_dupMsg = __('Mã này đã được quét trong phiên này');
$_connectionError = __('Lỗi kết nối. Vui lòng thử lại.');
$_lblSuccess = __('Thành công');
$_lblError = __('Lỗi');
$_lblDuplicate = __('Trùng');
$_lblTotal = __('Tổng quét');
$_lblYes = __('Có');
$_lblNo = __('Không');
$_lblCancel = __('Hủy');
$_clearConfirmTitle = __('Xóa lịch sử quét?');
$_clearConfirmText = __('Dữ liệu thống kê phiên này sẽ bị reset.');
$_emptyMsg = __('Chưa có lần quét nào. Bắt đầu quét mã vận đơn ở trên.');
$_noScanTitle = __('Chưa quét đơn nào');
$_noScanText = __('Bạn chưa quét đơn nào trong phiên này.');
$_endSessionTitle = __('Kết thúc phiên quét');
$_endAndNotify = __('Kết thúc & Gửi báo cáo');
$_sessionEndedTitle = __('Đã kết thúc phiên quét');

$body['footer'] = str_replace(
    ['{$_ajaxUrl}', '{$_csrfName}', '{$_dupMsg}', '{$_connectionError}',
     '{$_lblSuccess}', '{$_lblError}', '{$_lblDuplicate}', '{$_lblTotal}',
     '{$_lblYes}', '{$_lblNo}', '{$_lblCancel}',
     '{$_clearConfirmTitle}', '{$_clearConfirmText}', '{$_emptyMsg}',
     '{$_noScanTitle}', '{$_noScanText}', '{$_endSessionTitle}',
     '{$_endAndNotify}', '{$_sessionEndedTitle}'],
    [$_ajaxUrl, $_csrfName, $_dupMsg, $_connectionError,
     $_lblSuccess, $_lblError, $_lblDuplicate, $_lblTotal,
     $_lblYes, $_lblNo, $_lblCancel,
     $_clearConfirmTitle, $_clearConfirmText, $_emptyMsg,
     $_noScanTitle, $_noScanText, $_endSessionTitle,
     $_endAndNotify, $_sessionEndedTitle],
    $body['footer']
);

require_once(__DIR__.'/footer.php');
?>
