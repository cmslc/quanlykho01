<?php
require_once(__DIR__.'/../../../models/is_admin.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Lương Nhân Viên');

// Filters
$filterMonth = input_get('month') ?: date('n');
$filterYear = input_get('year') ?: date('Y');
$filterWarehouse = input_get('warehouse') ?: '';
$filterStatus = input_get('status') ?: '';
$filterStaff = input_get('user_id') ?: '';

$where = "s.month = ? AND s.year = ?";
$params = [intval($filterMonth), intval($filterYear)];

if ($filterWarehouse === 'cn') {
    $where .= " AND u.role = 'staffcn'";
} elseif ($filterWarehouse === 'vn') {
    $where .= " AND u.role = 'staffvn'";
}
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

// KPI
$totalNet = 0; $totalPaid = 0; $totalUnpaid = 0; $staffCount = count($salaries);
foreach ($salaries as $s) {
    $totalNet += $s['net_salary'];
    if ($s['status'] === 'paid') $totalPaid += $s['net_salary'];
    else $totalUnpaid += $s['net_salary'];
}

// Staff list for filter
$staffList = $ToryHub->get_list_safe(
    "SELECT id, fullname, username, role FROM `users` WHERE `role` IN ('staffcn','staffvn') AND `active` = 1 ORDER BY `fullname` ASC",
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
                    <button class="btn btn-primary" id="btn-generate">
                        <i class="ri-add-line me-1"></i><?= __('Tạo bảng lương tháng') ?>
                    </button>
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
                                <h4 class="fs-22 fw-semibold mt-4 mb-0"><?= number_format($totalNet, 0, ',', '.') ?></h4>
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
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-success"><?= number_format($totalPaid, 0, ',', '.') ?></h4>
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
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-danger"><?= number_format($totalUnpaid, 0, ',', '.') ?></h4>
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
                        <form method="GET" action="<?= base_url('admin/salary-list') ?>" class="row g-3 align-items-end">
                            <input type="hidden" name="module" value="admin">
                            <input type="hidden" name="action" value="salary-list">
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
                                <label class="form-label"><?= __('Kho') ?></label>
                                <select class="form-select" name="warehouse">
                                    <option value=""><?= __('Tất cả') ?></option>
                                    <option value="cn" <?= $filterWarehouse == 'cn' ? 'selected' : '' ?>><?= __('Kho Trung Quốc') ?></option>
                                    <option value="vn" <?= $filterWarehouse == 'vn' ? 'selected' : '' ?>><?= __('Kho Việt Nam') ?></option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><?= __('Trạng thái') ?></label>
                                <select class="form-select" name="status">
                                    <option value=""><?= __('Tất cả') ?></option>
                                    <option value="draft" <?= $filterStatus == 'draft' ? 'selected' : '' ?>><?= __('Nháp') ?></option>
                                    <option value="confirmed" <?= $filterStatus == 'confirmed' ? 'selected' : '' ?>><?= __('Đã xác nhận') ?></option>
                                    <option value="paid" <?= $filterStatus == 'paid' ? 'selected' : '' ?>><?= __('Đã trả') ?></option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><?= __('Nhân viên') ?></label>
                                <select class="form-select" name="user_id">
                                    <option value=""><?= __('Tất cả') ?></option>
                                    <?php foreach ($staffList as $st): ?>
                                    <option value="<?= $st['id'] ?>" <?= $filterStaff == $st['id'] ? 'selected' : '' ?>><?= htmlspecialchars($st['fullname'] ?: $st['username']) ?> (<?= $st['role'] === 'staffcn' ? 'TQ' : 'VN' ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-auto">
                                <button type="submit" class="btn btn-primary"><i class="ri-search-line me-1"></i><?= __('Lọc') ?></button>
                                <a href="<?= base_url('admin/salary-list') ?>" class="btn btn-secondary"><?= __('Reset') ?></a>
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
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-info" id="btn-bulk-confirm" disabled><i class="ri-check-line me-1"></i><?= __('Xác nhận') ?></button>
                            <button class="btn btn-sm btn-success" id="btn-bulk-paid" disabled><i class="ri-money-dollar-circle-line me-1"></i><?= __('Đã trả') ?></button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:40px;"><input type="checkbox" class="form-check-input" id="check-all"></th>
                                        <th><?= __('Nhân viên') ?></th>
                                        <th><?= __('Kho') ?></th>
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
                                        $isCN = $s['role'] === 'staffcn';
                                        $cur = $s['currency'];
                                        $formatFn = $cur === 'CNY' ? 'format_cny' : 'format_vnd';
                                    ?>
                                    <tr>
                                        <td><input type="checkbox" class="form-check-input row-check" value="<?= $s['id'] ?>" data-status="<?= $s['status'] ?>"></td>
                                        <td>
                                            <strong><?= htmlspecialchars($s['fullname'] ?: $s['username']) ?></strong>
                                            <?php if ($s['phone']): ?><br><small class="text-muted"><?= htmlspecialchars($s['phone']) ?></small><?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($isCN): ?>
                                            <span class="badge bg-danger-subtle text-danger"><?= __('Kho TQ') ?></span>
                                            <?php else: ?>
                                            <span class="badge bg-success-subtle text-success"><?= __('Kho VN') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end"><?= $formatFn($s['base_salary']) ?></td>
                                        <td class="text-end"><?= $s['allowance'] > 0 ? $formatFn($s['allowance']) : '<span class="text-muted">-</span>' ?></td>
                                        <td class="text-end"><?= $s['bonus'] > 0 ? $formatFn($s['bonus']) : '<span class="text-muted">-</span>' ?></td>
                                        <td class="text-end"><?= $s['deduction'] > 0 ? '<span class="text-danger">' . $formatFn($s['deduction']) . '</span>' : '<span class="text-muted">-</span>' ?></td>
                                        <td class="text-end fw-bold"><?= $formatFn($s['net_salary']) ?></td>
                                        <td class="text-center"><?= $s['work_days'] !== null ? $s['work_days'] : '<span class="text-muted">-</span>' ?></td>
                                        <td class="text-center"><span class="badge bg-<?= $statusBadge[$s['status']] ?? 'secondary' ?>"><?= $statusLabel[$s['status']] ?? $s['status'] ?></span></td>
                                        <td class="text-center">
                                            <div class="d-flex gap-1 justify-content-center">
                                                <?php if ($s['status'] !== 'paid'): ?>
                                                <a href="<?= base_url('admin/salary-detail&id=' . $s['id']) ?>" class="btn btn-sm btn-soft-primary" title="<?= __('Sửa') ?>"><i class="ri-edit-line"></i></a>
                                                <?php endif; ?>
                                                <?php if ($s['status'] === 'draft'): ?>
                                                <button class="btn btn-sm btn-soft-info btn-confirm-one" data-id="<?= $s['id'] ?>" title="<?= __('Xác nhận') ?>"><i class="ri-check-line"></i></button>
                                                <button class="btn btn-sm btn-soft-danger btn-delete" data-id="<?= $s['id'] ?>" title="<?= __('Xóa') ?>"><i class="ri-delete-bin-line"></i></button>
                                                <?php endif; ?>
                                                <?php if ($s['status'] === 'confirmed'): ?>
                                                <button class="btn btn-sm btn-soft-success btn-paid-one" data-id="<?= $s['id'] ?>" title="<?= __('Đánh dấu đã trả') ?>"><i class="ri-money-dollar-circle-line"></i></button>
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
    var ajaxUrl = '<?= base_url('ajaxs/admin/salaries.php') ?>';
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
        $('#btn-bulk-confirm, #btn-bulk-paid').prop('disabled', !hasChecked);
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

    // ===== Xác nhận 1 =====
    $(document).on('click', '.btn-confirm-one', function(){
        var id = $(this).data('id');
        Swal.fire({
            title: '<?= __('Xác nhận lương này?') ?>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<?= __('Xác nhận') ?>',
            cancelButtonText: '<?= __('Hủy') ?>'
        }).then(function(result){
            if (result.isConfirmed) {
                $.post(ajaxUrl, { request_name: 'confirm', id: id, csrf_token: csrfToken }, function(res){
                    if (res.status === 'success') location.reload();
                    else Swal.fire({icon: 'error', title: res.msg});
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

    // ===== Bulk xác nhận =====
    $('#btn-bulk-confirm').on('click', function(){
        var ids = getCheckedIds('draft');
        if (ids.length === 0) { Swal.fire({icon:'info', title:'<?= __('Chưa chọn bản ghi nháp nào') ?>'}); return; }
        Swal.fire({
            title: '<?= __('Xác nhận hàng loạt') ?> (' + ids.length + ')',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<?= __('Xác nhận') ?>',
            cancelButtonText: '<?= __('Hủy') ?>'
        }).then(function(result){
            if (result.isConfirmed) {
                $.post(ajaxUrl, { request_name: 'bulk_confirm', ids: ids.join(','), csrf_token: csrfToken }, function(res){
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
