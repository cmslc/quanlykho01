<?php
require_once(__DIR__.'/../../../models/is_staffvn.php');
if ($getUser['role'] !== 'finance_vn') { redirect(base_url('staffvn/home')); }
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Lương Nhân Viên');

// Filters
$filterMonth = input_get('month') ?: date('n');
$filterYear = input_get('year') ?: date('Y');
$filterStatus = input_get('status') ?: '';
$filterStaff = input_get('user_id') ?: '';

$where = "s.month = ? AND s.year = ? AND u.role IN ('staffvn', 'finance_vn')";
$params = [intval($filterMonth), intval($filterYear)];

if ($filterStatus) {
    $where .= " AND s.status = ?";
    $params[] = $filterStatus;
}
if ($filterStaff) {
    $where .= " AND s.user_id = ?";
    $params[] = intval($filterStaff);
}

$salaries = $ToryHub->get_list_safe(
    "SELECT s.*, u.fullname, u.username, u.role, u.phone
     FROM `salaries` s
     JOIN `users` u ON s.user_id = u.id
     WHERE $where
     ORDER BY u.role ASC, u.fullname ASC",
    $params
);

// KPI — tính riêng theo tiền tệ, quy đổi sang cả 2 (dùng stored rate per record)
$exchangeRate = get_exchange_rate();
$vndToCny = $exchangeRate > 0 ? 1 / $exchangeRate : 0;
$totalNetCny = 0; $totalNetVnd = 0;
$totalPaidCny = 0; $totalPaidVnd = 0;
$totalUnpaidCny = 0; $totalUnpaidVnd = 0;
$totalNetAllVnd = 0; $totalPaidAllVnd = 0; $totalUnpaidAllVnd = 0;
$staffCount = count($salaries);
foreach ($salaries as $s) {
    $sRate = floatval($s['exchange_rate'] ?? 0) ?: $exchangeRate;
    $net = floatval($s['net_salary']);
    if ($s['currency'] === 'CNY') {
        $totalNetCny += $net;
        $netVnd = $net * $sRate;
        if ($s['status'] === 'paid') { $totalPaidCny += $net; $totalPaidAllVnd += $netVnd; }
        else { $totalUnpaidCny += $net; $totalUnpaidAllVnd += $netVnd; }
    } else {
        $totalNetVnd += $net;
        $netVnd = $net;
        if ($s['status'] === 'paid') { $totalPaidVnd += $net; $totalPaidAllVnd += $netVnd; }
        else { $totalUnpaidVnd += $net; $totalUnpaidAllVnd += $netVnd; }
    }
    $totalNetAllVnd += $netVnd;
}
$totalNetAllCny = $totalNetCny + $totalNetVnd * $vndToCny;
$totalPaidAllCny = $totalPaidCny + $totalPaidVnd * $vndToCny;
$totalUnpaidAllCny = $totalUnpaidCny + $totalUnpaidVnd * $vndToCny;

// Staff list for filter
$staffList = $ToryHub->get_list_safe(
    "SELECT id, fullname, username, role FROM `users` WHERE `role` IN ('staffvn','finance_vn') AND `active` = 1 ORDER BY `fullname` ASC",
    []
);

