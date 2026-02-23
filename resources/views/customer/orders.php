<?php
require_once(__DIR__.'/../../../models/is_customer.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Đơn hàng của tôi');

// Get customer info
$customer = $ToryHub->get_row_safe("SELECT * FROM `customers` WHERE `user_id` = ?", [$getUser['id']]);
$customer_id = $customer ? $customer['id'] : 0;

// Filters
$filterStatus = input_get('status') ?: '';

$where = "`customer_id` = ?";
$params = [$customer_id];

if ($filterStatus) {
    $where .= " AND `status` = ?";
    $params[] = $filterStatus;
}

$orders = $ToryHub->get_list_safe("SELECT * FROM `orders` WHERE $where ORDER BY `create_date` DESC", $params);

$statuses = ['cn_warehouse', 'packed', 'shipping', 'vn_warehouse', 'delivered', 'cancelled'];

// Status counts for this customer
$statusCounts = [];
foreach ($statuses as $s) {
    $statusCounts[$s] = $ToryHub->num_rows_safe("SELECT * FROM `orders` WHERE `customer_id` = ? AND `status` = ?", [$customer_id, $s]) ?: 0;
}

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Đơn hàng của tôi') ?></h4>
                </div>
            </div>
        </div>

        <!-- Status Summary -->
        <div class="row">
            <?php foreach ($statuses as $s): ?>
            <div class="col">
                <a href="<?= base_url('customer/orders&status=' . $s) ?>" class="text-decoration-none">
                    <div class="card card-animate <?= $filterStatus == $s ? 'border border-primary' : '' ?>">
                        <div class="card-body py-2 text-center">
                            <h5 class="mb-0"><?= $statusCounts[$s] ?></h5>
                            <small class="text-muted"><?= display_order_status($s) ?></small>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Filter -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="<?= base_url('customer/orders') ?>" class="row g-3 align-items-end">
                            <input type="hidden" name="module" value="customer">
                            <input type="hidden" name="action" value="orders">
                            <div class="col-md-3">
                                <label class="form-label"><?= __('Trạng thái') ?></label>
                                <select class="form-select" name="status">
                                    <option value=""><?= __('Tất cả') ?></option>
                                    <?php foreach ($statuses as $s): ?>
                                    <option value="<?= $s ?>" <?= $filterStatus == $s ? 'selected' : '' ?>><?= display_order_status($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary"><?= __('Lọc') ?></button>
                                <a href="<?= base_url('customer/orders') ?>" class="btn btn-secondary"><?= __('Reset') ?></a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Danh sách đơn hàng') ?> (<?= count($orders) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table data-table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><?= __('Mã đơn') ?></th>
                                        <th><?= __('Sản phẩm') ?></th>
                                        <th><?= __('Nền tảng') ?></th>
                                        <th><?= __('Tiền CNY') ?></th>
                                        <th><?= __('Tổng VND') ?></th>
                                        <th><?= __('Trạng thái') ?></th>
                                        <th><?= __('Ngày tạo') ?></th>
                                        <th><?= __('Chi tiết') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= base_url('customer/orders-detail&id=' . $order['id']) ?>">
                                                <strong><?= htmlspecialchars($order['order_code']) ?></strong>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars(mb_strimwidth($order['product_name'] ?? '', 0, 30, '...')) ?></td>
                                        <td><?= display_platform($order['platform']) ?></td>
                                        <td class="text-end">&#165;<?= number_format($order['total_cny'] ?? 0, 2) ?></td>
                                        <td class="text-end fw-bold"><?= format_vnd($order['grand_total']) ?></td>
                                        <td><?= display_order_status($order['status']) ?></td>
                                        <td><?= $order['create_date'] ?></td>
                                        <td>
                                            <a href="<?= base_url('customer/orders-detail&id=' . $order['id']) ?>" class="btn btn-sm btn-info">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>
