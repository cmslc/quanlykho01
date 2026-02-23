<?php
require_once(__DIR__.'/../../../models/is_customer.php');
require_once(__DIR__.'/../../../libs/csrf.php');

// Get customer info
$customer = $ToryHub->get_row_safe("SELECT * FROM `customers` WHERE `user_id` = ?", [$getUser['id']]);
$customer_id = $customer ? $customer['id'] : 0;

$id = intval(input_get('id'));

// Only fetch orders belonging to this customer
$order = $ToryHub->get_row_safe("SELECT * FROM `orders` WHERE `id` = ? AND `customer_id` = ?", [$id, $customer_id]);
if (!$order) {
    redirect(base_url('customer/orders'));
}

$page_title = __('Chi tiết đơn') . ': ' . $order['order_code'];

// Status history
$history = $ToryHub->get_list_safe("SELECT h.*, u.username FROM `order_status_history` h
    LEFT JOIN `users` u ON h.changed_by = u.id
    WHERE h.order_id = ? ORDER BY h.create_date ASC", [$id]);

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Chi tiết đơn hàng') ?>: <?= htmlspecialchars($order['order_code']) ?></h4>
                    <div>
                        <a href="<?= base_url('customer/orders') ?>" class="btn btn-sm btn-secondary"><i class="ri-arrow-left-line"></i> <?= __('Quay lại') ?></a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Timeline -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <?php
                            $statusFlow = ['cn_warehouse', 'shipping', 'vn_warehouse', 'delivered'];
                            $currentIdx = array_search($order['status'], $statusFlow);
                            $isCancelled = $order['status'] === 'cancelled';
                            ?>
                            <?php foreach ($statusFlow as $idx => $s): ?>
                                <?php
                                $isCompleted = !$isCancelled && $currentIdx !== false && $idx <= $currentIdx;
                                $isCurrent = $order['status'] === $s;
                                $bgClass = $isCompleted ? 'bg-success' : 'bg-light text-muted';
                                if ($isCurrent) $bgClass = 'bg-primary';
                                ?>
                                <div class="text-center flex-fill">
                                    <div class="avatar-sm mx-auto mb-1">
                                        <span class="avatar-title <?= $bgClass ?> rounded-circle fs-5">
                                            <?php if ($isCompleted && !$isCurrent): ?><i class="ri-check-line"></i>
                                            <?php else: ?><?= $idx + 1 ?><?php endif; ?>
                                        </span>
                                    </div>
                                    <small class="<?= $isCurrent ? 'fw-bold text-primary' : '' ?>"><?= display_order_status($s) ?></small>
                                </div>
                                <?php if ($idx < count($statusFlow) - 1): ?>
                                <div class="flex-fill" style="height: 2px; background: <?= $isCompleted && $idx < $currentIdx ? '#0ab39c' : '#e9ebec' ?>; margin-top: -20px;"></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if ($isCancelled): ?>
                                <div class="text-center">
                                    <div class="avatar-sm mx-auto mb-1">
                                        <span class="avatar-title bg-danger rounded-circle fs-5"><i class="ri-close-line"></i></span>
                                    </div>
                                    <small class="fw-bold text-danger"><?= __('Đã hủy') ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Order Info -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Thông tin đơn hàng') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless mb-0">
                                    <tr><td class="text-muted"><?= __('Mã đơn') ?></td><td class="fw-bold"><?= htmlspecialchars($order['order_code']) ?></td></tr>
                                    <tr><td class="text-muted"><?= __('Nền tảng') ?></td><td><?= display_platform($order['platform']) ?></td></tr>
                                    <tr><td class="text-muted"><?= __('Sản phẩm') ?></td><td><?= htmlspecialchars($order['product_name'] ?? '') ?></td></tr>
                                    <tr><td class="text-muted"><?= __('Số lượng') ?></td><td><?= $order['quantity'] ?></td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless mb-0">
                                    <tr><td class="text-muted"><?= __('Trạng thái') ?></td><td><?= display_order_status($order['status']) ?></td></tr>
                                    <tr><td class="text-muted"><?= __('Ngày tạo') ?></td><td><?= $order['create_date'] ?></td></tr>
                                    <tr><td class="text-muted"><?= __('Cập nhật') ?></td><td><?= $order['update_date'] ?></td></tr>
                                    <?php if (!empty($order['source_url'])): ?>
                                    <tr><td class="text-muted"><?= __('Link sản phẩm') ?></td><td><a href="<?= htmlspecialchars($order['source_url']) ?>" target="_blank" class="text-truncate d-inline-block" style="max-width: 200px;"><?= htmlspecialchars($order['source_url']) ?></a></td></tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>

                        <?php if (!empty($order['product_image'])): ?>
                        <div class="mt-3">
                            <img src="<?= htmlspecialchars($order['product_image']) ?>" alt="Product" class="img-thumbnail" style="max-height: 150px;">
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tracking -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Tracking & Kiện hàng') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label class="text-muted"><?= __('Mã vận đơn TQ') ?></label>
                                <p class="fw-bold"><?= htmlspecialchars($order['cn_tracking'] ?: '-') ?></p>
                            </div>
                            <div class="col-md-4">
                                <label class="text-muted"><?= __('Mã vận chuyển QT') ?></label>
                                <p class="fw-bold"><?= htmlspecialchars($order['intl_tracking'] ?: '-') ?></p>
                            </div>
                            <div class="col-md-4">
                                <label class="text-muted"><?= __('Mã giao hàng VN') ?></label>
                                <p class="fw-bold"><?= htmlspecialchars($order['vn_tracking'] ?: '-') ?></p>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-3">
                                <label class="text-muted"><?= __('Cân nặng') ?></label>
                                <p><?= $order['weight_actual'] ? $order['weight_actual'] . ' kg' : '-' ?></p>
                            </div>
                            <div class="col-md-3">
                                <label class="text-muted"><?= __('Cân quy đổi') ?></label>
                                <p><?= $order['weight_volume'] ? $order['weight_volume'] . ' kg' : '-' ?></p>
                            </div>
                            <div class="col-md-3">
                                <label class="text-muted"><?= __('Cân tính phí') ?></label>
                                <p class="fw-bold"><?= $order['weight_charged'] ? $order['weight_charged'] . ' kg' : '-' ?></p>
                            </div>
                            <div class="col-md-3">
                                <label class="text-muted"><?= __('Kích thước') ?></label>
                                <p><?= htmlspecialchars($order['dimensions'] ?: '-') ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes (customer note only, no internal notes) -->
                <?php if (!empty($order['note'])): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Ghi chú') ?></h5>
                    </div>
                    <div class="card-body">
                        <p><?= htmlspecialchars($order['note']) ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right: Fee Breakdown + History -->
            <div class="col-lg-4">
                <!-- Fee Breakdown -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title text-white mb-0"><?= __('Chi phí') ?></h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td class="text-muted"><?= __('Đơn giá') ?></td><td class="text-end">&#165;<?= number_format($order['unit_price_cny'], 2) ?></td></tr>
                            <tr><td class="text-muted"><?= __('Số lượng') ?></td><td class="text-end"><?= $order['quantity'] ?></td></tr>
                            <tr><td class="text-muted"><?= __('Tiền hàng CNY') ?></td><td class="text-end fw-bold">&#165;<?= number_format($order['total_cny'], 2) ?></td></tr>
                            <tr><td class="text-muted"><?= __('Tỷ giá') ?></td><td class="text-end"><?= format_vnd($order['exchange_rate']) ?>/&#165;</td></tr>
                            <tr class="border-top"><td class="text-muted"><?= __('Tiền hàng VND') ?></td><td class="text-end"><?= format_vnd($order['total_vnd']) ?></td></tr>
                            <tr><td class="text-muted"><?= __('Phí mua hộ') ?></td><td class="text-end"><?= format_vnd($order['service_fee']) ?></td></tr>
                            <tr><td class="text-muted"><?= __('Ship nội TQ') ?></td><td class="text-end"><?= format_vnd($order['shipping_fee_cn']) ?></td></tr>
                            <tr><td class="text-muted"><?= __('Ship quốc tế') ?></td><td class="text-end"><?= format_vnd($order['shipping_fee_intl']) ?></td></tr>
                            <tr><td class="text-muted"><?= __('Phí đóng gỗ') ?></td><td class="text-end"><?= format_vnd($order['packing_fee']) ?></td></tr>
                            <tr><td class="text-muted"><?= __('Phí bảo hiểm') ?></td><td class="text-end"><?= format_vnd($order['insurance_fee']) ?></td></tr>
                            <tr><td class="text-muted"><?= __('Phí khác') ?></td><td class="text-end"><?= format_vnd($order['other_fee']) ?></td></tr>
                            <tr class="border-top"><td class="fw-bold"><?= __('Tổng phí') ?></td><td class="text-end fw-bold"><?= format_vnd($order['total_fee']) ?></td></tr>
                            <tr class="border-top"><td class="fw-bold fs-16"><?= __('TỔNG CỘNG') ?></td><td class="text-end fw-bold fs-16 text-danger"><?= format_vnd($order['grand_total']) ?></td></tr>
                        </table>
                    </div>
                </div>

                <!-- Status History -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Lịch sử trạng thái') ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($history)): ?>
                        <p class="text-muted text-center"><?= __('Chưa có lịch sử') ?></p>
                        <?php else: ?>
                        <div class="timeline-sm">
                            <?php foreach (array_reverse($history) as $h): ?>
                            <div class="timeline-sm-item pb-3">
                                <span class="timeline-sm-date"><?= date('d/m H:i', strtotime($h['create_date'])) ?></span>
                                <div>
                                    <?= display_order_status($h['old_status']) ?> &rarr; <?= display_order_status($h['new_status']) ?>
                                    <?php if (!empty($h['note'])): ?><br><small><?= htmlspecialchars($h['note']) ?></small><?php endif; ?>
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

<style>
.timeline-sm { position: relative; padding-left: 20px; }
.timeline-sm::before { content:''; position: absolute; left: 5px; top: 0; bottom: 0; width: 2px; background: #e9ebec; }
.timeline-sm-item { position: relative; }
.timeline-sm-item::before { content:''; position: absolute; left: -19px; top: 4px; width: 10px; height: 10px; border-radius: 50%; background: #0ab39c; border: 2px solid #fff; }
.timeline-sm-date { font-size: 11px; color: #878a99; }
</style>
