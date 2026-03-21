<?php
require_once(__DIR__.'/../../../models/is_staffvn.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Nhập kho tổng hợp');

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>

        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Nhập kho - Kho Việt Nam') ?></h4>
                    <div class="page-title-right">
                        <span class="badge bg-success-subtle text-success fs-13 px-2 py-1">
                            <i class="ri-calendar-line me-1"></i><?= date('d/m/Y H:i') ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI Cards (Sticky) -->
        <div id="stats-bar" style="position: sticky; top: 70px; z-index: 100;">
            <div class="row">
                <div class="col-xl-3 col-md-6">
                    <div class="card card-animate mb-2">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <p class="text-uppercase fw-medium text-muted fs-12 mb-1"><?= __('Tổng quét') ?></p>
                                    <h4 class="fs-22 fw-semibold mb-0"><span id="stat-total">0</span></h4>
                                </div>
                                <div class="avatar-sm flex-shrink-0">
                                    <span class="avatar-title bg-primary-subtle rounded fs-3">
                                        <i class="ri-barcode-line text-primary"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card card-animate mb-2">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <p class="text-uppercase fw-medium text-muted fs-12 mb-1"><?= __('Thành công') ?></p>
                                    <h4 class="fs-22 fw-semibold mb-0 text-success"><span id="stat-success">0</span></h4>
                                </div>
                                <div class="avatar-sm flex-shrink-0">
                                    <span class="avatar-title bg-success-subtle rounded fs-3">
                                        <i class="ri-checkbox-circle-line text-success"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card card-animate mb-2">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <p class="text-uppercase fw-medium text-muted fs-12 mb-1"><?= __('Lỗi') ?></p>
                                    <h4 class="fs-22 fw-semibold mb-0 text-danger"><span id="stat-error">0</span></h4>
                                </div>
                                <div class="avatar-sm flex-shrink-0">
                                    <span class="avatar-title bg-danger-subtle rounded fs-3">
                                        <i class="ri-error-warning-line text-danger"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card card-animate mb-2">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <p class="text-uppercase fw-medium text-muted fs-12 mb-1"><?= __('Trùng') ?> / <?= __('Bao') ?></p>
                                    <h4 class="fs-22 fw-semibold mb-0">
                                        <span class="text-warning" id="stat-duplicate">0</span>
                                        <span class="text-muted fs-14 mx-1">/</span>
                                        <span class="text-info" id="stat-bags">0</span>
                                    </h4>
                                </div>
                                <div class="avatar-sm flex-shrink-0">
                                    <span class="avatar-title bg-warning-subtle rounded fs-3">
                                        <i class="ri-inbox-unarchive-line text-warning"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mb-2">
                <div class="col">
                    <div class="d-flex align-items-center gap-3 px-1">
                        <div class="flex-grow-1">
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-success" id="progress-bar" role="progressbar" style="width: 0%"></div>
                            </div>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="sound-toggle" checked>
                            <label class="form-check-label text-muted fs-12" for="sound-toggle">
                                <i class="ri-volume-up-line"></i>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scan Input -->
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card border-success">
                    <div class="card-body p-4">
                        <form id="form-scan" autocomplete="off">
                            <input type="hidden" name="<?= $csrf->get_token_name() ?>" id="csrf-token" value="<?= $csrf->get_token_value() ?>">
                            <input type="hidden" name="request_name" id="request-name" value="scan_barcode">
                            <input type="hidden" name="target_status" id="target-status" value="vn_warehouse">
                            <input type="hidden" name="bag_id" id="bag-id" value="">

                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-success text-white" id="scan-icon">
                                    <i class="ri-barcode-line fs-20"></i>
                                </span>
                                <input type="text" class="form-control" id="scan-input" name="barcode"
                                    placeholder="<?= __('Quét mã kiện hàng (PKG/tracking) hoặc mã bao (BAO-xxx)...') ?>"
                                    autofocus required
                                    style="height: 60px; font-size: 20px; letter-spacing: 1px;">
                                <button type="submit" class="btn btn-success px-4" id="btn-scan">
                                    <i class="ri-qr-scan-2-line fs-20"></i>
                                </button>
                            </div>
                            <div class="text-muted mt-2 fs-12" id="scan-hint">
                                <i class="ri-information-line me-1"></i>
                                <?= __('Tìm theo: Mã kiện hàng (PKG), Tracking QT, Tracking VN, Mã đơn hàng, Mã bao (BAO-xxx).') ?>
                            </div>
                        </form>

                        <!-- Last scan result flash -->
                        <div id="last-result" class="mt-3" style="display:none;"></div>

                    </div>
                </div>
            </div>
        </div>

        <!-- Bag Panel (hidden by default) -->
        <div class="row" id="bag-panel" style="display:none;">
            <div class="col-12">
                <div class="card border-info">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-xs flex-shrink-0">
                                <span class="avatar-title bg-info-subtle rounded">
                                    <i class="ri-inbox-unarchive-line text-info"></i>
                                </span>
                            </div>
                            <div>
                                <h5 class="card-title mb-0">
                                    <span id="bag-title"></span>
                                    <span class="badge bg-info-subtle text-info fs-12 px-2 py-1 ms-1" id="bag-progress-badge"></span>
                                </h5>
                                <p class="text-muted fs-12 mb-0" id="bag-info-line"></p>
                            </div>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary" id="btn-close-bag">
                            <i class="ri-close-line me-1"></i><?= __('Đóng bao') ?>
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="40">#</th>
                                        <th><?= __('Mã kiện') ?></th>
                                        <th><?= __('Tracking QT') ?></th>
                                        <th><?= __('Sản phẩm') ?></th>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th width="70"><?= __('Kg') ?></th>
                                        <th width="100"><?= __('Trạng thái') ?></th>
                                        <th width="50" class="text-center"><?= __('OK') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="bag-pkg-tbody"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex align-items-center gap-3">
                            <div class="flex-grow-1">
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-info" id="bag-progress-bar" role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>
                            <span class="text-muted fs-12" id="bag-progress-text"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Wholesale Modal -->
        <div class="modal fade" id="wholesaleModal" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-warning-subtle">
                        <h5 class="modal-title">
                            <i class="ri-shopping-bag-3-line me-1"></i><?= __('Đơn hàng lô') ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted"><?= __('Mã đơn') ?></span>
                                <strong id="ws-order-code"></strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted"><?= __('Khách hàng') ?></span>
                                <span id="ws-customer"></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted"><?= __('Trạng thái') ?></span>
                                <span id="ws-status"></span>
                            </div>
                        </div>
                        <div class="row text-center mb-3">
                            <div class="col-4">
                                <div class="p-2 bg-light rounded">
                                    <div class="fs-20 fw-semibold" id="ws-pkg-total">0</div>
                                    <small class="text-muted"><?= __('Tổng kiện') ?></small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 bg-light rounded">
                                    <div class="fs-20 fw-semibold text-primary" id="ws-pkg-shipped">0</div>
                                    <small class="text-muted"><?= __('Đã gửi') ?></small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 bg-light rounded">
                                    <div class="fs-20 fw-semibold text-success" id="ws-pkg-received">0</div>
                                    <small class="text-muted"><?= __('Đã nhận') ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium"><?= __('Số kiện nhận được') ?> <span class="text-danger">*</span></label>
                            <input type="number" class="form-control form-control-lg text-center" id="ws-received-input"
                                min="0" step="1" required autofocus>
                            <div class="form-text" id="ws-hint"></div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label"><?= __('Ghi chú') ?> <small class="text-muted">(<?= __('không bắt buộc') ?>)</small></label>
                            <input type="text" class="form-control" id="ws-note" placeholder="<?= __('VD: thiếu 2 kiện lớn...') ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= __('Hủy') ?></button>
                        <button type="button" class="btn btn-success" id="btn-ws-confirm">
                            <i class="ri-checkbox-circle-line me-1"></i><?= __('Xác nhận nhập kho') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Retail Weight Modal -->
        <div class="modal fade" id="retailModal" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-info-subtle">
                        <h5 class="modal-title">
                            <i class="ri-scales-3-line me-1"></i><?= __('Nhập cân nặng - Hàng lẻ') ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted"><?= __('Mã vận đơn') ?></span>
                                <strong id="rt-order-code"></strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted"><?= __('Khách hàng') ?></span>
                                <span id="rt-customer"></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted"><?= __('Sản phẩm') ?></span>
                                <span id="rt-product"></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted"><?= __('Trạng thái') ?></span>
                                <span id="rt-status"></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2" id="rt-bag-row" style="display:none !important;">
                                <span class="text-muted"><?= __('Mã bao') ?></span>
                                <a href="#" id="rt-bag-code" target="_blank" class="fw-semibold text-info"></a>
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-medium"><?= __('Cân nặng (kg)') ?> <span class="text-danger">*</span></label>
                            <input type="number" class="form-control form-control-lg text-center" id="rt-weight-input"
                                min="0.01" step="0.1" placeholder="0.00" required autofocus>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= __('Hủy') ?></button>
                        <button type="button" class="btn btn-success" id="btn-rt-confirm">
                            <i class="ri-checkbox-circle-line me-1"></i><?= __('Xác nhận nhập kho') ?>
                        </button>
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
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="scan-log-table">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50"><?= __('STT') ?></th>
                                        <th><?= __('Mã quét') ?></th>
                                        <th width="70"><?= __('Loại') ?></th>
                                        <th><?= __('Sản phẩm') ?></th>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th width="120"><?= __('Kết quả') ?></th>
                                        <th><?= __('Chi tiết') ?></th>
                                        <th width="100"><?= __('Thời gian') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="scan-log-tbody">
                                    <tr id="empty-row">
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <i class="ri-scan-2-line fs-24 d-block mb-2"></i>
                                            <?= __('Chưa có lần quét nào. Bắt đầu quét mã ở trên.') ?>
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
// Map raw status -> tiếng Việt cho log description
$_statusVi = [
    'pending' => 'Chờ xử lý', 'cn_warehouse' => 'Kho TQ', 'packed' => 'Đã đóng gói',
    'loading' => 'Xếp xe', 'shipping' => 'Vận chuyển', 'vn_warehouse' => 'Kho VN',
    'delivered' => 'Đã giao', 'returned' => 'Hoàn trả', 'damaged' => 'Hư hỏng',
    'arrived' => 'Đã đến', 'completed' => 'Hoàn tất', 'sealed' => 'Đã niêm phong',
    'purchased' => 'Đã mua', 'cn_shipped' => 'Shop đã gửi', 'cancelled' => 'Đã hủy',
    'preparing' => 'Chuẩn bị', 'in_transit' => 'Vận chuyển', 'open' => 'Đang mở',
];
function _humanize_log_desc($desc, $map) {
    return preg_replace_callback('/(\w+)\s*->\s*(\w+)/', function($m) use ($map) {
        $from = $map[$m[1]] ?? $m[1];
        $to   = $map[$m[2]] ?? $m[2];
        return $from . ' → ' . $to;
    }, $desc);
}

