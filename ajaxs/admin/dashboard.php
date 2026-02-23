<?php
define('IN_SITE', true);
require_once(__DIR__.'/../../libs/db.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../libs/lang.php');
require_once(__DIR__.'/../../libs/helper.php');
require_once(__DIR__.'/../../libs/session.php');
require_once(__DIR__.'/../../libs/role.php');
require_once(__DIR__.'/../../libs/csrf.php');
require_once(__DIR__.'/../../models/is_admin.php');

header('Content-Type: application/json; charset=utf-8');

$csrf = new Csrf();
if (!$csrf->validate()) {
    echo json_encode(['status' => 'error', 'msg' => 'Token không hợp lệ']);
    exit;
}

$request = input_post('request_name');

// ======== REVENUE CHART ========
if ($request === 'revenue_chart') {
    $period = input_post('period') ?: '30d';
    $validPeriods = ['7d', '30d', '90d', '12m'];
    if (!in_array($period, $validPeriods)) {
        echo json_encode(['status' => 'error', 'msg' => 'Invalid period']);
        exit;
    }

    if ($period === '12m') {
        $data = $CMSNT->get_list_safe(
            "SELECT DATE_FORMAT(create_date, '%Y-%m-01') as date,
                    COALESCE(SUM(grand_total),0) as revenue,
                    COUNT(*) as orders_count
             FROM `orders`
             WHERE `status` != 'cancelled'
             AND create_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
             GROUP BY DATE_FORMAT(create_date, '%Y-%m')
             ORDER BY date ASC",
            []
        );
    } else {
        $days = intval(str_replace('d', '', $period));
        $data = $CMSNT->get_list_safe(
            "SELECT DATE(create_date) as date,
                    COALESCE(SUM(grand_total),0) as revenue,
                    COUNT(*) as orders_count
             FROM `orders`
             WHERE `status` != 'cancelled'
             AND create_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY DATE(create_date)
             ORDER BY date ASC",
            [$days]
        );
    }

    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}

echo json_encode(['status' => 'error', 'msg' => 'Invalid request']);
