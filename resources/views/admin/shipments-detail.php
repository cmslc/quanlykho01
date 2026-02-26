<?php
require_once(__DIR__.'/../../../models/is_admin.php');
require_once(__DIR__.'/../../../libs/csrf.php');
require_once(__DIR__.'/../../../libs/database/shipments.php');

$id = intval(input_get('id'));
$shipment = $ToryHub->get_row_safe("SELECT s.*, u.fullname as creator_name FROM `shipments` s LEFT JOIN `users` u ON s.created_by = u.id WHERE s.id = ?", [$id]);
if (!$shipment) {
    echo '<script>alert("Chuyến xe không tồn tại");window.location.href="' . base_url('admin/shipments-list') . '";</script>';
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
                        <button class="btn btn-success" id="btn-export-excel"><i class="ri-file-excel-2-line me-1"></i><?= __('Xuất Excel') ?></button>
                        <a href="<?= base_url('admin/shipments-list') ?>" class="btn btn-secondary"><i class="ri-arrow-left-line me-1"></i><?= __('Quay lại') ?></a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left: Shipment Info -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0"><?= __('Thông tin chuyến') ?></h5>
                        <span class="badge bg-<?= $cfg['bg'] ?> text-<?= $cfg['text'] ?> fs-12 px-2 py-1"><i class="<?= $cfg['icon'] ?> me-1"></i><?= __($cfg['label']) ?></span>
                        <button class="btn btn-sm btn-outline-secondary ms-2" id="btn-change-status" title="<?= __('Đổi trạng thái') ?>"><i class="ri-edit-line"></i></button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-borderless mb-0">
                                <tr><td class="text-muted" style="width:40%"><?= __('Mã chuyến') ?></td><td><strong><?= htmlspecialchars($shipment['shipment_code']) ?></strong></td></tr>
                                <tr><td class="text-muted"><?= __('Biển số xe') ?></td><td><?= htmlspecialchars($shipment['truck_plate'] ?: '-') ?></td></tr>
                                <tr><td class="text-muted"><?= __('Tài xế') ?></td><td><?= htmlspecialchars($shipment['driver_name'] ?: '-') ?><?= $shipment['driver_phone'] ? '<br><small>' . htmlspecialchars($shipment['driver_phone']) . '</small>' : '' ?></td></tr>
                                <tr><td class="text-muted"><?= __('Tuyến đường') ?></td><td><?= htmlspecialchars($shipment['route'] ?: '-') ?></td></tr>
                                <tr><td class="text-muted"><?= __('Trọng tải tối đa') ?></td><td><?= $shipment['max_weight'] > 0 ? fnum($shipment['max_weight'], 1) . ' kg' : '-' ?></td></tr>
                                <tr><td class="text-muted"><?= __('Chi phí') ?></td><td><?= $shipment['shipping_cost'] > 0 ? fnum($shipment['shipping_cost'], 0) : '-' ?></td></tr>
                                <tr><td class="text-muted"><?= __('Người tạo') ?></td><td><?= htmlspecialchars($shipment['creator_name'] ?? '-') ?></td></tr>
                                <tr><td class="text-muted"><?= __('Ngày tạo') ?></td><td><?= date('d/m/Y H:i', strtotime($shipment['create_date'])) ?></td></tr>
                                <?php if ($shipment['departed_date']): ?>
                                <tr><td class="text-muted"><?= __('Ngày xuất phát') ?></td><td><?= date('d/m/Y H:i', strtotime($shipment['departed_date'])) ?></td></tr>
                                <?php endif; ?>
                                <?php if ($shipment['arrived_date']): ?>
                                <tr><td class="text-muted"><?= __('Ngày đến') ?></td><td><?= date('d/m/Y H:i', strtotime($shipment['arrived_date'])) ?></td></tr>
                                <?php endif; ?>
                                <?php if ($shipment['note']): ?>
                                <tr><td class="text-muted"><?= __('Ghi chú') ?></td><td><?= nl2br(htmlspecialchars($shipment['note'])) ?></td></tr>
                                <?php endif; ?>
                            </table>
                        </div>

                        <!-- Summary -->
                        <div class="mt-3 p-3 bg-light rounded">
                            <div class="row text-center">
                                <div class="col-4">
                                    <h5 class="mb-1" id="info-packages"><?= $shipment['total_packages'] ?></h5>
                                    <small class="text-muted"><?= __('Kiện') ?></small>
                                </div>
                                <div class="col-4">
                                    <h5 class="mb-1 text-primary" id="info-weight"><?= fnum($shipment['total_weight'], 1) ?></h5>
                                    <small class="text-muted">kg</small>
                                </div>
                                <div class="col-4">
                                    <h5 class="mb-1 text-info" id="info-cbm"><?= fnum($shipment['total_cbm'], 2) ?></h5>
                                    <small class="text-muted">m³</small>
                                </div>
                            </div>
                            <?php if ($shipment['max_weight'] > 0): ?>
                            <?php $pct = min(100, round($shipment['total_weight'] / $shipment['max_weight'] * 100)); ?>
                            <div class="mt-2">
                                <div class="progress" style="height:6px;">
                                    <div class="progress-bar <?= $pct > 90 ? 'bg-danger' : ($pct > 70 ? 'bg-warning' : 'bg-success') ?>" style="width:<?= $pct ?>%"></div>
                                </div>
                                <small class="text-muted"><?= $pct ?>% <?= __('trọng tải') ?></small>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Action buttons -->
                        <div class="mt-3 d-flex gap-2 flex-wrap">
                            <button class="btn btn-primary btn-sm" id="btn-edit-shipment"><i class="ri-pencil-line me-1"></i><?= __('Sửa') ?></button>
                            <?php if ($isPreparing): ?>
                            <button class="btn btn-danger btn-sm" id="btn-delete-shipment"><i class="ri-delete-bin-line me-1"></i><?= __('Xóa') ?></button>
                            <?php elseif ($shipment['status'] === 'arrived'): ?>
                            <button class="btn btn-secondary btn-sm" id="btn-complete"><i class="ri-check-double-line me-1"></i><?= __('Hoàn thành') ?></button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Package List -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Danh sách kiện hàng') ?> (<?= count($packages) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Group packages by mã hàng (bag_code > product_code > order_code)
                        $colSpan = $isPreparing ? 10 : 9;
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
                                    'customer_code' => $pkg['customer_code'] ?: '',
                                    'is_bag' => !empty($pkg['bag_code']),
                                    'bag_status' => $pkg['bag_status'] ?? '',
                                    'bag_weight' => $pkg['bag_weight'] ?? 0,
                                    'bag_cbm' => (($pkg['bag_length'] ?? 0) * ($pkg['bag_width'] ?? 0) * ($pkg['bag_height'] ?? 0)) / 1000000,
                                ];
                            }
                            $grouped[$key]['pkgs'][] = $pkg;
                        }
                        ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?= __('Mã hàng') ?></th>
                                        <th><?= __('Ảnh') ?></th>
                                        <th><?= __('Sản phẩm') ?></th>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th><?= __('Tổng cân') ?></th>
                                        <th><?= __('Tổng khối') ?></th>
                                        <th><?= __('Ngày tạo') ?></th>
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
                                        <td>
                                            <strong><?= htmlspecialchars($maHang) ?></strong>
                                            <div class="mt-1">
                                                <a href="#" class="btn-expand-shipgrp text-muted text-decoration-none" data-group="<?= $groupId ?>">
                                                    <i class="ri-archive-line"></i> <?= count($pkgList) ?> <?= __('kiện') ?> <i class="ri-arrow-down-s-line expand-icon-<?= $groupId ?> fs-14"></i>
                                                </a>
                                            </div>
                                        </td>
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
                                        <td><?php $pn = $group['product_name'] ?? ''; echo htmlspecialchars(mb_strlen($pn) > 35 ? mb_substr($pn, 0, 35) . '…' : $pn); ?></td>
                                        <td>
                                            <?= htmlspecialchars($group['customer_code']) ?>
                                            <?php if ($group['customer']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($group['customer']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $totalW > 0 ? fnum($totalW, 2) . ' kg' : '' ?></td>
                                        <td><?= $totalCbm > 0 ? fnum($totalCbm, 2) . ' m³' : '' ?></td>
                                        <td><?= $group['create_date'] ? date('d/m/Y', strtotime($group['create_date'])) : '' ?></td>
                                        <td>
                                            <?php if ($group['is_bag'] && !empty($group['bag_status']) && isset($bagStatusLabels[$group['bag_status']])):
                                                $bsl = $bagStatusLabels[$group['bag_status']]; ?>
                                            <span class="badge bg-<?= $bsl['bg'] ?>-subtle text-<?= $bsl['bg'] ?>" style="font-size:11px;"><i class="<?= $bsl['icon'] ?> me-1"></i><?= __($bsl['label']) ?></span>
                                            <?php else: ?>
                                            <?= display_package_status($pkgList[0]['status']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($isPreparing): ?>
                                        <td></td>
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
                    <?php foreach ($statusLabels as $key => $s): ?>
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

<?php
$pkgStatusText = [
    'cn_warehouse' => 'Tại kho TQ', 'packed' => 'Đã đóng bao',
    'shipping' => 'Đang vận chuyển', 'vn_warehouse' => 'Tại kho VN',
    'delivered' => 'Đã giao', 'cancelled' => 'Đã hủy',
];
$bagStatusText = [
    'sealed' => 'Chờ vận chuyển', 'loading' => 'Đang xếp xe',
    'shipping' => 'Đang vận chuyển', 'arrived' => 'Đã đến kho VN',
];
$exportRows = [];
$stt = 0;
foreach ($grouped as $maHang => $group) {
    $stt++;
    $pkgList = $group['pkgs'];
    if ($group['is_bag']) {
        $expW = round(floatval($group['bag_weight']), 2);
        $expCbm = round(floatval($group['bag_cbm']), 4);
        $expStatus = $bagStatusText[$group['bag_status']] ?? $group['bag_status'];
    } else {
        $expW = round(array_sum(array_column($pkgList, 'weight_charged')), 2);
        $expCbm = 0;
        foreach ($pkgList as $p) { $expCbm += ($p['length_cm'] * $p['width_cm'] * $p['height_cm']) / 1000000; }
        $expCbm = round($expCbm, 4);
        $expStatus = $pkgStatusText[$pkgList[0]['status']] ?? $pkgList[0]['status'];
    }
    $exportRows[] = [
        $stt,
        $maHang,
        $group['product_name'],
        $group['customer_code'] . ($group['customer'] ? ' - '.$group['customer'] : ''),
        count($pkgList),
        $expW > 0 ? $expW : '',
        $expCbm > 0 ? $expCbm : '',
        $group['create_date'] ? date('d/m/Y', strtotime($group['create_date'])) : '',
        $expStatus,
    ];
}
?>
<?php require_once(__DIR__.'/footer.php'); ?>

<script>
$(function(){
    var csrfToken = '<?= (new Csrf())->get_token_value() ?>';
    var ajaxUrl = '<?= base_url('ajaxs/admin/shipments.php') ?>';
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
                            window.location.href = '<?= base_url('admin/shipments-list') ?>';
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

    // Export Excel
    $('#btn-export-excel').on('click', function(){
        var rows = [['STT','Mã hàng','Sản phẩm','Khách hàng','Số kiện','Tổng cân (kg)','Tổng khối (m³)','Ngày tạo','Trạng thái']]
            .concat(<?= json_encode($exportRows, JSON_UNESCAPED_UNICODE) ?>);
        function xlsEsc(v) {
            if (v === null || v === undefined) return '';
            return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
        var xml = '<?xml version="1.0" encoding="UTF-8"?>\n'
            + '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">\n'
            + '<Styles><Style ss:ID="H"><Font ss:Bold="1"/></Style></Styles>\n'
            + '<Worksheet ss:Name="Sheet1"><Table>\n';
        rows.forEach(function(row, ri){
            xml += '<Row>';
            row.forEach(function(cell){
                var t = (typeof cell === 'number') ? 'Number' : 'String';
                var s = (ri === 0) ? ' ss:StyleID="H"' : '';
                xml += '<Cell' + s + '><Data ss:Type="' + t + '">' + xlsEsc(cell) + '</Data></Cell>';
            });
            xml += '</Row>\n';
        });
        xml += '</Table></Worksheet></Workbook>';
        var blob = new Blob([xml], { type: 'application/vnd.ms-excel;charset=utf-8;' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'ChuyenXe_<?= $shipment['shipment_code'] ?>_<?= date('Y-m-d') ?>.xls';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
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
