<?php
require_once(__DIR__.'/../../../models/is_staff_vn.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = 'Dashboard';

// Stats relevant to VN warehouse
$vn_warehouse_orders = $ToryHub->num_rows_safe("SELECT * FROM `orders` WHERE `status` = 'vn_warehouse'", []) ?: 0;
$shipping_orders = $ToryHub->num_rows_safe("SELECT * FROM `orders` WHERE `status` = 'shipping'", []) ?: 0;
$delivered_orders = $ToryHub->num_rows_safe("SELECT * FROM `orders` WHERE `status` = 'delivered'", []) ?: 0;
$delivered_today = $ToryHub->num_rows_safe("SELECT * FROM `orders` WHERE `status` = 'delivered' AND DATE(`delivered_date`) = CURDATE()", []) ?: 0;

// Recent orders at VN warehouse / shipping / delivered
$recent_orders = $ToryHub->get_list_safe("SELECT o.*, c.fullname as customer_name, c.customer_code
    FROM `orders` o LEFT JOIN `customers` c ON o.customer_id = c.id
    WHERE o.status IN ('shipping', 'vn_warehouse', 'delivered')
    ORDER BY o.update_date DESC LIMIT 10", []);

// Orders waiting for delivery (vn_warehouse status)
$waiting_delivery = $ToryHub->get_list_safe("SELECT o.*, c.fullname as customer_name, c.customer_code, c.phone as customer_phone
    FROM `orders` o LEFT JOIN `customers` c ON o.customer_id = c.id
    WHERE o.status = 'vn_warehouse'
    ORDER BY o.create_date ASC LIMIT 10", []);

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>

        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Dashboard - <?= __('Kho Việt Nam') ?></h4>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1 overflow-hidden">
                                <p class="text-uppercase fw-medium text-muted text-truncate mb-0"><?= __('Kho hàng Việt Nam') ?></p>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div><h4 class="fs-22 fw-semibold mb-4"><?= $vn_warehouse_orders ?></h4></div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success-subtle rounded fs-3"><i class="ri-inbox-archive-line text-success"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1 overflow-hidden">
                                <p class="text-uppercase fw-medium text-muted text-truncate mb-0"><?= __('Đang vận chuyển') ?></p>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div><h4 class="fs-22 fw-semibold mb-4"><?= $shipping_orders ?></h4></div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning-subtle rounded fs-3"><i class="ri-truck-line text-warning"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1 overflow-hidden">
                                <p class="text-uppercase fw-medium text-muted text-truncate mb-0"><?= __('Đã giao hàng') ?></p>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div><h4 class="fs-22 fw-semibold mb-4"><?= $delivered_orders ?></h4></div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle rounded fs-3"><i class="ri-checkbox-circle-line text-primary"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1 overflow-hidden">
                                <p class="text-uppercase fw-medium text-muted text-truncate mb-0"><?= __('Giao hôm nay') ?></p>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div><h4 class="fs-22 fw-semibold mb-4"><?= $delivered_today ?></h4></div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-info-subtle rounded fs-3"><i class="ri-calendar-check-line text-info"></i></span>
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
                        <a href="<?= base_url('staff_vn/orders-list') ?>" class="btn btn-sm btn-primary"><?= __('Xem tất cả') ?></a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-color-heading">
                                    <tr>
                                        <th><?= __('Mã đơn') ?></th>
                                        <th><?= __('Khách hàng') ?></th>
                                        <th><?= __('Sản phẩm') ?></th>
                                        <th><?= __('Tổng tiền') ?></th>
                                        <th><?= __('Trạng thái') ?></th>
                                        <th><?= __('Cập nhật') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_orders)): ?>
                                    <tr><td colspan="6" class="text-center text-muted"><?= __('Chưa có đơn hàng') ?></td></tr>
                                    <?php else: ?>
                                    <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td><strong><?= $order['order_code'] ?></strong></td>
                                        <td>
                                            <?= htmlspecialchars($order['customer_code'] ?? '') ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($order['customer_name'] ?? '') ?></small>
                                        </td>
                                        <td><?= htmlspecialchars(mb_strimwidth($order['product_name'] ?? '', 0, 30, '...')) ?></td>
                                        <td><?= format_vnd($order['grand_total']) ?></td>
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

            <!-- Waiting Delivery -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0"><?= __('Chờ giao hàng') ?></h5>
                        <a href="<?= base_url('staff_vn/orders-delivery') ?>" class="btn btn-sm btn-success"><?= __('Giao hàng') ?></a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($waiting_delivery)): ?>
                            <p class="text-center text-muted"><?= __('Không có đơn chờ giao') ?></p>
                        <?php else: ?>
                            <?php foreach ($waiting_delivery as $order): ?>
                            <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?= $order['order_code'] ?></h6>
                                    <small class="text-muted"><?= htmlspecialchars($order['customer_name'] ?? 'N/A') ?></small>
                                    <?php if (!empty($order['customer_phone'])): ?>
                                        <br><small class="text-muted"><i class="ri-phone-line"></i> <?= htmlspecialchars($order['customer_phone']) ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <span class="fw-bold"><?= format_vnd($order['grand_total']) ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Delivery Stats -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Thống kê giao hàng') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <span><?= __('Đang vận chuyển') ?></span>
                            <span class="badge bg-warning"><?= $shipping_orders ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span><?= __('Kho hàng Việt Nam') ?></span>
                            <span class="badge bg-success"><?= $vn_warehouse_orders ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span><?= __('Đã giao hàng') ?></span>
                            <span class="badge bg-primary"><?= $delivered_orders ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span><strong><?= __('Giao hôm nay') ?></strong></span>
                            <span class="badge bg-info"><?= $delivered_today ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>
