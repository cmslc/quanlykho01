<?php
require_once(__DIR__.'/../../../models/is_staffvn.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Giao hàng');

// Filters
$filterSearch = trim(input_get('search') ?? '');
$filterCustomer = input_get('customer_id') ?: '';
$filterPtype = input_get('ptype') ?: '';
$filterDelivery = isset($_GET['delivery']) ? (input_get('delivery') ?: '') : 'not_delivered';

// Build WHERE
$where = "1=1";
$params = [];

if ($filterDelivery === 'delivered') {
    $where .= " AND o.status = 'delivered'";
} elseif ($filterDelivery === 'not_delivered') {
    // Đơn ở kho VN hoặc đơn lô có kiện đã về kho VN
    $where .= " AND (o.status = 'vn_warehouse' OR (o.product_type = 'wholesale' AND o.status NOT IN ('delivered','cancelled') AND EXISTS (SELECT 1 FROM `package_orders` po_ex JOIN `packages` p_ex ON po_ex.package_id = p_ex.id WHERE po_ex.order_id = o.id AND p_ex.status = 'vn_warehouse')))";
} else {
    $where .= " AND (o.status IN ('vn_warehouse', 'delivered') OR (o.product_type = 'wholesale' AND o.status NOT IN ('cancelled') AND EXISTS (SELECT 1 FROM `package_orders` po_ex JOIN `packages` p_ex ON po_ex.package_id = p_ex.id WHERE po_ex.order_id = o.id AND p_ex.status = 'vn_warehouse')))";
}

if ($filterPtype === 'wholesale') {
    $where .= " AND o.product_type = 'wholesale'";
} elseif ($filterPtype === 'retail') {
    $where .= " AND o.product_type = 'retail'";
}

