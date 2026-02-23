<?php
// Public tracking page - no login required
$ToryHub = new DB();
$csrf = new Csrf();
?>
<!doctype html>
<html lang="vi" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= __('Tra cứu đơn hàng') ?> - ToryHub</title>
    <link rel="shortcut icon" href="<?= base_url('public/material/assets/images/favicon.ico') ?>">
    <!-- Bootstrap 5 Css -->
    <link href="<?= base_url('public/material/assets/css/bootstrap.min.css') ?>" rel="stylesheet" type="text/css">
    <!-- Icons Css -->
    <link href="<?= base_url('public/material/assets/css/icons.min.css') ?>" rel="stylesheet" type="text/css">
    <!-- App Css -->
    <link href="<?= base_url('public/material/assets/css/app.min.css') ?>" rel="stylesheet" type="text/css">
    <!-- Custom Css -->
    <link href="<?= base_url('public/material/assets/css/custom.css') ?>" rel="stylesheet" type="text/css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- SweetAlert2 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .tracking-hero {
            background: linear-gradient(135deg, #405189 0%, #0ab39c 100%);
            min-height: 250px;
            display: flex;
            align-items: center;
        }
        .stat-card { transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .timeline-sm { position: relative; padding-left: 20px; }
        .timeline-sm::before { content:''; position: absolute; left: 5px; top: 0; bottom: 0; width: 2px; background: #e9ebec; }
        .timeline-sm-item { position: relative; }
        .timeline-sm-item::before { content:''; position: absolute; left: -19px; top: 4px; width: 10px; height: 10px; border-radius: 50%; background: #0ab39c; border: 2px solid #fff; }
        .timeline-sm-date { font-size: 11px; color: #878a99; }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <div class="tracking-hero">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center">
                    <h2 class="text-white mb-3"><b>ToryHub</b> - <?= __('Tra cứu đơn hàng') ?></h2>
                    <p class="text-white-50 mb-4"><?= __('Nhập mã đơn hàng để theo dõi trạng thái') ?></p>
                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            <div class="input-group input-group-lg">
                                <input type="text" class="form-control" id="tracking-code" placeholder="<?= __('Nhập mã đơn hàng...') ?>" autofocus>
                                <button class="btn btn-light" type="button" id="btn-tracking">
                                    <i class="ri-search-line"></i> <?= __('Tra cứu') ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Result Section -->
    <div class="container mt-4 mb-5">
        <div id="tracking-result" style="display:none;">
            <!-- Filled by AJAX -->
        </div>

        <div id="tracking-empty" style="display:none;">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="ri-search-line fs-1 text-muted"></i>
                            <h5 class="mt-3 text-muted"><?= __('Không tìm thấy đơn hàng') ?></h5>
                            <p class="text-muted"><?= __('Vui lòng kiểm tra lại mã đơn hàng') ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="tracking-loading" style="display:none;">
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted"><?= __('Đang tra cứu...') ?></p>
            </div>
        </div>

        <!-- Login link -->
        <div class="text-center mt-4">
            <a href="<?= base_url('customer/login') ?>" class="text-muted">
                <i class="ri-login-box-line"></i> <?= __('Đăng nhập để xem chi tiết') ?>
            </a>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="<?= base_url('public/material/assets/libs/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
    <script>
    $(document).ready(function(){
        function doTracking() {
            var code = $.trim($('#tracking-code').val());
            if (!code) {
                Swal.fire({icon:'warning', title:'<?= __('Thông báo') ?>', text:'<?= __('Vui lòng nhập mã đơn hàng') ?>'});
                return;
            }

            $('#tracking-result').hide();
            $('#tracking-empty').hide();
            $('#tracking-loading').show();

            $.ajax({
                url: '<?= base_url('ajaxs/customer/tracking.php') ?>',
                type: 'POST',
                data: { order_code: code },
                dataType: 'json',
                success: function(res) {
                    $('#tracking-loading').hide();
                    if (res.status == 'success') {
                        var o = res.data;
                        var html = '';

                        // Status Timeline
                        html += '<div class="card"><div class="card-body">';
                        html += '<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">';
                        var flow = ['cn_warehouse','shipping','vn_warehouse','delivered'];
                        var currentIdx = flow.indexOf(o.status);
                        var isCancelled = o.status === 'cancelled';

                        for (var i = 0; i < flow.length; i++) {
                            var isCompleted = !isCancelled && currentIdx >= 0 && i <= currentIdx;
                            var isCurrent = o.status === flow[i];
                            var bgClass = isCompleted ? 'bg-success' : 'bg-light text-muted';
                            if (isCurrent) bgClass = 'bg-primary';

                            html += '<div class="text-center flex-fill">';
                            html += '<div class="avatar-sm mx-auto mb-1"><span class="avatar-title ' + bgClass + ' rounded-circle fs-5">';
                            if (isCompleted && !isCurrent) {
                                html += '<i class="ri-check-line"></i>';
                            } else {
                                html += (i + 1);
                            }
                            html += '</span></div>';
                            html += '<small class="' + (isCurrent ? 'fw-bold text-primary' : '') + '">' + o.status_labels[flow[i]] + '</small>';
                            html += '</div>';

                            if (i < flow.length - 1) {
                                var lineColor = (isCompleted && i < currentIdx) ? '#0ab39c' : '#e9ebec';
                                html += '<div class="flex-fill" style="height:2px;background:' + lineColor + ';margin-top:-20px;"></div>';
                            }
                        }

                        if (isCancelled) {
                            html += '<div class="text-center"><div class="avatar-sm mx-auto mb-1"><span class="avatar-title bg-danger rounded-circle fs-5"><i class="ri-close-line"></i></span></div>';
                            html += '<small class="fw-bold text-danger">' + o.status_labels['cancelled'] + '</small></div>';
                        }
                        html += '</div></div></div>';

                        // Order Info
                        html += '<div class="row"><div class="col-lg-8">';
                        html += '<div class="card"><div class="card-header"><h5 class="card-title mb-0"><?= __('Thông tin đơn hàng') ?></h5></div>';
                        html += '<div class="card-body"><table class="table table-borderless mb-0">';
                        html += '<tr><td class="text-muted"><?= __('Mã đơn') ?></td><td class="fw-bold">' + o.order_code + '</td></tr>';
                        html += '<tr><td class="text-muted"><?= __('Sản phẩm') ?></td><td>' + o.product_name + '</td></tr>';
                        html += '<tr><td class="text-muted"><?= __('Trạng thái') ?></td><td>' + o.status_badge + '</td></tr>';
                        html += '<tr><td class="text-muted"><?= __('Ngày tạo') ?></td><td>' + o.create_date + '</td></tr>';
                        html += '</table></div></div>';

                        // Tracking info
                        html += '<div class="card"><div class="card-header"><h5 class="card-title mb-0"><?= __('Mã vận đơn') ?></h5></div>';
                        html += '<div class="card-body"><div class="row">';
                        html += '<div class="col-md-4"><label class="text-muted"><?= __('Mã vận đơn TQ') ?></label><p class="fw-bold">' + (o.cn_tracking || '-') + '</p></div>';
                        html += '<div class="col-md-4"><label class="text-muted"><?= __('Mã vận chuyển QT') ?></label><p class="fw-bold">' + (o.intl_tracking || '-') + '</p></div>';
                        html += '<div class="col-md-4"><label class="text-muted"><?= __('Mã giao hàng VN') ?></label><p class="fw-bold">' + (o.vn_tracking || '-') + '</p></div>';
                        html += '</div></div></div>';
                        html += '</div>';

                        // Status History
                        html += '<div class="col-lg-4">';
                        html += '<div class="card"><div class="card-header"><h5 class="card-title mb-0"><?= __('Lịch sử trạng thái') ?></h5></div>';
                        html += '<div class="card-body">';
                        if (o.history && o.history.length > 0) {
                            html += '<div class="timeline-sm">';
                            for (var j = o.history.length - 1; j >= 0; j--) {
                                var h = o.history[j];
                                html += '<div class="timeline-sm-item pb-3">';
                                html += '<span class="timeline-sm-date">' + h.date + '</span>';
                                html += '<div>' + h.old_status_badge + ' &rarr; ' + h.new_status_badge;
                                if (h.note) html += '<br><small>' + h.note + '</small>';
                                html += '</div></div>';
                            }
                            html += '</div>';
                        } else {
                            html += '<p class="text-muted text-center"><?= __('Chưa có lịch sử') ?></p>';
                        }
                        html += '</div></div>';
                        html += '</div></div>';

                        $('#tracking-result').html(html).show();
                    } else {
                        $('#tracking-empty').show();
                    }
                },
                error: function() {
                    $('#tracking-loading').hide();
                    Swal.fire({icon:'error', title:'<?= __('Lỗi') ?>', text:'<?= __('Lỗi kết nối. Vui lòng thử lại.') ?>'});
                }
            });
        }

        $('#btn-tracking').on('click', doTracking);
        $('#tracking-code').on('keypress', function(e){
            if (e.which == 13) doTracking();
        });

        // Check URL param
        var urlParams = new URLSearchParams(window.location.search);
        var codeParam = urlParams.get('code');
        if (codeParam) {
            $('#tracking-code').val(codeParam);
            doTracking();
        }
    });
    </script>
</body>
</html>
