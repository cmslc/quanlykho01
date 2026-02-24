<?php
require_once(__DIR__.'/../../../models/is_staffvn.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Kiện hàng tại kho');

// Filters
$filterStatus = input_get('status') ?: 'vn_warehouse';
$filterSearch = trim(input_get('search') ?? '');
$filterDateFrom = input_get('date_from') ?: '';
$filterDateTo = input_get('date_to') ?: '';
$pkgStatuses = ['shipping', 'vn_warehouse', 'delivered'];

$where = "1=1";
$params = [];

if ($filterStatus && in_array($filterStatus, $pkgStatuses)) {
    $where .= " AND p.status = ?";
    $params[] = $filterStatus;
} else {
    $where .= " AND p.status IN ('shipping','vn_warehouse','delivered')";
    $filterStatus = '';
}

if ($filterSearch) {
    $where .= " AND (p.package_code LIKE ? OR p.tracking_intl LIKE ? OR p.tracking_vn LIKE ? OR p.tracking_cn LIKE ?)";
    $s = '%' . $filterSearch . '%';
    $params = array_merge($params, [$s, $s, $s, $s]);
}

if ($filterDateFrom) {
    $where .= " AND DATE(p.vn_warehouse_date) >= ?";
    $params[] = $filterDateFrom;
}
if ($filterDateTo) {
    $where .= " AND DATE(p.vn_warehouse_date) <= ?";
    $params[] = $filterDateTo;
}

