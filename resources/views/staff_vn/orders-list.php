<?php
require_once(__DIR__.'/../../../models/is_staff_vn.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Đơn hàng tại kho Việt Nam');

// Filters - only VN-relevant statuses
$filterStatus = input_get('status') ?: '';
$vnStatuses = ['shipping', 'vn_warehouse', 'delivered'];

$where = "o.status IN ('shipping', 'vn_warehouse', 'delivered')";
$params = [];

if ($filterStatus && in_array($filterStatus, $vnStatuses)) {
    $where = "o.status = ?";
    $params[] = $filterStatus;
}

$orders = $CMSNT->get_list_safe("SELECT o.*, c.fullname as customer_name, c.customer_code, c.phone as customer_phone
    FROM `orders` o LEFT JOIN `customers` c ON o.customer_id = c.id
    WHERE $where ORDER BY o.update_date DESC", $params);

// Status counts
$statusCounts = [];
foreach ($vnStatuses as $s) {
    $statusCounts[$s] = $CMSNT->num_rows_safe("SELECT * FROM `orders` WHERE `status` = ?", [$s]) ?: 0;
}

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Đơn hàng tại kho Việt Nam') ?></h4>
                </div>
            </div>
        </div>

        <!-- Status Summary -->
        <div class="row">
            <?php
            $statusColors = [
                'shipping' => 'warning',
                'vn_warehouse' => 'success',
                'delivered' => 'primary'
            ];
            ?>
            <div class="col-md-3">
                <a href="<?= base_url('staff_vn/orders-list') ?>" class="text-decoration-none">
                    <div class="card card-animate <?= empty($filterStatus) ? 'border border-primary' : '' ?>">
                        <div class="card-body py-2 text-center">
                            <h5 class="mb-0"><?= array_sum($statusCounts) ?></h5>
                            <small class="text-muted"><?= __('Tất cả') ?></small>
                        </div>
                    </div>
                </a>
            </div>
            <?php foreach ($vnStatuses as $s): ?>
            <div class="col-md-3">
                <a href="<?= base_url('staff_vn/orders-list&status=' . $s) ?>" class="text-decoration-none">
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

        <!-- Filters -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="<?= base_url('staff_vn/orders-list') ?>" class="row g-3 align-items-end">
                            <input type="hidden" name="module" value="staff_vn">
                            <input type="hidden" name="action" value="orders-list">
                            <div class="col-md-3">
                                <label class="form-label"><?= __('Trạng thái') ?></label>
                                <select class="form-select" name="status">
                                    <option value=""><?= __('Tất cả') ?></option>
                                    <?php foreach ($vnStatuses as $s): ?>
                                    <option value="<?= $s ?>" <?= $filterStatus == $s ? 'selected' : '' ?>><?= display_order_status($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary"><?= __('Lọc') ?></button>
                                <a href="<?= base_url('staff_vn/orders-list') ?>" class="btn btn-secondary"><?= __('Reset') ?></a>
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
                                        <th><?= __('Khách hàng') ?></th>
                                        <th><?= __('SĐT') ?></th>
                                        <th><?= __('Sản phẩm') ?></th>
                                        <th><?= __('Tổng VND') ?></th>
                                        <th><?= __('Trạng thái') ?></th>
                                        <th><?= __('Cập nhật') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><strong><?= $order['order_code'] ?></strong></td>
                                        <td>
                                            <?= htmlspecialchars($order['customer_code'] ?? '') ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($order['customer_name'] ?? '') ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($order['customer_phone'] ?? '') ?></td>
                                        <td><?= htmlspecialchars(mb_strimwidth($order['product_name'] ?? '', 0, 30, '...')) ?></td>
                                        <td class="text-end fw-bold"><?= format_vnd($order['grand_total']) ?></td>
                                        <td><?= display_order_status($order['status']) ?></td>
                                        <td><?= $order['update_date'] ?></td>
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
