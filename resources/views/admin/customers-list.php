<?php
require_once(__DIR__.'/../../../models/is_admin.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Quản lý khách hàng');
$customers = $ToryHub->get_list_safe("SELECT * FROM `customers` ORDER BY `id` DESC", []);

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Quản lý khách hàng') ?></h4>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0"><?= __('Danh sách khách hàng') ?> (<?= count($customers) ?>)</h5>
                        <a href="<?= base_url('admin/customers-add') ?>" class="btn btn-sm btn-primary">
                            <i class="ri-add-line"></i> <?= __('Thêm mới') ?>
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table data-table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?= __('Họ tên') ?></th>
                                        <th><?= __('Điện thoại') ?></th>
                                        <th><?= __('Loại') ?></th>
                                        <th><?= __('Địa chỉ') ?></th>
                                        <th><?= __('Hành động') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $stt = 0; foreach ($customers as $cust): $stt++; ?>
                                    <tr>
                                        <td><?= $stt ?></td>
                                        <td>
                                            <a href="<?= base_url('admin/customers-detail?id=' . $cust['id']) ?>" class="fw-medium">
                                                <?= htmlspecialchars($cust['fullname']) ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($cust['phone'] ?? '') ?></td>
                                        <td><?= display_customer_type($cust['customer_type']) ?></td>
                                        <td><?= htmlspecialchars($cust['address_vn'] ?? '') ?></td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="<?= base_url('admin/customers-detail?id=' . $cust['id']) ?>" class="btn btn-sm btn-soft-info" title="<?= __('Chi tiết') ?>">
                                                    <i class="ri-eye-line me-1"></i><?= __('Xem') ?>
                                                </a>
                                                <a href="<?= base_url('admin/customers-edit?id=' . $cust['id']) ?>" class="btn btn-sm btn-soft-warning" title="<?= __('Sửa') ?>">
                                                    <i class="ri-pencil-line me-1"></i><?= __('Sửa') ?>
                                                </a>
                                                <button class="btn btn-sm btn-soft-danger btn-delete-customer" data-id="<?= $cust['id'] ?>" title="<?= __('Xóa') ?>">
                                                    <i class="ri-delete-bin-line me-1"></i><?= __('Xóa') ?>
                                                </button>
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
$(document).on('click', '.btn-delete-customer', function(){
    var id = $(this).data('id');
    Swal.fire({
        title: '<?= __('Bạn có chắc chắn?') ?>',
        text: '<?= __('Dữ liệu khách hàng sẽ bị xóa vĩnh viễn!') ?>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: '<?= __('Xóa') ?>',
        cancelButtonText: '<?= __('Hủy') ?>'
    }).then(function(result){
        if(result.isConfirmed){
            $.post('<?= base_url('ajaxs/admin/customers.php') ?>', {
                request_name: 'delete',
                id: id,
                csrf_token: '<?= $csrf->get_token_value() ?>'
            }, function(res){
                if(res.status == 'success'){
                    Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
                } else {
                    Swal.fire({icon: 'error', title: 'Error', text: res.msg});
                }
            }, 'json');
        }
    });
});
</script>
