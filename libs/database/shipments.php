<?php

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

class Shipments extends DB
{
    /**
     * Generate unique shipment code: XE-YYYYMMDD-NNN
     */
    public function generateShipmentCode()
    {
        $prefix = 'XE-' . date('Ymd') . '-';
        $last = $this->get_row_safe(
            "SELECT `shipment_code` FROM `shipments` WHERE `shipment_code` LIKE ? ORDER BY `id` DESC LIMIT 1",
            [$prefix . '%']
        );
        $seq = $last ? intval(substr($last['shipment_code'], -3)) + 1 : 1;
        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Create a new shipment
     */
    public function createShipment($data)
    {
        $this->connect();
        $data['shipment_code'] = $this->generateShipmentCode();
        $data['create_date'] = gettime();
        $data['update_date'] = gettime();

        $this->insert_safe('shipments', $data);
        return $this->insert_id();
    }

    /**
     * Add packages to shipment + auto update status to 'shipping'
     */
    public function addPackages($shipment_id, $package_ids, $user_id)
    {
        $this->connect();
        require_once(__DIR__ . '/packages.php');
        $Packages = new Packages();

        $added = 0;
        $skipped = 0;
        foreach ($package_ids as $pkg_id) {
            $pkg_id = intval($pkg_id);
            // Check if already in this shipment
            $exists = $this->get_row_safe(
                "SELECT id FROM `shipment_packages` WHERE `shipment_id` = ? AND `package_id` = ?",
                [$shipment_id, $pkg_id]
            );
            if ($exists) {
                $skipped++;
                continue;
            }

            // Check if already in another shipment (preparing/in_transit)
            $otherShipment = $this->get_row_safe(
                "SELECT s.shipment_code FROM `shipment_packages` sp
                 JOIN `shipments` s ON sp.shipment_id = s.id
                 WHERE sp.package_id = ? AND s.status IN ('preparing', 'in_transit')",
                [$pkg_id]
            );
            if ($otherShipment) {
                $skipped++;
                continue;
            }

            $this->insert_safe('shipment_packages', [
                'shipment_id' => $shipment_id,
                'package_id'  => $pkg_id,
                'added_by'    => $user_id,
                'added_at'    => gettime()
            ]);

            // Auto update package status to 'loading'
            $shipment = $this->get_row_safe("SELECT * FROM `shipments` WHERE `id` = ?", [$shipment_id]);
            $Packages->updateStatus($pkg_id, 'loading', $user_id, __('Xếp vào chuyến') . ' ' . ($shipment['shipment_code'] ?? ''));

            $added++;
        }

        $this->recalculateTotals($shipment_id);

        // Auto update bag status to 'loading' if all packages in bag are loading
        $this->syncBagStatuses($package_ids, 'loading');

        return ['added' => $added, 'skipped' => $skipped];
    }

    /**
     * Remove a package from shipment
     */
    public function removePackage($shipment_id, $package_id)
    {
        $this->connect();
        $this->remove_safe('shipment_packages', "`shipment_id` = ? AND `package_id` = ?", [$shipment_id, $package_id]);
        $this->recalculateTotals($shipment_id);
        return true;
    }

    /**
     * Update shipment status (supports forward and backward transitions)
     */
    public function updateStatus($shipment_id, $new_status, $user_id)
    {
        $this->connect();
        $shipment = $this->get_row_safe("SELECT * FROM `shipments` WHERE `id` = ?", [$shipment_id]);
        if (!$shipment) return false;

        $old_status = $shipment['status'];
        $updateData = ['status' => $new_status, 'update_date' => gettime()];

        // Map: shipment status → package status
        $packageStatusMap = [
            'preparing'  => 'loading',
            'in_transit' => 'shipping',
            'arrived'    => 'vn_warehouse',
            'completed'  => 'vn_warehouse',
        ];

        if ($new_status === 'in_transit') {
            $updateData['departed_date'] = gettime();
        } elseif ($new_status === 'arrived') {
            $updateData['arrived_date'] = gettime();
        } elseif ($new_status === 'preparing') {
            // Reset dates when going back to preparing
            $updateData['departed_date'] = null;
            $updateData['arrived_date'] = null;
        }

        // Auto update all packages to corresponding status
        $targetPkgStatus = $packageStatusMap[$new_status] ?? null;
        if ($targetPkgStatus && $old_status !== $new_status) {
            require_once(__DIR__ . '/packages.php');
            $Packages = new Packages();
            $pkgs = $this->get_list_safe(
                "SELECT package_id FROM `shipment_packages` WHERE `shipment_id` = ?", [$shipment_id]
            );
            $statusMsg = __('Chuyến xe') . ' ' . $shipment['shipment_code'] . ': ' . $old_status . ' → ' . $new_status;
            foreach ($pkgs as $p) {
                $Packages->updateStatus($p['package_id'], $targetPkgStatus, $user_id, $statusMsg);
            }
        }

        $this->update_safe('shipments', $updateData, "`id` = ?", [$shipment_id]);

        // Auto update bag statuses based on package statuses
        if ($targetPkgStatus && $old_status !== $new_status) {
            $pkgIds = array_column($pkgs, 'package_id');
            if (!empty($pkgIds)) {
                $bagStatusMap = [
                    'loading'      => 'loading',
                    'shipping'     => 'shipping',
                    'vn_warehouse' => 'arrived',
                ];
                $newBagStatus = $bagStatusMap[$targetPkgStatus] ?? null;
                if ($newBagStatus) {
                    $this->syncBagStatuses($pkgIds, $newBagStatus);
                }
            }
        }

        return true;
    }

    /**
     * Sync bag statuses when their packages change status
     * Updates bag to $newBagStatus if ALL packages in the bag have matching status
     */
    public function syncBagStatuses($packageIds, $newBagStatus)
    {
        if (empty($packageIds)) return;

        $this->connect();
        $ph = implode(',', array_fill(0, count($packageIds), '?'));

        // Find all bags containing these packages
        $bags = $this->get_list_safe(
            "SELECT DISTINCT bp.bag_id FROM `bag_packages` bp WHERE bp.package_id IN ($ph)",
            $packageIds
        );

        foreach ($bags as $b) {
            $bagId = $b['bag_id'];

            // Get current bag
            $bag = $this->get_row_safe("SELECT * FROM `bags` WHERE `id` = ?", [$bagId]);
            if (!$bag || $bag['status'] === $newBagStatus) continue;

            // Don't downgrade: arrived → shipping/loading is not allowed
            $statusOrder = ['open' => 0, 'sealed' => 1, 'loading' => 2, 'shipping' => 3, 'arrived' => 4, 'completed' => 5];
            $currentLevel = $statusOrder[$bag['status']] ?? 0;
            $newLevel = $statusOrder[$newBagStatus] ?? 0;
            if ($newLevel <= $currentLevel && $newBagStatus !== 'loading') continue;

            $this->update_safe('bags', [
                'status' => $newBagStatus,
                'update_date' => gettime()
            ], "`id` = ?", [$bagId]);
        }
    }

    /**
     * Recalculate totals from packages
     */
    public function recalculateTotals($shipment_id)
    {
        $totals = $this->get_row_safe(
            "SELECT COUNT(*) as cnt,
                    COALESCE(SUM(p.weight_charged), 0) as total_w,
                    COALESCE(SUM(p.length_cm * p.width_cm * p.height_cm / 1000000), 0) as total_c
             FROM `shipment_packages` sp
             JOIN `packages` p ON sp.package_id = p.id
             WHERE sp.shipment_id = ?", [$shipment_id]
        );

        $this->update_safe('shipments', [
            'total_packages' => $totals['cnt'],
            'total_weight'   => $totals['total_w'],
            'total_cbm'      => $totals['total_c'],
            'update_date'    => gettime()
        ], "`id` = ?", [$shipment_id]);
    }

    /**
     * Get packages in a shipment with order/customer info
     */
    public function getPackages($shipment_id)
    {
        return $this->get_list_safe(
            "SELECT p.*, sp.added_at, sp.added_by,
                    o.order_code, o.product_name, o.product_code, o.cn_tracking, o.cargo_type, o.product_image, o.status as order_status, o.product_type,
                    c.fullname as customer_name, c.customer_code,
                    b.bag_code, b.status as bag_status, b.total_weight as bag_weight,
                    b.length_cm as bag_length, b.width_cm as bag_width, b.height_cm as bag_height
             FROM `shipment_packages` sp
             JOIN `packages` p ON sp.package_id = p.id
             LEFT JOIN `package_orders` po ON p.id = po.package_id
             LEFT JOIN `orders` o ON po.order_id = o.id
             LEFT JOIN `customers` c ON o.customer_id = c.id
             LEFT JOIN `bag_packages` bp ON p.id = bp.package_id
             LEFT JOIN `bags` b ON bp.bag_id = b.id
             WHERE sp.shipment_id = ?
             GROUP BY p.id
             ORDER BY sp.added_at ASC", [$shipment_id]
        );
    }
}
