<?php
require_once(__DIR__.'/../../../models/is_staffvn.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Kiểm kê kho');

// History of checks
$checks = $ToryHub->get_list_safe("
    SELECT ic.*, u.fullname as staff_name
    FROM `inventory_checks` ic
    LEFT JOIN `users` u ON ic.staff_id = u.id
    ORDER BY ic.id DESC LIMIT 20
", []);

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>

        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><i class="ri-clipboard-line me-1"></i><?= __('Kiểm kê kho') ?></h4>
                </div>
            </div>
        </div>

        <!-- Start / Active Session -->
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <!-- No active session -->
                <div id="section-start" class="card border-primary shadow">
                    <div class="card-body text-center p-4">
                        <i class="ri-clipboard-line fs-1 text-primary d-block mb-2"></i>
                        <h5><?= __('Kiểm kê hàng tại kho Việt Nam') ?></h5>
                        <p class="text-muted"><?= __('Quét mã tất cả kiện hàng đang ở kho để đối chiếu với hệ thống.') ?></p>
                        <button class="btn btn-primary btn-lg" id="btn-start-check">
                            <i class="ri-play-line me-1"></i><?= __('Bắt đầu kiểm kê') ?>
                        </button>
                    </div>
                </div>

                <!-- Active session -->
                <div id="section-active" style="display:none;">
                    <!-- Stats Bar -->
                    <div class="card shadow-sm mb-3" style="position:sticky;top:70px;z-index:100;">
                        <div class="card-body py-2">
                            <div class="row text-center">
                                <div class="col">
                                    <small class="text-muted d-block"><?= __('Dự kiến') ?></small>
                                    <strong id="ic-expected">0</strong>
                                </div>
                                <div class="col">
                                    <small class="text-muted d-block"><?= __('Đã quét') ?></small>
                                    <strong id="ic-scanned" class="text-primary">0</strong>
                                </div>
                                <div class="col">
                                    <small class="text-muted d-block"><?= __('Khớp') ?></small>
                                    <strong id="ic-matched" class="text-success">0</strong>
                                </div>
                                <div class="col">
                                    <small class="text-muted d-block"><?= __('Thừa') ?></small>
                                    <strong id="ic-extra" class="text-danger">0</strong>
                                </div>
                                <div class="col">
                                    <small class="text-muted d-block"><?= __('Không thấy') ?></small>
                                    <strong id="ic-notfound" class="text-danger">0</strong>
                                </div>
                            </div>
                            <div class="progress mt-2" style="height:6px;">
                                <div class="progress-bar bg-success" id="ic-progress" style="width:0%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Scan Input -->
                    <div class="card border-primary shadow mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h6 class="mb-0"><i class="ri-qr-scan-2-line me-1"></i><span id="ic-check-code"></span></h6>
                                <button class="btn btn-sm btn-success" id="btn-complete-check"><i class="ri-checkbox-circle-line me-1"></i><?= __('Hoàn thành') ?></button>
                            </div>
                            <form id="form-ic-scan" autocomplete="off">
                                <div class="input-group">
                                    <input type="text" class="form-control form-control-lg" id="ic-scan-input"
                                        placeholder="<?= __('Quét mã kiện hàng...') ?>" autofocus
                                        style="height:55px;font-size:20px;letter-spacing:1px;">
                                    <button type="submit" class="btn btn-primary px-3"><i class="ri-qr-scan-2-line fs-20"></i></button>
                                </div>
                            </form>
                            <div id="ic-last-result" class="mt-2" style="display:none;"></div>
                        </div>
                    </div>

                    <!-- Scan Log -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0"><?= __('Lịch sử quét') ?></h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height:400px;overflow:auto;">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th width="40">#</th>
                                            <th><?= __('Mã quét') ?></th>
                                            <th><?= __('Kết quả') ?></th>
                                            <th><?= __('Chi tiết') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="ic-log-tbody">
                                        <tr id="ic-empty-row"><td colspan="4" class="text-center text-muted py-3"><?= __('Bắt đầu quét...') ?></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Results (shown after complete) -->
                <div id="section-results" style="display:none;">
                    <div class="card border-success shadow">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0 text-white"><i class="ri-checkbox-circle-line me-1"></i><?= __('Kết quả kiểm kê') ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center mb-3">
                                <div class="col"><h4 id="res-expected" class="mb-0">0</h4><small class="text-muted"><?= __('Dự kiến') ?></small></div>
                                <div class="col"><h4 id="res-scanned" class="mb-0 text-primary">0</h4><small class="text-muted"><?= __('Đã quét') ?></small></div>
                                <div class="col"><h4 id="res-matched" class="mb-0 text-success">0</h4><small class="text-muted"><?= __('Khớp') ?></small></div>
                                <div class="col"><h4 id="res-missing" class="mb-0 text-danger">0</h4><small class="text-muted"><?= __('Thiếu') ?></small></div>
                                <div class="col"><h4 id="res-extra" class="mb-0 text-danger">0</h4><small class="text-muted"><?= __('Thừa') ?></small></div>
                            </div>

                            <div id="missing-list-container" style="display:none;">
                                <h6 class="text-danger"><i class="ri-error-warning-line me-1"></i><?= __('Kiện hàng thiếu') ?></h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead><tr><th><?= __('Mã kiện') ?></th><th><?= __('Tracking') ?></th></tr></thead>
                                        <tbody id="missing-list-tbody"></tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="text-center mt-3">
                                <button class="btn btn-primary" onclick="location.reload()"><i class="ri-refresh-line me-1"></i><?= __('Kiểm kê mới') ?></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- History -->
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="ri-history-line me-1"></i><?= __('Lịch sử kiểm kê') ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($checks)): ?>
                            <p class="text-center text-muted"><?= __('Chưa có phiên kiểm kê nào') ?></p>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><?= __('Mã phiên') ?></th>
                                        <th><?= __('Nhân viên') ?></th>
                                        <th><?= __('Trạng thái') ?></th>
                                        <th><?= __('Dự kiến') ?></th>
                                        <th><?= __('Khớp') ?></th>
                                        <th><?= __('Thiếu') ?></th>
                                        <th><?= __('Thừa') ?></th>
                                        <th><?= __('Ngày') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($checks as $ck): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($ck['check_code']) ?></strong></td>
                                        <td><?= htmlspecialchars($ck['staff_name'] ?? '') ?></td>
                                        <td>
                                            <?php if ($ck['status'] === 'completed'): ?>
                                            <span class="badge bg-success"><?= __('Hoàn thành') ?></span>
                                            <?php else: ?>
                                            <span class="badge bg-warning"><?= __('Đang kiểm') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $ck['total_expected'] ?></td>
                                        <td class="text-success"><?= $ck['total_matched'] ?></td>
                                        <td class="text-danger"><?= $ck['total_missing'] ?></td>
                                        <td class="text-danger"><?= $ck['total_extra'] ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($ck['create_date'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
var csrfToken = '<?= $csrf->get_token_value() ?>';
var csrfName = '<?= $csrf->get_token_name() ?>';
var ajaxUrl = '<?= base_url('ajaxs/staffvn/inventory-check.php') ?>';
var checkId = null;
var scanCount = 0;

$(document).ready(function(){
    var scanInput = $('#ic-scan-input');
    var autoSubmitTimer = null;
    var isSubmitting = false;

    // Start check
    $('#btn-start-check').on('click', function(){
        var data = { request_name: 'start_check' };
        data[csrfName] = csrfToken;
        $.post(ajaxUrl, data, function(res){
            if (res.csrf_token) csrfToken = res.csrf_token;
            if (res.status === 'success') {
                checkId = res.check_id;
                $('#ic-check-code').text(res.check_code);
                $('#ic-expected').text(res.expected);
                $('#section-start').hide();
                $('#section-active').show();
                scanInput.focus();
            } else {
                Swal.fire({ icon: 'error', text: res.msg });
            }
        }, 'json');
    });

    // Auto-submit
    scanInput.on('input', function(){
        clearTimeout(autoSubmitTimer);
        var val = $(this).val().trim();
        if (val.length >= 6 && !isSubmitting) {
            autoSubmitTimer = setTimeout(function(){ $('#form-ic-scan').submit(); }, 500);
        }
    });

    // Scan
    $('#form-ic-scan').on('submit', function(e){
        e.preventDefault();
        clearTimeout(autoSubmitTimer);
        var barcode = scanInput.val().trim();
        if (!barcode || isSubmitting || !checkId) return;

        isSubmitting = true;
        scanInput.prop('readonly', true);

        var data = { request_name: 'scan_item', check_id: checkId, barcode: barcode };
        data[csrfName] = csrfToken;

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: data,
            dataType: 'json',
            timeout: 10000,
            success: function(res){
                if (res.csrf_token) csrfToken = res.csrf_token;

                scanCount++;
                $('#ic-empty-row').remove();

                var rowClass = '', badge = '';
                if (res.result_type === 'matched') {
                    rowClass = 'table-success';
                    badge = '<span class="badge bg-success"><?= __('Khớp') ?></span>';
                } else if (res.result_type === 'extra') {
                    rowClass = '';
                    badge = '<span class="badge bg-warning text-dark"><?= __('Thừa') ?></span>';
                } else if (res.error_type === 'duplicate') {
                    rowClass = 'table-secondary';
                    badge = '<span class="badge bg-secondary"><?= __('Đã quét') ?></span>';
                } else {
                    rowClass = 'table-danger';
                    badge = '<span class="badge bg-danger"><?= __('Không thấy') ?></span>';
                }

                var row = '<tr class="' + rowClass + '"><td>' + scanCount + '</td><td><code>' + escHtml(barcode) + '</code></td><td>' + badge + '</td><td class="small">' + escHtml(res.msg) + '</td></tr>';
                $('#ic-log-tbody').prepend(row);

                // Update counts
                if (res.counts) {
                    $('#ic-scanned').text(res.counts.scanned);
                    $('#ic-matched').text(res.counts.matched);
                    $('#ic-extra').text(res.counts.extra);
                    $('#ic-notfound').text(res.counts.not_found);

                    var expected = parseInt($('#ic-expected').text()) || 1;
                    var pct = Math.min(100, Math.round((res.counts.matched / expected) * 100));
                    $('#ic-progress').css('width', pct + '%');
                }

                // Flash
                var alertType = res.result_type === 'matched' ? 'success' : (res.error_type === 'duplicate' ? 'secondary' : 'danger');
                showFlash(alertType, res.msg);
            },
            error: function(){
                showFlash('danger', '<?= __('Lỗi kết nối') ?>');
            },
            complete: function(){
                isSubmitting = false;
                scanInput.prop('readonly', false).val('').focus();
            }
        });
    });

    // Complete check
    $('#btn-complete-check').on('click', function(){
        Swal.fire({
            title: '<?= __('Hoàn thành kiểm kê?') ?>',
            text: '<?= __('Hệ thống sẽ tính toán kiện thiếu và gửi báo cáo.') ?>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<?= __('Hoàn thành') ?>',
            cancelButtonText: '<?= __('Tiếp tục quét') ?>',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                var data = { request_name: 'complete_check', check_id: checkId };
                data[csrfName] = csrfToken;
                return $.post(ajaxUrl, data, null, 'json');
            }
        }).then((result) => {
            if (result.isConfirmed) {
                var res = result.value;
                if (res.csrf_token) csrfToken = res.csrf_token;
                if (res.status === 'success') {
                    // Show results
                    $('#section-active').hide();
                    var c = res.counts;
                    $('#res-expected').text(c.expected);
                    $('#res-scanned').text(c.scanned);
                    $('#res-matched').text(c.matched);
                    $('#res-missing').text(c.missing);
                    $('#res-extra').text(c.extra);

                    if (res.missing_list && res.missing_list.length > 0) {
                        var html = '';
                        res.missing_list.forEach(function(m){
                            html += '<tr><td>' + escHtml(m.package_code) + '</td><td>' + escHtml(m.tracking) + '</td></tr>';
                        });
                        $('#missing-list-tbody').html(html);
                        $('#missing-list-container').show();
                    }

                    $('#section-results').show();
                }
            }
        });
    });

    function showFlash(type, msg) {
        var el = $('#ic-last-result');
        el.stop(true).hide().html('<div class="alert alert-' + type + ' mb-0 py-1 small">' + msg + '</div>').fadeIn(100);
        setTimeout(function(){ el.fadeOut(500); }, 2500);
    }

    function escHtml(str) {
        if (!str) return '';
        return $('<div>').text(str).html();
    }

    // Keep focus
    $(document).on('click', function(e){
        if (!$(e.target).closest('button, .swal2-container').length && $('#section-active').is(':visible')) {
            scanInput.focus();
        }
    });
});
</script>
