<?php
require_once(__DIR__.'/../../../models/is_admin.php');
require_once(__DIR__.'/../../../libs/csrf.php');
require_once(__DIR__.'/../../../libs/database/packages.php');

$page_title = __('Quản lý kiện hàng');

// Filters
$filterStatus = input_get('status') ?: '';
$filterSearch = trim(input_get('search') ?? '');
$filterOrderId = input_get('order_id') ?: '';
$filterCustomer = input_get('customer_id') ?: '';
$filterDateFrom = input_get('date_from') ?: '';
$filterDateTo = input_get('date_to') ?: '';

$where = "1=1";
$params = [];

if ($filterStatus) {
    $where .= " AND p.status = ?";
    $params[] = $filterStatus;
}
if ($filterSearch) {
    $where .= " AND (p.package_code LIKE ? OR p.tracking_cn LIKE ? OR p.tracking_intl LIKE ? OR p.tracking_vn LIKE ?)";
    $searchLike = '%' . $filterSearch . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}
if ($filterOrderId) {
    $where .= " AND p.id IN (SELECT po.package_id FROM `package_orders` po WHERE po.order_id = ?)";
    $params[] = intval($filterOrderId);
}
if ($filterCustomer) {
    $where .= " AND p.id IN (SELECT po.package_id FROM `package_orders` po JOIN `orders` o ON po.order_id = o.id WHERE o.customer_id = ?)";
    $params[] = intval($filterCustomer);
}
if ($filterDateFrom) {
    $where .= " AND DATE(p.create_date) >= ?";
    $params[] = $filterDateFrom;
}
if ($filterDateTo) {
    $where .= " AND DATE(p.create_date) <= ?";
    $params[] = $filterDateTo;
}

