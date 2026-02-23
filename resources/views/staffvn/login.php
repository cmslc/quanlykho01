<?php
$ToryHub = new DB();
$csrf = new Csrf();

// If already logged in, redirect to dashboard
if (isset($_SESSION['staffvn_login'])) {
    $getUser = $ToryHub->get_row_safe("SELECT * FROM `users` WHERE `role` = 'staffvn' AND `token` = ?", [$_SESSION['staffvn_login']]);
    if ($getUser) {
        redirect(base_url('staffvn/home'));
    }
}
?>
<!doctype html>
<html lang="vi" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= $ToryHub->site('title') ?> - <?= __('Đăng nhập Kho Việt Nam') ?></title>
    <link rel="shortcut icon" href="<?= base_url('public/material/assets/images/favicon.ico') ?>">
    <link href="<?= base_url('public/material/assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('public/material/assets/css/icons.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('public/material/assets/css/app.min.css') ?>" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .auth-page-wrapper { min-height: 100vh; display: flex; align-items: center; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .auth-card { border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); border: none; }
    </style>
</head>
<body>
    <div class="auth-page-wrapper">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-5 col-lg-4">
                    <div class="card auth-card">
                        <div class="card-body p-4">
                            <div class="text-center mb-4">
                                <div class="avatar-md mx-auto mb-3">
                                    <div class="avatar-title bg-success-subtle text-success rounded-circle fs-24">
                                        <i class="ri-store-2-line"></i>
                                    </div>
                                </div>
                                <h4 class="mb-1"><b>Kho Việt Nam - ToryHub</b></h4>
                                <p class="text-muted fs-13"><?= __('Hệ thống quản lý kho Việt Nam') ?></p>
                            </div>

                            <div id="alert-box"></div>

                            <form id="form-login">
                                <input type="hidden" name="<?= $csrf->get_token_name() ?>" value="<?= $csrf->get_token_value() ?>">
                                <input type="hidden" name="request_name" value="login">

                                <div class="mb-3">
                                    <label class="form-label" for="username"><?= __('Tên đăng nhập') ?></label>
                                    <div class="position-relative">
                                        <input type="text" class="form-control" id="username" name="username" placeholder="<?= __('Nhập tên đăng nhập') ?>" required autofocus>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="password"><?= __('Mật khẩu') ?></label>
                                    <div class="position-relative">
                                        <input type="password" class="form-control" id="password" name="password" placeholder="<?= __('Nhập mật khẩu') ?>" required>
                                        <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted" type="button" id="toggle-password">
                                            <i class="ri-eye-fill align-middle"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" class="btn btn-success w-100" id="btn-login">
                                        <span id="btn-text"><?= __('Đăng nhập') ?></span>
                                        <span id="btn-loading" style="display:none;">
                                            <span class="spinner-border spinner-border-sm" role="status"></span> <?= __('Đang xử lý...') ?>
                                        </span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <!-- Navigation buttons -->
                    <div class="d-flex flex-wrap justify-content-center gap-2 mt-3">
                        <a href="<?= base_url('admin/login') ?>" class="btn btn-outline-light btn-sm">
                            <i class="ri-admin-line"></i> Login Admin
                        </a>
                        <a href="<?= base_url('staffvn/login') ?>" class="btn btn-light btn-sm active disabled">
                            <i class="ri-store-2-line"></i> Kho Việt Nam
                        </a>
                        <a href="<?= base_url('staffcn/login') ?>" class="btn btn-outline-light btn-sm">
                            <i class="ri-building-4-line"></i> Kho Trung Quốc
                        </a>
                        <a href="<?= base_url('customer/login') ?>" class="btn btn-outline-light btn-sm">
                            <i class="ri-user-line"></i> Khách Hàng
                        </a>
                    </div>
                    <div class="text-center mt-3">
                        <p class="text-white-50 fs-12">&copy; <?= date('Y') ?> ToryHub v1.0</p>
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

    $('#form-login').on('submit', function(e){
        e.preventDefault();
        $('#btn-text').hide();
        $('#btn-loading').show();
        $('#btn-login').prop('disabled', true);

        $.ajax({
            url: '<?= base_url('ajaxs/staffvn/login.php') ?>',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res){
                if(res.status == 'success'){
                    Swal.fire({
                        icon: 'success',
                        title: res.msg,
                        showConfirmButton: false,
                        timer: 1500
                    }).then(function(){
                        window.location.href = res.redirect || '<?= base_url('staffvn/home') ?>';
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
                    text: '<?= __('Vui lòng thử lại') ?>'
                });
            },
            complete: function(){
                $('#btn-text').show();
                $('#btn-loading').hide();
                $('#btn-login').prop('disabled', false);
            }
        });
    });
    </script>
</body>
</html>
