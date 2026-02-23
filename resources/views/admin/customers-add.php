<?php
require_once(__DIR__.'/../../../models/is_admin.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Thêm khách hàng');

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Thêm khách hàng') ?></h4>
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
                        <form id="form-add-customer">
                            <input type="hidden" name="<?= $csrf->get_token_name() ?>" value="<?= $csrf->get_token_value() ?>">
                            <input type="hidden" name="request_name" value="add">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Họ tên') ?> <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="fullname" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Số điện thoại') ?> <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="phone" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Email') ?></label>
                                        <input type="email" class="form-control" name="email">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Loại khách hàng') ?></label>
                                        <select class="form-select" name="customer_type">
                                            <option value="normal"><?= __('Thường') ?></option>
                                            <option value="vip">VIP</option>
                                            <option value="agent"><?= __('Đại lý') ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><?= __('Địa chỉ VN') ?></label>
                                <textarea class="form-control" name="address_vn" rows="2"></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Zalo</label>
                                        <input type="text" class="form-control" name="zalo">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">WeChat</label>
                                        <input type="text" class="form-control" name="wechat">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><?= __('Ghi chú') ?></label>
                                <textarea class="form-control" name="note" rows="2"></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary me-2"><?= __('Thêm mới') ?></button>
                            <a href="<?= base_url('admin/customers-list') ?>" class="btn btn-secondary"><?= __('Hủy') ?></a>
                        </form>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
$('#form-add-customer').on('submit', function(e){
    e.preventDefault();
    var $btn = $(this).find('button[type=submit]').prop('disabled', true);
    $.post('<?= base_url('ajaxs/admin/customers.php') ?>', $(this).serialize(), function(res){
        if(res.status == 'success'){
            Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){
                window.location.href = '<?= base_url('admin/customers-list') ?>';
            });
        } else {
            $('#alert-box').html('<div class="alert alert-danger">' + res.msg + '</div>');
            $btn.prop('disabled', false);
        }
    }, 'json').fail(function(xhr){
        $('#alert-box').html('<div class="alert alert-danger"><?= __('Lỗi kết nối server') ?> (' + xhr.status + ')</div>');
        $btn.prop('disabled', false);
    });
});
</script>
