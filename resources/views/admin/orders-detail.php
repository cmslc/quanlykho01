<?php
require_once(__DIR__.'/../../../models/is_admin.php');
require_once(__DIR__.'/../../../libs/csrf.php');
require_once(__DIR__.'/../../../libs/database/packages.php');

$id = intval(input_get('id'));
$order = $ToryHub->get_row_safe("SELECT o.*, c.fullname as customer_name, c.customer_code, c.phone as customer_phone
    FROM `orders` o LEFT JOIN `customers` c ON o.customer_id = c.id
    WHERE o.id = ?", [$id]);
if (!$order) {
    redirect(base_url('admin/orders-list'));
}

$productType = $order['product_type'] ?? 'retail';
$isRetail = $productType === 'retail';

// Get packages
$Packages = new Packages();
$order_packages = $Packages->getByOrder($id);

// Tracking codes from packages
$trackingCodes = array_filter(array_column($order_packages, 'tracking_cn'));

// Page title
if (!$isRetail && !empty($order['product_code'])) {
    $page_title = __('Chi tiết đơn') . ': ' . $order['product_code'];
} elseif (!empty($trackingCodes)) {
    $page_title = __('Chi tiết đơn') . ': ' . $trackingCodes[0];
} else {
    $page_title = __('Chi tiết đơn') . ' #' . $order['id'];
}

// Status history
$history = $ToryHub->get_list_safe("SELECT h.*, u.fullname as username FROM `order_status_history` h
    LEFT JOIN `users` u ON h.changed_by = u.id
    WHERE h.order_id = ? ORDER BY h.create_date ASC", [$id]);

$statuses = ['cn_warehouse', 'shipping', 'vn_warehouse', 'delivered', 'cancelled'];

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= htmlspecialchars($page_title) ?></h4>
                    <div>
                        <a href="<?= base_url('admin/orders-edit&id=' . $order['id']) ?>" class="btn btn-sm btn-warning"><i class="ri-pencil-line"></i> <?= __('Sửa') ?></a>
                        <a href="<?= base_url('admin/orders-list') ?>" class="btn btn-sm btn-secondary"><i class="ri-arrow-left-line"></i> <?= __('Quay lại') ?></a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Timeline -->
        <?php
        $statusLabels = [
            'cn_warehouse' => 'Đã về kho Trung Quốc',
            'shipping'     => 'Đang vận chuyển',
            'vn_warehouse' => 'Đã về kho Việt Nam',
            'delivered'    => 'Đã giao hàng',
        ];
        $flowKeys = array_keys($statusLabels);
        $currentIdx = array_search($order['status'], $flowKeys);
        $isCancelled = $order['status'] === 'cancelled';

        // Count packages per status + how many reached each timeline step
        $totalPkgs = count($order_packages);
        $pkgStatusCount = [];
        $statusRank = ['cn_warehouse' => 1, 'packed' => 2, 'loading' => 3, 'shipping' => 4, 'vn_warehouse' => 5, 'delivered' => 6];
        foreach ($order_packages as $p) {
            $st = $p['status'] ?? '';
            $pkgStatusCount[$st] = ($pkgStatusCount[$st] ?? 0) + 1;
        }
        // Count packages that have reached each timeline step
        $pkgReached = [];
        foreach ($flowKeys as $step) {
            $stepRank = $statusRank[$step] ?? 0;
            $count = 0;
            foreach ($order_packages as $p) {
                $pRank = $statusRank[$p['status'] ?? ''] ?? 0;
                if ($pRank >= $stepRank) $count++;
            }
            $pkgReached[$step] = $count;
        }
        $hasMultipleStatuses = count(array_filter($pkgStatusCount)) > 1;
        ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <?php foreach ($flowKeys as $idx => $s):
                                $isCompleted = !$isCancelled && $currentIdx !== false && $idx <= $currentIdx;
                                $isCurrent = $order['status'] === $s;
                                $bgClass = $isCompleted ? 'bg-success' : 'bg-light text-muted';
                                if ($isCurrent) $bgClass = 'bg-primary';
                                $reached = $pkgReached[$s] ?? 0;
                            ?>
                                <div class="text-center flex-fill">
                                    <div class="avatar-sm mx-auto mb-1">
                                        <span class="avatar-title <?= $bgClass ?> rounded-circle fs-5">
                                            <?php if ($isCompleted && !$isCurrent): ?><i class="ri-check-line"></i>
                                            <?php else: ?><?= $idx + 1 ?><?php endif; ?>
                                        </span>
                                    </div>
                                    <small class="<?= $isCurrent ? 'fw-bold text-primary' : ($isCompleted ? 'text-success' : 'text-muted') ?>"><?= __($statusLabels[$s]) ?></small>
                                    <?php if ($totalPkgs > 0): ?>
                                    <br><small class="<?= $reached > 0 ? ($reached == $totalPkgs ? 'text-success' : 'text-primary') : 'text-muted' ?>"><?= $reached ?>/<?= $totalPkgs ?> <?= __('kiện') ?></small>
                                    <?php endif; ?>
                                </div>
                                <?php if ($idx < count($flowKeys) - 1): ?>
                                <div class="flex-fill" style="height: 2px; background: <?= $isCompleted && $idx < $currentIdx ? '#0ab39c' : '#e9ebec' ?>; margin-top: -20px;"></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if ($isCancelled): ?>
                                <div class="text-center">
                                    <div class="avatar-sm mx-auto mb-1">
                                        <span class="avatar-title bg-danger rounded-circle fs-5"><i class="ri-close-line"></i></span>
                                    </div>
                                    <small class="fw-bold text-danger"><?= __('Đã hủy') ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($totalPkgs > 0 && $hasMultipleStatuses): ?>
                        <div class="mt-3 pt-3 border-top">
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <small class="text-muted me-1"><i class="ri-archive-line"></i> <?= __('Phân bổ kiện hàng') ?>:</small>
                                <?php
                                $allStatuses = [
                                    'cn_warehouse' => ['label' => 'Kho TQ', 'bg' => 'info'],
                                    'packed'       => ['label' => 'Đã đóng bao', 'bg' => 'secondary'],
                                    'shipping'     => ['label' => 'Vận chuyển', 'bg' => 'dark'],
                                    'vn_warehouse' => ['label' => 'Kho VN', 'bg' => 'primary'],
                                    'delivered'    => ['label' => 'Đã giao', 'bg' => 'success'],
                                ];
                                foreach ($allStatuses as $st => $cfg):
                                    if ($st === 'packed' && !$isRetail) continue;
                                    $cnt = $pkgStatusCount[$st] ?? 0;
                                    if ($cnt > 0):
                                ?>
                                <span class="badge bg-<?= $cfg['bg'] ?>-subtle text-<?= $cfg['bg'] ?> fs-12 px-2 py-1"><?= __($cfg['label']) ?>: <?= $cnt ?> <?= __('kiện') ?></span>
                                <?php endif; endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left -->
            <div class="col-lg-8">
                <!-- Packages -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><i class="ri-archive-line me-1"></i><?= __('Kiện hàng') ?> (<?= count($order_packages) ?>)</h5>
                        <?php if (!empty($order_packages)): ?>
                        <a href="<?= base_url('admin/packages-list&order_id=' . $order['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="ri-list-check me-1"></i><?= __('Quản lý kiện') ?></a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($order_packages)): ?>
                            <p class="text-muted mb-0"><?= __('Chưa có kiện hàng liên kết.') ?></p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table id="tbl-packages" class="table table-bordered table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th><?= __('Mã kiện') ?></th>
                                            <?php if ($isRetail): ?>
                                            <th><?= __('Mã vận đơn Trung Quốc') ?></th>
                                            <?php endif; ?>
                                            <th><?= __('Cân nặng') ?></th>
                                            <th><?= __('Kích thước') ?></th>
                                            <th><?= __('Số khối (m³)') ?></th>
                                            <th><?= __('Trạng thái') ?></th>
                                        </tr>
                                    </thead>
                                    <?php
                                    $pkgGroups = []; $gIdx = 0;
                                    foreach ($order_packages as $pkg) {
                                        $gKey = floatval($pkg['weight_actual']).'|'.floatval($pkg['length_cm']).'|'.floatval($pkg['width_cm']).'|'.floatval($pkg['height_cm']);
                                        if (!isset($pkgGroups[$gKey])) $pkgGroups[$gKey] = ['pkgs' => []];
                                        $pkgGroups[$gKey]['pkgs'][] = $pkg;
                                    }
                                    ?>
                                    <tbody>
                                        <?php foreach ($pkgGroups as $grp):
                                            $gpkgs   = $grp['pkgs'];
                                            $first   = $gpkgs[0];
                                            $gCount  = count($gpkgs);
                                            $pkgVolume = ($first['length_cm'] * $first['width_cm'] * $first['height_cm']) / 1000000;
                                            $uniqueStatuses = array_unique(array_column($gpkgs, 'status'));
                                        ?>
                                        <tr>
                                            <td>
                                                <?php if ($gCount === 1): ?>
                                                <a href="<?= base_url('admin/packages-detail&id='.$first['id']) ?>"><strong><?= htmlspecialchars($first['package_code']) ?></strong></a>
                                                <?php else: ?>
                                                <strong><?= htmlspecialchars($gpkgs[0]['package_code']) ?> ~ <?= htmlspecialchars($gpkgs[$gCount-1]['package_code']) ?></strong>
                                                <span class="badge bg-primary-subtle text-primary ms-1"><?= $gCount ?> <?= __('kiện') ?></span>
                                                <div class="mt-1">
                                                    <?php foreach ($gpkgs as $p): ?>
                                                    <a href="<?= base_url('admin/packages-detail&id='.$p['id']) ?>" class="badge bg-light text-dark border me-1 mb-1 text-decoration-none"><?= htmlspecialchars($p['package_code']) ?></a>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                            <?php if ($isRetail): ?>
                                            <td>
                                                <?php foreach ($gpkgs as $p): ?>
                                                <div><?= htmlspecialchars($p['tracking_cn'] ?: '-') ?></div>
                                                <?php endforeach; ?>
                                            </td>
                                            <?php endif; ?>
                                            <td><?= floatval($first['weight_actual']) > 0 ? fnum($first['weight_actual'], 2) . ' kg' : '<span class="text-muted">N/A</span>' ?></td>
                                            <td>
                                                <?php if ($first['length_cm'] > 0 || $first['width_cm'] > 0 || $first['height_cm'] > 0): ?>
                                                <?= $first['length_cm'] ?>x<?= $first['width_cm'] ?>x<?= $first['height_cm'] ?> cm
                                                <?php else: ?><span class="text-muted">N/A</span><?php endif; ?>
                                            </td>
                                            <td><?= $pkgVolume > 0 ? floatval(number_format($pkgVolume, 4, '.', '')) : '<span class="text-muted">N/A</span>' ?></td>
                                            <td>
                                                <?php foreach ($uniqueStatuses as $st): ?>
                                                <?= display_package_status($st) ?><?= count($uniqueStatuses) > 1 ? '<br>' : '' ?>
                                                <?php endforeach; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Order Info -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Thông tin đơn hàng') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless mb-0">
                                    <tr>
                                        <td class="text-muted"><?= __('Loại hàng') ?></td>
                                        <td>
                                            <?php if ($isRetail): ?>
                                            <span class="badge bg-secondary-subtle text-secondary"><?= __('Hàng lẻ') ?></span>
                                            <?php else: ?>
                                            <span class="badge bg-primary-subtle text-primary"><?= __('Hàng lô') ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php if (!$isRetail && !empty($order['cargo_type'])): ?>
                                    <tr><td class="text-muted"><?= __('Phân loại vận chuyển') ?></td><td><?= display_cargo_type($order['cargo_type']) ?></td></tr>
                                    <?php endif; ?>
                                    <?php if (!$isRetail && !empty($order['product_code'])): ?>
                                    <tr><td class="text-muted"><?= __('Mã hàng') ?></td><td class="fw-bold"><?= htmlspecialchars($order['product_code']) ?></td></tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td class="text-muted"><?= __('Khách hàng') ?></td>
                                        <td>
                                            <?php if ($order['customer_id']): ?>
                                            <a href="<?= base_url('admin/customers-detail&id=' . $order['customer_id']) ?>"><?= htmlspecialchars($order['customer_code'] . ' - ' . $order['customer_name']) ?></a>
                                            <?php if ($order['customer_phone']): ?>
                                            <br><small class="text-muted"><i class="ri-phone-line"></i> <?= htmlspecialchars($order['customer_phone']) ?></small>
                                            <?php endif; ?>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr><td class="text-muted"><?= __('Sản phẩm') ?></td><td><?= htmlspecialchars($order['product_name'] ?? '') ?: '-' ?></td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless mb-0">
                                    <tr><td class="text-muted"><?= __('Trạng thái') ?></td><td><?= display_order_status($order['status']) ?></td></tr>
                                    <?php
                                    $totalCbm = 0;
                                    foreach ($order_packages as $p) {
                                        if ($p['length_cm'] > 0 && $p['width_cm'] > 0 && $p['height_cm'] > 0) {
                                            $totalCbm += $p['length_cm'] * $p['width_cm'] * $p['height_cm'] / 1000000;
                                        }
                                    }
                                    ?>
                                    <?php if (!$isRetail && floatval($order['weight_actual'] ?? 0) > 0): ?>
                                    <tr>
                                        <td class="text-muted"><?= __('Tổng cân nặng mã hàng') ?></td>
                                        <td class="fw-bold"><?= floatval($order['weight_actual']) ?> kg</td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td class="text-muted"><?= __('Số khối') ?></td>
                                        <td class="fw-bold"><?= $totalCbm > 0 ? floatval(number_format($totalCbm, 3, '.', '')) . ' m³' : '<span class="text-muted fw-normal">N/A</span>' ?></td>
                                    </tr>
                                    <tr><td class="text-muted"><?= __('Ngày tạo') ?></td><td><?= $order['create_date'] ?></td></tr>
                                    <tr><td class="text-muted"><?= __('Cập nhật') ?></td><td><?= $order['update_date'] ?></td></tr>
                                </table>
                            </div>
                        </div>

                        <?php if (!empty($order['product_image'])): ?>
                        <div class="mt-3 d-flex flex-wrap gap-2">
                            <?php $imgIndex = 0; foreach (explode(',', $order['product_image']) as $img): ?>
                            <?php if (trim($img)): ?>
                            <a href="javascript:void(0)" onclick="openImagePopup(<?= $imgIndex ?>)" class="img-lightbox">
                                <img src="<?= get_upload_url(trim($img)) ?>" alt="Product" class="img-thumbnail" style="max-height: 150px; cursor:pointer;">
                            </a>
                            <?php $imgIndex++; endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Notes -->
                <?php if ($order['note'] || $order['note_internal']): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Ghi chú') ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if ($order['note']): ?>
                        <p><strong><?= __('Ghi chú khách hàng') ?>:</strong> <?= htmlspecialchars($order['note']) ?></p>
                        <?php endif; ?>
                        <?php if ($order['note_internal']): ?>
                        <p><strong><?= __('Ghi chú nội bộ') ?>:</strong> <span class="text-dark"><?= htmlspecialchars($order['note_internal']) ?></span></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right: Status History -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Lịch sử trạng thái') ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($history)): ?>
                        <p class="text-muted text-center"><?= __('Chưa có lịch sử') ?></p>
                        <?php else: ?>
                        <div class="timeline-sm">
                            <?php foreach (array_reverse($history) as $h): ?>
                            <div class="timeline-sm-item pb-3">
                                <span class="timeline-sm-date"><?= date('d/m H:i', strtotime($h['create_date'])) ?></span>
                                <div>
                                    <?= display_order_status($h['old_status']) ?> &rarr; <?= display_order_status($h['new_status']) ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($h['username'] ?? 'System') ?></small>
                                    <?php if ($h['note']): ?><br><small><?= htmlspecialchars($h['note']) ?></small><?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>


<?php require_once(__DIR__.'/footer.php'); ?>

<!-- Image Lightbox Modal -->
<div class="modal fade" id="modalImagePopup" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-transparent border-0 shadow-none">
            <div class="modal-body p-0 text-center position-relative">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-2" data-bs-dismiss="modal" style="z-index:10;filter:invert(1);"></button>
                <button type="button" class="btn btn-light rounded-circle position-absolute start-0 top-50 translate-middle-y ms-2" id="imgPrev" style="z-index:10;width:40px;height:40px;"><i class="ri-arrow-left-s-line"></i></button>
                <button type="button" class="btn btn-light rounded-circle position-absolute end-0 top-50 translate-middle-y me-2" id="imgNext" style="z-index:10;width:40px;height:40px;"><i class="ri-arrow-right-s-line"></i></button>
                <img id="imgPopupSrc" src="" class="img-fluid rounded" style="max-height:80vh;">
                <div class="text-white mt-2"><span id="imgPopupCounter"></span></div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function(){
    if($('#tbl-packages tbody tr').length > 0){
        $('#tbl-packages').DataTable({
            pageLength: 10,
            ordering: false,
            responsive: true,
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/vi.json' }
        });
    }
});

var popupImages = [];
var popupIndex = 0;
$('.img-lightbox img').each(function(){ popupImages.push($(this).attr('src')); });

function openImagePopup(idx){
    popupIndex = idx;
    showPopupImage();
    new bootstrap.Modal(document.getElementById('modalImagePopup')).show();
}
function showPopupImage(){
    $('#imgPopupSrc').attr('src', popupImages[popupIndex]);
    $('#imgPopupCounter').text((popupIndex+1) + ' / ' + popupImages.length);
    $('#imgPrev').toggle(popupIndex > 0);
    $('#imgNext').toggle(popupIndex < popupImages.length - 1);
}
$('#imgPrev').on('click', function(){ if(popupIndex > 0){ popupIndex--; showPopupImage(); } });
$('#imgNext').on('click', function(){ if(popupIndex < popupImages.length-1){ popupIndex++; showPopupImage(); } });
$(document).on('keydown', function(e){
    if(!$('#modalImagePopup').hasClass('show')) return;
    if(e.key==='ArrowLeft' && popupIndex>0){ popupIndex--; showPopupImage(); }
    if(e.key==='ArrowRight' && popupIndex<popupImages.length-1){ popupIndex++; showPopupImage(); }
    if(e.key==='Escape') bootstrap.Modal.getInstance(document.getElementById('modalImagePopup'))?.hide();
});
</script>

<style>
.timeline-sm { position: relative; padding-left: 20px; }
.timeline-sm::before { content:''; position: absolute; left: 5px; top: 0; bottom: 0; width: 2px; background: #e9ebec; }
.timeline-sm-item { position: relative; }
.timeline-sm-item::before { content:''; position: absolute; left: -19px; top: 4px; width: 10px; height: 10px; border-radius: 50%; background: #0ab39c; border: 2px solid #fff; }
.timeline-sm-date { font-size: 11px; color: #878a99; }
</style>