// Lịch sử quét từ DB (50 bản ghi gần nhất)
$scanHistory = $ToryHub->get_list_safe(
    "SELECT l.*, COALESCE(NULLIF(u.fullname,''), u.username) as user_name
     FROM `logs` l
     LEFT JOIN `users` u ON l.user_id = u.id
     WHERE l.action IN ('WAREHOUSE_RECEIVE_PKG', 'WAREHOUSE_RECEIVE_ORDER', 'WAREHOUSE_RECEIVE_BAG_PKG', 'BAG_ARRIVED', 'SCAN_PKG_DELIVERED', 'SCAN_DELIVERED', 'WHOLESALE_CONFIRM_FULL', 'WHOLESALE_CONFIRM_PARTIAL')
     ORDER BY l.create_date DESC
     LIMIT 50", []
);
?>
        <!-- Lịch sử quét -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0">
                            <i class="ri-history-line me-1"></i><?= __('Lịch sử quét gần đây') ?>
                        </h5>
                        <span class="badge bg-secondary-subtle text-secondary fs-12 px-2 py-1"><?= count($scanHistory) ?> <?= __('bản ghi') ?></span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-hover table-sm mb-0">
                                <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                                    <tr>
                                        <th width="50">#</th>
                                        <th><?= __('Nhân viên') ?></th>
                                        <th><?= __('Hành động') ?></th>
                                        <th><?= __('Chi tiết') ?></th>
                                        <th width="140"><?= __('Thời gian') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($scanHistory)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4"><?= __('Chưa có lịch sử quét') ?></td></tr>
                                    <?php else: $hIdx = 0; foreach ($scanHistory as $log): $hIdx++;
                                        $actionLabels = [
                                            'WAREHOUSE_RECEIVE_PKG' => ['label' => __('Nhập kho kiện'), 'color' => 'success'],
                                            'WAREHOUSE_RECEIVE_ORDER' => ['label' => __('Nhập kho đơn'), 'color' => 'success'],
                                            'WAREHOUSE_RECEIVE_BAG_PKG' => ['label' => __('Nhập kho (bao)'), 'color' => 'info'],
                                            'BAG_ARRIVED' => ['label' => __('Bao đã đến'), 'color' => 'info'],
                                            'SCAN_PKG_DELIVERED' => ['label' => __('Giao kiện'), 'color' => 'primary'],
                                            'SCAN_DELIVERED' => ['label' => __('Giao đơn'), 'color' => 'primary'],
                                            'WHOLESALE_CONFIRM_FULL' => ['label' => __('Lô - đủ kiện'), 'color' => 'success'],
                                            'WHOLESALE_CONFIRM_PARTIAL' => ['label' => __('Lô - thiếu kiện'), 'color' => 'warning'],
                                        ];
                                        $act = $actionLabels[$log['action']] ?? ['label' => $log['action'], 'color' => 'secondary'];
                                    ?>
                                    <tr>
                                        <td class="text-muted"><?= $hIdx ?></td>
                                        <td><?= htmlspecialchars($log['user_name'] ?: '-') ?></td>
                                        <td><span class="badge bg-<?= $act['color'] ?>-subtle text-<?= $act['color'] ?> fs-12 px-2 py-1"><?= $act['label'] ?></span></td>
                                        <td class="fs-12"><?= htmlspecialchars(_humanize_log_desc($log['description'], $_statusVi)) ?></td>
                                        <td class="text-muted fs-12"><?= date('d/m/Y H:i:s', strtotime($log['create_date'])) ?></td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<?php
