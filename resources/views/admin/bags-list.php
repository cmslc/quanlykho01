<?php
require_once(__DIR__.'/../../../models/is_admin.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Danh sách bao hàng');

$filterStatus = input_get('status') ?: '';
$filterSearch = trim(input_get('search') ?? '');

$where = "1=1";
$params = [];

if ($filterStatus && in_array($filterStatus, ['open', 'sealed', 'shipping', 'arrived'])) {
    $where .= " AND b.status = ?";
    $params[] = $filterStatus;
}
if ($filterSearch) {
    $where .= " AND b.bag_code LIKE ?";
    $params[] = '%' . $filterSearch . '%';
}

// Pagination
$perPage = 10;
$page = max(1, intval(input_get('page') ?: 1));
$totalBags = $ToryHub->num_rows_safe("SELECT b.id FROM `bags` b WHERE $where", $params);
$totalPages = max(1, ceil($totalBags / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$bags = $ToryHub->get_list_safe("SELECT b.*, u.fullname as creator_name
    FROM `bags` b LEFT JOIN `users` u ON b.created_by = u.id
    WHERE $where ORDER BY b.create_date DESC LIMIT $perPage OFFSET $offset", $params);

$bagStatuses = ['open', 'sealed', 'shipping', 'arrived'];
$bagStatusLabels = [
    'open' => ['label' => 'Đang mở', 'bg' => 'info-subtle', 'text' => 'info', 'icon' => 'ri-lock-unlock-line'],
    'sealed' => ['label' => 'Đã đóng', 'bg' => 'dark-subtle', 'text' => 'dark', 'icon' => 'ri-lock-line'],
    'shipping' => ['label' => 'Đang vận chuyển', 'bg' => 'primary-subtle', 'text' => 'primary', 'icon' => 'ri-ship-line'],
    'arrived' => ['label' => 'Đã đến kho Việt Nam', 'bg' => 'success-subtle', 'text' => 'success', 'icon' => 'ri-check-double-line'],
];

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Danh sách bao hàng') ?></h4>
                    <a href="<?= base_url('admin/bags-packing') ?>" class="btn btn-primary">
                        <i class="ri-archive-drawer-line"></i> <?= __('Đóng bao mới') ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="<?= base_url('admin/bags-list') ?>">
                            <input type="hidden" name="module" value="admin">
                            <input type="hidden" name="action" value="bags-list">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label"><?= __('Tìm kiếm') ?></label>
                                    <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($filterSearch) ?>" placeholder="<?= __('Mã bao...') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label"><?= __('Trạng thái') ?></label>
                                    <select class="form-select" name="status">
                                        <option value=""><?= __('Tất cả') ?></option>
                                        <?php foreach ($bagStatuses as $s): ?>
                                        <option value="<?= $s ?>" <?= $filterStatus == $s ? 'selected' : '' ?>><?= __($bagStatusLabels[$s]['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-auto">
                                    <button type="submit" class="btn btn-primary"><i class="ri-search-line me-1"></i><?= __('Lọc') ?></button>
                                    <a href="<?= base_url('admin/bags-list') ?>" class="btn btn-secondary"><?= __('Reset') ?></a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Summary -->
        <div class="row">
            <?php foreach ($bagStatuses as $s):
                $cnt = $ToryHub->num_rows_safe("SELECT id FROM `bags` WHERE `status` = ?", [$s]) ?: 0;
                $sl = $bagStatusLabels[$s];
            ?>
            <div class="col">
                <a href="<?= base_url('admin/bags-list&status=' . $s) ?>" class="text-decoration-none">
                    <div class="card card-animate <?= $filterStatus == $s ? 'border border-primary' : '' ?>">
                        <div class="card-body py-2 text-center">
                            <h5 class="mb-0"><?= $cnt ?></h5>
                            <small class="text-muted"><span class="badge bg-<?= $sl['bg'] ?> text-<?= $sl['text'] ?>"><i class="<?= $sl['icon'] ?> me-1"></i><?= __($sl['label']) ?></span></small>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Bags Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Danh sách bao hàng') ?> (<?= $totalBags ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><?= __('Mã bao') ?></th>
                                        <th><?= __('Trạng thái') ?></th>
                                        <th><?= __('Số kiện') ?></th>
                                        <th><?= __('Tổng cân (kg)') ?></th>
                                        <th><?= __('Số khối (m³)') ?></th>
                                        <th><?= __('Người tạo') ?></th>
                                        <th><?= __('Ngày tạo') ?></th>
                                        <th><?= __('Hành động') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($bags)): ?>
                                    <tr><td colspan="8" class="text-center text-muted py-4"><?= __('Chưa có bao hàng nào') ?></td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($bags as $bag):
                                        $sl = $bagStatusLabels[$bag['status']] ?? $bagStatusLabels['open'];
                                    ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($bag['bag_code']) ?></strong></td>
                                        <td><span class="badge bg-<?= $sl['bg'] ?> text-<?= $sl['text'] ?> fs-12 px-2 py-1"><i class="<?= $sl['icon'] ?> me-1"></i><?= __($sl['label']) ?></span></td>
                                        <td><span class="fw-bold"><?= $bag['total_packages'] ?></span></td>
                                        <td><?= number_format($bag['total_weight'], 2) ?></td>
                                        <td><?= floatval($bag['weight_volume']) ?></td>
                                        <td><?= htmlspecialchars($bag['creator_name'] ?? '') ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($bag['create_date'])) ?></td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <?php if ($bag['status'] === 'open'): ?>
                                                <a href="<?= base_url('admin/bags-packing&id=' . $bag['id']) ?>" class="btn btn-info"><i class="ri-barcode-line me-1"></i><?= __('Sửa bao') ?></a>
                                                <button type="button" class="btn btn-dark btn-seal-bag" data-id="<?= $bag['id'] ?>" data-code="<?= htmlspecialchars($bag['bag_code']) ?>" data-count="<?= $bag['total_packages'] ?>"><i class="ri-lock-line me-1"></i><?= __('Đóng bao') ?></button>
                                                <button type="button" class="btn btn-danger btn-delete-bag" data-id="<?= $bag['id'] ?>" data-code="<?= htmlspecialchars($bag['bag_code']) ?>"><i class="ri-delete-bin-line me-1"></i><?= __('Xóa') ?></button>
                                                <?php elseif ($bag['status'] === 'sealed'): ?>
                                                <button type="button" class="btn btn-warning btn-unseal-bag" data-id="<?= $bag['id'] ?>" data-code="<?= htmlspecialchars($bag['bag_code']) ?>"><i class="ri-lock-unlock-line me-1"></i><?= __('Mở bao') ?></button>
                                                <button type="button" class="btn btn-danger btn-delete-bag" data-id="<?= $bag['id'] ?>" data-code="<?= htmlspecialchars($bag['bag_code']) ?>"><i class="ri-delete-bin-line me-1"></i><?= __('Xóa') ?></button>
                                                <?php else: ?>
                                                <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalPages > 1): ?>
                        <div class="d-flex align-items-center justify-content-between mt-3">
                            <div class="text-muted">
                                <?= __('Hiển thị') ?> <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalBags) ?> / <?= $totalBags ?>
                            </div>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <?php
                                    $queryParams = $_GET;
                                    unset($queryParams['page']);
                                    $baseUrl = base_url('admin/bags-list') . ($queryParams ? '&' . http_build_query($queryParams) : '');
                                    ?>
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $baseUrl . '&page=' . ($page - 1) ?>">&laquo;</a>
                                    </li>
                                    <?php
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    if ($startPage > 1): ?>
                                        <li class="page-item"><a class="page-link" href="<?= $baseUrl . '&page=1' ?>">1</a></li>
                                        <?php if ($startPage > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                                    <?php endif; ?>
                                    <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= $baseUrl . '&page=' . $p ?>"><?= $p ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    <?php if ($endPage < $totalPages): ?>
                                        <?php if ($endPage < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                                        <li class="page-item"><a class="page-link" href="<?= $baseUrl . '&page=' . $totalPages ?>"><?= $totalPages ?></a></li>
                                    <?php endif; ?>
                                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $baseUrl . '&page=' . ($page + 1) ?>">&raquo;</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
$(function(){
    var csrfToken = '<?= (new Csrf())->get_token_value() ?>';
    var ajaxUrl = '<?= base_url('ajaxs/admin/bags.php') ?>';

    // Seal bag
    $(document).on('click', '.btn-seal-bag', function(){
        var id = $(this).data('id');
        var code = $(this).data('code');
        var count = $(this).data('count');
        if (count < 1) {
            Swal.fire({icon: 'warning', title: '<?= __('Bao hàng chưa có kiện nào') ?>'});
            return;
        }
        Swal.fire({
            title: '<?= __('Đóng bao?') ?>',
            html: '<?= __('Đóng bao') ?> <strong>' + code + '</strong> (<?= __('gồm') ?> ' + count + ' <?= __('kiện') ?>)?<br><small class="text-muted"><?= __('Sau khi đóng sẽ không thể thêm/gỡ kiện') ?></small>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<?= __('Đóng bao') ?>',
            cancelButtonText: '<?= __('Hủy') ?>'
        }).then(function(result){
            if(result.isConfirmed){
                $.post(ajaxUrl, { request_name: 'seal', bag_id: id, csrf_token: csrfToken }, function(res){
                    if(res.status == 'success'){
                        Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
                    } else {
                        Swal.fire({icon: 'error', title: 'Error', text: res.msg});
                    }
                }, 'json');
            }
        });
    });

    // Ship bag
    $(document).on('click', '.btn-ship-bag', function(){
        var id = $(this).data('id');
        var code = $(this).data('code');
        var count = $(this).data('count');
        Swal.fire({
            title: '<?= __('Xuất vận chuyển?') ?>',
            html: '<?= __('Xuất bao') ?> <strong>' + code + '</strong> (<?= __('gồm') ?> ' + count + ' <?= __('kiện') ?>)?<br><small class="text-muted"><?= __('Tất cả kiện trong bao sẽ chuyển sang trạng thái đang vận chuyển') ?></small>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3577f1',
            confirmButtonText: '<?= __('Xuất vận chuyển') ?>',
            cancelButtonText: '<?= __('Hủy') ?>'
        }).then(function(result){
            if(result.isConfirmed){
                $.post(ajaxUrl, { request_name: 'ship', bag_id: id, csrf_token: csrfToken }, function(res){
                    if(res.status == 'success'){
                        Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
                    } else {
                        Swal.fire({icon: 'error', title: 'Error', text: res.msg});
                    }
                }, 'json');
            }
        });
    });

    // Unseal bag
    $(document).on('click', '.btn-unseal-bag', function(){
        var id = $(this).data('id');
        var code = $(this).data('code');
        Swal.fire({
            title: '<?= __('Mở lại bao?') ?>',
            html: '<?= __('Mở lại bao') ?> <strong>' + code + '</strong>?<br><small class="text-muted"><?= __('Bao sẽ được mở lại để thêm/gỡ kiện') ?></small>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<?= __('Mở bao') ?>',
            cancelButtonText: '<?= __('Hủy') ?>'
        }).then(function(result){
            if(result.isConfirmed){
                $.post(ajaxUrl, { request_name: 'unseal', bag_id: id, csrf_token: csrfToken }, function(res){
                    if(res.status == 'success'){
                        Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
                    } else {
                        Swal.fire({icon: 'error', title: 'Error', text: res.msg});
                    }
                }, 'json');
            }
        });
    });

    // Delete bag
    $(document).on('click', '.btn-delete-bag', function(){
        var id = $(this).data('id');
        var code = $(this).data('code');
        Swal.fire({
            title: '<?= __('Xóa bao hàng?') ?>',
            html: '<?= __('Xóa bao') ?> <strong>' + code + '</strong>?<br><small class="text-muted"><?= __('Các kiện sẽ quay lại trạng thái kho Trung Quốc') ?></small>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: '<?= __('Xóa') ?>',
            cancelButtonText: '<?= __('Hủy') ?>'
        }).then(function(result){
            if(result.isConfirmed){
                $.post(ajaxUrl, { request_name: 'delete', bag_id: id, csrf_token: csrfToken }, function(res){
                    if(res.status == 'success'){
                        Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
                    } else {
                        Swal.fire({icon: 'error', title: 'Error', text: res.msg});
                    }
                }, 'json');
            }
        });
    });
});
</script>
