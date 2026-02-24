<?php
require_once(__DIR__.'/../../../models/is_staffvn.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Giao hàng');

// Orders at VN warehouse ready for delivery
$orders_ready = $ToryHub->get_list_safe("SELECT o.*, c.fullname as customer_name, c.customer_code, c.phone as customer_phone, c.address_vn as customer_address, c.balance as customer_balance
    FROM `orders` o LEFT JOIN `customers` c ON o.customer_id = c.id
    WHERE o.status = 'vn_warehouse'
    ORDER BY o.create_date ASC", []);

// Recently delivered orders (with COD info)
$orders_delivered = $ToryHub->get_list_safe("SELECT o.*, c.fullname as customer_name, c.customer_code,
    cod.amount as cod_amount, cod.payment_method as cod_method
    FROM `orders` o LEFT JOIN `customers` c ON o.customer_id = c.id
    LEFT JOIN `cod_collections` cod ON cod.order_id = o.id
    WHERE o.status = 'delivered'
    ORDER BY o.delivered_date DESC LIMIT 20", []);

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

        <!-- Orders Ready for Delivery -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="ri-inbox-archive-line text-success me-1"></i>
                            <?= __('Đơn hàng chờ giao') ?> (<?= count($orders_ready) ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($orders_ready)): ?>
                            <div class="text-center py-4">
                                <i class="ri-checkbox-circle-line fs-1 text-success"></i>
                                <p class="text-muted mt-2"><?= __('Không có đơn hàng chờ giao') ?></p>
                            </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table data-table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><?= __('Mã đơn') ?></th>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th><?= __('SĐT') ?></th>
                                        <th><?= __('Địa chỉ') ?></th>
                                        <th><?= __('Sản phẩm') ?></th>
                                        <th><?= __('Tổng VND') ?></th>
                                        <th><?= __('Thanh toán') ?></th>
                                        <th><?= __('Hành động') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders_ready as $order): ?>
                                    <tr id="order-row-<?= $order['id'] ?>">
                                        <td><strong><?= $order['order_code'] ?></strong></td>
                                        <td>
                                            <?= htmlspecialchars($order['customer_code'] ?? '') ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($order['customer_name'] ?? '') ?></small>
                                        </td>
                                        <td>
                                            <?php if ($order['customer_phone']): ?>
                                            <a href="tel:<?= htmlspecialchars($order['customer_phone']) ?>"><?= htmlspecialchars($order['customer_phone']) ?></a>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars(mb_strimwidth($order['customer_address'] ?? '', 0, 40, '...')) ?></td>
                                        <td><?= htmlspecialchars(mb_strimwidth($order['product_name'] ?? '', 0, 30, '...')) ?></td>
                                        <td class="text-end fw-bold"><?= format_vnd($order['grand_total']) ?></td>
                                        <td><?= display_payment_status($order['is_paid']) ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-success btn-deliver"
                                                data-id="<?= $order['id'] ?>"
                                                data-code="<?= htmlspecialchars($order['order_code']) ?>"
                                                data-customer="<?= htmlspecialchars($order['customer_name'] ?? '') ?>"
                                                data-customer-id="<?= $order['customer_id'] ?>"
                                                data-amount="<?= $order['grand_total'] ?>"
                                                data-is-paid="<?= $order['is_paid'] ?>"
                                                data-balance="<?= floatval($order['customer_balance']) ?>">
                                                <i class="ri-truck-line"></i> <?= __('Giao hàng') ?>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recently Delivered -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="ri-checkbox-circle-line text-primary me-1"></i>
                            <?= __('Đã giao gần đây') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($orders_delivered)): ?>
                            <p class="text-center text-muted"><?= __('Chưa có đơn hàng đã giao') ?></p>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><?= __('Mã đơn') ?></th>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th><?= __('Sản phẩm') ?></th>
                                        <th><?= __('Tổng VND') ?></th>
                                        <th><?= __('COD') ?></th>
                                        <th><?= __('Ngày giao') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders_delivered as $order): ?>
                                    <tr>
                                        <td><strong><?= $order['order_code'] ?></strong></td>
                                        <td>
                                            <?= htmlspecialchars($order['customer_code'] ?? '') ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($order['customer_name'] ?? '') ?></small>
                                        </td>
                                        <td><?= htmlspecialchars(mb_strimwidth($order['product_name'] ?? '', 0, 30, '...')) ?></td>
                                        <td class="text-end fw-bold"><?= format_vnd($order['grand_total']) ?></td>
                                        <td>
                                            <?php if ($order['cod_amount']): ?>
                                                <span class="text-success fw-semibold"><?= format_vnd($order['cod_amount']) ?></span>
                                                <?php
                                                $methodLabels = ['cash' => __('TM'), 'transfer' => __('CK'), 'balance' => __('Số dư')];
                                                ?>
                                                <br><small class="text-muted"><?= $methodLabels[$order['cod_method']] ?? '' ?></small>
                                            <?php elseif ($order['is_paid']): ?>
                                                <span class="badge bg-success-subtle text-success"><?= __('Đã TT') ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $order['delivered_date'] ?? $order['update_date'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
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
        var balance = parseFloat($(this).data('balance')) || 0;

        var codSection = '';
        if (!isPaid) {
            codSection = '' +
                '<hr class="my-3">' +
                '<div class="form-check form-switch mb-2">' +
                    '<input class="form-check-input" type="checkbox" id="cod-toggle" checked>' +
                    '<label class="form-check-label fw-semibold" for="cod-toggle"><?= __('Thu tiền COD') ?></label>' +
                '</div>' +
                '<div id="cod-fields">' +
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
                                '<option value="balance"><?= __('Trừ số dư') ?> (' + formatVnd(balance) + ')</option>' +
                            '</select>' +
                        '</div>' +
                    '</div>' +
                '</div>';
        }

        Swal.fire({
            title: '<?= __('Xác nhận giao hàng') ?>',
            html: '<?= __('Bạn xác nhận đã giao đơn hàng') ?> <strong>' + orderCode + '</strong> <?= __('cho') ?> <strong>' + customerName + '</strong>?' +
                  (isPaid ? '<br><span class="badge bg-success mt-1"><?= __('Đã thanh toán') ?></span>' : '') +
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
                // Toggle COD fields visibility
                $(document).off('change.cod').on('change.cod', '#cod-toggle', function(){
                    $('#cod-fields').toggle($(this).is(':checked'));
                });
            },
            preConfirm: () => {
                var note = $('#delivery-note').val() || '';
                var collectCod = !isPaid && $('#cod-toggle').is(':checked') ? '1' : '0';
                var codAmount = $('#cod-amount') ? $('#cod-amount').val() : '0';
                var codMethod = $('#cod-method') ? $('#cod-method').val() : 'cash';

                var postData = {
                    request_name: 'mark_delivered',
                    order_id: orderId,
                    note: note,
                    collect_cod: collectCod,
                    cod_amount: codAmount,
                    payment_method: codMethod
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

    function formatVnd(num) {
        return new Intl.NumberFormat('vi-VN').format(num) + ' ₫';
    }
});
</script>
