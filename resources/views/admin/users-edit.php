<?php
require_once(__DIR__.'/../../../models/is_admin.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Sửa nhân viên');

$id = intval(input_get('id'));
$editUser = $ToryHub->get_row_safe("SELECT * FROM `users` WHERE `id` = ?", [$id]);
if (!$editUser) {
    redirect(base_url('admin/users-list'));
}

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>

        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Sửa nhân viên') ?></h4>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Sửa thông tin') ?>: <?= htmlspecialchars($editUser['username']) ?></h5>
                    </div>
                    <div class="card-body">
                        <div id="alert-box"></div>
                        <form id="form-edit-user">
                            <input type="hidden" name="<?= $csrf->get_token_name() ?>" value="<?= $csrf->get_token_value() ?>">
                            <input type="hidden" name="request_name" value="edit">
                            <input type="hidden" name="id" value="<?= $editUser['id'] ?>">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label><?= __('Username') ?></label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($editUser['username']) ?>" disabled>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label><?= __('Mật khẩu mới') ?> <small class="text-muted">(<?= __('Bỏ trống nếu không đổi') ?>)</small></label>
                                        <input type="password" class="form-control" name="password">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label><?= __('Họ tên') ?></label>
                                        <input type="text" class="form-control" name="fullname" value="<?= htmlspecialchars($editUser['fullname'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label><?= __('Email') ?></label>
                                        <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($editUser['email'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label><?= __('Số điện thoại') ?></label>
                                        <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($editUser['phone'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label><?= __('Vai trò') ?></label>
                                        <select class="form-select" name="role">
                                            <option value="admin" <?= $editUser['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                                            <option value="staffcn" <?= $editUser['role'] == 'staffcn' ? 'selected' : '' ?>><?= __('Nhân viên Kho Trung Quốc') ?></option>
                                            <option value="finance_cn" <?= $editUser['role'] == 'finance_cn' ? 'selected' : '' ?>><?= __('Tài chính Kho Trung Quốc') ?></option>
                                            <option value="staffvn" <?= $editUser['role'] == 'staffvn' ? 'selected' : '' ?>><?= __('Nhân viên Kho Việt Nam') ?></option>
                                            <option value="customer" <?= $editUser['role'] == 'customer' ? 'selected' : '' ?>><?= __('Khách hàng') ?></option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label><?= __('Trạng thái') ?></label>
                                        <select class="form-select" name="banned">
                                            <option value="0" <?= $editUser['banned'] == 0 ? 'selected' : '' ?>>Active</option>
                                            <option value="1" <?= $editUser['banned'] == 1 ? 'selected' : '' ?>>Banned</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary me-2"><?= __('Cập nhật') ?></button>
                            <a href="<?= base_url('admin/users-list') ?>" class="btn btn-secondary"><?= __('Quay lại') ?></a>
                        </form>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
$('#form-edit-user').on('submit', function(e){
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
