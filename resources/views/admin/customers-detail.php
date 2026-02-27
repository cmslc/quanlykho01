<?php
require_once(__DIR__.'/../../../models/is_admin.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$id = intval(input_get('id'));
$customer = $ToryHub->get_row_safe("SELECT * FROM `customers` WHERE `id` = ?", [$id]);
if (!$customer) {
    redirect(base_url('admin/customers-list'));
}

$page_title = __('Chi tiết khách hàng') . ': ' . $customer['customer_code'];

// Orders of this customer
$orders = $ToryHub->get_list_safe("SELECT * FROM `orders` WHERE `customer_id` = ? ORDER BY `create_date` DESC", [$id]);

// Transactions
$transactions = $ToryHub->get_list_safe("SELECT * FROM `transactions` WHERE `customer_id` = ? ORDER BY `create_date` DESC LIMIT 50", [$id]);

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Chi tiết khách hàng') ?>: <?= htmlspecialchars($customer['customer_code']) ?></h4>
                    <div>
                        <a href="<?= base_url('admin/customers-edit&id=' . $customer['id']) ?>" class="btn btn-sm btn-warning">
                            <i class="ri-pencil-line"></i> <?= __('Sửa') ?>
                        </a>
                        <a href="<?= base_url('admin/customers-list') ?>" class="btn btn-sm btn-secondary">
                            <i class="ri-arrow-left-line"></i> <?= __('Quay lại') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer Info Cards -->
        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-end justify-content-between">
                            <div>
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Tổng đơn hàng') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0"><?= $customer['total_orders'] ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle rounded fs-3"><i class="ri-shopping-cart-2-line text-primary"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-end justify-content-between">
                            <div>
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Tổng chi tiêu') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0"><?= format_vnd($customer['total_spent']) ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success-subtle rounded fs-3"><i class="ri-money-dollar-circle-line text-success"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-end justify-content-between">
                            <div>
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Công nợ') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0 <?= $customer['balance'] < 0 ? 'text-danger' : 'text-success' ?>"><?= format_vnd($customer['balance']) ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning rounded fs-3"><i class="ri-wallet-3-line text-dark"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-end justify-content-between">
                            <div>
                                <p class="text-uppercase fw-medium text-muted mb-0"><?= __('Loại khách hàng') ?></p>
                                <h4 class="fs-22 fw-semibold mt-4 mb-0"><?= display_customer_type($customer['customer_type']) ?></h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-info-subtle rounded fs-3"><i class="ri-user-star-line text-info"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Customer Details -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Thông tin liên hệ') ?></h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted"><?= __('Họ tên') ?></td><td class="fw-bold"><?= htmlspecialchars($customer['fullname']) ?></td></tr>
                            <tr><td class="text-muted"><?= __('Điện thoại') ?></td><td><?= htmlspecialchars($customer['phone'] ?? '-') ?></td></tr>
                            <tr><td class="text-muted">Email</td><td><?= htmlspecialchars($customer['email'] ?? '-') ?></td></tr>
                            <tr><td class="text-muted"><?= __('Địa chỉ VN') ?></td><td><?= htmlspecialchars($customer['address_vn'] ?? '-') ?></td></tr>
                            <tr><td class="text-muted">Zalo</td><td><?= htmlspecialchars($customer['zalo'] ?? '-') ?></td></tr>
                            <tr><td class="text-muted">WeChat</td><td><?= htmlspecialchars($customer['wechat'] ?? '-') ?></td></tr>
                            <tr><td class="text-muted"><?= __('Ghi chú') ?></td><td><?= htmlspecialchars($customer['note'] ?? '-') ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Orders -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0"><?= __('Đơn hàng') ?></h5>
                        <a href="<?= base_url('admin/orders-add&customer_id=' . $customer['id']) ?>" class="btn btn-sm btn-primary">
                            <i class="ri-add-line"></i> <?= __('Tạo đơn') ?>
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><?= __('Mã đơn') ?></th>
                                        <th><?= __('Sản phẩm') ?></th>
                                        <th><?= __('Tổng tiền') ?></th>
                                        <th><?= __('Trạng thái') ?></th>
                                        <th><?= __('Ngày tạo') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($orders)): ?>
                                    <tr><td colspan="5" class="text-center text-muted"><?= __('Chưa có đơn hàng') ?></td></tr>
                                    <?php else: ?>
                                    <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><a href="<?= base_url('admin/orders-detail&id=' . $order['id']) ?>"><strong><?= $order['order_code'] ?></strong></a></td>
                                        <td><?= htmlspecialchars(mb_strimwidth($order['product_name'] ?? '', 0, 40, '...')) ?></td>
                                        <td><?= format_vnd($order['grand_total']) ?></td>
                                        <td><?= display_order_status($order['status']) ?></td>
                                        <td><?= $order['create_date'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Transactions -->
                <?php if (!empty($transactions)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Lịch sử giao dịch') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><?= __('Loại') ?></th>
                                        <th><?= __('Số tiền') ?></th>
                                        <th><?= __('Số dư trước') ?></th>
                                        <th><?= __('Số dư sau') ?></th>
                                        <th><?= __('Mô tả') ?></th>
                                        <th><?= __('Ngày') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $txn): ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $txnBadge = ['deposit' => 'success', 'payment' => 'primary', 'refund' => 'warning', 'adjustment' => 'info'];
                                            $txnLabel = ['deposit' => __('Nạp tiền'), 'payment' => __('Thanh toán'), 'refund' => __('Hoàn tiền'), 'adjustment' => __('Điều chỉnh')];
                                            ?>
                                            <span class="badge bg-<?= $txnBadge[$txn['type']] ?? 'secondary' ?>"><?= $txnLabel[$txn['type']] ?? $txn['type'] ?></span>
                                        </td>
                                        <td class="<?= $txn['amount'] >= 0 ? 'text-success' : 'text-danger' ?> fw-bold"><?= format_vnd($txn['amount']) ?></td>
                                        <td><?= format_vnd($txn['balance_before']) ?></td>
                                        <td><?= format_vnd($txn['balance_after']) ?></td>
                                        <td><?= htmlspecialchars($txn['description'] ?? '') ?></td>
                                        <td><?= $txn['create_date'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>
