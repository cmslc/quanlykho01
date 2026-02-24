<?php
require_once(__DIR__.'/../../../models/is_staffvn.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$batch_id = intval(input_get('id'));
$batch = $ToryHub->get_row_safe("SELECT db.*, u.fullname as staff_name
    FROM `delivery_batches` db LEFT JOIN `users` u ON db.staff_id = u.id
    WHERE db.id = ?", [$batch_id]);

if (!$batch) {
    header('Location: ' . base_url('staffvn/delivery-batches'));
    exit;
}

$page_title = __('Chi tiết chuyến giao') . ' - ' . $batch['batch_code'];

// Orders in this batch
$batchOrders = $ToryHub->get_list_safe("
    SELECT dbo.*, o.order_code, o.product_name, o.grand_total, o.is_paid,
           c.fullname as customer_name, c.customer_code, c.phone as customer_phone,
           c.address_vn as customer_address, c.balance as customer_balance
    FROM `delivery_batch_orders` dbo
    JOIN `orders` o ON dbo.order_id = o.id
    LEFT JOIN `customers` c ON o.customer_id = c.id
    ORDER BY dbo.delivery_status ASC, o.create_date ASC
", []);

// Available orders (for adding, if preparing)
$availableOrders = [];
if ($batch['status'] === 'preparing') {
    $availableOrders = $ToryHub->get_list_safe("
        SELECT o.id, o.order_code, o.product_name, o.grand_total,
               c.fullname as customer_name, c.customer_code, c.phone as customer_phone
        FROM `orders` o
        LEFT JOIN `customers` c ON o.customer_id = c.id
        WHERE o.status = 'vn_warehouse'
        AND o.id NOT IN (
            SELECT dbo2.order_id FROM `delivery_batch_orders` dbo2
            JOIN `delivery_batches` db2 ON dbo2.batch_id = db2.id
            WHERE db2.status IN ('preparing','delivering')
        )
        ORDER BY o.create_date ASC
    ", []);
}

$statusClass = ['preparing' => 'warning', 'delivering' => 'info', 'completed' => 'success'];
$statusLabel = ['preparing' => __('Đang chuẩn bị'), 'delivering' => __('Đang giao'), 'completed' => __('Hoàn thành')];

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>

        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">
                        <a href="<?= base_url('staffvn/delivery-batches') ?>" class="text-muted"><i class="ri-arrow-left-s-line"></i></a>
                        <?= __('Chuyến giao') ?> <?= htmlspecialchars($batch['batch_code']) ?>
                        <span class="badge bg-<?= $statusClass[$batch['status']] ?? 'secondary' ?> ms-2"><?= $statusLabel[$batch['status']] ?? $batch['status'] ?></span>
                    </h4>
                    <div>
                        <?php if ($batch['status'] === 'preparing' && count($batchOrders) > 0): ?>
                        <button class="btn btn-info" id="btn-start-batch"><i class="ri-play-line me-1"></i><?= __('Bắt đầu giao') ?></button>
                        <?php endif; ?>
                        <?php if ($batch['status'] === 'delivering'): ?>
                        <button class="btn btn-success" id="btn-complete-batch"><i class="ri-checkbox-circle-line me-1"></i><?= __('Hoàn thành chuyến') ?></button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Batch Summary -->
        <div class="row">
            <div class="col-md-3">
                <div class="card card-animate">
                    <div class="card-body py-3 text-center">
                        <h5 class="mb-0"><?= $batch['total_orders'] ?></h5>
                        <small class="text-muted"><?= __('Tổng đơn') ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-animate">
                    <div class="card-body py-3 text-center">
                        <h5 class="mb-0"><?= format_vnd($batch['total_amount']) ?></h5>
                        <small class="text-muted"><?= __('Tổng tiền') ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-animate">
                    <div class="card-body py-3 text-center">
                        <h5 class="mb-0 text-success"><?= format_vnd($batch['total_collected']) ?></h5>
                        <small class="text-muted"><?= __('Đã thu COD') ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-animate">
                    <div class="card-body py-3 text-center">
                        <h5 class="mb-0"><?= $batch['staff_name'] ?></h5>
                        <small class="text-muted"><?= __('Nhân viên') ?></small>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($batch['status'] === 'preparing' && !empty($availableOrders)): ?>
        <!-- Add Orders -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0"><i class="ri-add-circle-line me-1"></i><?= __('Thêm đơn vào chuyến') ?> (<?= count($availableOrders) ?> <?= __('đơn có sẵn') ?>)</h5>
                        <button class="btn btn-sm btn-success" id="btn-add-selected"><i class="ri-add-line me-1"></i><?= __('Thêm đã chọn') ?></button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive" style="max-height:300px;overflow:auto;">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th width="40"><input type="checkbox" id="check-all-avail"></th>
                                        <th><?= __('Mã đơn') ?></th>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th><?= __('SĐT') ?></th>
                                        <th><?= __('Tổng VND') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($availableOrders as $ao): ?>
                                    <tr>
                                        <td><input type="checkbox" class="avail-check" value="<?= $ao['id'] ?>"></td>
                                        <td><strong><?= $ao['order_code'] ?></strong></td>
                                        <td><?= htmlspecialchars($ao['customer_name'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($ao['customer_phone'] ?? '') ?></td>
                                        <td class="text-end"><?= format_vnd($ao['grand_total']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Batch Orders -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="ri-file-list-3-line me-1"></i><?= __('Đơn hàng trong chuyến') ?> (<?= count($batchOrders) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($batchOrders)): ?>
                            <p class="text-center text-muted py-3"><?= __('Chưa có đơn hàng. Thêm đơn ở trên.') ?></p>
                        <?php else: ?>
                        <!-- Mobile-friendly cards for delivering mode -->
                        <div class="row g-3">
                            <?php foreach ($batchOrders as $bo):
                                $dStatusClass = ['pending' => 'secondary', 'delivered' => 'success', 'failed' => 'danger'];
                                $dStatusLabel = ['pending' => __('Chờ giao'), 'delivered' => __('Đã giao'), 'failed' => __('Thất bại')];
                            ?>
                            <div class="col-md-6 col-lg-4" id="bo-card-<?= $bo['order_id'] ?>">
                                <div class="card border mb-0 <?= $bo['delivery_status'] === 'delivered' ? 'border-success' : ($bo['delivery_status'] === 'failed' ? 'border-danger' : '') ?>">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="mb-0"><strong><?= $bo['order_code'] ?></strong></h6>
                                                <small class="text-muted"><?= htmlspecialchars($bo['customer_code'] ?? '') ?> - <?= htmlspecialchars($bo['customer_name'] ?? '') ?></small>
                                            </div>
                                            <span class="badge bg-<?= $dStatusClass[$bo['delivery_status']] ?? 'secondary' ?>"><?= $dStatusLabel[$bo['delivery_status']] ?? $bo['delivery_status'] ?></span>
                                        </div>

                                        <?php if ($bo['customer_phone']): ?>
                                        <p class="mb-1"><i class="ri-phone-line me-1"></i><a href="tel:<?= htmlspecialchars($bo['customer_phone']) ?>"><?= htmlspecialchars($bo['customer_phone']) ?></a></p>
                                        <?php endif; ?>
                                        <?php if ($bo['customer_address']): ?>
                                        <p class="mb-1 text-muted small"><i class="ri-map-pin-line me-1"></i><?= htmlspecialchars($bo['customer_address']) ?></p>
                                        <?php endif; ?>

                                        <p class="mb-1"><small><?= htmlspecialchars(mb_strimwidth($bo['product_name'] ?? '', 0, 40, '...')) ?></small></p>

                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <span class="fw-bold text-primary"><?= format_vnd($bo['grand_total']) ?></span>

                                            <?php if ($bo['delivery_status'] === 'pending' && $batch['status'] === 'delivering'): ?>
                                            <div>
                                                <button class="btn btn-sm btn-success btn-batch-deliver"
                                                    data-order-id="<?= $bo['order_id'] ?>"
                                                    data-order-code="<?= htmlspecialchars($bo['order_code']) ?>"
                                                    data-amount="<?= $bo['grand_total'] ?>"
                                                    data-is-paid="<?= $bo['is_paid'] ?>"
                                                    data-balance="<?= floatval($bo['customer_balance']) ?>">
                                                    <i class="ri-checkbox-circle-line me-1"></i><?= __('Giao') ?>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger btn-batch-fail"
                                                    data-order-id="<?= $bo['order_id'] ?>"
                                                    data-order-code="<?= htmlspecialchars($bo['order_code']) ?>">
                                                    <i class="ri-close-circle-line"></i>
                                                </button>
                                            </div>
                                            <?php elseif ($bo['delivery_status'] === 'delivered' && $bo['cod_collected']): ?>
                                            <small class="text-success"><i class="ri-money-dollar-circle-line"></i> <?= format_vnd($bo['cod_amount']) ?></small>
                                            <?php endif; ?>

                                            <?php if ($bo['delivery_status'] === 'pending' && $batch['status'] === 'preparing'): ?>
                                            <button class="btn btn-sm btn-outline-danger btn-remove-order" data-order-id="<?= $bo['order_id'] ?>">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($bo['delivery_note']): ?>
                                        <div class="mt-2"><small class="text-muted"><i class="ri-chat-3-line me-1"></i><?= htmlspecialchars($bo['delivery_note']) ?></small></div>
                                        <?php endif; ?>
                                    </div>
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

<script>
var csrfToken = '<?= $csrf->get_token_value() ?>';
var csrfName = '<?= $csrf->get_token_name() ?>';
var ajaxUrl = '<?= base_url('ajaxs/staffvn/delivery-batches.php') ?>';
var batchId = <?= $batch_id ?>;

$(document).ready(function(){
    // Check all
    $('#check-all-avail').on('change', function(){
        $('.avail-check').prop('checked', $(this).is(':checked'));
    });

    // Add selected orders
    $('#btn-add-selected').on('click', function(){
        var ids = [];
        $('.avail-check:checked').each(function(){ ids.push($(this).val()); });
        if (!ids.length) {
            Swal.fire({ icon: 'warning', title: '<?= __('Chưa chọn đơn nào') ?>' });
            return;
        }
        var data = { request_name: 'add_orders', batch_id: batchId, order_ids: ids.join(',') };
        data[csrfName] = csrfToken;
        $.post(ajaxUrl, data, function(res){
            if (res.csrf_token) csrfToken = res.csrf_token;
            if (res.status === 'success') {
                location.reload();
            } else {
                Swal.fire({ icon: 'error', text: res.msg });
            }
        }, 'json');
    });

    // Remove order
    $(document).on('click', '.btn-remove-order', function(){
        var orderId = $(this).data('order-id');
        var data = { request_name: 'remove_order', batch_id: batchId, order_id: orderId };
        data[csrfName] = csrfToken;
        $.post(ajaxUrl, data, function(res){
            if (res.csrf_token) csrfToken = res.csrf_token;
            if (res.status === 'success') location.reload();
        }, 'json');
    });

    // Start batch
    $('#btn-start-batch').on('click', function(){
        Swal.fire({
            title: '<?= __('Bắt đầu giao hàng?') ?>',
            text: '<?= __('Sau khi bắt đầu, không thể thêm/xóa đơn.') ?>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<?= __('Bắt đầu') ?>',
            cancelButtonText: '<?= __('Hủy') ?>',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                var data = { request_name: 'start_batch', batch_id: batchId };
                data[csrfName] = csrfToken;
                return $.post(ajaxUrl, data, null, 'json');
            }
        }).then((result) => {
            if (result.isConfirmed && result.value.status === 'success') {
                location.reload();
            }
        });
    });

    // Deliver order
    $(document).on('click', '.btn-batch-deliver', function(){
        var orderId = $(this).data('order-id');
        var orderCode = $(this).data('order-code');
        var amount = parseFloat($(this).data('amount')) || 0;
        var isPaid = $(this).data('is-paid');
        var balance = parseFloat($(this).data('balance')) || 0;

        var codHtml = '';
        if (!isPaid) {
            codHtml = '<hr class="my-2">' +
                '<div class="form-check form-switch mb-2">' +
                    '<input class="form-check-input" type="checkbox" id="cod-toggle" checked>' +
                    '<label class="form-check-label fw-semibold" for="cod-toggle"><?= __('Thu COD') ?></label>' +
                '</div>' +
                '<div id="cod-fields" class="row g-2">' +
                    '<div class="col-6"><input type="number" id="cod-amount" class="form-control form-control-sm" value="' + amount + '"></div>' +
                    '<div class="col-6"><select id="cod-method" class="form-select form-select-sm">' +
                        '<option value="cash"><?= __('Tiền mặt') ?></option>' +
                        '<option value="transfer"><?= __('Chuyển khoản') ?></option>' +
                        '<option value="balance"><?= __('Trừ số dư') ?> (' + formatVnd(balance) + ')</option>' +
                    '</select></div>' +
                '</div>';
        }

        Swal.fire({
            title: '<?= __('Giao đơn') ?> ' + orderCode,
            html: '<textarea id="delivery-note" class="form-control form-control-sm" placeholder="<?= __('Ghi chú') ?>" rows="1"></textarea>' + codHtml,
            showCancelButton: true,
            confirmButtonColor: '#0ab39c',
            confirmButtonText: '<i class="ri-checkbox-circle-line"></i> <?= __('Xác nhận') ?>',
            cancelButtonText: '<?= __('Hủy') ?>',
            didOpen: function() {
                $(document).off('change.cod2').on('change.cod2', '#cod-toggle', function(){
                    $('#cod-fields').toggle($(this).is(':checked'));
                });
            },
            showLoaderOnConfirm: true,
            preConfirm: () => {
                var postData = {
                    request_name: 'deliver_order',
                    batch_id: batchId,
                    order_id: orderId,
                    note: $('#delivery-note').val() || '',
                    collect_cod: (!isPaid && $('#cod-toggle').is(':checked')) ? '1' : '0',
                    cod_amount: $('#cod-amount').val() || '0',
                    payment_method: $('#cod-method').val() || 'cash'
                };
                postData[csrfName] = csrfToken;
                return $.post(ajaxUrl, postData, null, 'json');
            }
        }).then((result) => {
            if (result.isConfirmed) {
                var res = result.value;
                if (res.csrf_token) csrfToken = res.csrf_token;
                if (res.status === 'success') {
                    location.reload();
                } else {
                    Swal.fire({ icon: 'error', text: res.msg });
                }
            }
        });
    });

    // Fail order
    $(document).on('click', '.btn-batch-fail', function(){
        var orderId = $(this).data('order-id');
        var orderCode = $(this).data('order-code');

        Swal.fire({
            title: '<?= __('Giao thất bại') ?> ' + orderCode,
            html: '<textarea id="fail-note" class="form-control" placeholder="<?= __('Lý do thất bại') ?>" rows="2"></textarea>',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: '<?= __('Xác nhận') ?>',
            cancelButtonText: '<?= __('Hủy') ?>',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                var postData = { request_name: 'fail_order', batch_id: batchId, order_id: orderId, note: $('#fail-note').val() || '' };
                postData[csrfName] = csrfToken;
                return $.post(ajaxUrl, postData, null, 'json');
            }
        }).then((result) => {
            if (result.isConfirmed && result.value.status === 'success') {
                location.reload();
            }
        });
    });

    // Complete batch
    $('#btn-complete-batch').on('click', function(){
        Swal.fire({
            title: '<?= __('Hoàn thành chuyến giao?') ?>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<?= __('Hoàn thành') ?>',
            cancelButtonText: '<?= __('Hủy') ?>',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                var postData = { request_name: 'complete_batch', batch_id: batchId };
                postData[csrfName] = csrfToken;
                return $.post(ajaxUrl, postData, null, 'json');
            }
        }).then((result) => {
            if (result.isConfirmed) {
                var res = result.value;
                if (res.csrf_token) csrfToken = res.csrf_token;
                if (res.status === 'success') {
                    var s = res.summary || {};
                    Swal.fire({
                        icon: 'success',
                        title: '<?= __('Hoàn thành!') ?>',
                        html: '✅ <?= __('Giao thành công') ?>: ' + (s.delivered||0) + '<br>' +
                              '❌ <?= __('Thất bại') ?>: ' + (s.failed||0) + '<br>' +
                              '⏳ <?= __('Chưa xử lý') ?>: ' + (s.pending||0)
                    }).then(function(){ location.reload(); });
                } else {
                    Swal.fire({ icon: 'error', text: res.msg });
                }
            }
        });
    });

    function formatVnd(num) {
        return new Intl.NumberFormat('vi-VN').format(num) + ' ₫';
    }
});
</script>