$packages = $ToryHub->get_list_safe("
    SELECT p.*,
           GROUP_CONCAT(DISTINCT o.order_code ORDER BY o.id SEPARATOR ', ') as order_codes,
           GROUP_CONCAT(DISTINCT c.fullname ORDER BY c.id SEPARATOR ', ') as customer_names,
           COUNT(DISTINCT pp.id) as photo_count,
           wz.zone_code, wz.zone_name
    FROM `packages` p
    LEFT JOIN `package_orders` po ON p.id = po.package_id
    LEFT JOIN `orders` o ON po.order_id = o.id
    LEFT JOIN `customers` c ON o.customer_id = c.id
    LEFT JOIN `package_photos` pp ON pp.package_id = p.id AND pp.photo_type = 'receive'
    LEFT JOIN `warehouse_zones` wz ON p.zone_id = wz.id
    WHERE $where
    GROUP BY p.id
    ORDER BY p.update_date DESC
", $params);

// Status counts
$statusCounts = [];
foreach ($pkgStatuses as $s) {
    $statusCounts[$s] = $ToryHub->num_rows_safe("SELECT * FROM `packages` WHERE `status` = ?", [$s]) ?: 0;
}

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Kiện hàng tại kho') ?></h4>
                </div>
            </div>
        </div>

        <!-- Status Summary -->
        <div class="row">
            <div class="col-md-3">
                <a href="<?= base_url('staffvn/packages-list&status=') ?>" class="text-decoration-none">
                    <div class="card card-animate <?= empty($filterStatus) ? 'border border-primary' : '' ?>">
                        <div class="card-body py-2 text-center">
                            <h5 class="mb-0"><?= array_sum($statusCounts) ?></h5>
                            <small class="text-muted"><?= __('Tất cả') ?></small>
                        </div>
                    </div>
                </a>
            </div>
            <?php
            $statusColors = ['shipping' => 'warning', 'vn_warehouse' => 'success', 'delivered' => 'primary'];
            foreach ($pkgStatuses as $s): ?>
            <div class="col-md-3">
                <a href="<?= base_url('staffvn/packages-list&status=' . $s) ?>" class="text-decoration-none">
                    <div class="card card-animate <?= $filterStatus == $s ? 'border border-primary' : '' ?>">
                        <div class="card-body py-2 text-center">
                            <h5 class="mb-0"><?= $statusCounts[$s] ?></h5>
                            <small class="text-muted"><?= display_package_status($s) ?></small>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Filters -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="<?= base_url('staffvn/packages-list') ?>" class="row g-3 align-items-end">
                            <input type="hidden" name="module" value="staffvn">
                            <input type="hidden" name="action" value="packages-list">
                            <div class="col-md-2">
                                <label class="form-label"><?= __('Trạng thái') ?></label>
                                <select class="form-select" name="status">
                                    <option value=""><?= __('Tất cả') ?></option>
                                    <?php foreach ($pkgStatuses as $s): ?>
                                    <option value="<?= $s ?>" <?= $filterStatus == $s ? 'selected' : '' ?>><?= display_package_status($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><?= __('Tìm kiếm') ?></label>
                                <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($filterSearch) ?>" placeholder="<?= __('Mã kiện, tracking...') ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><?= __('Từ ngày') ?></label>
                                <input type="date" class="form-control" name="date_from" value="<?= $filterDateFrom ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><?= __('Đến ngày') ?></label>
                                <input type="date" class="form-control" name="date_to" value="<?= $filterDateTo ?>">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary"><i class="ri-search-line me-1"></i><?= __('Lọc') ?></button>
                                <a href="<?= base_url('staffvn/packages-list') ?>" class="btn btn-secondary"><?= __('Reset') ?></a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Packages Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Danh sách kiện hàng') ?> (<?= count($packages) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table data-table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><?= __('Mã kiện') ?></th>
                                        <th><?= __('Tracking') ?></th>
                                        <th><?= __('Cân nặng') ?></th>
                                        <th><?= __('Kích thước') ?></th>
                                        <th><?= __('Số khối') ?></th>
                                        <th><?= __('Đơn hàng') ?></th>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th><?= __('Vị trí kho') ?></th>
                                        <th><?= __('Trạng thái') ?></th>
                                        <th><?= __('Ngày về kho') ?></th>
                                        <th><?= __('Ảnh') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($packages as $pkg):
                                        $cbm = ($pkg['length_cm'] * $pkg['width_cm'] * $pkg['height_cm']) / 1000000;
                                    ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($pkg['package_code']) ?></strong></td>
                                        <td>
                                            <?php if ($pkg['tracking_intl']): ?>
                                            <small class="d-block"><?= htmlspecialchars($pkg['tracking_intl']) ?></small>
                                            <?php endif; ?>
                                            <?php if ($pkg['tracking_vn']): ?>
                                            <small class="d-block text-muted"><?= htmlspecialchars($pkg['tracking_vn']) ?></small>
                                            <?php endif; ?>
                                            <?php if (!$pkg['tracking_intl'] && !$pkg['tracking_vn']): ?>-<?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($pkg['weight_charged'] > 0): ?>
                                            <?= $pkg['weight_charged'] ?> kg
                                            <?php if ($pkg['weight_actual'] > 0 && $pkg['weight_actual'] != $pkg['weight_charged']): ?>
                                            <br><small class="text-muted">(TT: <?= $pkg['weight_actual'] ?> kg)</small>
                                            <?php endif; ?>
                                            <?php else: ?>-<?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($pkg['length_cm'] > 0 || $pkg['width_cm'] > 0 || $pkg['height_cm'] > 0): ?>
                                            <?= floatval($pkg['length_cm']) ?>x<?= floatval($pkg['width_cm']) ?>x<?= floatval($pkg['height_cm']) ?>
                                            <?php else: ?>-<?php endif; ?>
                                        </td>
                                        <td><?= $cbm > 0 ? round($cbm, 4) . ' m³' : '-' ?></td>
                                        <td><small><?= htmlspecialchars($pkg['order_codes'] ?: '-') ?></small></td>
                                        <td><small><?= htmlspecialchars($pkg['customer_names'] ?: '-') ?></small></td>
                                        <td>
                                            <?php if (!empty($pkg['zone_code'])): ?>
                                            <span class="badge bg-info-subtle text-info"><?= htmlspecialchars($pkg['zone_code']) ?></span>
                                            <?php if (!empty($pkg['shelf_position'])): ?>
                                            <small class="d-block text-muted"><?= htmlspecialchars($pkg['shelf_position']) ?></small>
                                            <?php endif; ?>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= display_package_status($pkg['status']) ?></td>
                                        <td><?= $pkg['vn_warehouse_date'] ? date('d/m/Y H:i', strtotime($pkg['vn_warehouse_date'])) : '-' ?></td>
                                        <td>
                                            <?php if ($pkg['photo_count'] > 0): ?>
                                            <a href="javascript:void(0)" class="btn-view-photos" data-pkg-id="<?= $pkg['id'] ?>" data-pkg-code="<?= htmlspecialchars($pkg['package_code']) ?>">
                                                <i class="ri-image-line text-success"></i> <?= $pkg['photo_count'] ?>
                                            </a>
                                            <?php elseif ($pkg['receive_photo']): ?>
                                            <a href="javascript:void(0)" class="btn-view-photos" data-pkg-id="<?= $pkg['id'] ?>" data-pkg-code="<?= htmlspecialchars($pkg['package_code']) ?>">
                                                <i class="ri-image-line text-success"></i> 1
                                            </a>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
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

<!-- Modal xem ảnh -->
<div class="modal fade" id="modalPhotos" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ri-image-line me-1"></i><?= __('Ảnh kiện hàng') ?> - <span id="photo-pkg-code"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="photo-gallery" class="d-flex flex-wrap gap-2"></div>
                <p id="photo-empty" class="text-center text-muted py-3" style="display:none;"><?= __('Chưa có ảnh') ?></p>
            </div>
        </div>
    </div>
</div>

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
var csrfToken = '<?= $csrf->get_token_value() ?>';
var csrfName = '<?= $csrf->get_token_name() ?>';

// View photos
$(document).on('click', '.btn-view-photos', function(){
    var pkgId = $(this).data('pkg-id');
    var pkgCode = $(this).data('pkg-code');
    $('#photo-pkg-code').text(pkgCode);
    $('#photo-gallery').html('<div class="text-center py-3"><i class="ri-loader-4-line ri-spin fs-24"></i></div>');
    $('#photo-empty').hide();
    new bootstrap.Modal(document.getElementById('modalPhotos')).show();

    $.post('<?= base_url('ajaxs/staffvn/orders-scan.php') ?>', {
        request_name: 'get_photos',
        package_id: pkgId,
        [csrfName]: csrfToken
    }, function(res){
        if (res.status === 'success' && res.photos.length > 0) {
            var html = '';
            res.photos.forEach(function(p){
                html += '<a href="' + p.url + '" target="_blank"><img src="' + p.url + '" class="img-thumbnail" style="max-height:200px;cursor:pointer;"></a>';
            });
            $('#photo-gallery').html(html);
        } else {
            $('#photo-gallery').html('');
            $('#photo-empty').show();
        }
        if (res.csrf_token) csrfToken = res.csrf_token;
    }, 'json');
});
</script>
