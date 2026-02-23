<?php
require_once(__DIR__.'/../../../models/is_customer.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = 'Dashboard';

// Get customer info from customers table linked to this user
$customer = $ToryHub->get_row_safe("SELECT * FROM `customers` WHERE `user_id` = ?", [$getUser['id']]);
$customer_id = $customer ? $customer['id'] : 0;

// Stats - only this customer's orders
$total_orders = $ToryHub->num_rows_safe("SELECT * FROM `orders` WHERE `customer_id` = ?", [$customer_id]) ?: 0;
$pending_orders = $ToryHub->num_rows_safe("SELECT * FROM `orders` WHERE `customer_id` = ? AND `status` = 'cn_warehouse'", [$customer_id]) ?: 0;
$shipping_orders = $ToryHub->num_rows_safe("SELECT * FROM `orders` WHERE `customer_id` = ? AND `status` IN ('cn_shipped','shipping')", [$customer_id]) ?: 0;
$delivered_orders = $ToryHub->num_rows_safe("SELECT * FROM `orders` WHERE `customer_id` = ? AND `status` = 'delivered'", [$customer_id]) ?: 0;

// Customer balance
$balance = $customer ? ($customer['balance'] ?? 0) : 0;

// Recent orders
$recent_orders = $ToryHub->get_list_safe("SELECT * FROM `orders` WHERE `customer_id` = ? ORDER BY `create_date` DESC LIMIT 10", [$customer_id]);

// Exchange rate
$exchange_rate = get_exchange_rate();

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>

        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Xin chào') ?>, <?= htmlspecialchars($getUser['fullname'] ?? $getUser['username']) ?>!</h4>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Total Orders -->
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1 overflow-hidden">
                                <p class="text-uppercase fw-medium text-muted text-truncate mb-0"><?= __('Tổng đơn hàng') ?></p>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div><h4 class="fs-22 fw-semibold mb-4"><?= $total_orders ?></h4></div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle rounded fs-3"><i class="ri-shopping-cart-2-line text-primary"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Balance -->
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1 overflow-hidden">
                                <p class="text-uppercase fw-medium text-muted text-truncate mb-0"><?= __('Số dư') ?></p>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div><h4 class="fs-22 fw-semibold mb-4"><?= format_vnd($balance) ?></h4></div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success-subtle rounded fs-3"><i class="ri-wallet-3-line text-success"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending -->
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1 overflow-hidden">
                                <p class="text-uppercase fw-medium text-muted text-truncate mb-0"><?= __('Chờ xử lý') ?></p>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div><h4 class="fs-22 fw-semibold mb-4"><?= $pending_orders ?></h4></div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning-subtle rounded fs-3"><i class="ri-time-line text-warning"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Exchange Rate -->
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1 overflow-hidden">
                                <p class="text-uppercase fw-medium text-muted text-truncate mb-0"><?= __('Tỷ giá CNY') ?></p>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4">
                            <div><h4 class="fs-22 fw-semibold mb-4"><?= format_vnd($exchange_rate) ?>/&#165;</h4></div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-info-subtle rounded fs-3"><i class="ri-money-cny-circle-line text-info"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0"><?= __('Đơn hàng gần đây') ?></h5>
                        <a href="<?= base_url('customer/orders') ?>" class="btn btn-sm btn-primary"><?= __('Xem tất cả') ?></a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th><?= __('Mã đơn') ?></th>
                                        <th><?= __('Sản phẩm') ?></th>
                                        <th><?= __('Số lượng') ?></th>
                                        <th><?= __('Tổng tiền') ?></th>
                                        <th><?= __('Trạng thái') ?></th>
                                        <th><?= __('Ngày tạo') ?></th>
                                        <th><?= __('Chi tiết') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_orders)): ?>
                                    <tr><td colspan="7" class="text-center text-muted"><?= __('Chưa có đơn hàng') ?></td></tr>
                                    <?php else: ?>
                                    <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($order['order_code']) ?></strong></td>
                                        <td><?= htmlspecialchars(mb_strimwidth($order['product_name'] ?? '', 0, 40, '...')) ?></td>
                                        <td><?= $order['quantity'] ?></td>
                                        <td class="fw-bold"><?= format_vnd($order['grand_total']) ?></td>
                                        <td><?= display_order_status($order['status']) ?></td>
                                        <td><?= $order['create_date'] ?></td>
                                        <td>
                                            <a href="<?= base_url('customer/orders-detail&id=' . $order['id']) ?>" class="btn btn-sm btn-info">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>
