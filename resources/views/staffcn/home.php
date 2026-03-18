<?php
require_once(__DIR__.'/../../../models/is_staffcn.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Bảng điều khiển');

// Stats relevant to CN warehouse
$cn_warehouse_orders = $ToryHub->num_rows_safe("SELECT * FROM `orders` WHERE `status` = 'cn_warehouse'", []) ?: 0;

$today_scanned = $ToryHub->num_rows_safe("SELECT * FROM `orders` WHERE `status` = 'cn_warehouse' AND DATE(`update_date`) = CURDATE()", []) ?: 0;

// Warehouse weight & volume stats
$warehouseStats = $ToryHub->get_row_safe(
    "SELECT COALESCE(SUM(p.weight_actual),0) as total_weight,
            COALESCE(SUM(p.length_cm * p.width_cm * p.height_cm / 1000000),0) as total_cbm,
            COUNT(p.id) as total_packages
     FROM `packages` p WHERE p.status = 'cn_warehouse'", []
);

// Bag stats
$bags_open = $ToryHub->num_rows_safe("SELECT * FROM `bags` WHERE `status` = 'open'", []) ?: 0;
$bags_sealed = $ToryHub->num_rows_safe("SELECT * FROM `bags` WHERE `status` = 'sealed'", []) ?: 0;

// Shipment stats
$shipments_loading = $ToryHub->num_rows_safe("SELECT * FROM `shipments` WHERE `status` = 'loading'", []) ?: 0;
$shipments_shipping = $ToryHub->num_rows_safe("SELECT * FROM `shipments` WHERE `status` = 'shipping'", []) ?: 0;

// Recent orders in CN-relevant statuses
$recent_orders = $ToryHub->get_list_safe("SELECT o.*, c.fullname as customer_name
    FROM `orders` o LEFT JOIN `customers` c ON o.customer_id = c.id
    WHERE o.status IN ('cn_warehouse')
    ORDER BY o.update_date DESC LIMIT 15", []);

// Recent activity logs by this user
$recent_logs = $ToryHub->get_list_safe("SELECT * FROM `logs` WHERE `user_id` = ? ORDER BY `create_date` DESC LIMIT 10", [$getUser['id']]);

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>

        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Bảng điều khiển') ?> - <?= __('Kho Trung Quốc') ?></h4>
                    <div class="page-title-right">
                        <a href="<?= base_url('staffcn/orders-scan') ?>" class="btn btn-primary">
                            <i class="ri-qr-scan-2-line"></i> <?= __('Quét mã nhập kho') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row">
            <div class="col-xl-6 col-md-6">
                <div class="card card-animate stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1 overflow-hidden">
                                <p class="text-uppercase fw-medium text-muted text-truncate mb-0"><?= __('Tại kho Trung Quốc') ?></p>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div>
                                <h4 class="fs-22 fw-semibold mb-4">
                                    <a href="<?= base_url('staffcn/orders-list?status=cn_warehouse') ?>" class="text-decoration-none"><?= $cn_warehouse_orders ?></a>
                                </h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success-subtle rounded fs-3"><i class="ri-building-4-line text-success"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6 col-md-6">
                <div class="card card-animate stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1 overflow-hidden">
                                <p class="text-uppercase fw-medium text-muted text-truncate mb-0"><?= __('Quét hôm nay') ?></p>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div><h4 class="fs-22 fw-semibold mb-4"><?= $today_scanned ?></h4></div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle rounded fs-3"><i class="ri-qr-scan-2-line text-primary"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Warehouse & Logistics Stats -->
        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted text-truncate mb-0"><?= __('Tồn kho TQ') ?></p>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div>
                                <h4 class="fs-22 fw-semibold mb-1"><?= intval($warehouseStats['total_packages']) ?> <?= __('kiện') ?></h4>
                                <small class="text-muted"><?= fnum($warehouseStats['total_weight'], 1) ?> kg · <?= fnum($warehouseStats['total_cbm'], 2) ?> m³</small>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning-subtle rounded fs-3"><i class="ri-scales-line text-warning"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted text-truncate mb-0"><?= __('Bao đang mở') ?></p>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div>
                                <h4 class="fs-22 fw-semibold mb-1">
                                    <a href="<?= base_url('staffcn/bags-list') ?>" class="text-decoration-none"><?= $bags_open ?></a>
                                </h4>
                                <small class="text-muted"><?= __('Đã niêm phong') ?>: <?= $bags_sealed ?></small>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-dark-subtle rounded fs-3"><i class="ri-archive-line text-dark"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted text-truncate mb-0"><?= __('Đang xếp xe') ?></p>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div>
                                <h4 class="fs-22 fw-semibold mb-1">
                                    <a href="<?= base_url('staffcn/shipments-list') ?>" class="text-decoration-none"><?= $shipments_loading ?></a>
                                </h4>
                                <small class="text-muted"><?= __('chuyến xe') ?></small>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-secondary-subtle rounded fs-3"><i class="ri-truck-line text-secondary"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted text-truncate mb-0"><?= __('Đang vận chuyển') ?></p>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div>
                                <h4 class="fs-22 fw-semibold mb-1">
                                    <a href="<?= base_url('staffcn/shipments-list') ?>" class="text-decoration-none"><?= $shipments_shipping ?></a>
                                </h4>
                                <small class="text-muted"><?= __('chuyến xe') ?></small>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle rounded fs-3"><i class="ri-roadster-line text-primary"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Orders -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0"><?= __('Đơn hàng gần đây') ?></h5>
                        <a href="<?= base_url('staffcn/orders-list') ?>" class="btn btn-sm btn-primary"><?= __('Xem tất cả') ?></a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-color-heading">
                                    <tr>
                                        <th><?= __('Mã đơn') ?></th>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th><?= __('Mã vận đơn') ?></th>
                                        <th><?= __('Trạng thái') ?></th>
                                        <th><?= __('Cập nhật') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_orders)): ?>
                                    <tr><td colspan="5" class="text-center text-muted"><?= __('Chưa có đơn hàng') ?></td></tr>
                                    <?php else: ?>
                                    <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td><strong><?= $order['order_code'] ?></strong></td>
                                        <td><?= htmlspecialchars($order['customer_name'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($order['tracking_number'] ?? '-') ?></td>
                                        <td><?= display_order_status($order['status']) ?></td>
                                        <td><?= $order['update_date'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Hoạt động gần đây') ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_logs)): ?>
                        <p class="text-muted text-center"><?= __('Chưa có hoạt động nào') ?></p>
                        <?php else: ?>
                        <div class="acitivity-timeline acitivity-main">
                            <?php foreach ($recent_logs as $log): ?>
                            <div class="acitivity-item d-flex mb-3">
                                <div class="flex-shrink-0">
                                    <div class="avatar-xs">
                                        <div class="avatar-title rounded-circle bg-primary-subtle text-primary">
                                            <i class="ri-checkbox-circle-line"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <p class="text-muted mb-1"><?= htmlspecialchars($log['action'] ?? '') ?></p>
                                    <small class="text-muted"><?= $log['create_date'] ?? '' ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>
