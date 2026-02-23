<?php
require_once(__DIR__.'/../../../models/is_admin.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Cài đặt hệ thống');

// Get all settings
$settings = $CMSNT->get_list_safe("SELECT * FROM `settings` ORDER BY `id` ASC", []);
$settingsMap = [];
foreach ($settings as $s) {
    $settingsMap[$s['name']] = $s['value'];
}

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Cài đặt hệ thống') ?></h4>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header p-0 border-bottom-0">
                        <ul class="nav nav-tabs nav-tabs-custom nav-primary" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#tab-general" role="tab">
                                    <i class="ri-settings-3-line me-1"></i> <?= __('Cài đặt chung') ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#tab-shipping" role="tab">
                                    <i class="ri-truck-line me-1"></i> <?= __('Vận chuyển') ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#tab-telegram" role="tab">
                                    <i class="ri-telegram-line me-1"></i> Telegram
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#tab-email" role="tab">
                                    <i class="ri-mail-line me-1"></i> Email SMTP
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- Tab: General -->
                            <div class="tab-pane active" id="tab-general" role="tabpanel">
                                <div id="alert-box"></div>
                                <form id="form-settings">
                                    <input type="hidden" name="<?= $csrf->get_token_name() ?>" value="<?= $csrf->get_token_value() ?>">
                                    <input type="hidden" name="request_name" value="update_settings">

                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Tên website') ?></label>
                                        <input type="text" class="form-control" name="site_name" value="<?= htmlspecialchars($settingsMap['site_name'] ?? 'CMS01') ?>">
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label"><?= __('Tỷ giá CNY → VND') ?></label>
                                                <div class="input-group">
                                                    <span class="input-group-text">1¥ =</span>
                                                    <input type="number" class="form-control" name="exchange_rate_cny_vnd" value="<?= htmlspecialchars($settingsMap['exchange_rate_cny_vnd'] ?? '3500') ?>">
                                                    <span class="input-group-text">VND</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label"><?= __('Phí dịch vụ mua hộ') ?> (%)</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" name="service_fee_percent" value="<?= htmlspecialchars($settingsMap['service_fee_percent'] ?? '3') ?>" step="0.1">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Trạng thái website') ?></label>
                                        <select class="form-select" name="site_status">
                                            <option value="1" <?= ($settingsMap['site_status'] ?? '1') == '1' ? 'selected' : '' ?>><?= __('Hoạt động') ?></option>
                                            <option value="0" <?= ($settingsMap['site_status'] ?? '1') == '0' ? 'selected' : '' ?>><?= __('Bảo trì') ?></option>
                                        </select>
                                    </div>

                                    <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> <?= __('Lưu cài đặt') ?></button>
                                </form>
                            </div>

                            <!-- Tab: Shipping -->
                            <div class="tab-pane" id="tab-shipping" role="tabpanel">
                                <div id="alert-box-shipping"></div>
                                <form id="form-shipping">
                                    <input type="hidden" name="<?= $csrf->get_token_name() ?>" value="<?= $csrf->get_token_value() ?>">
                                    <input type="hidden" name="request_name" value="update_settings">

                                    <h6 class="mb-3"><i class="ri-truck-line me-1"></i><?= __('Đơn giá cước đường bộ') ?> <span class="text-muted fs-12">(VND)</span></h6>

                                    <div class="table-responsive mb-3">
                                        <table class="table table-bordered align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th></th>
                                                    <th class="text-center"><span class="badge bg-success-subtle text-success"><?= __('Hàng dễ vận chuyển') ?></span></th>
                                                    <th class="text-center"><span class="badge bg-danger-subtle text-danger"><?= __('Hàng khó vận chuyển') ?></span></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td class="fw-medium"><?= __('Đơn giá / kg') ?></td>
                                                    <td><input type="number" class="form-control" name="shipping_road_easy_per_kg" value="<?= htmlspecialchars($settingsMap['shipping_road_easy_per_kg'] ?? '25000') ?>" step="1" min="0"></td>
                                                    <td><input type="number" class="form-control" name="shipping_road_difficult_per_kg" value="<?= htmlspecialchars($settingsMap['shipping_road_difficult_per_kg'] ?? '35000') ?>" step="1" min="0"></td>
                                                </tr>
                                                <tr>
                                                    <td class="fw-medium"><?= __('Đơn giá / m³') ?></td>
                                                    <td><input type="number" class="form-control" name="shipping_road_easy_per_cbm" value="<?= htmlspecialchars($settingsMap['shipping_road_easy_per_cbm'] ?? '6000000') ?>" step="1" min="0"></td>
                                                    <td><input type="number" class="form-control" name="shipping_road_difficult_per_cbm" value="<?= htmlspecialchars($settingsMap['shipping_road_difficult_per_cbm'] ?? '8000000') ?>" step="1" min="0"></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="alert alert-info py-2 fs-12">
                                        <i class="ri-information-line me-1"></i><?= __('Cước tính = MAX(cân nặng × đơn giá/kg, số khối × đơn giá/m³)') ?>
                                    </div>

                                    <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> <?= __('Lưu cài đặt') ?></button>
                                </form>
                            </div>

                            <!-- Tab: Telegram -->
                            <div class="tab-pane" id="tab-telegram" role="tabpanel">
                                <div id="alert-box-tg"></div>
                                <form id="form-telegram">
                                    <input type="hidden" name="<?= $csrf->get_token_name() ?>" value="<?= $csrf->get_token_value() ?>">
                                    <input type="hidden" name="request_name" value="update_settings">

                                    <div class="mb-3 form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="telegram_enabled" value="1" <?= ($settingsMap['telegram_enabled'] ?? '0') == '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label"><?= __('Bật thông báo Telegram') ?></label>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Bot Token</label>
                                        <input type="text" class="form-control" name="telegram_bot_token" value="<?= htmlspecialchars($settingsMap['telegram_bot_token'] ?? '') ?>" placeholder="123456:ABC-DEF...">
                                        <small class="text-muted"><?= __('Tạo bot qua') ?> <a href="https://t.me/BotFather" target="_blank">@BotFather</a></small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Chat ID</label>
                                        <input type="text" class="form-control" name="telegram_chat_id" value="<?= htmlspecialchars($settingsMap['telegram_chat_id'] ?? '') ?>" placeholder="-100123456789">
                                        <small class="text-muted"><?= __('Lấy chat ID qua') ?> <a href="https://t.me/userinfobot" target="_blank">@userinfobot</a></small>
                                    </div>

                                    <button type="submit" class="btn btn-primary me-2"><i class="ri-save-line me-1"></i> <?= __('Lưu') ?></button>
                                    <button type="button" class="btn btn-info" id="btn-test-tg"><i class="ri-send-plane-line me-1"></i> <?= __('Gửi tin nhắn test') ?></button>
                                </form>
                            </div>

                            <!-- Tab: Email SMTP -->
                            <div class="tab-pane" id="tab-email" role="tabpanel">
                                <div id="alert-box-email"></div>
                                <form id="form-email">
                                    <input type="hidden" name="<?= $csrf->get_token_name() ?>" value="<?= $csrf->get_token_value() ?>">
                                    <input type="hidden" name="request_name" value="update_settings">

                                    <div class="mb-3 form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="email_enabled" value="1" <?= ($settingsMap['email_enabled'] ?? '0') == '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label"><?= __('Bật gửi email thông báo') ?></label>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="mb-3">
                                                <label class="form-label">SMTP Host</label>
                                                <input type="text" class="form-control" name="mail_host" value="<?= htmlspecialchars($settingsMap['mail_host'] ?? '') ?>" placeholder="smtp.gmail.com">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Port</label>
                                                <input type="number" class="form-control" name="mail_port" value="<?= htmlspecialchars($settingsMap['mail_port'] ?? '587') ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Username (Email)</label>
                                        <input type="text" class="form-control" name="mail_username" value="<?= htmlspecialchars($settingsMap['mail_username'] ?? '') ?>" placeholder="your@gmail.com">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Password (App Password)</label>
                                        <input type="password" class="form-control" name="mail_password" value="<?= htmlspecialchars($settingsMap['mail_password'] ?? '') ?>">
                                        <small class="text-muted"><?= __('Dùng App Password nếu bật 2FA') ?></small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Encryption</label>
                                        <select class="form-select" name="mail_encryption">
                                            <option value="tls" <?= ($settingsMap['mail_encryption'] ?? 'tls') == 'tls' ? 'selected' : '' ?>>TLS</option>
                                            <option value="ssl" <?= ($settingsMap['mail_encryption'] ?? 'tls') == 'ssl' ? 'selected' : '' ?>>SSL</option>
                                        </select>
                                    </div>

                                    <button type="submit" class="btn btn-primary me-2"><i class="ri-save-line me-1"></i> <?= __('Lưu') ?></button>
                                    <button type="button" class="btn btn-info" id="btn-test-email"><i class="ri-send-plane-line me-1"></i> <?= __('Gửi email test') ?></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="ri-information-line me-1"></i> <?= __('Thông tin hệ thống') ?></h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between"><span>PHP Version</span> <span class="text-muted"><?= phpversion() ?></span></li>
                            <li class="list-group-item d-flex justify-content-between"><span>Server</span> <span class="text-muted"><?= $_SERVER['SERVER_SOFTWARE'] ?? 'N/A' ?></span></li>
                            <li class="list-group-item d-flex justify-content-between"><span><?= __('Tỷ giá hiện tại') ?></span> <span class="text-primary fw-bold"><?= format_vnd(get_exchange_rate()) ?>/¥</span></li>
                        </ul>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="ri-truck-line me-1"></i> <?= __('Đơn giá cước đường bộ') ?></h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-borderless mb-0">
                            <tr class="border-bottom">
                                <td></td>
                                <td class="text-center text-muted small"><?= __('Hàng dễ') ?></td>
                                <td class="text-center text-muted small"><?= __('Hàng khó') ?></td>
                            </tr>
                            <tr class="border-bottom">
                                <td class="text-muted">/kg</td>
                                <td class="text-end fw-bold"><?= format_vnd($settingsMap['shipping_road_easy_per_kg'] ?? 25000) ?></td>
                                <td class="text-end fw-bold"><?= format_vnd($settingsMap['shipping_road_difficult_per_kg'] ?? 35000) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">/m³</td>
                                <td class="text-end fw-bold"><?= format_vnd($settingsMap['shipping_road_easy_per_cbm'] ?? 6000000) ?></td>
                                <td class="text-end fw-bold"><?= format_vnd($settingsMap['shipping_road_difficult_per_cbm'] ?? 8000000) ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
