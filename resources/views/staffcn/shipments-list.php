<?php
require_once(__DIR__.'/../../../models/is_staffcn.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Danh sách chuyến xe');

$filterStatus = input_get('status') ?: '';
$filterSearch = trim(input_get('search') ?? '');

$where = "1=1";
$params = [];

if ($filterStatus && in_array($filterStatus, ['preparing', 'in_transit', 'arrived', 'completed'])) {
    $where .= " AND s.status = ?";
    $params[] = $filterStatus;
}
if ($filterSearch) {
    $where .= " AND (s.shipment_code LIKE ? OR s.truck_plate LIKE ? OR s.driver_name LIKE ?)";
    $searchLike = '%' . $filterSearch . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

$perPage = 10;
$page = max(1, intval(input_get('page') ?: 1));
$totalShipments = $ToryHub->num_rows_safe("SELECT s.id FROM `shipments` s WHERE $where", $params);
$totalPages = max(1, ceil($totalShipments / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$shipments = $ToryHub->get_list_safe("SELECT s.*, u.fullname as creator_name,
    (SELECT COUNT(DISTINCT b.id) FROM `shipment_packages` sp2
     JOIN `bag_packages` bp2 ON sp2.package_id = bp2.package_id
     JOIN `bags` b ON bp2.bag_id = b.id
     WHERE sp2.shipment_id = s.id) as cnt_bags,
    (SELECT COUNT(DISTINCT o2.id) FROM `shipment_packages` sp3
     JOIN `package_orders` po3 ON sp3.package_id = po3.package_id
     JOIN `orders` o2 ON po3.order_id = o2.id
     WHERE sp3.shipment_id = s.id
     AND o2.product_type = 'wholesale'
     AND sp3.package_id NOT IN (SELECT bp3.package_id FROM `bag_packages` bp3)) as cnt_wholesale
    FROM `shipments` s LEFT JOIN `users` u ON s.created_by = u.id
    WHERE $where ORDER BY s.create_date DESC LIMIT $perPage OFFSET $offset", $params);


$statuses = ['preparing', 'in_transit', 'arrived', 'completed'];
$statusLabels = [
    'preparing'  => ['label' => 'Đang chuẩn bị', 'bg' => 'info-subtle', 'text' => 'info', 'icon' => 'ri-loader-4-line'],
    'in_transit' => ['label' => 'Đang vận chuyển', 'bg' => 'primary-subtle', 'text' => 'primary', 'icon' => 'ri-truck-line'],
    'arrived'    => ['label' => 'Đã đến', 'bg' => 'success-subtle', 'text' => 'success', 'icon' => 'ri-map-pin-2-line'],
    'completed'  => ['label' => 'Hoàn thành', 'bg' => 'secondary-subtle', 'text' => 'secondary', 'icon' => 'ri-check-double-line'],
];

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Danh sách chuyến xe') ?></h4>
                    <div class="d-flex gap-2">
                        <button class="btn btn-success" id="btn-export-excel"><i class="ri-file-excel-2-line me-1"></i><?= __('Xuất Excel') ?></button>
                        <button class="btn btn-primary" id="btn-create-shipment"><i class="ri-add-line me-1"></i><?= __('Tạo chuyến xe') ?></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="<?= base_url('staffcn/shipments-list') ?>">
                            <input type="hidden" name="module" value="admin">
                            <input type="hidden" name="action" value="shipments-list">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label"><?= __('Tìm kiếm') ?></label>
                                    <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($filterSearch) ?>" placeholder="<?= __('Mã chuyến, biển số, tài xế...') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label"><?= __('Trạng thái') ?></label>
                                    <select class="form-select" name="status">
                                        <option value=""><?= __('Tất cả') ?></option>
                                        <?php foreach ($statuses as $s): ?>
                                        <option value="<?= $s ?>" <?= $filterStatus == $s ? 'selected' : '' ?>><?= __($statusLabels[$s]['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-auto">
                                    <button type="submit" class="btn btn-primary"><i class="ri-search-line me-1"></i><?= __('Lọc') ?></button>
                                    <a href="<?= base_url('staffcn/shipments-list') ?>" class="btn btn-secondary"><?= __('Reset') ?></a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Shipments Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Chuyến xe') ?> (<?= $totalShipments ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><?= __('Mã chuyến') ?></th>
                                        <th><?= __('Biển số / Tài xế') ?></th>
                                        <th><?= __('Tuyến đường') ?></th>
                                        <th><?= __('Số kiện') ?></th>
                                        <th><?= __('Tổng cân') ?></th>
                                        <th><?= __('Tổng khối') ?></th>
                                        <th><?= __('Trạng thái') ?></th>
                                        <th><?= __('Ngày tạo') ?></th>
                                        <th><?= __('Hành động') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($shipments)): ?>
                                    <tr><td colspan="9" class="text-center text-muted py-4"><?= __('Chưa có chuyến xe nào') ?></td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($shipments as $s): ?>
                                    <?php $cfg = $statusLabels[$s['status']] ?? $statusLabels['preparing']; ?>
                                    <tr>
                                        <td>
                                            <a href="<?= base_url('staffcn/shipments-detail&id=' . $s['id']) ?>"><strong><?= htmlspecialchars($s['shipment_code']) ?></strong></a>
                                            <?php $totalMaHang = intval($s['cnt_bags']) + intval($s['cnt_wholesale']); ?>
                                            <div class="mt-1 text-muted small">
                                                <i class="ri-archive-line"></i> <?= $totalMaHang ?> <?= __('mã hàng') ?>
                                                &middot;
                                                <i class="ri-inbox-line"></i> <?= intval($s['total_packages']) ?> <?= __('kiện') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($s['truck_plate']): ?>
                                            <strong><?= htmlspecialchars($s['truck_plate']) ?></strong><br>
                                            <?php endif; ?>
                                            <?= $s['driver_name'] ? htmlspecialchars($s['driver_name']) : '' ?>
                                        </td>
                                        <td><?= $s['route'] ? htmlspecialchars($s['route']) : '' ?></td>
                                        <td class="text-center"><?= $s['total_packages'] ?></td>
                                        <td><?= $s['total_weight'] > 0 ? fnum($s['total_weight'], 1) . ' kg' : '' ?>
                                            <?php if ($s['max_weight'] > 0): ?>
                                            <br><small class="text-muted">/<?= fnum($s['max_weight'], 0) ?> kg</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $s['total_cbm'] > 0 ? fnum($s['total_cbm'], 2) . ' m³' : '' ?></td>
                                        <td><span class="badge bg-<?= $cfg['bg'] ?> text-<?= $cfg['text'] ?> fs-12 px-2 py-1"><i class="<?= $cfg['icon'] ?> me-1"></i><?= __($cfg['label']) ?></span></td>
                                        <td>
                                            <?= date('d/m/Y H:i', strtotime($s['create_date'])) ?>
                                            <?php if ($s['departed_date']): ?>
                                            <br><small class="text-muted"><?= __('Xuất') ?>: <?= date('d/m/Y', strtotime($s['departed_date'])) ?></small>
                                            <?php endif; ?>
                                            <?php if ($s['arrived_date']): ?>
                                            <br><small class="text-muted"><?= __('Đến') ?>: <?= date('d/m/Y', strtotime($s['arrived_date'])) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="<?= base_url('staffcn/shipments-detail&id=' . $s['id']) ?>" class="btn btn-sm btn-info"><i class="ri-eye-line me-1"></i><?= __('Xem') ?></a>
                                                <?php if ($s['status'] === 'preparing'): ?>
                                                <button type="button" class="btn btn-sm btn-danger btn-delete-shipment" data-id="<?= $s['id'] ?>" data-code="<?= htmlspecialchars($s['shipment_code']) ?>"><i class="ri-delete-bin-line me-1"></i><?= __('Xóa') ?></button>
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
                            <div class="text-muted"><?= __('Hiển thị') ?> <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalShipments) ?> / <?= $totalShipments ?></div>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <?php
                                    $queryParams = $_GET;
                                    unset($queryParams['page']);
                                    $baseUrl = base_url('staffcn/shipments-list') . ($queryParams ? '&' . http_build_query($queryParams) : '');
                                    ?>
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $baseUrl . '&page=' . ($page - 1) ?>">&laquo;</a></li>
                                    <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                                    <li class="page-item <?= $p == $page ? 'active' : '' ?>"><a class="page-link" href="<?= $baseUrl . '&page=' . $p ?>"><?= $p ?></a></li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="<?= $baseUrl . '&page=' . ($page + 1) ?>">&raquo;</a></li>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

<!-- Modal Create Shipment -->
<div class="modal fade" id="modal-create-shipment" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('Tạo chuyến xe mới') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label"><?= __('Biển số xe') ?></label>
                        <input type="text" class="form-control" id="inp-truck-plate" placeholder="VD: 29C-12345">
                    </div>
                    <div class="col-6">
                        <label class="form-label"><?= __('Tài xế') ?></label>
                        <input type="text" class="form-control" id="inp-driver-name">
                    </div>
                    <div class="col-6">
                        <label class="form-label"><?= __('SĐT tài xế') ?></label>
                        <input type="text" class="form-control" id="inp-driver-phone">
                    </div>
                    <div class="col-12">
                        <label class="form-label"><?= __('Tuyến đường') ?></label>
                        <input type="text" class="form-control" id="inp-route" value="<?= __('Kho Trung Quốc - Cửa Khẩu') ?>" placeholder="VD: Quảng Châu → Hà Nội">
                    </div>
                    <div class="col-6">
                        <label class="form-label"><?= __('Trọng tải tối đa (kg)') ?></label>
                        <input type="number" class="form-control" id="inp-max-weight" step="0.01">
                    </div>
                    <div class="col-6">
                        <label class="form-label"><?= __('Chi phí vận chuyển') ?></label>
                        <input type="number" class="form-control" id="inp-shipping-cost" step="0.01">
                    </div>
                    <div class="col-12">
                        <label class="form-label"><?= __('Ghi chú') ?></label>
                        <textarea class="form-control" id="inp-note" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Hủy') ?></button>
                <button type="button" class="btn btn-primary" id="btn-save-shipment"><i class="ri-save-line me-1"></i><?= __('Tạo chuyến') ?></button>
            </div>
        </div>
    </div>
</div>

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
$(function(){
    var csrfToken = '<?= (new Csrf())->get_token_value() ?>';
    var ajaxUrl = '<?= base_url('ajaxs/staffcn/shipments.php') ?>';

    // Create shipment
    $('#btn-create-shipment').on('click', function(){
        $('#modal-create-shipment').modal('show');
    });

    $('#btn-save-shipment').on('click', function(){
        var btn = $(this);
        btn.prop('disabled', true);
        $.post(ajaxUrl, {
            request_name: 'create',
            truck_plate: $('#inp-truck-plate').val(),
            driver_name: $('#inp-driver-name').val(),
            driver_phone: $('#inp-driver-phone').val(),
            route: $('#inp-route').val(),
            max_weight: $('#inp-max-weight').val(),
            shipping_cost: $('#inp-shipping-cost').val(),
            note: $('#inp-note').val(),
            csrf_token: csrfToken
        }, function(res){
            if (res.status === 'success') {
                Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){
                    window.location.href = '<?= base_url('staffcn/shipments-detail&id=') ?>' + res.shipment_id;
                });
            } else {
                Swal.fire({icon: 'error', title: 'Error', text: res.msg});
                btn.prop('disabled', false);
            }
        }, 'json').fail(function(){
            Swal.fire({icon: 'error', title: 'Error', text: '<?= __('Lỗi kết nối') ?>'});
            btn.prop('disabled', false);
        });
    });

    // Export Excel
    $('#btn-export-excel').on('click', function(){
        var params = new URLSearchParams(window.location.search);
        window.location.href = '<?= base_url('ajaxs/staffcn/shipments-list-export.php') ?>?' + params.toString();
    });

    // Delete shipment
    $(document).on('click', '.btn-delete-shipment', function(){
        var id = $(this).data('id');
        var code = $(this).data('code');
        Swal.fire({
            title: '<?= __('Xác nhận xóa?') ?>',
            html: '<?= __('Bạn có chắc muốn xóa chuyến xe') ?> <strong>' + code + '</strong>?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: '<?= __('Xóa') ?>',
            cancelButtonText: '<?= __('Hủy') ?>'
        }).then(function(result){
            if (result.isConfirmed) {
                $.post(ajaxUrl, {request_name: 'delete', id: id, csrf_token: csrfToken}, function(res){
                    if (res.status === 'success') {
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
