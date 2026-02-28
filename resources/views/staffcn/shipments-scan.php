<?php
require_once(__DIR__.'/../../../models/is_staffcn.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$id = intval(input_get('id'));
$shipment = $ToryHub->get_row_safe("SELECT * FROM `shipments` WHERE `id` = ?", [$id]);
if (!$shipment) {
    echo '<script>alert("Chuyến xe không tồn tại");window.location.href="' . base_url('staffcn/shipments-list') . '";</script>';
    exit;
}
if ($shipment['status'] !== 'preparing') {
    echo '<script>alert("Chỉ có thể xếp hàng khi chuyến đang chuẩn bị");window.location.href="' . base_url('staffcn/shipments-detail') . '?id=' . $id . '";</script>';
    exit;
}

$page_title = __('Quét xếp hàng') . ' - ' . $shipment['shipment_code'];

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>

        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><i class="ri-qr-scan-2-line me-2"></i><?= __('Quét xếp hàng') ?>: <?= htmlspecialchars($shipment['shipment_code']) ?></h4>
                    <a href="<?= base_url('staffcn/shipments-detail') ?>?id=<?= $id ?>" class="btn btn-secondary btn-sm">
                        <i class="ri-arrow-left-line me-1"></i><?= __('Quay lại chuyến xe') ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Bar (Sticky) -->
        <div class="row" id="stats-bar" style="position: sticky; top: 70px; z-index: 100;">
            <div class="col-12">
                <div class="card shadow-sm mb-3">
                    <div class="card-body py-2">
                        <div class="row align-items-center g-2">
                            <div class="col-auto">
                                <span class="badge bg-primary fs-14 px-3 py-2">
                                    <?= __('Tổng quét') ?>: <span id="stat-total" class="fw-bold">0</span>
                                </span>
                            </div>
                            <div class="col-auto">
                                <span class="badge bg-success fs-14 px-3 py-2">
                                    <?= __('Đã thêm') ?>: <span id="stat-added" class="fw-bold">0</span>
                                </span>
                            </div>
                            <div class="col-auto">
                                <span class="badge bg-warning text-dark fs-14 px-3 py-2">
                                    <?= __('Trùng/Bỏ qua') ?>: <span id="stat-skipped" class="fw-bold">0</span>
                                </span>
                            </div>
                            <div class="col-auto">
                                <span class="badge bg-danger fs-14 px-3 py-2">
                                    <?= __('Lỗi') ?>: <span id="stat-error" class="fw-bold">0</span>
                                </span>
                            </div>
                            <div class="col">
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" id="progress-bar" role="progressbar" style="width: 0%"></div>
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
                <div class="card border-primary shadow">
                    <div class="card-body p-4">
                        <div class="mb-3 d-flex align-items-center gap-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="sound-toggle" checked>
                                <label class="form-check-label" for="sound-toggle">
                                    <i class="ri-volume-up-line me-1"></i><?= __('Âm thanh') ?>
                                </label>
                            </div>
                        </div>

                        <form id="form-scan" autocomplete="off">
                            <input type="hidden" name="<?= $csrf->get_token_name() ?>" id="csrf-token" value="<?= $csrf->get_token_value() ?>">
                            <input type="hidden" name="request_name" value="scan_add_to_shipment">
                            <input type="hidden" name="shipment_id" value="<?= $id ?>">

                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-primary text-white">
                                    <i class="ri-barcode-line fs-20"></i>
                                </span>
                                <input type="text" class="form-control" id="scan-input" name="barcode"
                                    placeholder="<?= __('Quét mã bao (BAO-xxx) hoặc mã kiện (Kxxxxxx)...') ?>"
                                    autofocus required
                                    style="height: 65px; font-size: 22px; letter-spacing: 1px;">
                                <button type="submit" class="btn btn-primary px-4" id="btn-scan">
                                    <i class="ri-qr-scan-2-line fs-20"></i>
                                </button>
                            </div>
                            <div class="text-muted mt-2 fs-12">
                                <i class="ri-information-line me-1"></i>
                                <?= __('Mã bao (BAO-xxx): thêm toàn bộ kiện trong bao | Mã kiện (Kxxxxxx): thêm 1 kiện | Tracking TQ/QT/VN: tìm theo mã vận đơn. Auto-submit sau 500ms.') ?>
                            </div>
                        </form>

                        <div id="last-result" class="mt-3" style="display:none;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scan Log -->
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
                                        <th><?= __('Mã quét') ?></th>
                                        <th><?= __('Loại') ?></th>
                                        <th><?= __('Mã thực tế') ?></th>
                                        <th width="80"><?= __('Đã thêm') ?></th>
                                        <th width="80"><?= __('Bỏ qua') ?></th>
                                        <th width="120"><?= __('Kết quả') ?></th>
                                        <th width="100"><?= __('Thời gian') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="scan-log-tbody">
                                    <tr id="empty-row">
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <i class="ri-scan-2-line fs-24 d-block mb-2"></i>
                                            <?= __('Chưa có lần quét nào. Bắt đầu quét mã bao hoặc mã kiện ở trên.') ?>
                                        </td>
                                    </tr>
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
    var ajaxUrl = '<?= base_url('ajaxs/staffcn/shipments.php') ?>';
    var csrfName = '<?= $csrf->get_token_name() ?>';
    var scanCounter = 0;
    var stats = { total: 0, added: 0, skipped: 0, error: 0 };
    var isSubmitting = false;
    var autoSubmitTimer = null;

    // ===== Audio =====
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
            osc.connect(gain); gain.connect(ctx.destination);
            osc.type = type || 'sine'; osc.frequency.value = freq;
            gain.gain.value = 0.3;
            osc.start(ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration);
            osc.stop(ctx.currentTime + duration);
        } catch(e) {}
    }
    function soundSuccess() { playBeep(880, 0.15); setTimeout(function(){ playBeep(1100, 0.15); }, 150); }
    function soundWarning() { playBeep(440, 0.1, 'triangle'); setTimeout(function(){ playBeep(440, 0.1, 'triangle'); }, 200); }
    function soundError() { playBeep(200, 0.4, 'square'); }

    // ===== Stats =====
    function updateStats() {
        $('#stat-total').text(stats.total);
        $('#stat-added').text(stats.added);
        $('#stat-skipped').text(stats.skipped);
        $('#stat-error').text(stats.error);
        var pct = stats.total > 0 ? Math.round((stats.added / (stats.added + stats.error || 1)) * 100) : 0;
        $('#progress-bar').css('width', Math.min(pct, 100) + '%');
    }

    // ===== Auto-submit =====
    var scanInput = $('#scan-input');
    scanInput.on('input', function(){
        clearTimeout(autoSubmitTimer);
        var val = $(this).val().trim();
        if (val.length >= 4 && !isSubmitting) {
            autoSubmitTimer = setTimeout(function(){ $('#form-scan').submit(); }, 500);
        }
    });

    // ===== Form Submit =====
    $('#form-scan').on('submit', function(e){
        e.preventDefault();
        clearTimeout(autoSubmitTimer);
        var barcode = scanInput.val().trim();
        if (!barcode || isSubmitting) { scanInput.focus(); return; }

        isSubmitting = true;
        scanInput.prop('readonly', true);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            timeout: 10000,
            success: function(res){
                stats.total++;
                if (res.status === 'success') {
                    stats.added += (res.added || 1);
                    stats.skipped += (res.skipped || 0);
                    soundSuccess();
                    addLogRow(barcode, 'success', res.resolved_type, res.resolved_code, res.added, res.skipped, res.msg);
                    showFlash('success', '<i class="ri-checkbox-circle-line me-1"></i> <strong>' + barcode + '</strong> — ' + res.msg);
                } else if (res.status === 'duplicate') {
                    stats.skipped++;
                    soundWarning();
                    addLogRow(barcode, 'duplicate', res.resolved_type, res.resolved_code, 0, 1, res.msg);
                    showFlash('warning', '<i class="ri-repeat-line me-1"></i> <strong>' + barcode + '</strong> — ' + res.msg);
                } else {
                    stats.error++;
                    soundError();
                    addLogRow(barcode, 'error', null, null, 0, 0, res.msg);
                    showFlash('danger', '<i class="ri-error-warning-line me-1"></i> <strong>' + barcode + '</strong> — ' + res.msg);
                }
                updateStats();
            },
            error: function(){
                stats.total++; stats.error++;
                updateStats(); soundError();
                addLogRow(barcode, 'error', null, null, 0, 0, '<?= __('Lỗi kết nối') ?>');
                showFlash('danger', '<i class="ri-wifi-off-line me-1"></i><?= __('Lỗi kết nối mạng') ?>');
            },
            complete: function(){
                isSubmitting = false;
                scanInput.prop('readonly', false).val('').focus();
            }
        });
    });

    function showFlash(type, html) {
        var el = $('#last-result');
        el.stop(true).hide().html(
            '<div class="alert alert-' + type + ' alert-dismissible fade show mb-0 py-2">' +
            html + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>'
        ).fadeIn(100);
        setTimeout(function(){ el.fadeOut(500); }, 3000);
    }

    function addLogRow(barcode, resultType, resolvedType, resolvedCode, added, skipped, message) {
        scanCounter++;
        $('#empty-row').remove();
        var rowClass = resultType === 'success' ? 'table-success' : (resultType === 'duplicate' ? '' : 'table-danger');
        var badge = resultType === 'success'
            ? '<span class="badge bg-success"><?= __('Thành công') ?></span>'
            : (resultType === 'duplicate'
                ? '<span class="badge bg-warning text-dark"><?= __('Trùng') ?></span>'
                : '<span class="badge bg-danger"><?= __('Lỗi') ?></span>');
        var typeLabel = resolvedType === 'bag'
            ? '<span class="badge bg-info text-dark"><?= __('Bao') ?></span>'
            : (resolvedType === 'package'
                ? '<span class="badge bg-secondary"><?= __('Kiện') ?></span>'
                : '-');
        var now = new Date();
        var timeStr = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0') + ':' + now.getSeconds().toString().padStart(2,'0');

        var row = '<tr class="' + rowClass + '">' +
            '<td>' + scanCounter + '</td>' +
            '<td><code>' + $('<div>').text(barcode).html() + '</code></td>' +
            '<td>' + typeLabel + '</td>' +
            '<td>' + (resolvedCode ? '<strong>' + $('<div>').text(resolvedCode).html() + '</strong>' : '-') + '</td>' +
            '<td class="text-center">' + (added > 0 ? '<span class="badge bg-success">' + added + '</span>' : '-') + '</td>' +
            '<td class="text-center">' + (skipped > 0 ? '<span class="badge bg-warning text-dark">' + skipped + '</span>' : '-') + '</td>' +
            '<td>' + badge + '<div class="text-muted fs-11 mt-1">' + $('<div>').text(message).html() + '</div></td>' +
            '<td class="text-muted fs-12">' + timeStr + '</td>' +
            '</tr>';
        $('#scan-log-tbody').prepend(row);
    }

    $('#btn-clear-log').on('click', function(){
        $('#scan-log-tbody').html('<tr id="empty-row"><td colspan="8" class="text-center text-muted py-4"><?= __('Log đã xóa.') ?></td></tr>');
        stats = { total: 0, added: 0, skipped: 0, error: 0 };
        scanCounter = 0;
        updateStats();
        scanInput.focus();
    });
});
</script>
