<?php
/**
 * API Reports endpoints
 */

require_once(__DIR__.'/bootstrap.php');
require_once(__DIR__.'/jwt.php');

$user = api_auth();
api_method('GET');

$type = $_GET['type'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// ===== REVENUE REPORT =====
if ($type === 'revenue') {
    $totals = $ToryHub->get_row_safe(
        "SELECT COUNT(*) as total_orders, COALESCE(SUM(grand_total),0) as total_revenue
         FROM `orders` WHERE DATE(`create_date`) BETWEEN ? AND ?",
        [$date_from, $date_to]
    );

    $byStatus = $ToryHub->get_list_safe(
        "SELECT `status`, COUNT(*) as cnt FROM `orders`
         WHERE DATE(`create_date`) BETWEEN ? AND ? GROUP BY `status`",
        [$date_from, $date_to]
    );

    $byType = $ToryHub->get_list_safe(
        "SELECT `product_type`, COUNT(*) as cnt FROM `orders`
         WHERE DATE(`create_date`) BETWEEN ? AND ? GROUP BY `product_type`",
        [$date_from, $date_to]
    );

    $daily = $ToryHub->get_list_safe(
        "SELECT DATE(`create_date`) as date, COUNT(*) as orders_count, COALESCE(SUM(grand_total),0) as revenue
         FROM `orders` WHERE DATE(`create_date`) BETWEEN ? AND ?
         GROUP BY DATE(`create_date`) ORDER BY date ASC",
        [$date_from, $date_to]
    );

    api_success([
        'total_orders' => (int)$totals['total_orders'],
        'total_revenue' => floatval($totals['total_revenue']),
        'by_status' => $byStatus,
        'by_type' => $byType,
        'daily' => $daily,
        'date_from' => $date_from,
        'date_to' => $date_to
    ]);
}

// ===== CUSTOMER REPORT =====
if ($type === 'customers') {
    $topCustomers = $ToryHub->get_list_safe(
        "SELECT `id`, `customer_code`, `fullname`, `total_orders`, `total_spent`, `balance`
         FROM `customers` ORDER BY `total_spent` DESC LIMIT 20", []
    );

    $newThisMonth = $ToryHub->get_row_safe(
        "SELECT COUNT(*) as cnt FROM `customers` WHERE DATE(`create_date`) BETWEEN ? AND ?",
        [$date_from, $date_to]
    );

    $totalCustomers = $ToryHub->get_row_safe("SELECT COUNT(*) as cnt FROM `customers`", []);

    api_success([
        'top_customers' => $topCustomers,
        'new_customers' => (int)$newThisMonth['cnt'],
        'total_customers' => (int)$totalCustomers['cnt']
    ]);
}

// ===== FINANCE REPORT =====
if ($type === 'finance') {
    $deposits = $ToryHub->get_row_safe(
        "SELECT COALESCE(SUM(amount),0) as total FROM `transactions` WHERE `type` = 'deposit' AND DATE(`create_date`) BETWEEN ? AND ?",
        [$date_from, $date_to]
    );
    $payments = $ToryHub->get_row_safe(
        "SELECT COALESCE(SUM(ABS(amount)),0) as total FROM `transactions` WHERE `type` = 'payment' AND DATE(`create_date`) BETWEEN ? AND ?",
        [$date_from, $date_to]
    );
    $expenses = $ToryHub->get_row_safe(
        "SELECT COALESCE(SUM(amount),0) as total FROM `expenses` WHERE `expense_date` BETWEEN ? AND ?",
        [$date_from, $date_to]
    );
    $totalBalance = $ToryHub->get_row_safe("SELECT COALESCE(SUM(balance),0) as total FROM `customers`", []);

    api_success([
        'total_deposits' => floatval($deposits['total']),
        'total_payments' => floatval($payments['total']),
        'total_expenses' => floatval($expenses['total']),
        'net_customer_balance' => floatval($totalBalance['total']),
        'date_from' => $date_from,
        'date_to' => $date_to
    ]);
}

api_error('Loại báo cáo không hợp lệ');
