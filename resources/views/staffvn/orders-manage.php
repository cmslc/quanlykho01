<?php
require_once(__DIR__.'/../../../models/is_staffvn.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Quản lý mã hàng');

// Filters
$filterSearch = trim(input_get('search') ?? '');
$filterCustomer = input_get('customer_id') ?: '';
$filterType = input_get('type') ?: '';

// === BAGS (Mã bao) ===
$sealedBags = [];
$bagCustomerMap = [];

if ($filterType !== 'wholesale') {
    $bagWhere = "b.status IN ('sealed', 'loading', 'shipping', 'arrived')";
    $bagParams = [];
    if ($filterSearch) {
        $bagWhere .= " AND b.bag_code LIKE ?";
        $bagParams[] = '%' . $filterSearch . '%';
    }
    if ($filterCustomer) {
        $bagWhere .= " AND b.id IN (SELECT bp2.bag_id FROM `bag_packages` bp2 JOIN `packages` p2 ON bp2.package_id = p2.id JOIN `package_orders` po2 ON p2.id = po2.package_id JOIN `orders` o2 ON po2.order_id = o2.id WHERE o2.customer_id = ?)";
        $bagParams[] = intval($filterCustomer);
    }

    $sealedBags = $ToryHub->get_list_safe(
        "SELECT b.id as bag_id, b.bag_code, b.status, b.images as bag_images,
            b.note,
            COUNT(DISTINCT bp.package_id) as pkg_count,
            b.total_weight as bag_weight,
            COALESCE(b.weight_volume, 0) as bag_cbm,
            SUM(COALESCE(p.weight_charged, 0)) as pkg_weight_charged,
            SUM(COALESCE(p.weight_actual, 0)) as pkg_weight_actual,
            SUM(COALESCE(p.length_cm,0) * COALESCE(p.width_cm,0) * COALESCE(p.height_cm,0) / 1000000) as pkg_cbm,
            b.create_date, b.update_date
        FROM `bags` b
        LEFT JOIN `bag_packages` bp ON b.id = bp.bag_id
        LEFT JOIN `packages` p ON bp.package_id = p.id
        WHERE $bagWhere
        GROUP BY b.id
        ORDER BY b.update_date DESC",
        $bagParams
    );

    if (!empty($sealedBags)) {
        $bagIds = array_column($sealedBags, 'bag_id');
        $ph = implode(',', array_fill(0, count($bagIds), '?'));
        $bagCusts = $ToryHub->get_list_safe(
            "SELECT DISTINCT bp.bag_id, c.id as cid, c.fullname
             FROM `bag_packages` bp
             JOIN `packages` p ON bp.package_id = p.id
             JOIN `package_orders` po ON p.id = po.package_id
             JOIN `orders` o ON po.order_id = o.id
             LEFT JOIN `customers` c ON o.customer_id = c.id
             WHERE bp.bag_id IN ($ph) AND c.id IS NOT NULL",
            $bagIds
        );
        foreach ($bagCusts as $bc) {
            $bagCustomerMap[$bc['bag_id']][$bc['cid']] = $bc['fullname'];
        }
    }
}

// === WHOLESALE ORDERS (Mã hàng lô) ===
$wholesaleOrders = [];

if ($filterType !== 'bags') {
    $orderWhere = "o.product_type = 'wholesale' AND o.status IN ('shipping','vn_warehouse','delivered')";
    $orderParams = [];
    if ($filterSearch) {
        $orderWhere .= " AND (o.product_code LIKE ? OR o.order_code LIKE ?)";
        $searchLike = '%' . $filterSearch . '%';
        $orderParams[] = $searchLike;
        $orderParams[] = $searchLike;
    }
    if ($filterCustomer) {
        $orderWhere .= " AND o.customer_id = ?";
        $orderParams[] = intval($filterCustomer);
    }

    $wholesaleOrders = $ToryHub->get_list_safe(
        "SELECT o.id, o.product_code, o.order_code, o.product_name,
            o.product_image, o.customer_id, o.quantity, o.is_paid, o.status,
            c.fullname as customer_name, c.phone as customer_phone,
            COUNT(p.id) as pkg_count,
            SUM(COALESCE(p.weight_charged, 0)) as total_weight_charged,
            SUM(COALESCE(p.weight_actual, 0)) as total_weight_actual,
            SUM(COALESCE(p.length_cm,0) * COALESCE(p.width_cm,0) * COALESCE(p.height_cm,0) / 1000000) as total_cbm,
            o.create_date, o.update_date
        FROM `orders` o
        LEFT JOIN `customers` c ON o.customer_id = c.id
        LEFT JOIN `package_orders` po ON o.id = po.order_id
        LEFT JOIN `packages` p ON po.package_id = p.id
        WHERE $orderWhere
        GROUP BY o.id
        ORDER BY o.update_date DESC",
        $orderParams
    );
}

