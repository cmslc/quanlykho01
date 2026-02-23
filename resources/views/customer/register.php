<?php
$CMSNT = new DB();
$csrf = new Csrf();

// If already logged in, redirect to dashboard
if (isset($_SESSION['customer_login'])) {
    $getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `role` = 'customer' AND `token` = ?", [$_SESSION['customer_login']]);
    if ($getUser) {
        redirect(base_url('customer/home'));
    }
}
?>
<!doctype html>
<html lang="vi" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= $CMSNT->site('title') ?> - <?= __('Đăng ký tài khoản') ?></title>
    <link rel="shortcut icon" href="<?= base_url('public/material/assets/images/favicon.ico') ?>">
    <link href="<?= base_url('public/material/assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('public/material/assets/css/icons.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('public/material/assets/css/app.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('public/material/assets/css/custom.css') ?>" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .auth-page-wrapper { min-height: 100vh; display: flex; align-items: center; background: linear-gradient(135deg, #405189 0%, #0ab39c 100%); }
        .auth-card { border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); border: none; }
    </style>
</head>
<body>
    <div class="auth-page-wrapper">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="card auth-card">
                        <div class="card-body p-4">
                            <div class="text-center mb-4">
                                <div class="avatar-md mx-auto mb-3">
                                    <div class="avatar-title bg-info-subtle text-info rounded-circle fs-24">
                                        <i class="ri-user-add-line"></i>
                                    </div>
                                </div>
                                <h4 class="mb-1"><b><?= __('Đăng ký tài khoản') ?></b></h4>
                                <p class="text-muted fs-13"><?= __('Tạo tài khoản khách hàng mới') ?></p>
                            </div>

                            <form id="form-register">
                                <input type="hidden" name="<?= $csrf->get_token_name() ?>" value="<?= $csrf->get_token_value() ?>">
                                <input type="hidden" name="request_name" value="register">

                                <div class="mb-3">
                                    <label class="form-label" for="username"><?= __('Tên đăng nhập') ?> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="username" name="username" placeholder="<?= __('Nhập tên đăng nhập') ?>" required autofocus>
                                    <div class="form-text"><?= __('3-50 ký tự, chỉ gồm chữ cái, số và dấu gạch dưới') ?></div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="fullname"><?= __('Họ và tên') ?> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="fullname" name="fullname" placeholder="<?= __('Nhập họ và tên') ?>" required>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="email"><?= __('Email') ?></label>
                                        <input type="email" class="form-control" id="email" name="email" placeholder="<?= __('Nhập email') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="phone"><?= __('Số điện thoại') ?></label>
                                        <input type="text" class="form-control" id="phone" name="phone" placeholder="<?= __('Nhập số điện thoại') ?>">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="password"><?= __('Mật khẩu') ?> <span class="text-danger">*</span></label>
                                    <div class="position-relative">
                                        <input type="password" class="form-control" id="password" name="password" placeholder="<?= __('Nhập mật khẩu (tối thiểu 6 ký tự)') ?>" required>
                                        <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted" type="button" id="toggle-password">
                                            <i class="ri-eye-fill align-middle"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="confirm_password"><?= __('Xác nhận mật khẩu') ?> <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="<?= __('Nhập lại mật khẩu') ?>" required>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary w-100" id="btn-register">
                                        <span id="btn-text"><i class="ri-user-add-line me-1"></i> <?= __('Đăng ký') ?></span>
                                        <span id="btn-loading" style="display:none;">
                                            <span class="spinner-border spinner-border-sm" role="status"></span> <?= __('Đang xử lý...') ?>
                                        </span>
                                    </button>
                                </div>
                            </form>

                            <div class="mt-4 text-center">
                                <p class="mb-0 text-muted"><?= __('Đã có tài khoản?') ?>
                                    <a href="<?= base_url('customer/login') ?>" class="fw-semibold text-primary"> <?= __('Đăng nhập') ?></a>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mt-3">
                        <p class="text-white-50 fs-12">&copy; <?= date('Y') ?> CMS01 v1.0</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= base_url('public/material/assets/libs/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
    <script>
    $('#toggle-password').on('click', function(){
        var input = document.getElementById('password');
        input.type = input.type === 'password' ? 'text' : 'password';
    });

    $('#form-register').on('submit', function(e){
        e.preventDefault();

        var password = $('#password').val();
        var confirm = $('#confirm_password').val();
        if (password !== confirm) {
            Swal.fire({icon:'error', title:'<?= __('Lỗi') ?>', text:'<?= __('Mật khẩu xác nhận không khớp') ?>'});
            return;
        }

        $('#btn-text').hide();
        $('#btn-loading').show();
        $('#btn-register').prop('disabled', true);

        $.ajax({
            url: '<?= base_url('ajaxs/customer/register.php') ?>',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res){
                if(res.status == 'success'){
                    Swal.fire({
                        icon: 'success',
                        title: res.msg,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(function(){
                        window.location.href = res.redirect || '<?= base_url('customer/home') ?>';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '<?= __('Lỗi') ?>',
                        text: res.msg
                    });
                }
            },
            error: function(){
                Swal.fire({
                    icon: 'error',
                    title: '<?= __('Lỗi kết nối') ?>',
                    text: '<?= __('Lỗi kết nối. Vui lòng thử lại.') ?>'
                });
            },
            complete: function(){
                $('#btn-text').show();
                $('#btn-loading').hide();
                $('#btn-register').prop('disabled', false);
            }
        });
    });
    </script>
</body>
</html>