// Pagination
$perPage = 20;
$page = max(1, intval(input_get('page') ?: 1));
$totalPackages = $ToryHub->num_rows_safe("SELECT p.id FROM `packages` p WHERE $where", $params);
$totalPages = max(1, ceil($totalPackages / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$packages = $ToryHub->get_list_safe("SELECT p.*,
    (SELECT GROUP_CONCAT(DISTINCT o.order_code SEPARATOR ', ') FROM orders o INNER JOIN package_orders po ON o.id = po.order_id WHERE po.package_id = p.id) as order_codes,
    (SELECT GROUP_CONCAT(DISTINCT o.product_code SEPARATOR ', ') FROM orders o INNER JOIN package_orders po ON o.id = po.order_id WHERE po.package_id = p.id) as product_codes,
    (SELECT GROUP_CONCAT(DISTINCT c.fullname SEPARATOR ', ') FROM customers c INNER JOIN orders o ON c.id = o.customer_id INNER JOIN package_orders po ON o.id = po.order_id WHERE po.package_id = p.id) as customer_names
    FROM `packages` p WHERE $where ORDER BY p.create_date DESC LIMIT $perPage OFFSET $offset", $params);

$pkgStatuses = ['cn_warehouse', 'packed', 'shipping', 'vn_warehouse', 'delivered'];
$customers = $ToryHub->get_list_safe("SELECT `id`, `customer_code`, `fullname` FROM `customers` ORDER BY `fullname` ASC", []);

// If filtering by order_id, get order info for display
$filterOrderInfo = null;
if ($filterOrderId) {
    $filterOrderInfo = $ToryHub->get_row_safe("SELECT id, order_code, product_code, product_name FROM `orders` WHERE `id` = ?", [intval($filterOrderId)]);
}

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><i class="ri-archive-line me-2"></i><?= __('Quản lý kiện hàng') ?></h4>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddPackage">
                        <i class="ri-add-line"></i> <?= __('Tạo kiện mới') ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="<?= base_url('admin/packages-list') ?>">
                            <input type="hidden" name="module" value="admin">
                            <input type="hidden" name="action" value="packages-list">
                            <?php if ($filterOrderId): ?>
                            <input type="hidden" name="order_id" value="<?= intval($filterOrderId) ?>">
                            <?php endif; ?>
                            <div class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label"><?= __('Tìm kiếm') ?></label>
                                    <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($filterSearch) ?>" placeholder="<?= __('Mã kiện, mã vận đơn...') ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><?= __('Trạng thái') ?></label>
                                    <select class="form-select" name="status">
                                        <option value=""><?= __('Tất cả') ?></option>
                                        <?php foreach ($pkgStatuses as $s): ?>
                                        <option value="<?= $s ?>" <?= $filterStatus == $s ? 'selected' : '' ?>><?= strip_tags(display_package_status($s)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label"><?= __('Khách hàng') ?></label>
                                    <select class="form-select" name="customer_id">
                                        <option value=""><?= __('Tất cả') ?></option>
                                        <?php foreach ($customers as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= $filterCustomer == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['customer_code'] . ' - ' . $c['fullname']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><?= __('Từ ngày') ?></label>
                                    <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><?= __('Đến ngày') ?></label>
                                    <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($filterDateTo) ?>">
                                </div>
                            </div>
                            <div class="row g-3 align-items-end mt-0">
                                <div class="col-md-auto">
                                    <button type="submit" class="btn btn-primary"><i class="ri-search-line me-1"></i><?= __('Lọc') ?></button>
                                    <a href="<?= base_url('admin/packages-list') ?>" class="btn btn-secondary"><?= __('Reset') ?></a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($filterOrderInfo): ?>
        <div class="row">
            <div class="col-12">
                <div class="alert alert-info d-flex align-items-center gap-2 mb-3">
                    <i class="ri-filter-line fs-16"></i>
                    <?= __('Đang lọc kiện hàng của đơn') ?>: <strong><?= htmlspecialchars($filterOrderInfo['product_code'] ?: $filterOrderInfo['order_code']) ?></strong>
                    <?php if ($filterOrderInfo['product_name']): ?> - <?= htmlspecialchars($filterOrderInfo['product_name']) ?><?php endif; ?>
                    <a href="<?= base_url('admin/packages-list') ?>" class="btn btn-sm btn-outline-info ms-auto"><i class="ri-close-line"></i> <?= __('Bỏ lọc') ?></a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Status Summary -->
        <div class="row">
            <?php
            $statusColors = ['cn_warehouse' => 'info', 'packed' => 'secondary', 'shipping' => 'dark', 'vn_warehouse' => 'primary', 'delivered' => 'success'];
            foreach ($pkgStatuses as $s):
                $cnt = $ToryHub->num_rows_safe("SELECT * FROM `packages` WHERE `status` = ?", [$s]) ?: 0;
            ?>
            <div class="col">
                <a href="<?= base_url('admin/packages-list&status=' . $s) ?>" class="text-decoration-none">
                    <div class="card card-animate <?= $filterStatus == $s ? 'border border-primary' : '' ?>">
                        <div class="card-body py-2 text-center">
                            <h5 class="mb-0"><?= $cnt ?></h5>
                            <small><?= display_package_status($s) ?></small>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Packages Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h5 class="card-title mb-0"><?= __('Danh sách kiện') ?> (<?= $totalPackages ?>)</h5>
                        <div class="d-flex align-items-center gap-2">
                            <select class="form-select" id="bulk-status" style="width:220px;">
                                <option value=""><?= __('-- Chọn trạng thái --') ?></option>
                                <option value="cn_warehouse"><?= __('Đã về kho Trung Quốc') ?></option>
                                <option value="packed"><?= __('Đã đóng bao') ?></option>
                                <option value="shipping"><?= __('Đang vận chuyển') ?></option>
                                <option value="vn_warehouse"><?= __('Đã về kho Việt Nam') ?></option>
                                <option value="delivered"><?= __('Đã giao hàng') ?></option>
                            </select>
                            <button class="btn btn-primary" id="btn-bulk-apply"><i class="ri-check-line me-1"></i><?= __('Cập nhật') ?></button>
                        </div>
                    </div>
                    <div id="selected-summary" class="card-body border-bottom py-2 d-none">
                        <div class="d-flex align-items-center gap-4 flex-wrap">
                            <span class="text-muted"><?= __('Đã chọn') ?>: <strong id="sum-count">0</strong> <?= __('kiện') ?></span>
                            <span><?= __('Tổng cân nặng') ?>: <strong id="sum-weight" class="text-primary">0 kg</strong></span>
                            <span><?= __('Tổng số khối') ?>: <strong id="sum-cbm" class="text-primary">0 m³</strong></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:40px;"><input type="checkbox" class="form-check-input" id="check-all"></th>
                                        <th><?= __('Mã kiện') ?></th>
                                        <th><?= __('Mã vận đơn / Mã hàng') ?></th>
                                        <th><?= __('Đơn hàng') ?></th>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th><?= __('Cân nặng') ?></th>
                                        <th><?= __('Kích thước') ?></th>
                                        <th><?= __('Số khối') ?></th>
                                        <th><?= __('Trạng thái') ?></th>
                                        <th><?= __('Ngày tạo') ?></th>
                                        <th><?= __('Hành động') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($packages as $pkg):
                                        $pkgCbm = ($pkg['length_cm'] * $pkg['width_cm'] * $pkg['height_cm']) / 1000000;
                                    ?>
                                    <tr>
                                        <td><input type="checkbox" class="form-check-input pkg-check" value="<?= $pkg['id'] ?>" data-weight="<?= $pkg['weight_actual'] ?>" data-cbm="<?= $pkgCbm ?>"></td>
                                        <td>
                                            <a href="<?= base_url('admin/packages-detail&id=' . $pkg['id']) ?>">
                                                <strong><?= htmlspecialchars($pkg['package_code']) ?></strong>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if (!empty($pkg['tracking_cn'])): ?>
                                            <small><?= htmlspecialchars($pkg['tracking_cn']) ?></small>
                                            <?php endif; ?>
                                            <?php if (!empty($pkg['product_codes'])): ?>
                                            <?php if (!empty($pkg['tracking_cn'])): ?><br><?php endif; ?>
                                            <small class="text-muted"><?= htmlspecialchars($pkg['product_codes']) ?></small>
                                            <?php endif; ?>
                                            <?php if (empty($pkg['tracking_cn']) && empty($pkg['product_codes'])): ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($pkg['order_codes'])): ?>
                                            <small><?= htmlspecialchars(mb_strimwidth($pkg['order_codes'], 0, 30, '...')) ?></small>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($pkg['customer_names'])): ?>
                                            <small><?= htmlspecialchars(mb_strimwidth($pkg['customer_names'], 0, 20, '...')) ?></small>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($pkg['weight_actual'] > 0): ?>
                                            <?= number_format($pkg['weight_actual'], 2) ?> kg
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($pkg['length_cm'] > 0 || $pkg['width_cm'] > 0 || $pkg['height_cm'] > 0): ?>
                                            <small><?= $pkg['length_cm'] ?>x<?= $pkg['width_cm'] ?>x<?= $pkg['height_cm'] ?></small>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($pkgCbm > 0): ?>
                                            <?= number_format($pkgCbm, 4) ?>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= display_package_status($pkg['status']) ?></td>
                                        <td><small><?= date('d/m/Y H:i', strtotime($pkg['create_date'])) ?></small></td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="<?= base_url('admin/packages-detail&id=' . $pkg['id']) ?>" class="btn btn-sm btn-info"><i class="ri-eye-line"></i></a>
                                                <button class="btn btn-sm btn-danger btn-delete" data-id="<?= $pkg['id'] ?>" data-code="<?= htmlspecialchars($pkg['package_code']) ?>"><i class="ri-delete-bin-line"></i></button>
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
                                <?= __('Hiển thị') ?> <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalPackages) ?> / <?= $totalPackages ?> <?= __('kiện') ?>
                            </div>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <?php
                                    $queryParams = $_GET;
                                    unset($queryParams['page']);
                                    $baseUrl = base_url('admin/packages-list') . ($queryParams ? '&' . http_build_query($queryParams) : '');
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

<!-- Modal Add Package -->
<div class="modal fade" id="modalAddPackage" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('Tạo kiện mới') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-add-package">
                <input type="hidden" name="<?= $csrf->get_token_name() ?>" value="<?= $csrf->get_token_value() ?>">
                <input type="hidden" name="request_name" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?= __('Tracking TQ') ?></label>
                        <input type="text" class="form-control" name="tracking_cn" placeholder="<?= __('Mã vận đơn từ seller TQ') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('Tracking QT') ?></label>
                        <input type="text" class="form-control" name="tracking_intl">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?= __('Cân nặng') ?> (kg)</label>
                            <input type="number" step="0.01" class="form-control" name="weight_actual" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?= __('Phương thức') ?></label>
                            <select class="form-select" name="shipping_method">
                                <option value="road"><?= __('Đường bộ') ?></option>
                                <option value="sea"><?= __('Đường biển') ?></option>
                                <option value="air"><?= __('Đường bay') ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-4 mb-3">
                            <label class="form-label">L (cm)</label>
                            <input type="number" step="0.1" class="form-control" name="length_cm" value="0">
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label">W (cm)</label>
                            <input type="number" step="0.1" class="form-control" name="width_cm" value="0">
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label">H (cm)</label>
                            <input type="number" step="0.1" class="form-control" name="height_cm" value="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('Liên kết đơn hàng') ?></label>
                        <input type="text" class="form-control" id="search-order-input" placeholder="<?= __('Tìm mã đơn / mã khách hàng...') ?>">
                        <div id="search-order-results" class="list-group mt-1" style="display:none;"></div>
                        <div id="linked-orders" class="mt-2"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('Ghi chú') ?></label>
                        <textarea class="form-control" name="note" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Hủy') ?></button>
                    <button type="submit" class="btn btn-primary"><?= __('Tạo kiện') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
$(function(){
    var csrfToken = '<?= (new Csrf())->get_token_value() ?>';
    var ajaxUrl = '<?= base_url('ajaxs/admin/packages.php') ?>';

    // ===== Selected summary =====
    function updateSelectedSummary(){
        var count = 0, totalWeight = 0, totalCbm = 0;
        $('.pkg-check:checked').each(function(){
            count++;
            totalWeight += parseFloat($(this).data('weight')) || 0;
            totalCbm += parseFloat($(this).data('cbm')) || 0;
        });
        if(count > 0){
            $('#selected-summary').removeClass('d-none');
            $('#sum-count').text(count);
            $('#sum-weight').text(totalWeight.toFixed(2) + ' kg');
            $('#sum-cbm').text(totalCbm.toFixed(4) + ' m³');
        } else {
            $('#selected-summary').addClass('d-none');
        }
    }

    // ===== Check all =====
    $('#check-all').on('change', function(){
        $('.pkg-check').prop('checked', this.checked);
        updateSelectedSummary();
    });

    $(document).on('change', '.pkg-check', function(){
        var total = $('.pkg-check').length;
        var checked = $('.pkg-check:checked').length;
        $('#check-all').prop('checked', total === checked);
        updateSelectedSummary();
    });

    // ===== Bulk update status =====
    $('#btn-bulk-apply').on('click', function(){
        var ids = [];
        $('.pkg-check:checked').each(function(){ ids.push($(this).val()); });
        var newStatus = $('#bulk-status').val();

        if(ids.length === 0){
            Swal.fire({icon: 'warning', title: '<?= __('Vui lòng chọn ít nhất 1 kiện hàng') ?>'});
            return;
        }
        if(!newStatus){
            Swal.fire({icon: 'warning', title: '<?= __('Vui lòng chọn trạng thái') ?>'});
            return;
        }

        Swal.fire({
            title: '<?= __('Xác nhận cập nhật?') ?>',
            html: '<?= __('Cập nhật') ?> <strong>' + ids.length + '</strong> <?= __('kiện hàng') ?>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<?= __('Cập nhật') ?>',
            cancelButtonText: '<?= __('Hủy') ?>'
        }).then(function(result){
            if(result.isConfirmed){
                var btn = $('#btn-bulk-apply');
                btn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin me-1"></i>');

                $.post(ajaxUrl, {
                    request_name: 'bulk_update_status',
                    package_ids: ids.join(','),
                    new_status: newStatus,
                    csrf_token: csrfToken
                }, function(res){
                    if(res.status == 'success'){
                        Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
                    } else {
                        Swal.fire({icon: 'error', title: 'Error', text: res.msg});
                        btn.prop('disabled', false).html('<i class="ri-check-line me-1"></i><?= __('Cập nhật') ?>');
                    }
                }, 'json').fail(function(){
                    Swal.fire({icon: 'error', text: '<?= __('Lỗi kết nối') ?>'});
                    btn.prop('disabled', false).html('<i class="ri-check-line me-1"></i><?= __('Cập nhật') ?>');
                });
            }
        });
    });

    // ===== Delete package =====
    $('.btn-delete').on('click', function(){
        var id = $(this).data('id');
        var code = $(this).data('code');
        Swal.fire({
            title: '<?= __('Xóa kiện hàng') ?>?',
            text: code,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f06548',
            confirmButtonText: '<?= __('Xóa') ?>',
            cancelButtonText: '<?= __('Hủy') ?>'
        }).then(function(result){
            if (result.isConfirmed) {
                $.post(ajaxUrl, {
                    request_name: 'delete', id: id,
                    csrf_token: csrfToken
                }, function(res){
                    if (res.status === 'success') {
                        Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
                    } else {
                        Swal.fire({icon: 'error', text: res.msg});
                    }
                }, 'json');
            }
        });
    });

    // ===== Add package modal =====
    var searchTimer;
    var selectedOrders = [];
    $('#search-order-input').on('input', function(){
        clearTimeout(searchTimer);
        var keyword = $(this).val().trim();
        if (keyword.length < 2) { $('#search-order-results').hide(); return; }
        searchTimer = setTimeout(function(){
            $.post(ajaxUrl, {
                request_name: 'search_orders',
                keyword: keyword,
                csrf_token: csrfToken
            }, function(res){
                if (res.status === 'success' && res.orders.length > 0) {
                    var html = '';
                    res.orders.forEach(function(o){
                        if (selectedOrders.indexOf(o.id) === -1) {
                            html += '<a href="#" class="list-group-item list-group-item-action search-order-item" data-id="' + o.id + '" data-code="' + o.order_code + '">'
                                + '<strong>' + o.order_code + '</strong> - ' + (o.customer_name || '') + '<br><small class="text-muted">' + (o.product_name || '').substring(0,40) + '</small></a>';
                        }
                    });
                    $('#search-order-results').html(html || '<div class="list-group-item text-muted"><?= __('Không có kết quả') ?></div>').show();
                } else {
                    $('#search-order-results').html('<div class="list-group-item text-muted"><?= __('Không có kết quả') ?></div>').show();
                }
            }, 'json');
        }, 300);
    });

    $(document).on('click', '.search-order-item', function(e){
        e.preventDefault();
        var id = $(this).data('id');
        var code = $(this).data('code');
        if (selectedOrders.indexOf(id) === -1) {
            selectedOrders.push(id);
            $('#linked-orders').append(
                '<span class="badge bg-info me-1 mb-1" data-id="' + id + '">' + code +
                ' <i class="ri-close-line ms-1" style="cursor:pointer" onclick="removeOrder(' + id + ',this)"></i>' +
                '<input type="hidden" name="order_ids[]" value="' + id + '">' +
                '</span>'
            );
        }
        $('#search-order-results').hide();
        $('#search-order-input').val('');
    });

    // Add package form
    $('#form-add-package').on('submit', function(e){
        e.preventDefault();
        var $btn = $(this).find('button[type=submit]').prop('disabled', true);
        $.post(ajaxUrl, $(this).serialize(), function(res){
            if (res.status === 'success') {
                Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
            } else {
                Swal.fire({icon: 'error', title: '<?= __('Lỗi') ?>', text: res.msg});
                $btn.prop('disabled', false);
            }
        }, 'json').fail(function(){ $btn.prop('disabled', false); });
    });
});

function removeOrder(id, el){
    $(el).closest('.badge').remove();
}
</script>