$totalRows = count($sealedBags) + count($wholesaleOrders);

$bagStatusLabels = [
    'sealed' => ['label' => 'Chờ vận chuyển', 'bg' => 'warning', 'icon' => 'ri-time-line'],
    'loading' => ['label' => 'Đang xếp xe', 'bg' => 'secondary', 'icon' => 'ri-truck-line'],
    'shipping' => ['label' => 'Đang vận chuyển', 'bg' => 'primary', 'icon' => 'ri-ship-line'],
    'arrived' => ['label' => 'Đã đến kho VN', 'bg' => 'success', 'icon' => 'ri-check-double-line'],
];

$customers = $ToryHub->get_list_safe("SELECT `id`, `customer_code`, `fullname` FROM `customers` ORDER BY `fullname` ASC", []);

$csrf = new Csrf();
require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= $page_title ?></h4>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="<?= base_url('staffvn/orders-manage') ?>">
                            <input type="hidden" name="module" value="staffvn">
                            <input type="hidden" name="action" value="orders-manage">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label"><?= __('Tìm kiếm') ?></label>
                                    <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($filterSearch) ?>" placeholder="<?= __('Mã bao, mã hàng lô...') ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><?= __('Loại hàng') ?></label>
                                    <select class="form-select" name="type">
                                        <option value=""><?= __('Tất cả') ?></option>
                                        <option value="bags" <?= $filterType == 'bags' ? 'selected' : '' ?>><?= __('Mã bao') ?></option>
                                        <option value="wholesale" <?= $filterType == 'wholesale' ? 'selected' : '' ?>><?= __('Hàng lô') ?></option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label"><?= __('Khách hàng') ?></label>
                                    <select class="form-select" name="customer_id">
                                        <option value=""><?= __('Tất cả') ?></option>
                                        <?php foreach ($customers as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= $filterCustomer == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars(($c['customer_code'] ? $c['customer_code'] . ' - ' : '') . $c['fullname']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-auto">
                                    <button type="submit" class="btn btn-primary"><i class="ri-search-line me-1"></i><?= __('Lọc') ?></button>
                                    <a href="<?= base_url('staffvn/orders-manage') ?>" class="btn btn-secondary"><?= __('Reset') ?></a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= $page_title ?> (<?= $totalRows ?> <?= __('dòng') ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th class="align-middle"><?= __('Mã hàng') ?></th>
                                        <th class="align-middle text-center" style="width:60px;"><?= __('Ảnh') ?></th>
                                        <th class="align-middle"><?= __('Khách hàng') ?></th>
                                        <th class="align-middle text-center"><?= __('Số kiện') ?></th>
                                        <th class="align-middle text-end"><?= __('Cân nặng') ?></th>
                                        <th class="align-middle text-end"><?= __('Số khối') ?></th>
                                        <th class="align-middle"><?= __('Trạng thái') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($totalRows === 0): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4"><?= __('Không có dữ liệu') ?></td></tr>
                                    <?php endif; ?>

                                    <?php // === BAG ROWS === ?>
                                    <?php foreach ($sealedBags as $bag):
                                        $bagW = floatval($bag['bag_weight'] ?? 0);
                                        $pkgWCharged = floatval($bag['pkg_weight_charged'] ?? 0);
                                        $pkgWActual = floatval($bag['pkg_weight_actual'] ?? 0);
                                        $weight = $bagW > 0 ? $bagW : ($pkgWCharged > 0 ? $pkgWCharged : $pkgWActual);
                                        $bagCbm = floatval($bag['bag_cbm'] ?? 0);
                                        $pkgCbm = floatval($bag['pkg_cbm'] ?? 0);
                                        $cbm = $bagCbm > 0 ? $bagCbm : $pkgCbm;
                                        $pkgCount = intval($bag['pkg_count'] ?? 0);
                                        $bagCusts = $bagCustomerMap[$bag['bag_id']] ?? [];
                                        $bsl = $bagStatusLabels[$bag['status']] ?? $bagStatusLabels['open'];
                                    ?>
                                    <tr class="bag-row" data-bag-id="<?= $bag['bag_id'] ?>" style="cursor:pointer;">
                                        <td class="align-middle">
                                            <strong class="text-primary"><?= htmlspecialchars($bag['bag_code']) ?></strong>
                                            <i class="ri-arrow-right-s-line fs-14 toggle-icon text-muted ms-1"></i>
                                            <?php if ($pkgCount > 0): ?>
                                            <div class="mt-1"><span class="text-muted"><i class="ri-archive-line"></i> <?= $pkgCount ?> <?= __('kiện') ?></span></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle text-center">
                                            <?php if (!empty($bag['bag_images'])):
                                                $bagImgArr = array_filter(array_map('trim', explode(',', $bag['bag_images'])));
                                                $bagImgUrls = array_map('get_upload_url', $bagImgArr);
                                                $thumbUrl = $bagImgUrls[0];
                                                $imgCount = count($bagImgArr);
                                            ?>
                                            <a href="#" class="btn-view-images position-relative d-inline-block" data-images="<?= htmlspecialchars(json_encode(array_values($bagImgUrls))) ?>">
                                                <img src="<?= $thumbUrl ?>" class="rounded" style="width:40px;height:40px;object-fit:cover;">
                                                <?php if ($imgCount > 1): ?>
                                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary" style="font-size:10px;"><?= $imgCount ?></span>
                                                <?php endif; ?>
                                            </a>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle">
                                            <?php if (count($bagCusts) == 1): ?>
                                                <?= htmlspecialchars(array_values($bagCusts)[0]) ?>
                                            <?php elseif (count($bagCusts) > 1): ?>
                                                <span class="text-muted"><?= count($bagCusts) ?> <?= __('khách') ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle text-center"><strong><?= $pkgCount ?></strong></td>
                                        <td class="align-middle text-end"><?= $weight > 0 ? fnum($weight, 1) . ' kg' : '<span class="text-muted">-</span>' ?></td>
                                        <td class="align-middle text-end"><?= $cbm > 0 ? fnum($cbm, 2) . ' m&sup3;' : '<span class="text-muted">-</span>' ?></td>
                                        <td class="align-middle"><span class="badge bg-<?= $bsl['bg'] ?>-subtle text-<?= $bsl['bg'] ?>" style="font-size:11px;"><i class="<?= $bsl['icon'] ?> me-1"></i><?= __($bsl['label']) ?></span></td>
                                    </tr>
                                    <tr class="bag-detail-row d-none" id="bag-detail-<?= $bag['bag_id'] ?>">
                                        <td colspan="7" class="p-0">
                                            <div class="px-4 py-2 bg-light">
                                                <div class="bag-packages-content">
                                                    <div class="text-center py-2 text-muted"><i class="ri-loader-4-line ri-spin fs-20"></i> <?= __('Đang tải...') ?></div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>

                                    <?php // === WHOLESALE ORDER ROWS === ?>
                                    <?php foreach ($wholesaleOrders as $order):
                                        $wCharged = floatval($order['total_weight_charged'] ?? 0);
                                        $wActual = floatval($order['total_weight_actual'] ?? 0);
                                        $weight = $wCharged > 0 ? $wCharged : $wActual;
                                        $cbm = floatval($order['total_cbm'] ?? 0);
                                        $pkgCount = intval($order['pkg_count'] ?? 0);
                                    ?>
                                    <tr class="order-row" data-order-id="<?= $order['id'] ?>" style="cursor:pointer;">
                                        <td class="align-middle">
                                            <?php if ($order['product_code'] ?? ''): ?>
                                            <strong class="text-primary"><?= htmlspecialchars($order['product_code']) ?></strong>
                                            <?php else: ?>
                                            <span class="text-muted">#<?= $order['id'] ?></span>
                                            <?php endif; ?>
                                            <i class="ri-arrow-right-s-line fs-14 toggle-icon text-muted ms-1"></i>
                                            <?php if ($pkgCount > 0): ?>
                                            <div class="mt-1"><span class="text-muted"><i class="ri-archive-line"></i> <?= $pkgCount ?> <?= __('kiện') ?></span></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle text-center">
                                            <?php if (!empty($order['product_image'])):
                                                $orderImgArr = array_filter(array_map('trim', explode(',', $order['product_image'])));
                                                $orderImgUrls = array_map('get_upload_url', $orderImgArr);
                                                $thumbUrl = $orderImgUrls[0];
                                                $imgCount = count($orderImgArr);
                                            ?>
                                            <a href="#" class="btn-view-images position-relative d-inline-block" data-images="<?= htmlspecialchars(json_encode(array_values($orderImgUrls))) ?>">
                                                <img src="<?= $thumbUrl ?>" class="rounded" style="width:40px;height:40px;object-fit:cover;">
                                                <?php if ($imgCount > 1): ?>
                                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary" style="font-size:10px;"><?= $imgCount ?></span>
                                                <?php endif; ?>
                                            </a>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle">
                                            <?php if ($order['customer_id']): ?>
                                            <strong><?= htmlspecialchars($order['customer_name'] ?? '') ?></strong>
                                            <?php if ($order['customer_phone']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($order['customer_phone']) ?></small>
                                            <?php endif; ?>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle text-center"><strong><?= $pkgCount ?></strong></td>
                                        <td class="align-middle text-end"><?= $weight > 0 ? fnum($weight, 1) . ' kg' : '<span class="text-muted">-</span>' ?></td>
                                        <td class="align-middle text-end"><?= $cbm > 0 ? fnum($cbm, 2) . ' m&sup3;' : '<span class="text-muted">-</span>' ?></td>
                                        <td class="align-middle">
                                            <?= display_order_status($order['status']) ?>
                                            <?php if ($order['is_paid']): ?>
                                            <span class="badge bg-success-subtle text-success ms-1" style="font-size:10px;"><?= __('Đã TT') ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr class="order-detail-row d-none" id="order-detail-<?= $order['id'] ?>">
                                        <td colspan="7" class="p-0">
                                            <div class="px-4 py-2 bg-light">
                                                <div class="order-packages-content">
                                                    <div class="text-center py-2 text-muted"><i class="ri-loader-4-line ri-spin fs-20"></i> <?= __('Đang tải...') ?></div>
                                                </div>
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

<!-- Image Gallery Modal -->
<div class="modal fade" id="imageGalleryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark border-0">
            <div class="modal-header border-0 py-2">
                <span class="text-white-50 fs-12" id="gallery-counter"></span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="imageCarousel" class="carousel slide" data-bs-touch="true">
                    <div class="carousel-inner" id="carousel-items"></div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#imageCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#imageCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
$(function(){
    var csrfToken = '<?= $csrf->get_token_value() ?>';
    var csrfName = '<?= $csrf->get_token_name() ?>';
    var loadedBags = {};
    var loadedOrders = {};
    var ajaxUrl = '<?= base_url("ajaxs/staffvn/bag-unpack.php") ?>';

    // ===== Expand bag rows =====
    $(document).on('click', '.bag-row', function(e){
        if ($(e.target).closest('.btn-view-images').length) return;
        var bagId = $(this).data('bag-id');
        var $detail = $('#bag-detail-' + bagId);
        var $icon = $(this).find('.toggle-icon');
        if ($detail.hasClass('d-none')) {
            $detail.removeClass('d-none');
            $icon.removeClass('ri-arrow-right-s-line').addClass('ri-arrow-down-s-line');
            if (!loadedBags[bagId]) loadBagPackages(bagId);
        } else {
            $detail.addClass('d-none');
            $icon.removeClass('ri-arrow-down-s-line').addClass('ri-arrow-right-s-line');
        }
    });

    function loadBagPackages(bagId) {
        var $c = $('#bag-detail-' + bagId).find('.bag-packages-content');
        $.post(ajaxUrl, {
            request_name: 'load_bag_detail', bag_id: bagId, [csrfName]: csrfToken
        }, function(res){
            if (res.csrf_token) csrfToken = res.csrf_token;
            if (res.status === 'success' && res.packages && res.packages.length > 0) {
                var h = '<table class="table table-sm table-borderless mb-0 text-muted"><thead><tr>';
                h += '<th>#</th><th><?= __("Mã kiện") ?></th><th><?= __("Mã vận đơn") ?></th>';
                h += '<th><?= __("Sản phẩm") ?></th><th><?= __("Khách hàng") ?></th>';
                h += '<th><?= __("Cân nặng") ?></th>';
                h += '</tr></thead><tbody>';
                res.packages.forEach(function(p, i){
                    h += '<tr>';
                    h += '<td>' + (i + 1) + '</td>';
                    h += '<td><strong>' + esc(p.package_code) + '</strong></td>';
                    h += '<td><code class="fs-11">' + esc(p.tracking_cn || '-') + '</code></td>';
                    h += '<td><small>' + esc(p.product_name || '-') + '</small></td>';
                    h += '<td><small>' + esc(p.customer_name || '-') + '</small></td>';
                    h += '<td>' + (p.weight_charged > 0 ? p.weight_charged + ' kg' : '-') + '</td>';
                    h += '</tr>';
                });
                h += '</tbody></table>';
                $c.html(h);
                loadedBags[bagId] = true;
            } else {
                $c.html('<div class="text-center py-2 text-muted"><?= __("Bao trống") ?></div>');
                loadedBags[bagId] = true;
            }
        }, 'json').fail(function(){
            $c.html('<div class="text-center py-2 text-danger"><?= __("Lỗi tải dữ liệu") ?></div>');
        });
    }

    // ===== Expand wholesale order rows =====
    $(document).on('click', '.order-row', function(e){
        if ($(e.target).closest('.btn-view-images').length) return;
        var orderId = $(this).data('order-id');
        var $detail = $('#order-detail-' + orderId);
        var $icon = $(this).find('.toggle-icon');
        if ($detail.hasClass('d-none')) {
            $detail.removeClass('d-none');
            $icon.removeClass('ri-arrow-right-s-line').addClass('ri-arrow-down-s-line');
            if (!loadedOrders[orderId]) loadOrderPackages(orderId);
        } else {
            $detail.addClass('d-none');
            $icon.removeClass('ri-arrow-down-s-line').addClass('ri-arrow-right-s-line');
        }
    });

    function loadOrderPackages(orderId) {
        var $c = $('#order-detail-' + orderId).find('.order-packages-content');
        $.post(ajaxUrl, {
            request_name: 'load_order_detail', order_id: orderId, [csrfName]: csrfToken
        }, function(res){
            if (res.csrf_token) csrfToken = res.csrf_token;
            if (res.status === 'success' && res.packages && res.packages.length > 0) {
                var h = '<table class="table table-sm table-borderless mb-0 text-muted"><thead><tr>';
                h += '<th>#</th><th><?= __("Mã kiện") ?></th><th><?= __("Mã vận đơn") ?></th>';
                h += '<th><?= __("Cân nặng") ?></th><th><?= __("Số khối") ?></th>';
                h += '</tr></thead><tbody>';
                res.packages.forEach(function(p, i){
                    h += '<tr>';
                    h += '<td>' + (i + 1) + '</td>';
                    h += '<td><strong>' + esc(p.package_code) + '</strong></td>';
                    h += '<td><code class="fs-11">' + esc(p.tracking_cn || '-') + '</code></td>';
                    h += '<td>' + (p.weight_charged > 0 ? p.weight_charged + ' kg' : (p.weight_actual > 0 ? p.weight_actual + ' kg' : '-')) + '</td>';
                    h += '<td>' + (p.cbm > 0 ? p.cbm + ' m³' : '-') + '</td>';
                    h += '</tr>';
                });
                h += '</tbody></table>';
                $c.html(h);
                loadedOrders[orderId] = true;
            } else {
                $c.html('<div class="text-center py-2 text-muted"><?= __("Không có kiện hàng") ?></div>');
                loadedOrders[orderId] = true;
            }
        }, 'json').fail(function(){
            $c.html('<div class="text-center py-2 text-danger"><?= __("Lỗi tải dữ liệu") ?></div>');
        });
    }

    // ===== Image Gallery =====
    var galleryCarousel = null;
    var galleryTotal = 0;

    function updateGalleryCounter() {
        var idx = $('#imageCarousel .carousel-item.active').index();
        $('#gallery-counter').text((idx + 1) + ' / ' + galleryTotal);
        if (galleryTotal <= 1) {
            $('#imageCarousel .carousel-control-prev, #imageCarousel .carousel-control-next').addClass('d-none');
        } else {
            $('#imageCarousel .carousel-control-prev, #imageCarousel .carousel-control-next').removeClass('d-none');
        }
    }

    $('#imageCarousel').on('slid.bs.carousel', updateGalleryCounter);

    $(document).on('click', '.btn-view-images', function(e){
        e.preventDefault();
        e.stopPropagation();
        var images = $(this).data('images');
        if (!images || !images.length) return;
        galleryTotal = images.length;
        var html = '';
        images.forEach(function(url, i){
            html += '<div class="carousel-item' + (i === 0 ? ' active' : '') + '">'
                + '<div class="d-flex align-items-center justify-content-center" style="min-height:300px;">'
                + '<img src="' + url + '" class="d-block" style="max-width:100%;max-height:75vh;object-fit:contain;">'
                + '</div></div>';
        });
        $('#carousel-items').html(html);
        if (galleryCarousel) galleryCarousel.dispose();
        galleryCarousel = new bootstrap.Carousel($('#imageCarousel')[0], { interval: false, touch: true, keyboard: true });
        updateGalleryCounter();
        new bootstrap.Modal($('#imageGalleryModal')[0]).show();
    });

    function esc(s){ if(!s) return ''; var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
});
</script>