$_ajaxUrl = base_url('ajaxs/staffvn/warehouse-receive.php');
$_csrfName = $csrf->get_token_name();

$body['footer'] = <<<'SCRIPT'
<script>
$(document).ready(function(){
    // ===== Web Audio API =====
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
    function soundError() { playBeep(200, 0.4, 'square'); }
    function soundDuplicate() {
        playBeep(440, 0.1, 'triangle');
        setTimeout(function(){ playBeep(440, 0.1, 'triangle'); }, 200);
        setTimeout(function(){ playBeep(440, 0.1, 'triangle'); }, 400);
    }
    function soundBag() {
        playBeep(660, 0.1, 'sine');
        setTimeout(function(){ playBeep(880, 0.1, 'sine'); }, 120);
        setTimeout(function(){ playBeep(1100, 0.15, 'sine'); }, 240);
    }

    // ===== State =====
    var stats = { total: 0, success: 0, error: 0, duplicate: 0, bags: 0 };
    var scanCounter = 0;
    var scannedCodes = {};
    var MODE = 'normal'; // 'normal' or 'bag'
    var currentBag = null;
    var bagScannedPkgs = {};
    var isSubmitting = false;
    var originalPlaceholder = '{$_placeholderNormal}';
    var targetStatus = 'vn_warehouse';

    function updateStats() {
        $('#stat-total').text(stats.total);
        $('#stat-success').text(stats.success);
        $('#stat-error').text(stats.error);
        $('#stat-duplicate').text(stats.duplicate);
        $('#stat-bags').text(stats.bags);
        var pct = stats.total > 0 ? Math.round((stats.success / stats.total) * 100) : 0;
        $('#progress-bar').css('width', pct + '%');
    }

    // ===== Auto-submit =====
    var scanInput = $('#scan-input');
    var autoSubmitTimer = null;

    scanInput.on('input', function(){
        clearTimeout(autoSubmitTimer);
        var val = $(this).val().trim();
        if (val.length >= 4 && !isSubmitting) {
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

        // Reject date-like strings (dd/mm/yyyy, mm/dd/yyyy, yyyy/mm/dd, etc.)
        if (/^\d{1,4}[\/\-\.]\d{1,2}[\/\-\.]\d{1,4}(\s+\d{1,2}:\d{2}(:\d{2})?)?$/.test(barcode)) {
            scanInput.val('').focus();
            return;
        }

        isSubmitting = true;
        scanInput.prop('readonly', true);

        if (MODE === 'bag' && currentBag) {
            // Check if scanning a new bag code while in bag mode
            if (barcode.toUpperCase().indexOf('BAO-') === 0) {
                // Close current bag and scan as new bag
                closeBagPanel(true);
                scanBarcode(barcode);
            } else {
                scanBagPackage(barcode);
            }
        } else {
            // Client-side duplicate check
            var dupKey = barcode + '_' + targetStatus;
            if (scannedCodes[dupKey]) {
                stats.total++;
                stats.duplicate++;
                updateStats();
                soundDuplicate();
                addLogRow(barcode, 'duplicate', '{$_dupMsg}', null, '{$_lblPkg}');
                showFlash('warning', '<i class="ri-repeat-line me-1"></i> <strong>' + barcode + '</strong> - {$_dupMsg}');
                resetInput();
                return;
            }
            scanBarcode(barcode);
        }
    });

    // ===== SCAN BARCODE (normal mode) =====
    function scanBarcode(barcode) {
        $.ajax({
            url: '{$_ajaxUrl}',
            type: 'POST',
            data: {
                '{$_csrfName}': $('#csrf-token').val(),
                request_name: 'scan_barcode',
                barcode: barcode,
                target_status: targetStatus
            },
            dataType: 'json',
            timeout: 10000,
            success: function(res){
                if (res.csrf_token) $('#csrf-token').val(res.csrf_token);

                if (res.status === 'success' && (res.type === 'retail' || res.type === 'retail_pkg')) {
                    // Retail order/package → show modal for weight input
                    soundBag();
                    showRetailModal(res);
                    resetInput();
                } else if (res.status === 'success' && res.type === 'wholesale') {
                    // Wholesale order → show modal for received count
                    soundBag();
                    showWholesaleModal(res);
                    resetInput();
                } else if (res.status === 'success' && res.type === 'bag') {
                    // Bag scanned
                    stats.bags++;
                    updateStats();
                    soundBag();
                    addLogRow(barcode, 'success', res.msg, null, '{$_lblBag}');
                    showFlash('info', '<i class="ri-inbox-unarchive-line me-1"></i> <strong>' + barcode + '</strong> - ' + res.msg);
                    renderBagPanel(res.bag, res.packages, res.received_count);
                    resetInput();
                } else if (res.status === 'success' && res.type === 'package') {
                    // Package scanned
                    stats.total++;
                    stats.success++;
                    updateStats();
                    soundSuccess();
                    scannedCodes[barcode + '_' + targetStatus] = true;
                    var productName = res.order ? (res.order.product_name || '-') : '-';
                    var customerName = res.order ? (res.order.customer_name || '-') : '-';
                    addLogRow(barcode, 'success', res.msg, res.order, '{$_lblPkg}');
                    showFlash('success', '<i class="ri-checkbox-circle-line me-1"></i> <strong>' + barcode + '</strong> - ' + res.msg);

                    resetInput();
                } else if (res.error_type === 'duplicate') {
                    stats.total++;
                    stats.duplicate++;
                    updateStats();
                    soundDuplicate();
                    scannedCodes[barcode + '_' + targetStatus] = true;
                    addLogRow(barcode, 'duplicate', res.msg, res.order || null, '{$_lblPkg}');
                    showFlash('warning', '<i class="ri-repeat-line me-1"></i> <strong>' + barcode + '</strong> - ' + res.msg);
                    resetInput();
                } else {
                    stats.total++;
                    stats.error++;
                    updateStats();
                    soundError();
                    addLogRow(barcode, 'error', res.msg, res.order || null, '{$_lblPkg}');
                    showFlash('danger', '<i class="ri-error-warning-line me-1"></i> <strong>' + barcode + '</strong> - ' + res.msg);
                    resetInput();
                }
            },
            error: function(){
                stats.total++;
                stats.error++;
                updateStats();
                soundError();
                addLogRow(barcode, 'error', '{$_connectionError}', null, '-');
                showFlash('danger', '<i class="ri-wifi-off-line me-1"></i> <strong>' + barcode + '</strong> - {$_connectionError}');
                resetInput();
            }
        });
    }

    // ===== SCAN BAG PACKAGE (bag mode) =====
    function scanBagPackage(barcode) {
        $.ajax({
            url: '{$_ajaxUrl}',
            type: 'POST',
            data: {
                '{$_csrfName}': $('#csrf-token').val(),
                request_name: 'scan_bag_package',
                barcode: barcode,
                bag_id: currentBag.id
            },
            dataType: 'json',
            timeout: 10000,
            success: function(res){
                if (res.csrf_token) $('#csrf-token').val(res.csrf_token);

                if (res.status === 'success') {
                    stats.total++;
                    stats.success++;
                    updateStats();
                    soundSuccess();
                    scannedCodes[barcode + '_' + targetStatus] = true;

                    // Mark package row as received
                    markPackageReceived(res.package_id);
                    updateBagProgress(res.total - res.remaining, res.total);

                    addLogRow(barcode, 'success', res.msg, res.order, '{$_lblPkg}');
                    showFlash('success', '<i class="ri-checkbox-circle-line me-1"></i> <strong>' + barcode + '</strong> - ' + res.msg);

                    // Check if all done
                    if (res.all_done) {
                        setTimeout(function(){
                            Swal.fire({
                                icon: 'success',
                                title: '{$_allDoneTitle}',
                                text: currentBag.bag_code + ' - {$_allDoneText}',
                                timer: 2500,
                                showConfirmButton: false
                            });
                            closeBagPanel(false);
                        }, 500);
                    }

                    resetInput();
                } else if (res.error_type === 'not_in_bag') {
                    // Not in this bag - try as normal scan
                    scanBarcode(barcode);
                } else if (res.error_type === 'duplicate') {
                    stats.total++;
                    stats.duplicate++;
                    updateStats();
                    soundDuplicate();
                    // Still mark as received in UI
                    if (res.package_id) markPackageReceived(res.package_id);
                    addLogRow(barcode, 'duplicate', res.msg, res.order || null, '{$_lblPkg}');
                    showFlash('warning', '<i class="ri-repeat-line me-1"></i> <strong>' + barcode + '</strong> - ' + res.msg);
                    resetInput();
                } else {
                    stats.total++;
                    stats.error++;
                    updateStats();
                    soundError();
                    addLogRow(barcode, 'error', res.msg, res.order || null, '{$_lblPkg}');
                    showFlash('danger', '<i class="ri-error-warning-line me-1"></i> <strong>' + barcode + '</strong> - ' + res.msg);
                    resetInput();
                }
            },
            error: function(){
                stats.total++;
                stats.error++;
                updateStats();
                soundError();
                addLogRow(barcode, 'error', '{$_connectionError}', null, '-');
                showFlash('danger', '<i class="ri-wifi-off-line me-1"></i> <strong>' + barcode + '</strong> - {$_connectionError}');
                resetInput();
            }
        });
    }

    // ===== BAG PANEL =====
    function renderBagPanel(bag, packages, receivedCount) {
        currentBag = bag;
        bagScannedPkgs = {};

        var totalPkgs = packages.length;
        receivedCount = receivedCount || 0;

        // Render info
        $('#bag-title').text(bag.bag_code);
        var info = bag.total_weight + ' kg';
        if (bag.create_date) info += ' | ' + bag.create_date;
        if (bag.note) info += ' | ' + bag.note;
        $('#bag-info-line').text(info);

        // Render table
        var tbody = $('#bag-pkg-tbody');
        tbody.empty();

        for (var i = 0; i < packages.length; i++) {
            var p = packages[i];
            var isReceived = p.is_received;
            if (isReceived) bagScannedPkgs[p.id] = true;

            var rowClass = isReceived ? 'table-success' : '';
            var checkIcon = isReceived ? '<i class="ri-check-line text-success fs-16"></i>' : '';

            var row = '<tr data-pkg-id="' + p.id + '" class="' + rowClass + '">' +
                '<td>' + (i + 1) + '</td>' +
                '<td><code class="fs-12">' + escHtml(p.package_code) + '</code></td>' +
                '<td class="text-muted fs-12">' + escHtml(p.tracking_intl || '-') + '</td>' +
                '<td>' + escHtml(p.product_names || '-') + '</td>' +
                '<td>' + escHtml(p.customer_names || '-') + '</td>' +
                '<td>' + p.weight_charged + '</td>' +
                '<td>' + p.status_html + '</td>' +
                '<td class="text-center pkg-check">' + checkIcon + '</td>' +
                '</tr>';
            tbody.append(row);
        }

        updateBagProgress(receivedCount, totalPkgs);

        // Show panel, switch mode
        $('#bag-panel').slideDown(200);
        MODE = 'bag';
        $('#bag-id').val(bag.id);
        $('#request-name').val('scan_bag_package');
        $('#scan-icon').removeClass('bg-success').addClass('bg-info');
        scanInput.attr('placeholder', '{$_placeholderBag} ' + bag.bag_code + '...');
    }

    function markPackageReceived(pkgId) {
        bagScannedPkgs[pkgId] = true;
        var row = $('#bag-pkg-tbody tr[data-pkg-id="' + pkgId + '"]');
        if (row.length) {
            row.addClass('table-success');
            row.find('.pkg-check').html('<i class="ri-check-line text-success fs-16"></i>');
            // Update status cell
            row.find('td:eq(6)').html('<span class="badge bg-success"><i class="ri-check-line me-1"></i>{$_lblVnWarehouse}</span>');
        }
    }

    function updateBagProgress(received, total) {
        var pct = total > 0 ? Math.round((received / total) * 100) : 0;
        $('#bag-progress-bar').css('width', pct + '%');
        $('#bag-progress-badge').text(received + '/' + total + ' {$_lblPkgUnit}');
        $('#bag-progress-text').text(pct + '%');
    }

    function closeBagPanel(silent) {
        $('#bag-panel').slideUp(200);
        currentBag = null;
        bagScannedPkgs = {};
        MODE = 'normal';
        $('#bag-id').val('');
        $('#request-name').val('scan_barcode');
        $('#scan-icon').removeClass('bg-info').addClass('bg-success');
        scanInput.attr('placeholder', originalPlaceholder);
        if (!silent) scanInput.focus();
    }

    $('#btn-close-bag').on('click', function(){ closeBagPanel(false); });

    // ===== HELPERS =====
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

    function addLogRow(barcode, resultType, message, order, typeLabel) {
        scanCounter++;
        $('#empty-row').remove();

        var rowClass = '', badge = '';
        if (resultType === 'success') {
            rowClass = 'table-success';
            badge = '<span class="badge bg-success-subtle text-success fs-12 px-2 py-1"><i class="ri-checkbox-circle-line me-1"></i>{$_lblSuccess}</span>';
        } else if (resultType === 'duplicate') {
            rowClass = '';
            badge = '<span class="badge bg-warning-subtle text-warning fs-12 px-2 py-1"><i class="ri-repeat-line me-1"></i>{$_lblDuplicate}</span>';
        } else {
            rowClass = 'table-danger';
            badge = '<span class="badge bg-danger-subtle text-danger fs-12 px-2 py-1"><i class="ri-error-warning-line me-1"></i>{$_lblError}</span>';
        }

        var productName = order ? (order.product_name || '-') : '-';
        var customerName = order ? (order.customer_name || '-') : '-';
        var time = new Date().toLocaleTimeString('vi-VN');

        var row = '<tr class="' + rowClass + '">' +
            '<td class="fw-semibold">' + scanCounter + '</td>' +
            '<td><code class="fs-13">' + escHtml(barcode) + '</code></td>' +
            '<td><span class="badge bg-secondary-subtle text-secondary fs-12 px-2 py-1">' + escHtml(typeLabel) + '</span></td>' +
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

    // ===== WHOLESALE MODAL =====
    var wsModal = null;
    var wsData = null;

    function showWholesaleModal(res) {
        wsData = res;
        $('#ws-order-code').text(res.product_code || res.order_code);
        $('#ws-customer').text(res.order ? res.order.customer_name : '-');
        $('#ws-status').text(res.current_status || '-');
        $('#ws-pkg-total').text(res.pkg_total);
        $('#ws-pkg-shipped').text(res.pkg_shipped);
        $('#ws-pkg-received').text(res.pkg_received);
        var defaultVal = res.pkg_shipped;
        $('#ws-received-input').val(defaultVal).attr('max', res.pkg_shipped);
        $('#ws-hint').text('{$_wsHintPrefix} ' + res.pkg_shipped + ' {$_wsHintSuffix}');
        $('#ws-note').val('');
        if (!wsModal) wsModal = new bootstrap.Modal('#wholesaleModal');
        wsModal.show();
        setTimeout(function(){ $('#ws-received-input').select(); }, 300);
    }

    $('#btn-ws-confirm').on('click', function(){
        if (!wsData) return;
        var receivedCount = parseInt($('#ws-received-input').val());
        if (isNaN(receivedCount) || receivedCount < 0) {
            toastr.warning('{$_wsInvalidCount}');
            $('#ws-received-input').focus();
            return;
        }
        if (receivedCount > wsData.pkg_shipped) {
            toastr.warning('{$_wsOverCount}');
            $('#ws-received-input').focus();
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin me-1"></i>{$_wsProcessing}');
        $.post('{$_ajaxUrl}', {
            '{$_csrfName}': $('#csrf-token').val(),
            request_name: 'confirm_wholesale',
            order_id: wsData.order_id,
            received_count: receivedCount,
            note: $('#ws-note').val().trim()
        }, function(res){
            if (res.csrf_token) $('#csrf-token').val(res.csrf_token);
            if (res.status === 'success') {
                wsModal.hide();
                stats.total++;
                stats.success++;
                updateStats();
                soundSuccess();
                var label = wsData.product_code || wsData.order_code;
                scannedCodes[label + '_' + targetStatus] = true;
                addLogRow(label, 'success', res.msg, wsData.order, '{$_lblWholesale}');
                showFlash('success', '<i class="ri-checkbox-circle-line me-1"></i> <strong>' + escHtml(label) + '</strong> - ' + escHtml(res.msg));
            } else {
                toastr.error(res.msg || '{$_connectionError}');
            }
            $btn.prop('disabled', false).html('<i class="ri-checkbox-circle-line me-1"></i>{$_wsConfirmBtn}');
            scanInput.focus();
        }, 'json').fail(function(){
            toastr.error('{$_connectionError}');
            $btn.prop('disabled', false).html('<i class="ri-checkbox-circle-line me-1"></i>{$_wsConfirmBtn}');
        });
    });

    // Enter key in modal input
    $('#ws-received-input, #ws-note').on('keydown', function(e){
        if (e.key === 'Enter') { e.preventDefault(); $('#btn-ws-confirm').click(); }
    });

    // Refocus scan input when modal closes
    $('#wholesaleModal').on('hidden.bs.modal', function(){
        wsData = null;
        scanInput.focus();
    });

    // ===== RETAIL / BAG PACKAGE WEIGHT MODAL (shared) =====
    var rtModal = null;
    var rtData = null;
    var rtMode = 'retail'; // 'retail' or 'retail_pkg'

    function showRetailModal(res) {
        rtData = res;
        rtMode = res.type === 'retail_pkg' ? 'retail_pkg' : 'retail';
        $('#rt-order-code').text(res.tracking_cn || res.order_code || res.package_code);
        $('#rt-customer').text(res.order ? res.order.customer_name : '-');
        $('#rt-product').text(res.order ? res.order.product_name : '-');
        $('#rt-status').text(res.current_status || '-');
        if (res.bag_code) {
            $('#rt-bag-code').text(res.bag_code).attr('href', '<?= base_url("staffvn/bag-unpack") ?>');
            $('#rt-bag-row').show().css('display', '');
        } else {
            $('#rt-bag-row').hide();
        }
        $('#rt-weight-input').val(res.current_weight > 0 ? res.current_weight : '');
        if (!rtModal) rtModal = new bootstrap.Modal('#retailModal');
        rtModal.show();
        setTimeout(function(){ $('#rt-weight-input').select(); }, 300);
    }

    $('#btn-rt-confirm').on('click', function(){
        if (!rtData) return;
        var weight = parseFloat($('#rt-weight-input').val());
        if (isNaN(weight) || weight <= 0) {
            toastr.warning('{$_rtInvalidWeight}');
            $('#rt-weight-input').focus();
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin me-1"></i>{$_wsProcessing}');

        var postData = {
            '{$_csrfName}': $('#csrf-token').val(),
            weight: weight
        };

        if (rtMode === 'retail_pkg') {
            postData.request_name = 'confirm_retail_pkg';
            postData.package_id = rtData.package_id;
        } else {
            postData.request_name = 'confirm_retail';
            postData.order_id = rtData.order_id;
        }

        $.post('{$_ajaxUrl}', postData, function(res){
            if (res.csrf_token) $('#csrf-token').val(res.csrf_token);
            if (res.status === 'success') {
                rtModal.hide();
                stats.total++;
                stats.success++;
                updateStats();
                soundSuccess();

                var label = rtData.tracking_cn || rtData.order_code || rtData.package_code;
                scannedCodes[label + '_' + targetStatus] = true;
                addLogRow(label, 'success', res.msg, res.order, '{$_lblRetail}');
                showFlash('success', '<i class="ri-checkbox-circle-line me-1"></i> <strong>' + escHtml(label) + '</strong> - ' + escHtml(res.msg));
            } else {
                toastr.error(res.msg || '{$_connectionError}');
            }
            $btn.prop('disabled', false).html('<i class="ri-checkbox-circle-line me-1"></i>{$_wsConfirmBtn}');
            scanInput.focus();
        }, 'json').fail(function(){
            toastr.error('{$_connectionError}');
            $btn.prop('disabled', false).html('<i class="ri-checkbox-circle-line me-1"></i>{$_wsConfirmBtn}');
        });
    });

    // Enter key in retail modal
    $('#rt-weight-input').on('keydown', function(e){
        if (e.key === 'Enter') { e.preventDefault(); $('#btn-rt-confirm').click(); }
    });

    // Refocus scan input when retail modal closes
    $('#retailModal').on('hidden.bs.modal', function(){
        rtData = null;
        rtMode = 'retail';
        scanInput.focus();
    });

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
                stats = { total: 0, success: 0, error: 0, duplicate: 0, bags: 0 };
                scannedCodes = {};
                updateStats();
                $('#scan-log-tbody').html(
                    '<tr id="empty-row"><td colspan="8" class="text-center text-muted py-4">' +
                    '<i class="ri-scan-2-line fs-24 d-block mb-2"></i>{$_emptyMsg}</td></tr>'
                );
                $('#last-result').hide();
                scanInput.focus();
            }
        });
    });

    // ===== Keep focus =====
    $(document).on('click', function(e){
        if (!$(e.target).closest('select, button, input, textarea, .swal2-container, .btn-close, .modal').length) {
            scanInput.focus();
        }
    });
    $(document).on('click', '.swal2-confirm, .swal2-cancel', function(){
        setTimeout(function(){ scanInput.focus(); }, 300);
    });
});
</script>
SCRIPT;