if ($filterSearch) {
    $where .= " AND (o.product_code LIKE ? OR o.order_code LIKE ? OR c.fullname LIKE ? OR c.phone LIKE ?)";
    $searchLike = '%' . $filterSearch . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

if ($filterCustomer) {
    $where .= " AND o.customer_id = ?";
    $params[] = intval($filterCustomer);
}

// Count total
$totalOrders = $ToryHub->get_row_safe("SELECT COUNT(*) as cnt FROM `orders` o LEFT JOIN `customers` c ON o.customer_id = c.id WHERE $where", $params)['cnt'] ?? 0;

$perPage = 10;
$page = max(1, intval(input_get('pg') ?? 1));
$totalPages = max(1, ceil($totalOrders / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$orders = $ToryHub->get_list_safe("
    SELECT o.*, o.product_code, o.product_type, o.product_image,
        (SELECT p.tracking_cn FROM `package_orders` po JOIN `packages` p ON po.package_id = p.id WHERE po.order_id = o.id LIMIT 1) as tracking_cn,
        (SELECT SUM(COALESCE(p2.weight_charged, 0)) FROM `package_orders` po2 JOIN `packages` p2 ON po2.package_id = p2.id WHERE po2.order_id = o.id) as total_weight,
        (SELECT SUM(COALESCE(p2a.weight_actual, 0)) FROM `package_orders` po2a JOIN `packages` p2a ON po2a.package_id = p2a.id WHERE po2a.order_id = o.id) as total_weight_actual,
        (SELECT SUM(COALESCE(p3.length_cm,0) * COALESCE(p3.width_cm,0) * COALESCE(p3.height_cm,0) / 1000000) FROM `package_orders` po3 JOIN `packages` p3 ON po3.package_id = p3.id WHERE po3.order_id = o.id) as total_cbm,
        (SELECT COUNT(p4.id) FROM `package_orders` po4 JOIN `packages` p4 ON po4.package_id = p4.id WHERE po4.order_id = o.id) as pkg_total,
        (SELECT COUNT(p5.id) FROM `package_orders` po5 JOIN `packages` p5 ON po5.package_id = p5.id WHERE po5.order_id = o.id AND p5.status IN ('vn_warehouse', 'delivered')) as pkg_received,
        (SELECT COUNT(p6.id) FROM `package_orders` po6 JOIN `packages` p6 ON po6.package_id = p6.id WHERE po6.order_id = o.id AND p6.status = 'delivered') as pkg_delivered,
        (SELECT COUNT(p7.id) FROM `package_orders` po7 JOIN `packages` p7 ON po7.package_id = p7.id WHERE po7.order_id = o.id AND p7.status IN ('packed','loading','shipping')) as pkg_in_transit,
        c.fullname as customer_name, c.phone as customer_phone, c.address_vn as customer_address,
        cod.amount as cod_amount, cod.payment_method as cod_method
    FROM `orders` o
    LEFT JOIN `customers` c ON o.customer_id = c.id
    LEFT JOIN `cod_collections` cod ON cod.order_id = o.id
    WHERE $where
    ORDER BY o.status ASC, o.create_date DESC
    LIMIT $perPage OFFSET $offset
", $params);

$customers = $ToryHub->get_list_safe("SELECT `id`, `fullname` FROM `customers` ORDER BY `fullname` ASC", []);

$csrf = new Csrf();
require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Giao hàng') ?></h4>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="<?= base_url('staffvn/orders-delivery') ?>">
                            <input type="hidden" name="module" value="staffvn">
                            <input type="hidden" name="action" value="orders-delivery">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label"><?= __('Tìm kiếm') ?></label>
                                    <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($filterSearch) ?>" placeholder="<?= __('Mã đơn, khách hàng, SĐT...') ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><?= __('Loại hàng') ?></label>
                                    <select class="form-select" name="ptype">
                                        <option value=""><?= __('Tất cả') ?></option>
                                        <option value="retail" <?= $filterPtype == 'retail' ? 'selected' : '' ?>><?= __('Hàng lẻ') ?></option>
                                        <option value="wholesale" <?= $filterPtype == 'wholesale' ? 'selected' : '' ?>><?= __('Hàng lô') ?></option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><?= __('Trạng thái') ?></label>
                                    <select class="form-select" name="delivery">
                                        <option value="" <?= $filterDelivery == '' ? 'selected' : '' ?>><?= __('Tất cả') ?></option>
                                        <option value="not_delivered" <?= $filterDelivery == 'not_delivered' ? 'selected' : '' ?>><?= __('Chưa giao') ?></option>
                                        <option value="delivered" <?= $filterDelivery == 'delivered' ? 'selected' : '' ?>><?= __('Đã giao') ?></option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label"><?= __('Khách hàng') ?></label>
                                    <select class="form-select" name="customer_id">
                                        <option value=""><?= __('Tất cả') ?></option>
                                        <?php foreach ($customers as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= $filterCustomer == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['fullname']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-auto">
                                    <button type="submit" class="btn btn-primary"><i class="ri-search-line me-1"></i><?= __('Lọc') ?></button>
                                    <a href="<?= base_url('staffvn/orders-delivery') ?>" class="btn btn-secondary"><?= __('Reset') ?></a>
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
                        <h5 class="card-title mb-0"><?= __('Giao hàng') ?> (<?= $totalOrders ?> <?= __('đơn') ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($orders)): ?>
                        <div class="text-center py-4">
                            <i class="ri-truck-line fs-1 text-muted"></i>
                            <p class="text-muted mt-2"><?= __('Không có đơn hàng') ?></p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:50px;" class="text-center">#</th>
                                        <th><?= __('Mã đơn') ?></th>
                                        <th><?= __('Ảnh') ?></th>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th><?= __('SĐT') ?></th>
                                        <th class="text-end"><?= __('Cân nặng') ?></th>
                                        <th class="text-end"><?= __('Số khối') ?></th>
                                        <th class="text-end"><?= __('Tổng VND') ?></th>
                                        <th><?= __('TT đơn hàng') ?></th>
                                        <th><?= __('Trạng thái') ?></th>
                                        <th><?= __('Hành động') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $rowIdx = 0; foreach ($orders as $order):
                                        $rowIdx++;
                                        $w = ($order['product_type'] === 'retail' && floatval($order['total_weight_actual'] ?? 0) > 0)
                                            ? floatval($order['total_weight_actual'])
                                            : floatval($order['total_weight'] ?? 0);
                                        $cbm = floatval($order['total_cbm'] ?? 0);
                                        if (floatval($order['volume_actual'] ?? 0) > 0) $cbm = floatval($order['volume_actual']);
                                        $pkgTotal = intval($order['pkg_total'] ?? 0);
                                        $pkgReceived = intval($order['pkg_received'] ?? 0);
                                        $pkgDelivered = intval($order['pkg_delivered'] ?? 0);
                                        $pkgInTransit = intval($order['pkg_in_transit'] ?? 0);
                                        $pkgReady = $pkgReceived - $pkgDelivered; // kiện ở kho VN, chưa giao
                                        $imgArr = parse_product_images($order['product_image']);
                                        $imgUrls = array_map('get_upload_url', $imgArr);
                                    ?>
                                    <tr>
                                        <td class="text-center text-muted"><?= $rowIdx ?></td>
                                        <td><strong><?= ($order['product_type'] === 'wholesale' && !empty($order['product_code'])) ? htmlspecialchars($order['product_code']) : (!empty($order['tracking_cn']) ? htmlspecialchars($order['tracking_cn']) : $order['order_code']) ?></strong></td>
                                        <td>
                                            <?php if (!empty($imgUrls)): ?>
                                            <img src="<?= $imgUrls[0] ?>" class="rounded" style="width:36px;height:36px;object-fit:cover;">
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($order['customer_name'] ?? '') ?></td>
                                        <td>
                                            <?php if ($order['customer_phone']): ?>
                                            <a href="tel:<?= htmlspecialchars($order['customer_phone']) ?>"><?= htmlspecialchars($order['customer_phone']) ?></a>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end"><?= $w > 0 ? fnum($w, 1) . ' kg' : '<span class="text-muted">-</span>' ?></td>
                                        <td class="text-end"><?= $cbm > 0 ? fnum($cbm, 2) . ' m&sup3;' : '<span class="text-muted">-</span>' ?></td>
                                        <td class="text-end fw-bold"><?= format_vnd($order['grand_total']) ?></td>
                                        <td>
                                            <?php if ($order['product_type'] === 'wholesale' && $pkgTotal > 0): ?>
                                            <div><span class="badge bg-secondary-subtle text-secondary fs-12"><?= __('Tổng số kiện') ?>: <?= $pkgTotal ?></span></div>
                                            <?php if ($pkgInTransit > 0): ?>
                                            <div class="mt-1"><span class="badge bg-primary-subtle text-primary fs-12"><?= __('Đang vận chuyển') ?>: <?= $pkgInTransit ?></span></div>
                                            <?php endif; ?>
                                            <?php if ($pkgReceived > 0): ?>
                                            <div class="mt-1"><span class="badge bg-success-subtle text-success fs-12"><?= __('Đã về kho Việt Nam') ?>: <?= $pkgReceived ?></span></div>
                                            <?php endif; ?>
                                            <?php if ($pkgDelivered > 0): ?>
                                            <div class="mt-1"><span class="badge bg-info-subtle text-info fs-12"><?= __('Đã giao') ?>: <?= $pkgDelivered ?></span></div>
                                            <?php endif; ?>
                                            <?php else: ?>
                                            <?= display_order_status($order['status']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($order['status'] === 'delivered'): ?>
                                                <span class="badge bg-success"><?= __('Đã giao') ?></span>
                                                <?php if ($order['cod_amount']): ?>
                                                <br><small class="text-success"><?= format_vnd($order['cod_amount']) ?> (<?= $order['cod_method'] === 'transfer' ? __('CK') : __('TM') ?>)</small>
                                                <?php endif; ?>
                                            <?php elseif ($order['product_type'] === 'wholesale' && $pkgReady > 0 && $order['status'] !== 'vn_warehouse'): ?>
                                                <span class="badge bg-info"><?= __('Giao từng phần') ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-warning"><?= __('Chờ giao') ?></span>
                                            <?php endif; ?>
                                            <?php if ($order['is_paid']): ?>
                                            <br><span class="badge bg-success-subtle text-success fs-12"><?= __('Đã thanh toán') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($order['status'] === 'vn_warehouse' || ($order['product_type'] === 'wholesale' && $pkgReady > 0)): ?>
                                            <button type="button" class="btn btn-success btn-deliver"
                                                data-id="<?= $order['id'] ?>"
                                                data-code="<?= ($order['product_type'] === 'wholesale' && !empty($order['product_code'])) ? htmlspecialchars($order['product_code']) : (!empty($order['tracking_cn']) ? htmlspecialchars($order['tracking_cn']) : htmlspecialchars($order['order_code'])) ?>"
                                                data-customer="<?= htmlspecialchars($order['customer_name'] ?? '') ?>"
                                                data-customer-id="<?= $order['customer_id'] ?>"
                                                data-amount="<?= $order['grand_total'] ?>"
                                                data-is-paid="<?= $order['is_paid'] ?>"
                                                data-ptype="<?= $order['product_type'] ?>"
                                                data-pkg-ready="<?= $pkgReady ?>"
                                                data-pkg-total="<?= $pkgTotal ?>">
                                                <i class="ri-truck-line me-1"></i><?= __('Giao hàng') ?>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalPages > 1): ?>
                        <!-- Pagination -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <small class="text-muted">
                                <?= __('Hiển thị') ?> <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalOrders) ?> / <?= $totalOrders ?>
                            </small>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <?php
                                    $queryParams = $_GET;
                                    unset($queryParams['pg']);
                                    $baseUrl = base_url('staffvn/orders-delivery') . '?' . http_build_query($queryParams);
                                    ?>
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $baseUrl ?>&page=<?= $page - 1 ?>">&laquo;</a>
                                    </li>
                                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= $baseUrl ?>&page=<?= $p ?>"><?= $p ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $baseUrl ?>&page=<?= $page + 1 ?>">&raquo;</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
var csrfToken = '<?= $csrf->get_token_value() ?>';
var csrfName = '<?= $csrf->get_token_name() ?>';
var ajaxUrl = '<?= base_url('ajaxs/staffvn/orders-delivery.php') ?>';

$(document).ready(function(){
    $('.btn-deliver').on('click', function(){
        var orderId = $(this).data('id');
        var orderCode = $(this).data('code');
        var customerName = $(this).data('customer');
        var customerId = $(this).data('customer-id');
        var amount = parseFloat($(this).data('amount')) || 0;
        var isPaid = $(this).data('is-paid');
        var ptype = $(this).data('ptype');
        var pkgReady = parseInt($(this).data('pkg-ready')) || 0;
        var pkgTotal = parseInt($(this).data('pkg-total')) || 0;

        var pkgSection = '';
        if (ptype === 'wholesale' && pkgTotal > 0) {
            pkgSection = '' +
                '<div class="alert alert-info py-2 mb-2 text-start">' +
                    '<small><i class="ri-inbox-line me-1"></i><?= __('Kiện sẵn sàng giao') ?>: <strong>' + pkgReady + '</strong> / ' + pkgTotal + ' <?= __('kiện') ?></small>' +
                '</div>' +
                '<div class="row g-2 mb-2">' +
                    '<div class="col-12">' +
                        '<label class="form-label small mb-1 text-start d-block"><?= __('Số kiện giao lần này') ?></label>' +
                        '<input type="number" id="deliver-pkg-count" class="form-control text-center" value="' + pkgReady + '" min="1" max="' + pkgReady + '">' +
                    '</div>' +
                '</div>';
        }

        var codSection = '';
        if (!isPaid) {
            codSection = '' +
                '<hr class="my-3">' +
                '<div class="form-check form-switch mb-2">' +
                    '<input class="form-check-input" type="checkbox" id="cod-toggle">' +
                    '<label class="form-check-label fw-semibold" for="cod-toggle"><?= __('Thu tiền COD') ?></label>' +
                '</div>' +
                '<div id="cod-fields" style="display:none">' +
                    '<div class="row g-2 mb-2">' +
                        '<div class="col-6">' +
                            '<label class="form-label small mb-1"><?= __('Số tiền thu') ?></label>' +
                            '<input type="number" id="cod-amount" class="form-control" value="' + amount + '" min="0">' +
                        '</div>' +
                        '<div class="col-6">' +
                            '<label class="form-label small mb-1"><?= __('Phương thức') ?></label>' +
                            '<select id="cod-method" class="form-select">' +
                                '<option value="cash"><?= __('Tiền mặt') ?></option>' +
                                '<option value="transfer"><?= __('Chuyển khoản') ?></option>' +
                            '</select>' +
                        '</div>' +
                    '</div>' +
                '</div>';
        }

        Swal.fire({
            title: '<?= __('Xác nhận giao hàng') ?>',
            html: '<?= __('Bạn xác nhận đã giao đơn hàng') ?> <strong>' + orderCode + '</strong> <?= __('cho') ?> <strong>' + customerName + '</strong>?' +
                  (isPaid ? '<br><span class="badge bg-success mt-1"><?= __('Đã thanh toán') ?></span>' : '') +
                  '<br/>' +
                  pkgSection +
                  codSection +
                  '<hr class="my-3">' +
                  '<textarea id="delivery-note" class="form-control" placeholder="<?= __('Ghi chú giao hàng (không bắt buộc)') ?>" rows="2"></textarea>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0ab39c',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="ri-truck-line"></i> <?= __('Xác nhận giao') ?>',
            cancelButtonText: '<?= __('Hủy') ?>',
            showLoaderOnConfirm: true,
            didOpen: function() {
                $(document).off('change.cod').on('change.cod', '#cod-toggle', function(){
                    $('#cod-fields').toggle($(this).is(':checked'));
                });
            },
            preConfirm: () => {
                var note = $('#delivery-note').val() || '';
                var collectCod = !isPaid && $('#cod-toggle').is(':checked') ? '1' : '0';
                var codAmount = $('#cod-amount') ? $('#cod-amount').val() : '0';
                var codMethod = $('#cod-method') ? $('#cod-method').val() : 'cash';
                var deliverPkgCount = $('#deliver-pkg-count').length ? parseInt($('#deliver-pkg-count').val()) : 0;

                if (ptype === 'wholesale' && pkgTotal > 0 && (deliverPkgCount < 1 || deliverPkgCount > pkgReady)) {
                    Swal.showValidationMessage('<?= __('Số kiện giao phải từ 1 đến') ?> ' + pkgReady);
                    return false;
                }

                var postData = {
                    request_name: 'mark_delivered',
                    order_id: orderId,
                    note: note,
                    collect_cod: collectCod,
                    cod_amount: codAmount,
                    payment_method: codMethod,
                    deliver_pkg_count: deliverPkgCount
                };
                postData[csrfName] = csrfToken;

                return $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: postData,
                    dataType: 'json'
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                var res = result.value;
                if (res.csrf_token) csrfToken = res.csrf_token;
                if (res.status == 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: res.msg,
                        showConfirmButton: false,
                        timer: 1500
                    }).then(function(){
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '<?= __('Lỗi') ?>',
                        text: res.msg
                    });
                }
            }
        });
    });
});
</script>
