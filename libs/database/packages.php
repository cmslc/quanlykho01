<?php

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

class Packages extends DB
{
    /**
     * Generate unique package code: PKG + date + 3-digit sequence
     */
    public function generatePackageCode()
    {
        $prefix = 'PKG' . date('Ymd');
        $last = $this->get_row_safe(
            "SELECT `package_code` FROM `packages` WHERE `package_code` LIKE ? ORDER BY `id` DESC LIMIT 1",
            [$prefix . '%']
        );
        $seq = $last ? intval(substr($last['package_code'], -3)) + 1 : 1;
        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Create a new package and link to orders
     */
    public function createPackage($data, $order_ids = [])
    {
        $this->connect();
        $this->beginTransaction();
        try {
            $data['package_code'] = $this->generatePackageCode();
            $data['create_date'] = gettime();
            $data['update_date'] = gettime();

            $insertResult = $this->insert_safe('packages', $data);
            if (!$insertResult) {
                throw new Exception('Failed to insert package: ' . mysqli_error($this->ketnoi));
            }
            $package_id = $this->insert_id();
            if (!$package_id) {
                throw new Exception('Failed to get package insert_id');
            }

            foreach ($order_ids as $order_id) {
                $linkResult = $this->insert_safe('package_orders', [
                    'package_id'  => $package_id,
                    'order_id'    => intval($order_id),
                    'create_date' => gettime()
                ]);
                if (!$linkResult) {
                    throw new Exception('Failed to link package to order: ' . mysqli_error($this->ketnoi));
                }
            }

            $this->insert_safe('package_status_history', [
                'package_id'  => $package_id,
                'old_status'  => '',
                'new_status'  => $data['status'] ?? 'cn_warehouse',
                'note'        => 'Package created',
                'changed_by'  => $data['created_by'] ?? null,
                'create_date' => gettime()
            ]);

            $this->commit();
            return $package_id;
        } catch (Exception $e) {
            $this->rollBack();
            error_log('createPackage error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update package status and auto-sync linked orders
     */
    public function updateStatus($package_id, $new_status, $changed_by, $note = '')
    {
        $this->connect();
        $package = $this->get_row_safe("SELECT * FROM `packages` WHERE `id` = ?", [$package_id]);
        if (!$package) return false;

        if ($package['status'] === $new_status) return 'duplicate';

        $dateFields = [
            'cn_warehouse' => 'cn_warehouse_date',
            'packed'       => 'packed_date',
            'shipping'     => 'shipping_date',
            'vn_warehouse' => 'vn_warehouse_date',
            'delivered'    => 'delivered_date',
        ];

        $this->beginTransaction();
        try {
            $updateData = ['status' => $new_status, 'update_date' => gettime()];
            if (isset($dateFields[$new_status])) {
                $updateData[$dateFields[$new_status]] = gettime();
            }
            $this->update_safe('packages', $updateData, "`id` = ?", [$package_id]);

            $this->insert_safe('package_status_history', [
                'package_id'  => $package_id,
                'old_status'  => $package['status'],
                'new_status'  => $new_status,
                'note'        => $note,
                'changed_by'  => $changed_by,
                'create_date' => gettime()
            ]);

            // Auto-sync linked orders
            $links = $this->get_list_safe(
                "SELECT `order_id` FROM `package_orders` WHERE `package_id` = ?", [$package_id]
            );
            foreach ($links as $link) {
                $this->syncOrderStatus($link['order_id'], $changed_by);
            }

            $this->commit();
            return true;
        } catch (Exception $e) {
            $this->rollBack();
            return false;
        }
    }

    /**
     * Derive order status from all its packages (MIN status, forward-only)
     */
    public function syncOrderStatus($order_id, $changed_by)
    {
        $order = $this->get_row_safe("SELECT * FROM `orders` WHERE `id` = ?", [$order_id]);
        if (!$order) return;

        $packages = $this->get_list_safe(
            "SELECT p.status FROM `packages` p
             INNER JOIN `package_orders` po ON p.id = po.package_id
             WHERE po.order_id = ?", [$order_id]
        );
        if (empty($packages)) return;

        $pkgRank = ['cn_warehouse' => 1, 'packed' => 2, 'shipping' => 3, 'vn_warehouse' => 4, 'delivered' => 5];
        $minRank = 999;
        foreach ($packages as $pkg) {
            $rank = $pkgRank[$pkg['status']] ?? 0;
            if ($rank < $minRank) $minRank = $rank;
        }

        $rankToStatus = [1 => 'cn_warehouse', 2 => 'packed', 3 => 'shipping', 4 => 'vn_warehouse', 5 => 'delivered'];
        $derivedStatus = $rankToStatus[$minRank] ?? null;
        if (!$derivedStatus) return;

        // Order rank (forward-only check)
        $orderRank = [
            'cn_warehouse' => 1, 'packed' => 2,
            'shipping' => 3, 'vn_warehouse' => 4,
            'delivered' => 5, 'cancelled' => -1
        ];
        $currentRank = $orderRank[$order['status']] ?? 0;
        $derivedRank = $orderRank[$derivedStatus] ?? 0;

        if ($derivedRank > $currentRank) {
            $dateFields = [
                'cn_warehouse' => 'cn_warehouse_date', 'shipping' => 'shipping_date',
                'vn_warehouse' => 'vn_warehouse_date', 'delivered' => 'delivered_date',
            ];
            $updateData = ['status' => $derivedStatus, 'update_date' => gettime()];
            if (isset($dateFields[$derivedStatus]) && empty($order[$dateFields[$derivedStatus]])) {
                $updateData[$dateFields[$derivedStatus]] = gettime();
            }

            $this->update_safe('orders', $updateData, "`id` = ?", [$order_id]);
            $this->insert_safe('order_status_history', [
                'order_id'    => $order_id,
                'old_status'  => $order['status'],
                'new_status'  => $derivedStatus,
                'note'        => __('Tự động cập nhật từ kiện hàng'),
                'changed_by'  => $changed_by,
                'create_date' => gettime()
            ]);
        }
    }

    /**
     * Get all packages for an order
     */
    public function getByOrder($order_id)
    {
        return $this->get_list_safe(
            "SELECT p.* FROM `packages` p
             INNER JOIN `package_orders` po ON p.id = po.package_id
             WHERE po.order_id = ? ORDER BY p.create_date ASC", [$order_id]
        );
    }

    /**
     * Get all orders for a package
     */
    public function getOrdersByPackage($package_id)
    {
        return $this->get_list_safe(
            "SELECT o.*, c.fullname as customer_name, c.customer_code
             FROM `orders` o
             INNER JOIN `package_orders` po ON o.id = po.order_id
             LEFT JOIN `customers` c ON o.customer_id = c.id
             WHERE po.package_id = ? ORDER BY o.create_date ASC", [$package_id]
        );
    }

    /**
     * Link order to package
     */
    public function linkOrder($package_id, $order_id, $note = '')
    {
        $existing = $this->get_row_safe(
            "SELECT id FROM `package_orders` WHERE `package_id` = ? AND `order_id` = ?",
            [$package_id, $order_id]
        );
        if ($existing) return 'exists';

        return $this->insert_safe('package_orders', [
            'package_id' => $package_id, 'order_id' => $order_id,
            'note' => $note, 'create_date' => gettime()
        ]);
    }

    /**
     * Unlink order from package
     */
    public function unlinkOrder($package_id, $order_id)
    {
        return $this->remove_safe('package_orders', "`package_id` = ? AND `order_id` = ?", [$package_id, $order_id]);
    }

    /**
     * Merge: create new package from multiple source packages
     */
    public function mergePackages($source_ids, $new_data, $created_by)
    {
        $this->connect();
        $this->beginTransaction();
        try {
            $order_ids = [];
            foreach ($source_ids as $src_id) {
                $links = $this->get_list_safe("SELECT order_id FROM package_orders WHERE package_id = ?", [$src_id]);
                foreach ($links as $l) $order_ids[$l['order_id']] = true;
            }

            $new_data['created_by'] = $created_by;
            $new_data['status'] = $new_data['status'] ?? 'cn_warehouse';
            $new_id = $this->createPackage($new_data, array_keys($order_ids));
            if (!$new_id) throw new Exception('Failed to create merged package');

            foreach ($source_ids as $src_id) {
                $this->remove_safe('package_orders', "`package_id` = ?", [$src_id]);
            }

            $this->commit();
            return $new_id;
        } catch (Exception $e) {
            $this->rollBack();
            return false;
        }
    }

    /**
     * Split: 1 package with N orders -> N packages
     * $splits = [['order_ids' => [1,2]], ['order_ids' => [3]]]
     */
    public function splitPackage($source_id, $splits, $created_by)
    {
        $this->connect();
        $source = $this->get_row_safe("SELECT * FROM `packages` WHERE `id` = ?", [$source_id]);
        if (!$source) return false;

        $this->beginTransaction();
        try {
            $new_ids = [];
            foreach ($splits as $split) {
                $data = [
                    'tracking_cn'     => $source['tracking_cn'],
                    'shipping_method' => $source['shipping_method'],
                    'status'          => $source['status'],
                    'note'            => $split['note'] ?? '',
                    'created_by'      => $created_by,
                ];
                $new_id = $this->createPackage($data, $split['order_ids']);
                if (!$new_id) throw new Exception('Failed to create split package');
                $new_ids[] = $new_id;
            }

            $this->remove_safe('package_orders', "`package_id` = ?", [$source_id]);
            $this->commit();
            return $new_ids;
        } catch (Exception $e) {
            $this->rollBack();
            return false;
        }
    }

    /**
     * Recalculate volume/charged weight from dimensions
     */
    public function recalculateWeight($package_id)
    {
        $pkg = $this->get_row_safe("SELECT * FROM `packages` WHERE `id` = ?", [$package_id]);
        if (!$pkg) return false;

        $volume = calculate_volume_weight($pkg['length_cm'], $pkg['width_cm'], $pkg['height_cm']);
        $charged = calculate_charged_weight($pkg['weight_actual'], $volume);

        $this->update_safe('packages', [
            'weight_volume' => $volume, 'weight_charged' => $charged, 'update_date' => gettime()
        ], "`id` = ?", [$package_id]);

        return $charged;
    }
}
