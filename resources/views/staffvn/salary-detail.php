<?php
require_once(__DIR__.'/../../../models/is_staffvn.php');
if ($getUser['role'] !== 'finance_vn') { redirect(base_url('staffvn/home')); }
require_once(__DIR__.'/../../../libs/csrf.php');

$salaryId = intval(input_get('id'));
if (!$salaryId) { header('Location: ' . base_url('staffvn/salary-list')); exit; }

$salary = $ToryHub->get_row_safe(
    "SELECT s.*, u.fullname, u.username, u.role, u.phone
     FROM `salaries` s
     JOIN `users` u ON s.user_id = u.id
     WHERE s.id = ?",
    [$salaryId]
);

if (!$salary) { header('Location: ' . base_url('staffvn/salary-list')); exit; }

$isCN = $salary['role'] === 'staffcn';
$cur = $salary['currency'];
$curSymbol = $cur === 'CNY' ? '¥' : 'đ';
$formatFn = $cur === 'CNY' ? 'format_cny' : 'format_vnd';
$isPaid = $salary['status'] === 'paid';

$page_title = __('Chi tiết lương') . ' — ' . htmlspecialchars($salary['fullname'] ?: $salary['username']);

$csrf = new Csrf();
require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= $page_title ?></h4>
                    <a href="<?= base_url('staffvn/salary-list?month=' . $salary['month'] . '&year=' . $salary['year']) ?>" class="btn btn-light">
                        <i class="ri-arrow-left-line me-1"></i><?= __('Quay lại') ?>
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left: Form -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Thông tin lương') ?> — <?= __('Tháng') ?> <?= $salary['month'] ?>/<?= $salary['year'] ?></h5>
                    </div>
                    <div class="card-body">
                        <div id="alert-box"></div>
                        <form id="form-salary">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Lương cơ bản') ?> <span class="text-muted">(<?= $cur ?>)</span></label>
                                        <input type="number" class="form-control salary-input" name="base_salary" id="base_salary" value="<?= $salary['base_salary'] ?>" step="0.01" min="0" <?= $isPaid ? 'disabled' : '' ?>>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Phụ cấp') ?> <span class="text-muted">(<?= $cur ?>)</span></label>
                                        <input type="number" class="form-control salary-input" name="allowance" id="allowance" value="<?= $salary['allowance'] ?>" step="0.01" min="0" <?= $isPaid ? 'disabled' : '' ?>>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Thưởng') ?> <span class="text-muted">(<?= $cur ?>)</span></label>
                                        <input type="number" class="form-control salary-input" name="bonus" id="bonus" value="<?= $salary['bonus'] ?>" step="0.01" min="0" <?= $isPaid ? 'disabled' : '' ?>>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Khấu trừ') ?> <span class="text-muted">(<?= $cur ?>)</span></label>
                                        <input type="number" class="form-control salary-input" name="deduction" id="deduction" value="<?= $salary['deduction'] ?>" step="0.01" min="0" <?= $isPaid ? 'disabled' : '' ?>>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Số ngày công') ?></label>
                                        <input type="number" class="form-control" name="work_days" id="work_days" value="<?= $salary['work_days'] ?? '' ?>" min="0" max="31" <?= $isPaid ? 'disabled' : '' ?>>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Thực nhận') ?> <span class="text-muted">(<?= $cur ?>)</span></label>
                                        <input type="text" class="form-control fw-bold text-success bg-light" id="net_salary" value="<?= $formatFn($salary['net_salary']) ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Ghi chú') ?></label>
                                        <textarea class="form-control" name="note" id="note" rows="3" <?= $isPaid ? 'disabled' : '' ?>><?= htmlspecialchars($salary['note'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <?php if (!$isPaid): ?>
                            <div class="d-flex gap-2 mt-2">
                                <button type="button" class="btn btn-primary" id="btn-save">
                                    <i class="ri-save-line me-1"></i><?= __('Lưu') ?>
                                </button>
                                <?php if ($salary['status'] === 'draft'): ?>
                                <button type="button" class="btn btn-info" id="btn-confirm">
                                    <i class="ri-check-line me-1"></i><?= __('Xác nhận') ?>
                                </button>
                                <?php endif; ?>
                                <?php if ($salary['status'] === 'draft' || $salary['status'] === 'confirmed'): ?>
                                <button type="button" class="btn btn-success" id="btn-paid">
                                    <i class="ri-money-dollar-circle-line me-1"></i><?= __('Đánh dấu đã trả') ?>
                                </button>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-success mb-0">
                                <i class="ri-checkbox-circle-line me-1"></i>
                                <?= __('Đã thanh toán ngày') ?>: <strong><?= $salary['paid_date'] ?></strong>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right: Info -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Thông tin nhân viên') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-borderless mb-0">
                                <tbody>
                                    <tr>
                                        <td class="text-muted"><?= __('Họ tên') ?></td>
                                        <td class="fw-bold"><?= htmlspecialchars($salary['fullname'] ?: $salary['username']) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted"><?= __('Username') ?></td>
                                        <td><?= htmlspecialchars($salary['username']) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted"><?= __('Kho') ?></td>
                                        <td>
                                            <?php if ($isCN): ?>
                                            <span class="badge bg-danger-subtle text-danger"><?= __('Kho Trung Quốc') ?></span>
                                            <?php else: ?>
                                            <span class="badge bg-success-subtle text-success"><?= __('Kho Việt Nam') ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted"><?= __('SĐT') ?></td>
                                        <td><?= htmlspecialchars($salary['phone'] ?: '-') ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted"><?= __('Tiền tệ') ?></td>
                                        <td><strong><?= $cur ?></strong> (<?= $curSymbol ?>)</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted"><?= __('Tháng') ?></td>
                                        <td><strong><?= $salary['month'] ?>/<?= $salary['year'] ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted"><?= __('Trạng thái') ?></td>
                                        <td>
                                            <?php
                                            $statusBadge = ['draft' => 'warning', 'confirmed' => 'info', 'paid' => 'success'];
                                            $statusLabel = ['draft' => __('Nháp'), 'confirmed' => __('Đã xác nhận'), 'paid' => __('Đã trả')];
                                            ?>
                                            <span class="badge bg-<?= $statusBadge[$salary['status']] ?? 'secondary' ?>"><?= $statusLabel[$salary['status']] ?? $salary['status'] ?></span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Summary -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Tổng kết') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted"><?= __('Lương cơ bản') ?></span>
                            <span id="sum-base"><?= $formatFn($salary['base_salary']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted"><?= __('Phụ cấp') ?></span>
                            <span class="text-success" id="sum-allowance">+ <?= $formatFn($salary['allowance']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted"><?= __('Thưởng') ?></span>
                            <span class="text-success" id="sum-bonus">+ <?= $formatFn($salary['bonus']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted"><?= __('Khấu trừ') ?></span>
                            <span class="text-danger" id="sum-deduction">- <?= $formatFn($salary['deduction']) ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <strong><?= __('Thực nhận') ?></strong>
                            <strong class="text-primary fs-16" id="sum-net"><?= $formatFn($salary['net_salary']) ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
$(function(){
    var csrfToken = '<?= $csrf->get_token_value() ?>';
    var ajaxUrl = '<?= base_url('ajaxs/staffvn/salaries.php') ?>';
    var salaryId = <?= $salaryId ?>;
    var cur = '<?= $cur ?>';

    function formatMoney(val) {
        var n = parseFloat(val) || 0;
        var parts = n.toFixed(cur === 'CNY' ? 2 : 0).split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        if (cur === 'CNY') {
            return '¥' + (parts[1] ? parts.join(',') : parts[0]);
        } else {
            return parts[0] + ' đ';
        }
    }

    function calcNet() {
        var base = parseFloat($('#base_salary').val()) || 0;
        var allowance = parseFloat($('#allowance').val()) || 0;
        var bonus = parseFloat($('#bonus').val()) || 0;
        var deduction = parseFloat($('#deduction').val()) || 0;
        var net = base + allowance + bonus - deduction;

        $('#net_salary').val(formatMoney(net));
        $('#sum-base').text(formatMoney(base));
        $('#sum-allowance').text('+ ' + formatMoney(allowance));
        $('#sum-bonus').text('+ ' + formatMoney(bonus));
        $('#sum-deduction').text('- ' + formatMoney(deduction));
        $('#sum-net').text(formatMoney(net));
    }

    $('.salary-input').on('input change', calcNet);

    function getFormData() {
        return {
            request_name: 'update',
            id: salaryId,
            base_salary: $('#base_salary').val(),
            allowance: $('#allowance').val(),
            bonus: $('#bonus').val(),
            deduction: $('#deduction').val(),
            work_days: $('#work_days').val(),
            note: $('#note').val(),
            csrf_token: csrfToken
        };
    }

    // Lưu
    $('#btn-save').on('click', function(){
        var btn = $(this);
        btn.prop('disabled', true);
        $.post(ajaxUrl, getFormData(), function(res){
            btn.prop('disabled', false);
            if (res.status === 'success') {
                Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false});
            } else {
                Swal.fire({icon: 'error', title: res.msg});
            }
        }, 'json').fail(function(){
            btn.prop('disabled', false);
            Swal.fire({icon: 'error', title: '<?= __('Lỗi kết nối') ?>'});
        });
    });

    // Xác nhận
    $('#btn-confirm').on('click', function(){
        // Lưu trước, rồi confirm
        $.post(ajaxUrl, getFormData(), function(res){
            if (res.status === 'success') {
                Swal.fire({
                    title: '<?= __('Xác nhận lương này?') ?>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: '<?= __('Xác nhận') ?>',
                    cancelButtonText: '<?= __('Hủy') ?>'
                }).then(function(result){
                    if (result.isConfirmed) {
                        $.post(ajaxUrl, { request_name: 'confirm', id: salaryId, csrf_token: csrfToken }, function(res2){
                            if (res2.status === 'success') location.reload();
                            else Swal.fire({icon: 'error', title: res2.msg});
                        }, 'json');
                    }
                });
            } else {
                Swal.fire({icon: 'error', title: res.msg});
            }
        }, 'json');
    });

    // Đánh dấu đã trả
    $('#btn-paid').on('click', function(){
        // Lưu trước, rồi mark paid
        $.post(ajaxUrl, getFormData(), function(res){
            if (res.status === 'success') {
                Swal.fire({
                    title: '<?= __('Đánh dấu đã trả lương?') ?>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: '<?= __('Đã trả') ?>',
                    cancelButtonText: '<?= __('Hủy') ?>'
                }).then(function(result){
                    if (result.isConfirmed) {
                        $.post(ajaxUrl, { request_name: 'mark_paid', id: salaryId, csrf_token: csrfToken }, function(res2){
                            if (res2.status === 'success') location.reload();
                            else Swal.fire({icon: 'error', title: res2.msg});
                        }, 'json');
                    }
                });
            } else {
                Swal.fire({icon: 'error', title: res.msg});
            }
        }, 'json');
    });
});
</script>
