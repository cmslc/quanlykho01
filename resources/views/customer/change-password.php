<?php
require_once(__DIR__.'/../../../models/is_customer.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Đổi mật khẩu');

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Đổi mật khẩu') ?></h4>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="ri-lock-password-line me-1"></i> <?= __('Đổi mật khẩu') ?></h5>
                    </div>
                    <div class="card-body">
                        <div id="alert-box"></div>

                        <form id="form-change-password">
                            <input type="hidden" name="<?= $csrf->get_token_name() ?>" value="<?= $csrf->get_token_value() ?>">
                            <input type="hidden" name="request_name" value="change_password">

                            <div class="mb-3">
                                <label class="form-label"><?= __('Mật khẩu hiện tại') ?> <span class="text-danger">*</span></label>
                                <div class="position-relative">
                                    <input type="password" class="form-control" name="current_password" id="current_password" required>
                                    <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted" type="button" onclick="togglePassword('current_password')">
                                        <i class="ri-eye-fill align-middle"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><?= __('Mật khẩu mới') ?> <span class="text-danger">*</span></label>
                                <div class="position-relative">
                                    <input type="password" class="form-control" name="new_password" id="new_password" required minlength="6">
                                    <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted" type="button" onclick="togglePassword('new_password')">
                                        <i class="ri-eye-fill align-middle"></i>
                                    </button>
                                </div>
                                <small class="text-muted"><?= __('Tối thiểu 6 ký tự') ?></small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><?= __('Xác nhận mật khẩu mới') ?> <span class="text-danger">*</span></label>
                                <div class="position-relative">
                                    <input type="password" class="form-control" name="confirm_password" id="confirm_password" required minlength="6">
                                    <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted" type="button" onclick="togglePassword('confirm_password')">
                                        <i class="ri-eye-fill align-middle"></i>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100"><?= __('Đổi mật khẩu') ?></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
function togglePassword(id) {
    var input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
}

$('#form-change-password').on('submit', function(e){
    e.preventDefault();

    var newPass = $('[name=new_password]').val();
    var confirmPass = $('[name=confirm_password]').val();

    if (newPass !== confirmPass) {
        $('#alert-box').html('<div class="alert alert-danger"><?= __('Mật khẩu xác nhận không khớp') ?></div>');
        return;
    }

    if (newPass.length < 6) {
        $('#alert-box').html('<div class="alert alert-danger"><?= __('Mật khẩu phải có ít nhất 6 ký tự') ?></div>');
        return;
    }

    $.post('<?= base_url('ajaxs/customer/change-password.php') ?>', $(this).serialize(), function(res){
        if(res.status == 'success'){
            Swal.fire({icon: 'success', title: res.msg, timer: 2000, showConfirmButton: false}).then(function(){
                $('[name=current_password]').val('');
                $('[name=new_password]').val('');
                $('[name=confirm_password]').val('');
            });
        } else {
            $('#alert-box').html('<div class="alert alert-danger">' + res.msg + '</div>');
        }
    }, 'json');
});
</script>