$('#form-settings').on('submit', function(e){
    e.preventDefault();
    $.post('<?= base_url('ajaxs/admin/settings.php') ?>', $(this).serialize(), function(res){
        if(res.status == 'success'){
            Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
        } else {
            $('#alert-box').html('<div class="alert alert-danger">' + res.msg + '</div>');
        }
    }, 'json');
});

$('#form-shipping').on('submit', function(e){
    e.preventDefault();
    $.post('<?= base_url('ajaxs/admin/settings.php') ?>', $(this).serialize(), function(res){
        if(res.status == 'success'){
            Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
        } else {
            $('#alert-box-shipping').html('<div class="alert alert-danger">' + res.msg + '</div>');
        }
    }, 'json');
});

$('#form-telegram').on('submit', function(e){
    e.preventDefault();
    $.post('<?= base_url('ajaxs/admin/settings.php') ?>', $(this).serialize(), function(res){
        if(res.status == 'success'){
            Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
        } else {
            $('#alert-box-tg').html('<div class="alert alert-danger">' + res.msg + '</div>');
        }
    }, 'json');
});

$('#form-email').on('submit', function(e){
    e.preventDefault();
    $.post('<?= base_url('ajaxs/admin/settings.php') ?>', $(this).serialize(), function(res){
        if(res.status == 'success'){
            Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
        } else {
            $('#alert-box-email').html('<div class="alert alert-danger">' + res.msg + '</div>');
        }
    }, 'json');
});

$('#btn-test-email').on('click', function(){
    var email = prompt('<?= __('Nhập email nhận test') ?>:', $('[name=mail_username]').val());
    if(!email) return;
    $.post('<?= base_url('ajaxs/admin/settings.php') ?>', {
        request_name: 'test_email',
        test_email: email,
        <?= $csrf->get_token_name() ?>: '<?= $csrf->get_token_value() ?>'
    }, function(res){
        if(res.status == 'success'){
            Swal.fire({icon: 'success', title: '<?= __('Gửi thành công!') ?>'});
        } else {
            Swal.fire({icon: 'error', title: 'Error', text: res.msg});
        }
    }, 'json');
});

$('#btn-test-tg').on('click', function(){
    $.post('<?= base_url('ajaxs/admin/settings.php') ?>', {
        request_name: 'test_telegram',
        csrf_token: '<?= $csrf->get_token_value() ?>'
    }, function(res){
        if(res.status == 'success'){
            Swal.fire({icon: 'success', title: '<?= __('Gửi thành công!') ?>'});
        } else {
            Swal.fire({icon: 'error', title: 'Error', text: res.msg});
        }
    }, 'json');
});
</script>
