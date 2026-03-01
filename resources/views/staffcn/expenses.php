<?php
require_once(__DIR__.'/../../../models/is_staffcn.php');
if (get_user_role() !== 'finance_cn') { redirect(base_url('staffcn/home')); }
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Chi phí vận hành kho');

// Lấy danh sách danh mục đã dùng từ DB
$existingCats = $ToryHub->get_list_safe("SELECT DISTINCT category FROM `expenses` ORDER BY category ASC", []);
$catList = array_column($existingCats, 'category');

// Filters
$filterCat = input_get('category') ?: '';
$filterMonth = input_get('month') ?: '';
$filterYear = input_get('year') ?: '';
$hasMonthFilter = ($filterMonth !== '' && $filterYear !== '');

$where = "1=1";
$params = [];

if ($hasMonthFilter) {
    $monthStart = sprintf('%04d-%02d-01', $filterYear, $filterMonth);
    $monthEnd = date('Y-m-t', strtotime($monthStart));
    $where .= " AND e.expense_date BETWEEN ? AND ?";
    $params[] = $monthStart;
    $params[] = $monthEnd;
}
if ($filterCat) {
    $where .= " AND e.category = ?";
    $params[] = $filterCat;
}

$expenses = $ToryHub->get_list_safe("SELECT e.*, u.username as created_by_name
    FROM `expenses` e
    LEFT JOIN `users` u ON e.created_by = u.id
    WHERE $where ORDER BY e.expense_date DESC, e.id DESC LIMIT 500", $params);

// Summary
$summaryLabel = __('Tất cả');
if ($hasMonthFilter) {
    $summaryLabel = __('Tháng') . ' ' . $filterMonth . '/' . $filterYear;
    $totalFiltered = $ToryHub->get_row_safe("SELECT COALESCE(SUM(amount),0) as total FROM `expenses` WHERE `expense_date` BETWEEN ? AND ?", [$monthStart, $monthEnd]);
    $countFiltered = $ToryHub->num_rows_safe("SELECT id FROM `expenses` WHERE `expense_date` BETWEEN ? AND ?", [$monthStart, $monthEnd]);
    $catSums = $ToryHub->get_list_safe("SELECT category, COALESCE(SUM(amount),0) as total FROM `expenses` WHERE `expense_date` BETWEEN ? AND ? GROUP BY category", [$monthStart, $monthEnd]);
} else {
    $totalFiltered = $ToryHub->get_row_safe("SELECT COALESCE(SUM(amount),0) as total FROM `expenses`", []);
    $countFiltered = $ToryHub->num_rows_safe("SELECT id FROM `expenses`", []);
    $catSums = $ToryHub->get_list_safe("SELECT category, COALESCE(SUM(amount),0) as total FROM `expenses` GROUP BY category", []);
}
$catSumMap = [];
foreach ($catSums as $cs) {
    $catSumMap[$cs['category']] = floatval($cs['total']);
}

// Năm có dữ liệu
$yearsData = $ToryHub->get_list_safe("SELECT DISTINCT YEAR(expense_date) as y FROM `expenses` ORDER BY y DESC", []);
$availableYears = array_column($yearsData, 'y');
if (!in_array(date('Y'), $availableYears)) $availableYears[] = date('Y');
rsort($availableYears);

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
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= $summaryLabel ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-danger"><?= format_vnd($totalFiltered['total']) ?></h4>
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
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Số khoản') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 text-primary"><?= $countFiltered ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle rounded fs-3"><i class="ri-wallet-3-line text-primary"></i></span>
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
                    <?php foreach ($catSumMap as $catName => $val): if ($val > 0): ?>
                        <span class="badge bg-secondary-subtle text-secondary fs-12 px-2 py-1">
                            <?= htmlspecialchars($catName) ?>: <?= format_vnd($val) ?>
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
                        <form method="GET" action="<?= base_url('staffcn/expenses') ?>" class="row g-3 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label"><?= __('Tháng') ?></label>
                                <select class="form-select" name="month">
                                    <option value=""><?= __('Tất cả') ?></option>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $filterMonth !== '' && $filterMonth == $m ? 'selected' : '' ?>><?= $m ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><?= __('Năm') ?></label>
                                <select class="form-select" name="year">
                                    <option value=""><?= __('Tất cả') ?></option>
                                    <?php foreach ($availableYears as $y): ?>
                                    <option value="<?= $y ?>" <?= $filterYear !== '' && $filterYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><?= __('Danh mục') ?></label>
                                <select class="form-select" name="category">
                                    <option value=""><?= __('Tất cả') ?></option>
                                    <?php foreach ($catList as $c): ?>
                                    <option value="<?= htmlspecialchars($c) ?>" <?= $filterCat == $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary"><?= __('Lọc') ?></button>
                                <a href="<?= base_url('staffcn/expenses') ?>" class="btn btn-secondary"><?= __('Reset') ?></a>
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
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($exp['category']) ?></span></td>
                                        <td class="text-danger fw-bold"><?= format_vnd($exp['amount']) ?></td>
                                        <td><?= htmlspecialchars($exp['description'] ?? '') ?></td>
                                        <td><?= $exp['expense_date'] ?></td>
                                        <td><?= htmlspecialchars($exp['created_by_name'] ?? '') ?></td>
                                        <td><?= $exp['create_date'] ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-soft-info btn-edit-expense" data-id="<?= $exp['id'] ?>" data-category="<?= htmlspecialchars($exp['category']) ?>" data-amount="<?= $exp['amount'] ?>" data-date="<?= $exp['expense_date'] ?>" data-description="<?= htmlspecialchars($exp['description'] ?? '') ?>">
                                                <i class="ri-pencil-line"></i>
                                            </button>
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
                                <input type="text" class="form-control" name="category" list="cat-suggestions" placeholder="<?= __('VD: Thuê mặt bằng, Điện nước...') ?>" required>
                                <datalist id="cat-suggestions">
                                    <?php foreach ($catList as $c): ?>
                                    <option value="<?= htmlspecialchars($c) ?>">
                                    <?php endforeach; ?>
                                </datalist>
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

        <!-- Modal Edit Expense -->
        <div class="modal fade" id="modalEditExpense" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><?= __('Sửa chi phí') ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="form-edit-expense">
                        <input type="hidden" name="<?= $csrf->get_token_name() ?>" value="<?= $csrf->get_token_value() ?>">
                        <input type="hidden" name="request_name" value="edit">
                        <input type="hidden" name="id" id="edit-id">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label"><?= __('Danh mục') ?> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="category" id="edit-category" list="cat-suggestions-edit" placeholder="<?= __('VD: Thuê mặt bằng, Điện nước...') ?>" required>
                                <datalist id="cat-suggestions-edit">
                                    <?php foreach ($catList as $c): ?>
                                    <option value="<?= htmlspecialchars($c) ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= __('Số tiền') ?> <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="amount" id="edit-amount" min="0" step="1000" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= __('Ngày chi') ?> <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="expense_date" id="edit-expense-date" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= __('Mô tả') ?></label>
                                <textarea class="form-control" name="description" id="edit-description" rows="3"></textarea>
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
            url: '<?= base_url('ajaxs/staffcn/expenses.php') ?>',
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

    // Edit expense - open modal
    $(document).on('click', '.btn-edit-expense', function() {
        $('#edit-id').val($(this).data('id'));
        $('#edit-category').val($(this).data('category'));
        $('#edit-amount').val($(this).data('amount'));
        $('#edit-expense-date').val($(this).data('date'));
        $('#edit-description').val($(this).data('description'));
        $('#modalEditExpense').modal('show');
    });

    // Edit expense - submit
    $('#form-edit-expense').on('submit', function(e) {
        e.preventDefault();
        var $btn = $('button[type=submit]', this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status"></span><?= __('Đang lưu...') ?>');

        $.ajax({
            url: '<?= base_url('ajaxs/staffcn/expenses.php') ?>',
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
                    url: '<?= base_url('ajaxs/staffcn/expenses.php') ?>',
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
