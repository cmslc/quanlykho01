<?php
require_once(__DIR__.'/../../../models/is_staffvn.php');
require_once(__DIR__.'/../../../libs/database/shipments.php');

$id = intval(input_get('id'));
$shipment = $ToryHub->get_row_safe("SELECT s.*, u.fullname as creator_name FROM `shipments` s LEFT JOIN `users` u ON s.created_by = u.id WHERE s.id = ?", [$id]);
if (!$shipment) {
    echo '<script>alert("' . __('Chuyến xe không tồn tại') . '");window.location.href="' . base_url('staffvn/orders-list') . '";</script>';
    exit;
}

$page_title = __('Chi tiết chuyến xe') . ' - ' . $shipment['shipment_code'];

$ShipmentsDB = new Shipments();
$packages = $ShipmentsDB->getPackages($id);

$statusLabels = [
    'preparing'  => ['label' => 'Đang chuẩn bị', 'bg' => 'info-subtle', 'text' => 'info', 'icon' => 'ri-loader-4-line'],
    'in_transit' => ['label' => 'Vận chuyển', 'bg' => 'primary-subtle', 'text' => 'primary', 'icon' => 'ri-truck-line'],
    'arrived'    => ['label' => 'Đã đến', 'bg' => 'success-subtle', 'text' => 'success', 'icon' => 'ri-map-pin-2-line'],
    'completed'  => ['label' => 'Hoàn thành', 'bg' => 'secondary-subtle', 'text' => 'secondary', 'icon' => 'ri-check-double-line'],
];
$cfg = $statusLabels[$shipment['status']] ?? $statusLabels['in_transit'];

