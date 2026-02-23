<?php
require_once(__DIR__.'/../../../models/is_staff_cn.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Đơn hàng kho Trung Quốc');

// Filters - only CN-relevant statuses
$filterStatus = input_get('status') ?: '';

$cnStatuses = ['purchased', 'cn_shipped', 'cn_warehouse'];

$where = "o.status IN ('purchased', 'cn_shipped', 'cn_warehouse')";
$params = [];

if ($filterStatus && in_array($filterStatus, $cnStatuses)) {
    $where = "o.status = ?";
    $params[] = $filterStatus;
}

$orders = $ToryHub->get_list_safe("SELECT o.*, c.fullname as customer_name, c.customer_code
    FROM `orders` o LEFT JOIN `customers` c ON o.customer_id = c.id
    WHERE $where ORDER BY o.create_date DESC", $params);

// Status counts
$statusCounts = [];
foreach ($cnStatuses as $s) {
    $statusCounts[$s] = $ToryHub->num_rows_safe("SELECT * FROM `orders` WHERE `status` = ?", [$s]) ?: 0;
}

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Đơn hàng kho Trung Quốc') ?></h4>
                    <div class="page-title-right">
                        <a href="<?= base_url('staff_cn/orders-scan') ?>" class="btn btn-primary">
                            <i class="ri-qr-scan-2-line"></i> <?= __('Quét mã nhập kho') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="<?= base_url('staff_cn/orders-list') ?>" class="row g-3 align-items-end">
                            <input type="hidden" name="module" value="staff_cn">
                            <input type="hidden" name="action" value="orders-list">
                            <div class="col-md-3">
                                <label class="form-label"><?= __('Trạng thái') ?></label>
                                <select class="form-select" name="status">
                                    <option value=""><?= __('Tất cả trạng thái kho Trung Quốc') ?></option>
                                    <?php foreach ($cnStatuses as $s): ?>
                                    <option value="<?= $s ?>" <?= $filterStatus == $s ? 'selected' : '' ?>><?= display_order_status($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary"><?= __('Lọc') ?></button>
                                <a href="<?= base_url('staff_cn/orders-list') ?>" class="btn btn-secondary"><?= __('Reset') ?></a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Summary -->
        <div class="row">
            <?php
            $statusColors = [
                'purchased' => 'info',
                'cn_shipped' => 'warning',
                'cn_warehouse' => 'success',
            ];
            ?>
            <?php foreach ($cnStatuses as $s): ?>
            <div class="col-md-4">
                <a href="<?= base_url('staff_cn/orders-list&status=' . $s) ?>" class="text-decoration-none">
                    <div class="card card-animate <?= $filterStatus == $s ? 'border border-primary' : '' ?>">
                        <div class="card-body py-3 text-center">
                            <h4 class="mb-1"><?= $statusCounts[$s] ?></h4>
                            <small class="text-muted"><?= display_order_status($s) ?></small>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
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
                                        <th><?= __('Sản phẩm') ?></th>
                                        <th><?= __('Mã vận đơn') ?></th>
                                        <th><?= __('Nền tảng') ?></th>
                                        <th><?= __('Tiền CNY') ?></th>
                                        <th><?= __('Trạng thái') ?></th>
                                        <th><?= __('Ngày tạo') ?></th>
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
                                        <td><?= htmlspecialchars(mb_strimwidth($order['product_name'] ?? '', 0, 30, '...')) ?></td>
                                        <td>
                                            <?php if (!empty($order['tracking_number'])): ?>
                                                <code><?= htmlspecialchars($order['tracking_number']) ?></code>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= display_platform($order['platform']) ?></td>
                                        <td class="text-end">&yen;<?= number_format($order['total_cny'] ?? 0, 2) ?></td>
                                        <td><?= display_order_status($order['status']) ?></td>
                                        <td><?= $order['create_date'] ?></td>
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
