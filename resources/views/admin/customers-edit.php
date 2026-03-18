<?php
require_once(__DIR__.'/../../../models/is_admin.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Sửa khách hàng');

$id = intval(input_get('id'));
$customer = $ToryHub->get_row_safe("SELECT * FROM `customers` WHERE `id` = ?", [$id]);
if (!$customer) {
    redirect(base_url('admin/customers-list'));
}

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Sửa khách hàng') ?>: <?= htmlspecialchars($customer['fullname']) ?></h4>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Thông tin khách hàng') ?></h5>
                    </div>
                    <div class="card-body">
                        <div id="alert-box"></div>
                        <form id="form-edit-customer">
                            <input type="hidden" name="<?= $csrf->get_token_name() ?>" value="<?= $csrf->get_token_value() ?>">
                            <input type="hidden" name="request_name" value="edit">
                            <input type="hidden" name="id" value="<?= $customer['id'] ?>">

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Mã khách hàng') ?></label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($customer['customer_code'] ?? '') ?>" disabled>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Họ tên') ?> <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="fullname" value="<?= htmlspecialchars($customer['fullname']) ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Số điện thoại') ?></label>
                                        <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($customer['phone'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Email') ?></label>
                                        <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($customer['email'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Loại khách hàng') ?></label>
                                        <select class="form-select" name="customer_type">
                                            <option value="normal" <?= $customer['customer_type'] == 'normal' ? 'selected' : '' ?>><?= __('Thường') ?></option>
                                            <option value="vip" <?= $customer['customer_type'] == 'vip' ? 'selected' : '' ?>>VIP</option>
                                            <option value="agent" <?= $customer['customer_type'] == 'agent' ? 'selected' : '' ?>><?= __('Đại lý') ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><?= __('Địa chỉ VN') ?></label>
                                <textarea class="form-control" name="address_vn" rows="2"><?= htmlspecialchars($customer['address_vn'] ?? '') ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Zalo</label>
                                        <input type="text" class="form-control" name="zalo" value="<?= htmlspecialchars($customer['zalo'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">WeChat</label>
                                        <input type="text" class="form-control" name="wechat" value="<?= htmlspecialchars($customer['wechat'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><?= __('Ghi chú') ?></label>
                                <textarea class="form-control" name="note" rows="2"><?= htmlspecialchars($customer['note'] ?? '') ?></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary me-2"><?= __('Cập nhật') ?></button>
                            <a href="<?= base_url('admin/customers-list') ?>" class="btn btn-secondary"><?= __('Quay lại') ?></a>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Thống kê') ?></h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between">
                                <span><?= __('Tổng đơn hàng') ?></span>
                                <span class="badge bg-primary"><?= $customer['total_orders'] ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span><?= __('Tổng chi tiêu') ?></span>
                                <span class="text-success"><?= format_vnd($customer['total_spent']) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span><?= __('Ngày tạo') ?></span>
                                <span class="text-muted"><?= $customer['create_date'] ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
$('#form-edit-customer').on('submit', function(e){
    e.preventDefault();
    $.post('<?= base_url('ajaxs/admin/customers.php') ?>', $(this).serialize(), function(res){
        if(res.status == 'success'){
            Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){
                window.location.href = '<?= base_url('admin/customers-list') ?>';
            });
        } else {
            $('#alert-box').html('<div class="alert alert-danger">' + res.msg + '</div>');
        }
    }, 'json');
});
</script>