// Group packages by mã hàng
$grouped = [];
foreach ($packages as $pkg) {
    $key = $pkg['bag_code'] ?: ($pkg['product_code'] ?: ($pkg['order_code'] ?: __('Không xác định')));
    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'pkgs' => [],
            'customer' => $pkg['customer_name'] ?: '',
            'is_bag' => !empty($pkg['bag_code']),
            'is_wholesale' => ($pkg['product_type'] ?? '') === 'wholesale',
            'cn_tracking' => $pkg['cn_tracking'] ?? '',
            'order_status' => $pkg['order_status'] ?? '',
            'bag_weight' => $pkg['bag_weight'] ?? 0,
            'bag_cbm' => (($pkg['bag_length'] ?? 0) * ($pkg['bag_width'] ?? 0) * ($pkg['bag_height'] ?? 0)) / 1000000,
        ];
    }
    $grouped[$key]['pkgs'][] = $pkg;
}

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Chi tiết chuyến xe') ?>: <?= htmlspecialchars($shipment['shipment_code']) ?></h4>
                    <div class="d-flex gap-2">
                        <a href="<?= base_url('ajaxs/staffvn/shipments-export.php') ?>?id=<?= $id ?>" class="btn btn-success btn-sm"><i class="ri-file-excel-2-line me-1"></i><?= __('Xuất Excel') ?></a>
                        <a href="<?= base_url('staffvn/orders-list') ?>" class="btn btn-secondary btn-sm"><i class="ri-arrow-left-line me-1"></i><?= __('Quay lại') ?></a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Shipment Info -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0"><?= __('Thông tin chuyến') ?></h5>
                        <span class="badge bg-<?= $cfg['bg'] ?> text-<?= $cfg['text'] ?> fs-12 px-2 py-1"><i class="<?= $cfg['icon'] ?> me-1"></i><?= __($cfg['label']) ?></span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <table class="table table-borderless table-sm mb-0">
                                    <tr><td class="text-muted" style="width:30%"><?= __('Biển số xe') ?></td><td><?= htmlspecialchars($shipment['truck_plate'] ?: '-') ?></td></tr>
                                    <tr><td class="text-muted"><?= __('Tài xế') ?></td><td><?= htmlspecialchars($shipment['driver_name'] ?: '-') ?><?= $shipment['driver_phone'] ? ' · <a href="tel:' . htmlspecialchars($shipment['driver_phone']) . '">' . htmlspecialchars($shipment['driver_phone']) . '</a>' : '' ?></td></tr>
                                    <tr><td class="text-muted"><?= __('Tuyến đường') ?></td><td><?= htmlspecialchars($shipment['route'] ?: '-') ?></td></tr>
                                    <?php if ($shipment['departed_date']): ?>
                                    <tr><td class="text-muted"><?= __('Xuất phát') ?></td><td><?= date('d/m/Y H:i', strtotime($shipment['departed_date'])) ?></td></tr>
                                    <?php endif; ?>
                                    <?php if ($shipment['arrived_date']): ?>
                                    <tr><td class="text-muted"><?= __('Đến nơi') ?></td><td><?= date('d/m/Y H:i', strtotime($shipment['arrived_date'])) ?></td></tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <h5 class="mb-1"><?= count($grouped) ?></h5>
                                            <small class="text-muted"><?= __('Mã hàng') ?></small>
                                        </div>
                                        <div class="col-4">
                                            <h5 class="mb-1 text-primary"><?= $shipment['total_packages'] ?></h5>
                                            <small class="text-muted"><?= __('Kiện') ?></small>
                                        </div>
                                        <div class="col-4">
                                            <h5 class="mb-1 text-info"><?= fnum($shipment['total_weight'], 1) ?></h5>
                                            <small class="text-muted">kg</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Package List -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="ri-list-check-2 me-1"></i><?= __('Danh sách mã hàng') ?> (<?= count($grouped) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?= __('Mã hàng') ?></th>
                                        <th><?= __('Mã vận đơn') ?></th>
                                        <th><?= __('Loại hàng') ?></th>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th><?= __('Số kiện') ?></th>
                                        <th><?= __('Tổng cân') ?></th>
                                        <th><?= __('Tổng khối') ?></th>
                                        <th><?= __('Trạng thái') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($grouped)): ?>
                                    <tr><td colspan="9" class="text-center text-muted py-4"><?= __('Chưa có kiện hàng nào') ?></td></tr>
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
                                        $groupId = 'grp-' . $gIdx;
                                    ?>
                                    <tr class="pkg-row" style="cursor:pointer;" data-group="<?= $groupId ?>">
                                        <td class="align-middle text-muted"><?= $gIdx ?></td>
                                        <td class="align-middle">
                                            <strong><?= htmlspecialchars($maHang) ?></strong>
                                            <i class="ri-arrow-right-s-line fs-14 expand-icon-<?= $groupId ?> text-muted ms-1"></i>
                                        </td>
                                        <td class="align-middle"><?= !empty($group['cn_tracking']) ? htmlspecialchars($group['cn_tracking']) : '<span class="text-muted">-</span>' ?></td>
                                        <td class="align-middle"><?php if ($group['is_bag']): ?><span class="badge bg-info-subtle text-info"><?= __('Hàng lẻ') ?></span><?php else: ?><span class="badge bg-warning-subtle text-warning"><?= __('Hàng lô') ?></span><?php endif; ?></td>
                                        <td class="align-middle"><?= htmlspecialchars($group['customer']) ?></td>
                                        <td class="align-middle"><?= count($pkgList) ?></td>
                                        <td class="align-middle"><?= $totalW > 0 ? fnum($totalW, 2) . ' kg' : '' ?></td>
                                        <td class="align-middle"><?= $totalCbm > 0 ? fnum($totalCbm, 2) . ' m³' : '' ?></td>
                                        <td class="align-middle"><?= $group['is_wholesale'] && $group['order_status'] ? display_order_status($group['order_status']) : display_package_status($pkgList[0]['status']) ?></td>
                                    </tr>
                                    <tr class="d-none" id="expand-<?= $groupId ?>">
                                        <td colspan="9" class="p-0">
                                            <div class="px-4 py-2 bg-light">
                                                <table class="table table-sm table-borderless mb-0 text-muted">
                                                    <thead><tr>
                                                        <th>#</th>
                                                        <th><?= $group['is_bag'] ? __('Mã vận đơn') : __('Mã kiện') ?></th>
                                                        <th><?= __('Cân nặng') ?></th>
                                                        <th><?= __('Kích thước') ?></th>
                                                        <th><?= __('Số khối') ?></th>
                                                        <th><?= __('Trạng thái') ?></th>
                                                    </tr></thead>
                                                    <tbody>
                                                    <?php $pIdx = 0; foreach ($pkgList as $pkg): $pIdx++;
                                                        $cbm = ($pkg['length_cm'] * $pkg['width_cm'] * $pkg['height_cm']) / 1000000;
                                                    ?>
                                                    <tr>
                                                        <td><?= $pIdx ?></td>
                                                        <td><strong><?= htmlspecialchars($group['is_bag'] ? ($pkg['tracking_cn'] ?: '-') : $pkg['package_code']) ?></strong></td>
                                                        <td><?= $pkg['weight_charged'] > 0 ? fnum($pkg['weight_charged'], 2) . ' kg' : '' ?></td>
                                                        <td><?= ($pkg['length_cm'] > 0) ? floatval($pkg['length_cm']) . '×' . floatval($pkg['width_cm']) . '×' . floatval($pkg['height_cm']) : '' ?></td>
                                                        <td><?= $cbm > 0 ? fnum($cbm, 2) . ' m³' : '' ?></td>
                                                        <td><?= display_package_status($pkg['status']) ?></td>
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

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
$(function(){
    // Expand rows
    $(document).on('click', '.pkg-row', function(){
        var grp = $(this).data('group');
        $('#expand-' + grp).toggleClass('d-none');
        var $icon = $('.expand-icon-' + grp);
        if ($('#expand-' + grp).hasClass('d-none')) {
            $icon.removeClass('ri-arrow-down-s-line').addClass('ri-arrow-right-s-line');
        } else {
            $icon.removeClass('ri-arrow-right-s-line').addClass('ri-arrow-down-s-line');
        }
    });
});
</script>
