<?php
require_once(__DIR__.'/../../../models/is_staffvn.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Chuyến giao hàng');

// Batches list
$batches = $ToryHub->get_list_safe("
    SELECT db.*, u.fullname as staff_name,
           (SELECT COUNT(*) FROM delivery_batch_orders WHERE batch_id = db.id AND delivery_status = 'delivered') as delivered_count,
           (SELECT COUNT(*) FROM delivery_batch_orders WHERE batch_id = db.id AND delivery_status = 'failed') as failed_count,
           (SELECT COUNT(*) FROM delivery_batch_orders WHERE batch_id = db.id AND delivery_status = 'pending') as pending_count
    FROM `delivery_batches` db
    LEFT JOIN `users` u ON db.staff_id = u.id
    ORDER BY db.id DESC
", []);

// Available orders for new batch (vn_warehouse, not in any active batch)
$availableOrders = $ToryHub->get_list_safe("
    SELECT o.*, c.fullname as customer_name, c.customer_code, c.phone as customer_phone, c.address_vn as customer_address
    FROM `orders` o
    LEFT JOIN `customers` c ON o.customer_id = c.id
    WHERE o.status = 'vn_warehouse'
    AND o.id NOT IN (
        SELECT dbo.order_id FROM `delivery_batch_orders` dbo
        JOIN `delivery_batches` db ON dbo.batch_id = db.id
        WHERE db.status IN ('preparing','delivering')
    )
    ORDER BY o.create_date ASC
", []);

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>

        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><i class="ri-truck-line me-1"></i><?= __('Chuyến giao hàng') ?></h4>
                    <button class="btn btn-success" id="btn-create-batch"><i class="ri-add-line me-1"></i><?= __('Tạo chuyến mới') ?></button>
                </div>
            </div>
        </div>

        <!-- Batches List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Danh sách chuyến giao') ?> (<?= count($batches) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($batches)): ?>
                            <div class="text-center py-4">
                                <i class="ri-truck-line fs-1 text-muted"></i>
                                <p class="text-muted mt-2"><?= __('Chưa có chuyến giao nào') ?></p>
                            </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table data-table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><?= __('Mã chuyến') ?></th>
                                        <th><?= __('Nhân viên') ?></th>
                                        <th><?= __('Trạng thái') ?></th>
                                        <th><?= __('Đơn hàng') ?></th>
                                        <th><?= __('Tổng tiền') ?></th>
                                        <th><?= __('Đã thu') ?></th>
                                        <th><?= __('Ngày tạo') ?></th>
                                        <th><?= __('Hành động') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($batches as $b):
                                        $statusClass = ['preparing' => 'warning', 'delivering' => 'info', 'completed' => 'success'];
                                        $statusLabel = ['preparing' => __('Đang chuẩn bị'), 'delivering' => __('Đang giao'), 'completed' => __('Hoàn thành')];
                                    ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($b['batch_code']) ?></strong></td>
                                        <td><?= htmlspecialchars($b['staff_name'] ?? '') ?></td>
                                        <td><span class="badge bg-<?= $statusClass[$b['status']] ?? 'secondary' ?>"><?= $statusLabel[$b['status']] ?? $b['status'] ?></span></td>
                                        <td>
                                            <span class="text-success" title="<?= __('Giao thành công') ?>"><?= $b['delivered_count'] ?></span> /
                                            <span class="text-danger" title="<?= __('Thất bại') ?>"><?= $b['failed_count'] ?></span> /
                                            <span class="text-muted" title="<?= __('Chờ giao') ?>"><?= $b['pending_count'] ?></span>
                                            <small class="text-muted">(<?= $b['total_orders'] ?>)</small>
                                        </td>
                                        <td class="text-end"><?= format_vnd($b['total_amount']) ?></td>
                                        <td class="text-end"><?= format_vnd($b['total_collected']) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($b['create_date'])) ?></td>
                                        <td>
                                            <a href="<?= base_url('staffvn/delivery-detail&id=' . $b['id']) ?>" class="btn btn-sm btn-primary">
                                                <i class="ri-eye-line me-1"></i><?= __('Chi tiết') ?>
                                            </a>
                                            <?php if ($b['status'] === 'preparing'): ?>
                                            <button class="btn btn-sm btn-danger btn-delete-batch" data-id="<?= $b['id'] ?>" data-code="<?= htmlspecialchars($b['batch_code']) ?>">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                            <?php endif; ?>
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

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
var csrfToken = '<?= $csrf->get_token_value() ?>';
var csrfName = '<?= $csrf->get_token_name() ?>';
var ajaxUrl = '<?= base_url('ajaxs/staffvn/delivery-batches.php') ?>';

$(document).ready(function(){
    // Create batch
    $('#btn-create-batch').on('click', function(){
        Swal.fire({
            title: '<?= __('Tạo chuyến giao mới') ?>',
            html: '<textarea id="batch-note" class="form-control" placeholder="<?= __('Ghi chú (không bắt buộc)') ?>" rows="2"></textarea>',
            showCancelButton: true,
            confirmButtonText: '<i class="ri-add-line"></i> <?= __('Tạo') ?>',
            cancelButtonText: '<?= __('Hủy') ?>',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                var data = { request_name: 'create_batch', note: $('#batch-note').val() || '' };
                data[csrfName] = csrfToken;
                return $.post(ajaxUrl, data, null, 'json');
            }
        }).then((result) => {
            if (result.isConfirmed) {
                var res = result.value;
                if (res.csrf_token) csrfToken = res.csrf_token;
                if (res.status === 'success') {
                    Swal.fire({ icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false }).then(function(){
                        window.location.href = '<?= base_url('staffvn/delivery-detail&id=') ?>' + res.batch_id;
                    });
                } else {
                    Swal.fire({ icon: 'error', title: '<?= __('Lỗi') ?>', text: res.msg });
                }
            }
        });
    });

    // Delete batch
    $('.btn-delete-batch').on('click', function(){
        var batchId = $(this).data('id');
        var batchCode = $(this).data('code');
        Swal.fire({
            title: '<?= __('Xóa chuyến giao') ?>?',
            text: batchCode,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: '<?= __('Xóa') ?>',
            cancelButtonText: '<?= __('Hủy') ?>',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                var data = { request_name: 'delete_batch', batch_id: batchId };
                data[csrfName] = csrfToken;
                return $.post(ajaxUrl, data, null, 'json');
            }
        }).then((result) => {
            if (result.isConfirmed) {
                var res = result.value;
                if (res.csrf_token) csrfToken = res.csrf_token;
                if (res.status === 'success') {
                    location.reload();
                } else {
                    Swal.fire({ icon: 'error', title: '<?= __('Lỗi') ?>', text: res.msg });
                }
            }
        });
    });
});
</script>
