<?php
/**
 * API Logs endpoints
 */

require_once(__DIR__.'/bootstrap.php');
require_once(__DIR__.'/jwt.php');

$user = api_auth();
api_method('GET');

$pg = api_pagination();
$action_filter = $_GET['action'] ?? '';
$user_id = $_GET['user_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where = "1=1"; $params = [];
if ($action_filter) { $where .= " AND l.action LIKE ?"; $params[] = "%$action_filter%"; }
if ($user_id) { $where .= " AND l.user_id = ?"; $params[] = $user_id; }
if ($date_from) { $where .= " AND DATE(l.create_date) >= ?"; $params[] = $date_from; }
if ($date_to) { $where .= " AND DATE(l.create_date) <= ?"; $params[] = $date_to; }

$total = $ToryHub->get_row_safe("SELECT COUNT(*) as cnt FROM `logs` l WHERE $where", $params);
$listParams = array_merge($params, [$pg['per_page'], $pg['offset']]);
$logs = $ToryHub->get_list_safe(
    "SELECT l.*, u.fullname, u.username FROM `logs` l
     LEFT JOIN `users` u ON l.user_id = u.id
     WHERE $where ORDER BY l.id DESC LIMIT ? OFFSET ?", $listParams
);

api_success([
    'logs' => $logs,
    'total' => (int)($total['cnt'] ?? 0),
    'page' => $pg['page'],
    'per_page' => $pg['per_page']
]);