$csrf = new Csrf();
require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= $page_title ?> — <?= __('Tháng') ?> <?= $filterMonth ?>/<?= $filterYear ?></h4>
                    <div class="d-flex gap-2">
                        <a href="<?= base_url('ajaxs/staffvn/salary-export.php') ?>?month=<?= $filterMonth ?>&year=<?= $filterYear ?>&status=<?= urlencode($filterStatus) ?>&user_id=<?= urlencode($filterStaff) ?>" class="btn btn-success">
                            <i class="ri-file-excel-2-line"></i> <?= __('Xuất Excel') ?>
                        </a>
                        <button class="btn btn-primary" id="btn-generate">
                            <i class="ri-add-line me-1"></i><?= __('Tạo bảng lương tháng') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-end justify-content-between mt-2">
                            <div>
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Tổng lương tháng') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0"><?= format_vnd($totalNetAllVnd) ?></h4>
                                <small class="text-muted"><?= format_cny($totalNetAllCny) ?></small>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle rounded fs-3"><i class="ri-money-cny-circle-line text-primary"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-end justify-content-between mt-2">
                            <div>
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Đã trả') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-success"><?= format_vnd($totalPaidAllVnd) ?></h4>
                                <small class="text-muted"><?= format_cny($totalPaidAllCny) ?></small>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success-subtle rounded fs-3"><i class="ri-checkbox-circle-line text-success"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-end justify-content-between mt-2">
                            <div>
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Chưa trả') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-danger"><?= format_vnd($totalUnpaidAllVnd) ?></h4>
                                <small class="text-muted"><?= format_cny($totalUnpaidAllCny) ?></small>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-danger-subtle rounded fs-3"><i class="ri-time-line text-danger"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-end justify-content-between mt-2">
                            <div>
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Số nhân viên') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0"><?= $staffCount ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-info-subtle rounded fs-3"><i class="ri-team-line text-info"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="<?= base_url('staffvn/salary-list') ?>" class="row g-3 align-items-end">
                            <div class="col-md-1">
                                <label class="form-label"><?= __('Tháng') ?></label>
                                <select class="form-select" name="month">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $filterMonth == $m ? 'selected' : '' ?>><?= $m ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label"><?= __('Năm') ?></label>
                                <select class="form-select" name="year">
                                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                                    <option value="<?= $y ?>" <?= $filterYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><?= __('Trạng thái') ?></label>
                                <select class="form-select" name="status">
                                    <option value=""><?= __('Tất cả') ?></option>
                                    <option value="draft" <?= $filterStatus == 'draft' ? 'selected' : '' ?>><?= __('Nháp') ?></option>
                                    <option value="paid" <?= $filterStatus == 'paid' ? 'selected' : '' ?>><?= __('Đã trả') ?></option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><?= __('Nhân viên') ?></label>
                                <select class="form-select" name="user_id">
                                    <option value=""><?= __('Tất cả') ?></option>
                                    <?php foreach ($staffList as $st): ?>
                                    <option value="<?= $st['id'] ?>" <?= $filterStaff == $st['id'] ? 'selected' : '' ?>><?= htmlspecialchars($st['fullname'] ?: $st['username']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-auto">
                                <button type="submit" class="btn btn-primary"><i class="ri-search-line me-1"></i><?= __('Lọc') ?></button>
                                <a href="<?= base_url('staffvn/salary-list') ?>" class="btn btn-secondary"><?= __('Reset') ?></a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Salary Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h5 class="card-title mb-0"><?= __('Bảng lương tháng') ?> <?= $filterMonth ?>/<?= $filterYear ?> (<?= $staffCount ?> <?= __('nhân viên') ?>)</h5>
                        <button class="btn btn-sm btn-success" id="btn-bulk-paid" disabled><i class="ri-money-dollar-circle-line me-1"></i><?= __('Đã trả') ?></button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:40px;"><input type="checkbox" class="form-check-input" id="check-all"></th>
                                        <th><?= __('Nhân viên') ?></th>
                                        <th class="text-end"><?= __('Tỉ giá') ?></th>
                                        <th class="text-end"><?= __('Lương CB') ?></th>
                                        <th class="text-end"><?= __('Phụ cấp') ?></th>
                                        <th class="text-end"><?= __('Thưởng') ?></th>
                                        <th class="text-end"><?= __('Khấu trừ') ?></th>
                                        <th class="text-end"><?= __('Thực nhận') ?></th>
                                        <th class="text-center"><?= __('Ngày công') ?></th>
                                        <th class="text-center"><?= __('Trạng thái') ?></th>
                                        <th class="text-center"><?= __('Thao tác') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($salaries)): ?>
                                    <tr><td colspan="11" class="text-center text-muted py-4"><?= __('Chưa có bảng lương tháng này. Bấm "Tạo bảng lương tháng" để bắt đầu.') ?></td></tr>
                                    <?php endif; ?>
                                    <?php
                                    $statusBadge = ['draft' => 'warning', 'confirmed' => 'info', 'paid' => 'success'];
                                    $statusLabel = ['draft' => __('Nháp'), 'confirmed' => __('Đã xác nhận'), 'paid' => __('Đã trả')];
                                    foreach ($salaries as $s):
                                        $isCNY = $s['currency'] === 'CNY';
                                        $_sRate = floatval($s['exchange_rate'] ?? 0) ?: $exchangeRate;
                                        $toVnd = $isCNY ? $_sRate : 1;
                                        $toCny = $isCNY ? 1 : $vndToCny;
                                    ?>
                                    <tr>
                                        <td><input type="checkbox" class="form-check-input row-check" value="<?= $s['id'] ?>" data-status="<?= $s['status'] ?>"></td>
                                        <td>
                                            <strong><?= htmlspecialchars($s['fullname'] ?: $s['username']) ?></strong>
                                            <?php if ($s['phone']): ?><br><small class="text-muted"><?= htmlspecialchars($s['phone']) ?></small><?php endif; ?>
                                        </td>
                                        <td class="text-end"><small class="text-muted"><?= $isCNY ? fnum($_sRate, 0) : '-' ?></small></td>
                                        <td class="text-end"><?= format_dual($s['base_salary'] * $toVnd, $s['base_salary'] * $toCny) ?></td>
                                        <td class="text-end"><?= format_dual_or_dash($s['allowance'], $s['allowance'] * $toVnd, $s['allowance'] * $toCny) ?></td>
                                        <td class="text-end"><?= format_dual_or_dash($s['bonus'], $s['bonus'] * $toVnd, $s['bonus'] * $toCny) ?></td>
                                        <td class="text-end"><?= format_dual_or_dash($s['deduction'], $s['deduction'] * $toVnd, $s['deduction'] * $toCny, true) ?></td>
                                        <td class="text-end"><?= format_dual($s['net_salary'] * $toVnd, $s['net_salary'] * $toCny, true) ?></td>
                                        <td class="text-center"><?= $s['work_days'] !== null ? $s['work_days'] : '<span class="text-muted">-</span>' ?></td>
                                        <td class="text-center"><span class="badge bg-<?= $statusBadge[$s['status']] ?? 'secondary' ?>-subtle text-<?= $statusBadge[$s['status']] ?? 'secondary' ?> fs-12 px-2 py-1"><?= $statusLabel[$s['status']] ?? $s['status'] ?></span></td>
                                        <td class="text-center">
                                            <div class="d-flex gap-1 justify-content-center">
                                                <?php if ($s['status'] !== 'paid'): ?>
                                                <a href="<?= base_url('staffvn/salary-detail?id=' . $s['id']) ?>" class="btn btn-sm btn-soft-primary" title="<?= __('Sửa') ?>"><i class="ri-edit-line me-1"></i><?= __('Sửa') ?></a>
                                                <button class="btn btn-sm btn-soft-success btn-paid-one" data-id="<?= $s['id'] ?>" title="<?= __('Đánh dấu đã trả') ?>"><i class="ri-money-dollar-circle-line me-1"></i><?= __('Đã trả') ?></button>
                                                <?php endif; ?>
                                                <?php if ($s['status'] === 'draft'): ?>
                                                <button class="btn btn-sm btn-soft-danger btn-delete" data-id="<?= $s['id'] ?>" title="<?= __('Xóa') ?>"><i class="ri-delete-bin-line me-1"></i><?= __('Xóa') ?></button>
                                                <?php endif; ?>
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
    var csrfToken = '<?= $csrf->get_token_value() ?>';
    var ajaxUrl = '<?= base_url('ajaxs/staffvn/salaries.php') ?>';
    var currentMonth = <?= intval($filterMonth) ?>;
    var currentYear = <?= intval($filterYear) ?>;

    // ===== Check all =====
    $('#check-all').on('change', function(){
        $('.row-check').prop('checked', this.checked);
        updateBulkButtons();
    });
    $(document).on('change', '.row-check', function(){
        var total = $('.row-check').length;
        var chk = $('.row-check:checked').length;
        $('#check-all').prop('checked', total === chk).prop('indeterminate', chk > 0 && chk < total);
        updateBulkButtons();
    });

    function updateBulkButtons(){
        var hasChecked = $('.row-check:checked').length > 0;
        $('#btn-bulk-paid').prop('disabled', !hasChecked);
    }

    function getCheckedIds(statusFilter){
        var ids = [];
        $('.row-check:checked').each(function(){
            if (!statusFilter || $(this).data('status') === statusFilter) {
                ids.push($(this).val());
            }
        });
        return ids;
    }

    // ===== Tạo bảng lương tháng =====
    $('#btn-generate').on('click', function(){
        Swal.fire({
            title: '<?= __('Tạo bảng lương') ?>',
            html: '<?= __('Tạo bảng lương nháp cho tất cả nhân viên') ?><br><strong><?= __('Tháng') ?> ' + currentMonth + '/' + currentYear + '</strong>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<?= __('Tạo') ?>',
            cancelButtonText: '<?= __('Hủy') ?>'
        }).then(function(result){
            if (result.isConfirmed) {
                $.post(ajaxUrl, { request_name: 'generate_monthly', month: currentMonth, year: currentYear, csrf_token: csrfToken }, function(res){
                    if (res.status === 'success') {
                        Swal.fire({icon: 'success', title: res.msg, timer: 2000, showConfirmButton: false}).then(function(){ location.reload(); });
                    } else {
                        Swal.fire({icon: res.status === 'info' ? 'info' : 'error', title: res.msg});
                    }
                }, 'json');
            }
        });
    });

    // ===== Đánh dấu đã trả 1 =====
    $(document).on('click', '.btn-paid-one', function(){
        var id = $(this).data('id');
        Swal.fire({
            title: '<?= __('Đánh dấu đã trả lương?') ?>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<?= __('Đã trả') ?>',
            cancelButtonText: '<?= __('Hủy') ?>'
        }).then(function(result){
            if (result.isConfirmed) {
                $.post(ajaxUrl, { request_name: 'mark_paid', id: id, csrf_token: csrfToken }, function(res){
                    if (res.status === 'success') location.reload();
                    else Swal.fire({icon: 'error', title: res.msg});
                }, 'json');
            }
        });
    });

    // ===== Xóa =====
    $(document).on('click', '.btn-delete', function(){
        var id = $(this).data('id');
        Swal.fire({
            title: '<?= __('Xóa bản ghi lương này?') ?>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<?= __('Xóa') ?>',
            cancelButtonText: '<?= __('Hủy') ?>',
            confirmButtonColor: '#d33'
        }).then(function(result){
            if (result.isConfirmed) {
                $.post(ajaxUrl, { request_name: 'delete', id: id, csrf_token: csrfToken }, function(res){
                    if (res.status === 'success') location.reload();
                    else Swal.fire({icon: 'error', title: res.msg});
                }, 'json');
            }
        });
    });

    // ===== Bulk đã trả =====
    $('#btn-bulk-paid').on('click', function(){
        var ids = [];
        $('.row-check:checked').each(function(){
            var st = $(this).data('status');
            if (st === 'draft' || st === 'confirmed') ids.push($(this).val());
        });
        if (ids.length === 0) { Swal.fire({icon:'info', title:'<?= __('Chưa chọn bản ghi nào') ?>'}); return; }
        Swal.fire({
            title: '<?= __('Đánh dấu đã trả hàng loạt') ?> (' + ids.length + ')',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<?= __('Đã trả') ?>',
            cancelButtonText: '<?= __('Hủy') ?>'
        }).then(function(result){
            if (result.isConfirmed) {
                $.post(ajaxUrl, { request_name: 'bulk_paid', ids: ids.join(','), csrf_token: csrfToken }, function(res){
                    if (res.status === 'success') location.reload();
                    else Swal.fire({icon: 'error', title: res.msg});
                }, 'json');
            }
        });
    });
});
</script>
