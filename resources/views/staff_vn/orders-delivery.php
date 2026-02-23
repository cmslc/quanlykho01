<?php
require_once(__DIR__.'/../../../models/is_staff_vn.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Giao hàng');

// Orders at VN warehouse ready for delivery
$orders_ready = $CMSNT->get_list_safe("SELECT o.*, c.fullname as customer_name, c.customer_code, c.phone as customer_phone, c.address_vn as customer_address
    FROM `orders` o LEFT JOIN `customers` c ON o.customer_id = c.id
    WHERE o.status = 'vn_warehouse'
    ORDER BY o.create_date ASC", []);

// Recently delivered orders
$orders_delivered = $CMSNT->get_list_safe("SELECT o.*, c.fullname as customer_name, c.customer_code
    FROM `orders` o LEFT JOIN `customers` c ON o.customer_id = c.id
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
                                        <td><?= htmlspecialchars($order['customer_phone'] ?? '') ?></td>
                                        <td><?= htmlspecialchars(mb_strimwidth($order['customer_address'] ?? '', 0, 40, '...')) ?></td>
                                        <td><?= htmlspecialchars(mb_strimwidth($order['product_name'] ?? '', 0, 30, '...')) ?></td>
                                        <td class="text-end fw-bold"><?= format_vnd($order['grand_total']) ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-success btn-deliver"
                                                data-id="<?= $order['id'] ?>"
                                                data-code="<?= htmlspecialchars($order['order_code']) ?>"
                                                data-customer="<?= htmlspecialchars($order['customer_name'] ?? '') ?>">
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
$(document).ready(function(){
    $('.btn-deliver').on('click', function(){
        var orderId = $(this).data('id');
        var orderCode = $(this).data('code');
        var customerName = $(this).data('customer');

        Swal.fire({
            title: '<?= __('Xác nhận giao hàng') ?>',
            html: '<?= __('Bạn xác nhận đã giao đơn hàng') ?> <strong>' + orderCode + '</strong> <?= __('cho') ?> <strong>' + customerName + '</strong>?<br><br>' +
                  '<textarea id="delivery-note" class="form-control" placeholder="<?= __('Ghi chú giao hàng (không bắt buộc)') ?>" rows="2"></textarea>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0ab39c',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="ri-truck-line"></i> <?= __('Xác nhận giao') ?>',
            cancelButtonText: '<?= __('Hủy') ?>',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                var note = $('#delivery-note').val();
                return $.ajax({
                    url: '<?= base_url('ajaxs/staff_vn/orders-delivery.php') ?>',
                    type: 'POST',
                    data: {
                        order_id: orderId,
                        note: note,
                        <?= $csrf->get_token_name() ?>: '<?= $csrf->get_token_value() ?>',
                        request_name: 'mark_delivered'
                    },
                    dataType: 'json'
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                var res = result.value;
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
