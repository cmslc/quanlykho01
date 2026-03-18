<?php
require_once(__DIR__.'/../../../models/is_admin.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Thêm nhân viên');

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>

        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Thêm nhân viên') ?></h4>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Thêm nhân viên mới') ?></h5>
                    </div>
                    <div class="card-body">
                        <div id="alert-box"></div>
                        <form id="form-add-user">
                            <input type="hidden" name="<?= $csrf->get_token_name() ?>" value="<?= $csrf->get_token_value() ?>">
                            <input type="hidden" name="request_name" value="add">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label><?= __('Username') ?> <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="username" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label><?= __('Mật khẩu') ?> <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" name="password" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label><?= __('Họ tên') ?></label>
                                        <input type="text" class="form-control" name="fullname">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label><?= __('Email') ?></label>
                                        <input type="email" class="form-control" name="email">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label><?= __('Số điện thoại') ?></label>
                                        <input type="text" class="form-control" name="phone">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label><?= __('Vai trò') ?> <span class="text-danger">*</span></label>
                                        <select class="form-select" name="role" required>
                                            <option value="" disabled selected><?= __('-- Chọn vai trò --') ?></option>
                                            <option value="admin">Admin</option>
                                            <option value="staffcn"><?= __('Nhân viên Kho Trung Quốc') ?></option>
                                            <option value="finance_cn"><?= __('Tài chính Kho Trung Quốc') ?></option>
                                            <option value="staffvn"><?= __('Nhân viên Kho Việt Nam') ?></option>
                                            <option value="finance_vn"><?= __('Tài chính Kho Việt Nam') ?></option>
                                            <option value="customer"><?= __('Khách hàng') ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary me-2"><?= __('Thêm mới') ?></button>
                            <a href="<?= base_url('admin/users-list') ?>" class="btn btn-secondary"><?= __('Hủy') ?></a>
                        </form>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
$('#form-add-user').on('submit', function(e){
    e.preventDefault();
    var $btn = $(this).find('button[type=submit]');
    $btn.prop('disabled', true);
    $.post('<?= base_url('ajaxs/admin/users.php') ?>', $(this).serialize(), function(res){
        if(res.status == 'success'){
            window.location.href = '<?= base_url('admin/users-list') ?>';
        } else {
            $('#alert-box').html('<div class="alert alert-danger">' + res.msg + '</div>');
            $btn.prop('disabled', false);
        }
    }, 'json').fail(function(xhr){
        var msg = 'Lỗi kết nối server';
        try { var r = JSON.parse(xhr.responseText); if(r.msg) msg = r.msg; } catch(e) {
            if(xhr.responseText && xhr.responseText.indexOf('Invalid token') !== -1) msg = 'Phiên hết hạn, vui lòng tải lại trang';
        }
        $('#alert-box').html('<div class="alert alert-danger">' + msg + '</div>');
        $btn.prop('disabled', false);
    });
});
</script>
