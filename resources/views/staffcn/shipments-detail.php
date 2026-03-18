<?php
require_once(__DIR__.'/../../../models/is_staffcn.php');
require_once(__DIR__.'/../../../libs/csrf.php');
require_once(__DIR__.'/../../../libs/database/shipments.php');

$id = intval(input_get('id'));
$shipment = $ToryHub->get_row_safe("SELECT s.*, u.fullname as creator_name FROM `shipments` s LEFT JOIN `users` u ON s.created_by = u.id WHERE s.id = ?", [$id]);
if (!$shipment) {
    echo '<script>alert("Chuyến xe không tồn tại");window.location.href="' . base_url('staffcn/shipments-list') . '";</script>';
    exit;
}

$page_title = __('Chi tiết chuyến xe') . ' - ' . $shipment['shipment_code'];

$ShipmentsDB = new Shipments();
$packages = $ShipmentsDB->getPackages($id);

$statusLabels = [
    'preparing'  => ['label' => 'Đang chuẩn bị', 'bg' => 'info-subtle', 'text' => 'info', 'icon' => 'ri-loader-4-line'],
    'in_transit' => ['label' => 'Đang vận chuyển', 'bg' => 'primary-subtle', 'text' => 'primary', 'icon' => 'ri-truck-line'],
    'arrived'    => ['label' => 'Đã đến', 'bg' => 'success-subtle', 'text' => 'success', 'icon' => 'ri-map-pin-2-line'],
    'completed'  => ['label' => 'Hoàn thành', 'bg' => 'secondary-subtle', 'text' => 'secondary', 'icon' => 'ri-check-double-line'],
];
$cfg = $statusLabels[$shipment['status']] ?? $statusLabels['preparing'];
$isPreparing = $shipment['status'] === 'preparing';

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Chi tiết chuyến xe') ?>: <?= htmlspecialchars($shipment['shipment_code']) ?></h4>
                    <div class="d-flex gap-2">
                        <a href="<?= base_url('staffcn/shipments-list') ?>" class="btn btn-secondary"><i class="ri-arrow-left-line me-1"></i><?= __('Quay lại') ?></a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Shipment Info - Full width horizontal -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center gap-2 flex-wrap">
                        <h5 class="card-title mb-0"><?= __('Thông tin chuyến') ?></h5>
                        <span class="badge bg-<?= $cfg['bg'] ?> text-<?= $cfg['text'] ?> fs-12 px-2 py-1"><i class="<?= $cfg['icon'] ?> me-1"></i><?= __($cfg['label']) ?></span>
                        <button class="btn btn-sm btn-outline-secondary" id="btn-change-status" title="<?= __('Đổi trạng thái') ?>"><i class="ri-edit-line"></i></button>
                        <div class="ms-auto d-flex gap-2">
                            <button class="btn btn-primary btn-sm" id="btn-edit-shipment"><i class="ri-pencil-line me-1"></i><?= __('Sửa') ?></button>
                            <?php if ($isPreparing): ?>
                            <button class="btn btn-danger btn-sm" id="btn-delete-shipment"><i class="ri-delete-bin-line me-1"></i><?= __('Xóa') ?></button>
                            <?php elseif ($shipment['status'] === 'arrived'): ?>
                            <button class="btn btn-secondary btn-sm" id="btn-complete"><i class="ri-check-double-line me-1"></i><?= __('Hoàn thành') ?></button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body py-3">
                        <div class="row g-3 align-items-start">
                            <!-- Info fields -->
                            <div class="col-12 col-xl-9">
                                <div class="row g-3">
                                    <div class="col-6 col-md-3">
                                        <div class="text-muted small mb-1"><?= __('Mã chuyến') ?></div>
                                        <strong class="fs-14"><?= htmlspecialchars($shipment['shipment_code']) ?></strong>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="text-muted small mb-1"><?= __('Biển số xe') ?></div>
                                        <?= htmlspecialchars($shipment['truck_plate'] ?: '-') ?>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="text-muted small mb-1"><?= __('Tài xế') ?></div>
                                        <?= htmlspecialchars($shipment['driver_name'] ?: '-') ?>
                                        <?= $shipment['driver_phone'] ? '<div class="text-muted small">' . htmlspecialchars($shipment['driver_phone']) . '</div>' : '' ?>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="text-muted small mb-1"><?= __('Tuyến đường') ?></div>
                                        <?= htmlspecialchars($shipment['route'] ?: '-') ?>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="text-muted small mb-1"><?= __('Trọng tải') ?></div>
                                        <?= $shipment['max_weight'] > 0 ? fnum($shipment['max_weight'], 1) . ' kg' : '-' ?>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="text-muted small mb-1"><?= __('Chi phí') ?></div>
                                        <?= $shipment['shipping_cost'] > 0 ? fnum($shipment['shipping_cost'], 0) : '-' ?>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="text-muted small mb-1"><?= __('Người tạo') ?></div>
                                        <?= htmlspecialchars($shipment['creator_name'] ?? '-') ?>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="text-muted small mb-1"><?= __('Ngày tạo') ?></div>
                                        <?= date('d/m/Y H:i', strtotime($shipment['create_date'])) ?>
                                    </div>
                                    <?php if ($shipment['departed_date']): ?>
                                    <div class="col-6 col-md-3">
                                        <div class="text-muted small mb-1"><?= __('Ngày xuất phát') ?></div>
                                        <?= date('d/m/Y H:i', strtotime($shipment['departed_date'])) ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($shipment['arrived_date']): ?>
                                    <div class="col-6 col-md-3">
                                        <div class="text-muted small mb-1"><?= __('Ngày đến') ?></div>
                                        <?= date('d/m/Y H:i', strtotime($shipment['arrived_date'])) ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($shipment['note']): ?>
                                    <div class="col-12">
                                        <div class="text-muted small mb-1"><?= __('Ghi chú') ?></div>
                                        <?= nl2br(htmlspecialchars($shipment['note'])) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <!-- Stats -->
                            <div class="col-12 col-xl-3">
                                <div class="p-3 bg-light rounded text-center">
                                    <div class="d-flex justify-content-around mb-2">
                                        <div><h5 class="mb-0" id="info-packages"><?= $shipment['total_packages'] ?></h5><small class="text-muted"><?= __('Kiện') ?></small></div>
                                        <div class="vr"></div>
                                        <div><h5 class="mb-0 text-primary" id="info-weight"><?= fnum($shipment['total_weight'], 1) ?></h5><small class="text-muted">kg</small></div>
                                        <div class="vr"></div>
                                        <div><h5 class="mb-0 text-info" id="info-cbm"><?= fnum($shipment['total_cbm'], 2) ?></h5><small class="text-muted">m³</small></div>
                                    </div>
                                    <?php if ($shipment['max_weight'] > 0): ?>
                                    <?php $pct = min(100, round($shipment['total_weight'] / $shipment['max_weight'] * 100)); ?>
                                    <div class="progress mb-1" style="height:6px;">
                                        <div class="progress-bar <?= $pct > 90 ? 'bg-danger' : ($pct > 70 ? 'bg-warning' : 'bg-success') ?>" style="width:<?= $pct ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?= $pct ?>% <?= __('trọng tải') ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Package List - Full width -->
            <div class="col-12">
                <div class="card">
                    <?php
                        // Group packages by mã hàng (bag_code > product_code > order_code)
                        $colSpan = $isPreparing ? 11 : 10;
                        $bagStatusLabels = [
                            'sealed' => ['label' => 'Chờ vận chuyển', 'bg' => 'warning', 'icon' => 'ri-time-line'],
                            'loading' => ['label' => 'Đang xếp xe', 'bg' => 'secondary', 'icon' => 'ri-truck-line'],
                            'shipping' => ['label' => 'Đang vận chuyển', 'bg' => 'primary', 'icon' => 'ri-ship-line'],
                            'arrived' => ['label' => 'Đã đến kho VN', 'bg' => 'success', 'icon' => 'ri-check-double-line'],
                        ];
                        $grouped = [];
                        foreach ($packages as $pkg) {
                            $key = $pkg['bag_code'] ?: ($pkg['product_code'] ?: ($pkg['order_code'] ?: __('Không xác định')));
                            if (!isset($grouped[$key])) {
                                $grouped[$key] = [
                                    'pkgs' => [],
                                    'product_name' => $pkg['product_name'] ?? '',
                                    'product_image' => $pkg['product_image'] ?? '',
                                    'create_date' => $pkg['create_date'] ?? '',
                                    'customer' => $pkg['customer_name'] ?: '',
                                    'customer_code' => '',
                                    'is_bag' => !empty($pkg['bag_code']),
                                    'bag_status' => $pkg['bag_status'] ?? '',
                                    'bag_weight' => $pkg['bag_weight'] ?? 0,
                                    'bag_cbm' => (($pkg['bag_length'] ?? 0) * ($pkg['bag_width'] ?? 0) * ($pkg['bag_height'] ?? 0)) / 1000000,
                                ];
                            }
                            $grouped[$key]['pkgs'][] = $pkg;
                        }
                    ?>
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0">
                            <?= __('Danh sách mã hàng') ?>
                            <span class="text-muted fw-normal fs-13 ms-1">(<?= count($grouped) ?> <?= __('mã') ?> &mdash; <?= count($packages) ?> <?= __('kiện') ?>)</span>
                        </h5>
                        <div class="d-flex gap-2">
                            <?php if ($isPreparing): ?>
                            <a href="<?= base_url('staffcn/shipments-scan') ?>?id=<?= $id ?>" class="btn btn-primary"><i class="ri-qr-scan-2-line me-1"></i><?= __('Quét xếp hàng') ?></a>
                            <?php endif; ?>
                            <a href="<?= base_url('ajaxs/staffcn/shipments-export.php?id=' . $id) ?>" class="btn btn-success" target="_blank"><i class="ri-file-excel-2-line me-1"></i><?= __('Xuất Excel') ?></a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?= __('Ngày tạo') ?></th>
                                        <th><?= __('Mã hàng') ?></th>
                                        <th><?= __('Sản phẩm') ?></th>
                                        <th><?= __('Số kiện') ?></th>
                                        <th><?= __('Tổng cân') ?></th>
                                        <th><?= __('Tổng khối') ?></th>
                                        <th><?= __('Ảnh') ?></th>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th><?= __('Trạng thái') ?></th>
                                        <?php if ($isPreparing): ?>
                                        <th></th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($packages)): ?>
                                    <tr><td colspan="<?= $colSpan ?>" class="text-center text-muted py-4"><?= __('Chưa có kiện hàng nào') ?></td></tr>
                                    <?php endif; ?>
                                    <?php $gIdx = 0; foreach ($grouped as $maHang => $group): $gIdx++;
                                        $pkgList = $group['pkgs'];
                                        if ($group['is_bag']) {
                                            $totalW = floatval($group['bag_weight']);
                                            $totalCbm = floatval($group['bag_cbm']);
                                        } else {
                                            $totalW = array_sum(array_column($pkgList, 'weight_charged'));
                                            $totalCbm = 0;
                                            foreach ($pkgList as $p) { $totalCbm += ($p['length_cm'] * $p['width_cm'] * $p['height_cm']) / 1000000; }
                                        }
                                        $groupId = 'shipgrp-' . $gIdx;
                                    ?>
                                    <tr>
                                        <td><?= $gIdx ?></td>
                                        <td><?= $group['create_date'] ? date('d/m/Y', strtotime($group['create_date'])) : '' ?></td>
                                        <td><strong><?= htmlspecialchars($maHang) ?></strong></td>
                                        <td><?php $pn = $group['product_name'] ?? ''; echo htmlspecialchars(mb_strlen($pn) > 35 ? mb_substr($pn, 0, 35) . '…' : $pn); ?></td>
                                        <td>
                                            <a href="#" class="btn-expand-shipgrp text-decoration-none" data-group="<?= $groupId ?>">
                                                <?= count($pkgList) ?> <i class="ri-arrow-down-s-line expand-icon-<?= $groupId ?> fs-14"></i>
                                            </a>
                                        </td>
                                        <td><?= $totalW > 0 ? fnum($totalW, 2) . ' kg' : '' ?></td>
                                        <td><?= $totalCbm > 0 ? fnum($totalCbm, 2) . ' m³' : '' ?></td>
                                        <td class="text-center">
                                            <?php if (!empty($group['product_image'])):
                                                $grpImgArr = array_filter(array_map('trim', explode(',', $group['product_image'])));
                                                $grpImgUrls = array_map('get_upload_url', $grpImgArr);
                                                $grpThumb = $grpImgUrls[0];
                                                $grpImgCount = count($grpImgArr);
                                            ?>
                                            <a href="#" class="btn-view-images position-relative d-inline-block" data-images="<?= htmlspecialchars(json_encode(array_values($grpImgUrls))) ?>">
                                                <img src="<?= $grpThumb ?>" class="rounded" style="width:40px;height:40px;object-fit:cover;">
                                                <?php if ($grpImgCount > 1): ?>
                                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary" style="font-size:10px;"><?= $grpImgCount ?></span>
                                                <?php endif; ?>
                                            </a>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($group['customer']) ?>
                                        </td>
                                        <td>
                                            <?php if ($group['is_bag'] && !empty($group['bag_status']) && isset($bagStatusLabels[$group['bag_status']])):
                                                $bsl = $bagStatusLabels[$group['bag_status']]; ?>
                                            <span class="badge bg-<?= $bsl['bg'] ?>-subtle text-<?= $bsl['bg'] ?>" style="font-size:11px;"><i class="<?= $bsl['icon'] ?> me-1"></i><?= __($bsl['label']) ?></span>
                                            <?php else: ?>
                                            <?= display_package_status($pkgList[0]['status']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($isPreparing): ?>
                                        <td>
                                            <button class="btn btn-sm btn-outline-danger btn-remove-group"
                                                data-ids="<?= htmlspecialchars(json_encode(array_column($pkgList, 'id'))) ?>"
                                                data-label="<?= htmlspecialchars($maHang) ?>"
                                                title="<?= __('Xóa mã hàng khỏi chuyến') ?>">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <!-- Expand row -->
                                    <tr class="shipgrp-expand d-none" id="expand-<?= $groupId ?>">
                                        <td colspan="<?= $colSpan ?>" class="p-0">
                                            <div class="px-4 py-2 bg-light">
                                                <table class="table table-sm table-borderless mb-0 text-muted">
                                                    <thead><tr>
                                                        <th><?= __('Mã kiện') ?></th>
                                                        <th><?= __('Cân nặng') ?></th>
                                                        <th><?= __('Kích thước') ?></th>
                                                        <th><?= __('Số khối') ?></th>
                                                        <?php if ($isPreparing): ?><th></th><?php endif; ?>
                                                    </tr></thead>
                                                    <tbody>
                                                    <?php foreach ($pkgList as $pkg):
                                                        $cbm = ($pkg['length_cm'] * $pkg['width_cm'] * $pkg['height_cm']) / 1000000;
                                                    ?>
                                                    <tr>
                                                        <td><strong><?= htmlspecialchars($pkg['package_code']) ?></strong></td>
                                                        <td><?= $pkg['weight_charged'] > 0 ? fnum($pkg['weight_charged'], 2) . ' kg' : '' ?></td>
                                                        <td><?= ($pkg['length_cm'] > 0) ? floatval($pkg['length_cm']) . '×' . floatval($pkg['width_cm']) . '×' . floatval($pkg['height_cm']) : '' ?></td>
                                                        <td><?= $cbm > 0 ? fnum($cbm, 2) . ' m³' : '' ?></td>
                                                        <?php if ($isPreparing): ?>
                                                        <td><button class="btn btn-sm btn-outline-danger btn-remove-pkg" data-id="<?= $pkg['id'] ?>" data-code="<?= htmlspecialchars($pkg['package_code']) ?>"><i class="ri-close-line"></i></button></td>
                                                        <?php endif; ?>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                    </tbody>
                                                </table>
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

<!-- Modal Change Status -->
<div class="modal fade" id="modal-change-status" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('Đổi trạng thái') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <select class="form-select" id="select-new-status">
                    <?php foreach ($statusLabels as $key => $s):
                        if (in_array($key, ['arrived', 'completed'])) continue;
                    ?>
                    <option value="<?= $key ?>" <?= $shipment['status'] === $key ? 'selected' : '' ?>><?= __($s['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Hủy') ?></button>
                <button type="button" class="btn btn-primary" id="btn-save-status"><i class="ri-check-line me-1"></i><?= __('Cập nhật') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit Shipment -->
<div class="modal fade" id="modal-edit-shipment" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('Sửa thông tin chuyến xe') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label"><?= __('Biển số xe') ?></label>
                        <input type="text" class="form-control" id="edit-truck-plate" value="<?= htmlspecialchars($shipment['truck_plate'] ?? '') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label"><?= __('Tài xế') ?></label>
                        <input type="text" class="form-control" id="edit-driver-name" value="<?= htmlspecialchars($shipment['driver_name'] ?? '') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label"><?= __('SĐT tài xế') ?></label>
                        <input type="text" class="form-control" id="edit-driver-phone" value="<?= htmlspecialchars($shipment['driver_phone'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label"><?= __('Tuyến đường') ?></label>
                        <input type="text" class="form-control" id="edit-route" value="<?= htmlspecialchars($shipment['route'] ?? '') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label"><?= __('Trọng tải tối đa (kg)') ?></label>
                        <input type="number" class="form-control" id="edit-max-weight" step="0.01" value="<?= $shipment['max_weight'] ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label"><?= __('Chi phí vận chuyển') ?></label>
                        <input type="number" class="form-control" id="edit-shipping-cost" step="0.01" value="<?= $shipment['shipping_cost'] ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label"><?= __('Ghi chú') ?></label>
                        <textarea class="form-control" id="edit-note" rows="2"><?= htmlspecialchars($shipment['note'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Hủy') ?></button>
                <button type="button" class="btn btn-primary" id="btn-save-edit"><i class="ri-save-line me-1"></i><?= __('Lưu') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Image Gallery Modal -->
<div class="modal fade" id="imageGalleryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark border-0">
            <div class="modal-header border-0 py-2">
                <span class="text-white-50 fs-12" id="gallery-counter"></span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="imageCarousel" class="carousel slide" data-bs-touch="true">
                    <div class="carousel-inner" id="carousel-items"></div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#imageCarousel" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
                    <button class="carousel-control-next" type="button" data-bs-target="#imageCarousel" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
$(function(){
    var csrfToken = '<?= (new Csrf())->get_token_value() ?>';
    var ajaxUrl = '<?= base_url('ajaxs/staffcn/shipments.php') ?>';
    var shipmentId = <?= $id ?>;

    // Change status
    $('#btn-change-status').on('click', function(){ $('#modal-change-status').modal('show'); });
    $('#btn-save-status').on('click', function(){
        var newStatus = $('#select-new-status').val();
        if (newStatus === '<?= $shipment['status'] ?>') {
            $('#modal-change-status').modal('hide');
            return;
        }
        var btn = $(this);
        btn.prop('disabled', true);
        $.post(ajaxUrl, {request_name: 'update_status', shipment_id: shipmentId, new_status: newStatus, csrf_token: csrfToken}, function(res){
            if (res.status === 'success') {
                $('#modal-change-status').modal('hide');
                Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
            } else {
                Swal.fire({icon: 'error', text: res.msg});
                btn.prop('disabled', false);
            }
        }, 'json');
    });

    // Edit
    $('#btn-edit-shipment').on('click', function(){ $('#modal-edit-shipment').modal('show'); });
    $('#btn-save-edit').on('click', function(){
        var btn = $(this);
        btn.prop('disabled', true);
        $.post(ajaxUrl, {
            request_name: 'edit', id: shipmentId,
            truck_plate: $('#edit-truck-plate').val(),
            driver_name: $('#edit-driver-name').val(),
            driver_phone: $('#edit-driver-phone').val(),
            route: $('#edit-route').val(),
            max_weight: $('#edit-max-weight').val(),
            shipping_cost: $('#edit-shipping-cost').val(),
            note: $('#edit-note').val(),
            csrf_token: csrfToken
        }, function(res){
            if (res.status === 'success') {
                Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
            } else {
                Swal.fire({icon: 'error', title: 'Error', text: res.msg});
                btn.prop('disabled', false);
            }
        }, 'json');
    });

    // Remove package
    $(document).on('click', '.btn-remove-pkg', function(){
        var pkgId = $(this).data('id');
        var code = $(this).data('code');
        Swal.fire({
            title: '<?= __('Gỡ kiện khỏi chuyến?') ?>',
            html: code,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<?= __('Gỡ') ?>',
            cancelButtonText: '<?= __('Hủy') ?>'
        }).then(function(result){
            if (result.isConfirmed) {
                $.post(ajaxUrl, {request_name: 'remove_package', shipment_id: shipmentId, package_id: pkgId, csrf_token: csrfToken}, function(res){
                    if (res.status === 'success') {
                        Swal.fire({icon: 'success', title: res.msg, timer: 1200, showConfirmButton: false}).then(function(){ location.reload(); });
                    } else {
                        Swal.fire({icon: 'error', text: res.msg});
                    }
                }, 'json');
            }
        });
    });


    // Remove entire group (all packages in a mã hàng)
    $(document).on('click', '.btn-remove-group', function(){
        var ids = $(this).data('ids');
        var label = $(this).data('label');
        Swal.fire({
            title: '<?= __('Xóa mã hàng khỏi chuyến?') ?>',
            html: '<strong>' + $('<span>').text(label).html() + '</strong><br><small class="text-muted">' + ids.length + ' <?= __('kiện') ?></small>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: '<?= __('Xóa') ?>',
            cancelButtonText: '<?= __('Hủy') ?>'
        }).then(function(result){
            if (!result.isConfirmed) return;
            var done = 0;
            var failed = 0;
            ids.forEach(function(pkgId){
                $.post(ajaxUrl, {request_name: 'remove_package', shipment_id: shipmentId, package_id: pkgId, csrf_token: csrfToken}, function(res){
                    if (res.status === 'success') done++; else failed++;
                    if (done + failed === ids.length) {
                        location.reload();
                    }
                }, 'json').fail(function(){ failed++; if (done + failed === ids.length) location.reload(); });
            });
        });
    });

    // Complete
    $('#btn-complete').on('click', function(){
        $.post(ajaxUrl, {request_name: 'update_status', shipment_id: shipmentId, new_status: 'completed', csrf_token: csrfToken}, function(res){
            if (res.status === 'success') {
                Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
            } else {
                Swal.fire({icon: 'error', text: res.msg});
            }
        }, 'json');
    });

    // Delete
    $('#btn-delete-shipment').on('click', function(){
        Swal.fire({
            title: '<?= __('Xác nhận xóa chuyến xe?') ?>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: '<?= __('Xóa') ?>',
            cancelButtonText: '<?= __('Hủy') ?>'
        }).then(function(result){
            if (result.isConfirmed) {
                $.post(ajaxUrl, {request_name: 'delete', id: shipmentId, csrf_token: csrfToken}, function(res){
                    if (res.status === 'success') {
                        Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){
                            window.location.href = '<?= base_url('staffcn/shipments-list') ?>';
                        });
                    } else {
                        Swal.fire({icon: 'error', text: res.msg});
                    }
                }, 'json');
            }
        });
    });
    // Image gallery
    var galleryCarousel = null, galleryTotal = 0;
    function updateGalleryCounter() {
        var idx = $('#imageCarousel .carousel-item.active').index();
        $('#gallery-counter').text((idx + 1) + ' / ' + galleryTotal);
        $('#imageCarousel .carousel-control-prev, #imageCarousel .carousel-control-next').toggleClass('d-none', galleryTotal <= 1);
    }
    $('#imageCarousel').on('slid.bs.carousel', updateGalleryCounter);
    $(document).on('click', '.btn-view-images', function(e){
        e.preventDefault();
        var images = $(this).data('images');
        if (!images || !images.length) return;
        galleryTotal = images.length;
        var html = '';
        images.forEach(function(url, i){
            html += '<div class="carousel-item' + (i === 0 ? ' active' : '') + '"><div class="d-flex align-items-center justify-content-center" style="min-height:300px;"><img src="' + url + '" class="d-block" style="max-width:100%;max-height:75vh;object-fit:contain;"></div></div>';
        });
        $('#carousel-items').html(html);
        if (galleryCarousel) galleryCarousel.dispose();
        galleryCarousel = new bootstrap.Carousel($('#imageCarousel')[0], { interval: false, touch: true, keyboard: true });
        updateGalleryCounter();
        new bootstrap.Modal($('#imageGalleryModal')[0]).show();
    });

    // Expand/collapse package groups (like orders-list)
    $(document).on('click', '.btn-expand-shipgrp', function(e){
        e.preventDefault();
        var grp = $(this).data('group');
        var $expand = $('#expand-' + grp);
        var $icon = $('.expand-icon-' + grp);
        $expand.toggleClass('d-none');
        if ($expand.hasClass('d-none')) {
            $icon.removeClass('ri-arrow-up-s-line').addClass('ri-arrow-down-s-line');
        } else {
            $icon.removeClass('ri-arrow-down-s-line').addClass('ri-arrow-up-s-line');
        }
    });
});
</script>