$_placeholderNormal = __('Quét mã kiện hàng (PKG/tracking) hoặc mã bao (BAO-xxx)...');
$_placeholderDelivered = __('Quét mã kiện hàng để xác nhận giao hàng...');
$_placeholderBag = __('Quét mã kiện trong bao');
$_dupMsg = __('Mã này đã được quét trong phiên này');
$_connectionError = __('Lỗi kết nối. Vui lòng thử lại.');
$_lblSuccess = __('Thành công');
$_lblError = __('Lỗi');
$_lblDuplicate = __('Trùng');
$_lblYes = __('Có');
$_lblNo = __('Không');
$_lblBag = __('Bao');
$_lblPkg = __('Kiện');
$_lblPkgUnit = __('kiện');
$_lblVnWarehouse = __('Đã về kho VN');
$_allDoneTitle = __('Hoàn thành!');
$_allDoneText = __('Tất cả kiện trong bao đã được nhập kho');
$_clearConfirmTitle = __('Xóa lịch sử quét?');
$_clearConfirmText = __('Dữ liệu thống kê phiên này sẽ bị reset.');
$_emptyMsg = __('Chưa có lần quét nào. Bắt đầu quét mã ở trên.');
$_lblWholesale = __('Lô');
$_lblRetail = __('Lẻ');
$_rtInvalidWeight = __('Vui lòng nhập cân nặng hợp lệ');
$_wsHintPrefix = __('Tối đa');
$_wsHintSuffix = __('kiện (đã gửi từ TQ)');
$_wsInvalidCount = __('Vui lòng nhập số kiện hợp lệ');
$_wsOverCount = __('Không thể lớn hơn số kiện đã gửi');
$_wsProcessing = __('Đang xử lý');
$_wsConfirmBtn = __('Xác nhận nhập kho');

