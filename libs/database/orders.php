<?php

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

class Orders extends DB
{
    public function updateStatus($order_id, $new_status, $changed_by, $note = '')
    {
        $this->connect();
        $order = $this->get_row_safe("SELECT * FROM `orders` WHERE `id` = ?", [$order_id]);
        if (!$order) return false;

        $old_status = $order['status'];

        $this->beginTransaction();
        try {
            // Update order status
            $this->update_safe('orders', [
                'status'     => $new_status,
                'updated_by' => $changed_by,
                'update_date'=> gettime()
            ], "`id` = ?", [$order_id]);

            // Log status change
            $this->insert_safe('order_status_history', [
                'order_id'    => $order_id,
                'old_status'  => $old_status,
                'new_status'  => $new_status,
                'note'        => $note,
                'changed_by'  => $changed_by,
                'create_date' => gettime()
            ]);

            // Sync linked packages
            $this->syncPackageStatus($order_id, $new_status, $changed_by);

            $this->commit();
            return true;
        } catch (Exception $e) {
            $this->rollBack();
            return false;
        }
    }

    /**
     * Sync linked packages when order status changes
     */
    private function syncPackageStatus($order_id, $new_status, $changed_by)
    {
        $orderToPkg = [
            'cn_warehouse' => 'cn_warehouse',
            'packed'       => 'packed',
            'shipping'     => 'shipping',
            'vn_warehouse' => 'vn_warehouse',
            'delivered'    => 'delivered',
        ];

        if (!isset($orderToPkg[$new_status])) return;

        $pkg_status = $orderToPkg[$new_status];
        $links = $this->get_list_safe(
            "SELECT `package_id` FROM `package_orders` WHERE `order_id` = ?", [$order_id]
        );

        $dateFields = [
            'cn_warehouse' => 'cn_warehouse_date',
            'packed'       => 'packed_date',
            'shipping'     => 'shipping_date',
            'vn_warehouse' => 'vn_warehouse_date',
            'delivered'    => 'delivered_date',
        ];

        foreach ($links as $link) {
            $pkg = $this->get_row_safe("SELECT * FROM `packages` WHERE `id` = ?", [$link['package_id']]);
            if (!$pkg || $pkg['status'] === $pkg_status) continue;

            $updateData = ['status' => $pkg_status, 'update_date' => gettime()];
            if (isset($dateFields[$pkg_status]) && empty($pkg[$dateFields[$pkg_status]])) {
                $updateData[$dateFields[$pkg_status]] = gettime();
            }

            $this->update_safe('packages', $updateData, "`id` = ?", [$link['package_id']]);
            $this->insert_safe('package_status_history', [
                'package_id'  => $link['package_id'],
                'old_status'  => $pkg['status'],
                'new_status'  => $pkg_status,
                'note'        => __('Tự động cập nhật từ đơn hàng'),
                'changed_by'  => $changed_by,
                'create_date' => gettime()
            ]);
        }
    }

    public function calculateFees($order_id)
    {
        $order = $this->get_row_safe("SELECT * FROM `orders` WHERE `id` = ?", [$order_id]);
        if (!$order) return false;

        $total_cny = $order['unit_price_cny'] * $order['quantity'];
        $exchange_rate = get_exchange_rate();
        $total_vnd = $total_cny * $exchange_rate;

        // Service fee
        $service_fee_cny = calculate_service_fee($total_cny);
        $service_fee = $service_fee_cny * $exchange_rate;

        // Shipping fee
        $weight = calculate_charged_weight(
            floatval($order['weight_actual'] ?: 0),
            floatval($order['weight_volume'] ?: 0)
        );
        $shipping_fee_intl = calculate_shipping_fee($weight, $order['shipping_method'] ?: 'road');

        // Total
        $total_fee = $service_fee + floatval($order['shipping_fee_cn']) + $shipping_fee_intl
                   + floatval($order['packing_fee']) + floatval($order['insurance_fee'])
                   + floatval($order['other_fee']);
        $grand_total = $total_vnd + $total_fee;

        $this->update_safe('orders', [
            'total_cny'        => $total_cny,
            'exchange_rate'    => $exchange_rate,
            'total_vnd'        => $total_vnd,
            'service_fee'      => $service_fee,
            'shipping_fee_intl'=> $shipping_fee_intl,
            'weight_charged'   => $weight,
            'total_fee'        => $total_fee,
            'grand_total'      => $grand_total
        ], "`id` = ?", [$order_id]);

        return $grand_total;
    }

    /**
     * Recalculate order weight & shipping fee from linked packages
     */
    public function recalculateFeesFromPackages($order_id)
    {
        $order = $this->get_row_safe("SELECT * FROM `orders` WHERE `id` = ?", [$order_id]);
        if (!$order) return false;

        $w = $this->get_row_safe(
            "SELECT SUM(p.weight_actual) as total_actual, SUM(p.weight_volume) as total_volume,
                    SUM(p.weight_charged) as total_charged
             FROM `packages` p INNER JOIN `package_orders` po ON p.id = po.package_id
             WHERE po.order_id = ?", [$order_id]
        );

        if ($w && floatval($w['total_charged']) > 0) {
            $weight = floatval($w['total_charged']);
            $shipping_fee_intl = calculate_shipping_fee($weight, $order['shipping_method'] ?: 'road');
            $total_fee = floatval($order['service_fee']) + floatval($order['shipping_fee_cn'])
                       + $shipping_fee_intl + floatval($order['packing_fee'])
                       + floatval($order['insurance_fee']) + floatval($order['other_fee']);
            $grand_total = floatval($order['total_vnd']) + $total_fee;

            $this->update_safe('orders', [
                'weight_actual' => $w['total_actual'], 'weight_volume' => $w['total_volume'],
                'weight_charged' => $weight, 'shipping_fee_intl' => $shipping_fee_intl,
                'total_fee' => $total_fee, 'grand_total' => $grand_total,
            ], "`id` = ?", [$order_id]);
            return $grand_total;
        }
        return false;
    }
}
