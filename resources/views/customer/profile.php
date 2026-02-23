<?php
require_once(__DIR__.'/../../../models/is_customer.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Hồ sơ cá nhân');

// Get customer info
$customer = $ToryHub->get_row_safe("SELECT * FROM `customers` WHERE `user_id` = ?", [$getUser['id']]);

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Hồ sơ cá nhân') ?></h4>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left: Edit Form -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="ri-edit-line me-1"></i> <?= __('Cập nhật thông tin') ?></h5>
                    </div>
                    <div class="card-body">
                        <div id="alert-box"></div>

                        <form id="form-profile">
                            <input type="hidden" name="<?= $csrf->get_token_name() ?>" value="<?= $csrf->get_token_value() ?>">
                            <input type="hidden" name="request_name" value="update_profile">

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?= __('Họ và tên') ?> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="fullname" value="<?= htmlspecialchars($getUser['fullname'] ?? '') ?>" required minlength="2" maxlength="255">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?= __('Email') ?></label>
                                    <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($getUser['email'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?= __('Số điện thoại') ?></label>
                                    <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($getUser['phone'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Zalo</label>
                                    <input type="text" class="form-control" name="zalo" value="<?= htmlspecialchars($customer['zalo'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">WeChat</label>
                                    <input type="text" class="form-control" name="wechat" value="<?= htmlspecialchars($customer['wechat'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><?= __('Địa chỉ nhận hàng VN') ?></label>
                                <textarea class="form-control" name="address_vn" rows="3"><?= htmlspecialchars($customer['address_vn'] ?? '') ?></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> <?= __('Lưu thay đổi') ?></button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right: Account Info -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title text-white mb-0"><i class="ri-user-line me-1"></i> <?= __('Thông tin tài khoản') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <div class="avatar-lg mx-auto">
                                <span class="avatar-title bg-primary-subtle rounded-circle fs-1">
                                    <i class="ri-user-line text-primary"></i>
                                </span>
                            </div>
                            <h5 class="mt-2 mb-0"><?= htmlspecialchars($getUser['fullname'] ?? $getUser['username']) ?></h5>
                            <small class="text-muted">@<?= htmlspecialchars($getUser['username']) ?></small>
                        </div>

                        <table class="table table-borderless mb-0">
                            <?php if ($customer): ?>
                            <tr>
                                <td class="text-muted"><?= __('Mã khách hàng') ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($customer['customer_code']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted"><?= __('Loại khách hàng') ?></td>
                                <td><?= display_customer_type($customer['customer_type']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted"><?= __('Số dư') ?></td>
                                <td class="fw-bold text-success"><?= format_vnd($customer['balance']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted"><?= __('Tổng đơn') ?></td>
                                <td><?= $customer['total_orders'] ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted"><?= __('Tổng chi tiêu') ?></td>
                                <td><?= format_vnd($customer['total_spent']) ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td class="text-muted"><?= __('Ngày tạo') ?></td>
                                <td><?= $getUser['create_date'] ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
$('#form-profile').on('submit', function(e){
    e.preventDefault();

    var fullname = $('[name=fullname]').val().trim();
    if (fullname.length < 2) {
        $('#alert-box').html('<div class="alert alert-danger"><?= __('Họ và tên phải có ít nhất 2 ký tự') ?></div>');
        return;
    }

    $.post('<?= base_url('ajaxs/customer/profile.php') ?>', $(this).serialize(), function(res){
        if(res.status == 'success'){
            Swal.fire({icon: 'success', title: res.msg, timer: 2000, showConfirmButton: false});
            $('#alert-box').html('');
        } else {
            $('#alert-box').html('<div class="alert alert-danger">' + res.msg + '</div>');
        }
    }, 'json');
});
</script>