$body['footer'] = str_replace(
    ['{$_ajaxUrl}', '{$_csrfName}',
     '{$_placeholderNormal}', '{$_placeholderDelivered}', '{$_placeholderBag}',
     '{$_dupMsg}', '{$_connectionError}',
     '{$_lblSuccess}', '{$_lblError}', '{$_lblDuplicate}',
     '{$_lblYes}', '{$_lblNo}',
     '{$_lblBag}', '{$_lblPkg}', '{$_lblPkgUnit}',
     '{$_lblVnWarehouse}',
     '{$_allDoneTitle}', '{$_allDoneText}',
     '{$_clearConfirmTitle}', '{$_clearConfirmText}', '{$_emptyMsg}',
     '{$_lblWholesale}',
     '{$_lblRetail}', '{$_rtInvalidWeight}',
     '{$_wsHintPrefix}', '{$_wsHintSuffix}',
     '{$_wsInvalidCount}', '{$_wsOverCount}',
     '{$_wsProcessing}', '{$_wsConfirmBtn}'],
    [$_ajaxUrl, $_csrfName,
     $_placeholderNormal, $_placeholderDelivered, $_placeholderBag,
     $_dupMsg, $_connectionError,
     $_lblSuccess, $_lblError, $_lblDuplicate,
     $_lblYes, $_lblNo,
     $_lblBag, $_lblPkg, $_lblPkgUnit,
     $_lblVnWarehouse,
     $_allDoneTitle, $_allDoneText,
     $_clearConfirmTitle, $_clearConfirmText, $_emptyMsg,
     $_lblWholesale,
     $_lblRetail, $_rtInvalidWeight,
     $_wsHintPrefix, $_wsHintSuffix,
     $_wsInvalidCount, $_wsOverCount,
     $_wsProcessing, $_wsConfirmBtn],
    $body['footer']
);

require_once(__DIR__.'/footer.php');
?>
