<?php
require_once(__DIR__.'/../../../models/is_admin.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Chi phí vận hành kho');

$catLabels = [
    'rent'        => ['label' => __('Thuê mặt bằng'),      'bg' => 'danger'],
    'utilities'   => ['label' => __('Điện nước'),           'bg' => 'warning'],
    'packaging'   => ['label' => __('Vật tư đóng gói'),    'bg' => 'info'],
    'fuel'        => ['label' => __('Nhiên liệu'),         'bg' => 'dark'],
    'maintenance' => ['label' => __('Bảo trì/sửa chữa'),  'bg' => 'secondary'],
    'other'       => ['label' => __('Khác'),               'bg' => 'primary'],
];

// Filters
$filterCat = input_get('category') ?: '';
$filterDateFrom = input_get('date_from') ?: '';
$filterDateTo = input_get('date_to') ?: '';

$where = "1=1";
$params = [];

if ($filterCat) {
    $where .= " AND e.category = ?";
    $params[] = $filterCat;
}
if ($filterDateFrom) {
    $where .= " AND e.expense_date >= ?";
    $params[] = $filterDateFrom;
}
if ($filterDateTo) {
    $where .= " AND e.expense_date <= ?";
    $params[] = $filterDateTo;
}

$expenses = $ToryHub->get_list_safe("SELECT e.*, u.username as created_by_name
    FROM `expenses` e
    LEFT JOIN `users` u ON e.created_by = u.id
    WHERE $where ORDER BY e.expense_date DESC, e.id DESC LIMIT 500", $params);

// Summary: tổng chi tháng này
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$totalMonth = $ToryHub->get_row_safe("SELECT COALESCE(SUM(amount),0) as total FROM `expenses` WHERE `expense_date` BETWEEN ? AND ?", [$monthStart, $monthEnd]);
$totalAll = $ToryHub->get_row_safe("SELECT COALESCE(SUM(amount),0) as total FROM `expenses`", []);
$countMonth = $ToryHub->num_rows_safe("SELECT id FROM `expenses` WHERE `expense_date` BETWEEN ? AND ?", [$monthStart, $monthEnd]);

// Tổng theo danh mục tháng này
$catSums = $ToryHub->get_list_safe("SELECT category, COALESCE(SUM(amount),0) as total FROM `expenses` WHERE `expense_date` BETWEEN ? AND ? GROUP BY category", [$monthStart, $monthEnd]);
$catSumMap = [];
foreach ($catSums as $cs) {
    $catSumMap[$cs['category']] = floatval($cs['total']);
}

$csrf = new Csrf();

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Chi phí vận hành kho') ?></h4>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row">
            <div class="col-xl-4 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-end justify-content-between mt-2">
                            <div>
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Chi tháng này') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-danger"><?= format_vnd($totalMonth['total']) ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-danger-subtle rounded fs-3"><i class="ri-money-cny-circle-line text-danger"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-end justify-content-between mt-2">
                            <div>
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Tổng tất cả') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-primary"><?= format_vnd($totalAll['total']) ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle rounded fs-3"><i class="ri-wallet-3-line text-primary"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-end justify-content-between mt-2">
                            <div>
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Số khoản tháng này') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0"><?= $countMonth ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-info-subtle rounded fs-3"><i class="ri-file-list-3-line text-info"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chi tiết theo danh mục tháng này -->
        <?php if (!empty($catSumMap)): ?>
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($catLabels as $key => $cfg): ?>
                        <?php $val = $catSumMap[$key] ?? 0; if ($val > 0): ?>
                        <span class="badge bg-<?= $cfg['bg'] ?>-subtle text-<?= $cfg['bg'] ?> fs-12 px-2 py-1">
                            <?= $cfg['label'] ?>: <?= format_vnd($val) ?>
                        </span>
                        <?php endif; endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="<?= base_url('admin/expenses') ?>" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label"><?= __('Danh mục') ?></label>
                                <select class="form-select" name="category">
                                    <option value=""><?= __('Tất cả') ?></option>
                                    <?php foreach ($catLabels as $key => $cfg): ?>
                                    <option value="<?= $key ?>" <?= $filterCat == $key ? 'selected' : '' ?>><?= $cfg['label'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><?= __('Từ ngày') ?></label>
                                <input type="date" class="form-control" name="date_from" value="<?= $filterDateFrom ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><?= __('Đến ngày') ?></label>
                                <input type="date" class="form-control" name="date_to" value="<?= $filterDateTo ?>">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary"><?= __('Lọc') ?></button>
                                <a href="<?= base_url('admin/expenses') ?>" class="btn btn-secondary"><?= __('Reset') ?></a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expenses Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0"><?= __('Danh sách chi phí') ?></h5>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddExpense">
                            <i class="ri-add-line"></i> <?= __('Thêm chi phí') ?>
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table data-table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th><?= __('Danh mục') ?></th>
                                        <th><?= __('Số tiền') ?></th>
                                        <th><?= __('Mô tả') ?></th>
                                        <th><?= __('Ngày chi') ?></th>
                                        <th><?= __('Người tạo') ?></th>
                                        <th><?= __('Ngày tạo') ?></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expenses as $exp): ?>
                                    <tr>
                                        <td><?= $exp['id'] ?></td>
                                        <td>
                                            <?php $cat = $catLabels[$exp['category']] ?? ['label' => $exp['category'], 'bg' => 'secondary']; ?>
                                            <span class="badge bg-<?= $cat['bg'] ?>"><?= $cat['label'] ?></span>
                                        </td>
                                        <td class="text-danger fw-bold"><?= format_vnd($exp['amount']) ?></td>
                                        <td><?= htmlspecialchars($exp['description'] ?? '') ?></td>
                                        <td><?= $exp['expense_date'] ?></td>
                                        <td><?= htmlspecialchars($exp['created_by_name'] ?? '') ?></td>
                                        <td><?= $exp['create_date'] ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-soft-danger btn-delete-expense" data-id="<?= $exp['id'] ?>">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
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

        <!-- Modal Add Expense -->
        <div class="modal fade" id="modalAddExpense" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><?= __('Thêm chi phí') ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="form-add-expense">
                        <input type="hidden" name="<?= $csrf->get_token_name() ?>" value="<?= $csrf->get_token_value() ?>">
                        <input type="hidden" name="request_name" value="add">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label"><?= __('Danh mục') ?> <span class="text-danger">*</span></label>
                                <select class="form-select" name="category" required>
                                    <option value="" disabled selected><?= __('-- Chọn danh mục --') ?></option>
                                    <?php foreach ($catLabels as $key => $cfg): ?>
                                    <option value="<?= $key ?>"><?= $cfg['label'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= __('Số tiền') ?> <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="amount" min="0" step="1000" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= __('Ngày chi') ?> <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="expense_date" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= __('Mô tả') ?></label>
                                <textarea class="form-control" name="description" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Hủy') ?></button>
                            <button type="submit" class="btn btn-primary"><?= __('Lưu') ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

<script>
$(function() {
    // Add expense
    $('#form-add-expense').on('submit', function(e) {
        e.preventDefault();
        var $btn = $('button[type=submit]', this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status"></span><?= __('Đang lưu...') ?>');

        $.ajax({
            url: '<?= base_url('ajaxs/admin/expenses.php') ?>',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function() {
                        location.reload();
                    });
                } else {
                    Swal.fire({icon: 'error', title: res.msg});
                }
            },
            error: function() {
                Swal.fire({icon: 'error', title: '<?= __('Lỗi kết nối') ?>'});
            },
            complete: function() {
                $btn.prop('disabled', false).html('<?= __('Lưu') ?>');
            }
        });
    });

    // Delete expense
    $(document).on('click', '.btn-delete-expense', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: '<?= __('Xác nhận xóa?') ?>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: '<?= __('Xóa') ?>',
            cancelButtonText: '<?= __('Hủy') ?>'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: '<?= base_url('ajaxs/admin/expenses.php') ?>',
                    type: 'POST',
                    data: {
                        request_name: 'delete',
                        id: id,
                        <?= $csrf->get_token_name() ?>: '<?= $csrf->get_token_value() ?>'
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function() {
                                location.reload();
                            });
                        } else {
                            Swal.fire({icon: 'error', title: res.msg});
                        }
                    }
                });
            }
        });
    });
});
</script>
<?php require_once(__DIR__.'/footer.php'); ?>
