<?php

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

$ToryHub = new DB;
date_default_timezone_set($ToryHub->site('timezone') ?: 'Asia/Ho_Chi_Minh');

$session_login = $ToryHub->site('session_login') ?: 86400;

if (session_status() == PHP_SESSION_NONE) {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    ini_set('session.gc_maxlifetime', $session_login);
    ini_set('session.cookie_lifetime', $session_login);
    ini_set('session.cookie_secure', $is_https);
    ini_set('session.cookie_httponly', true);
    ini_set('session.use_strict_mode', true);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// Check banned IP
if ($ToryHub->get_row_safe("SELECT * FROM `banned_ips` WHERE `ip` = ? AND `banned` = 1", [myip()])) {
    require_once(__DIR__.'/../resources/views/common/block-ip.php');
    exit();
}

// Auto-insert settings
function insert_options($name, $value)
{
    global $ToryHub;
    if (!$ToryHub->get_row_safe("SELECT * FROM `settings` WHERE `name` = ?", [$name])) {
        $ToryHub->insert_safe("settings", [
            'name'  => $name,
            'value' => $value
        ]);
    }
}

$host = $_SERVER['HTTP_HOST'] ?? '';
$host = check_string($host);
$domains = $host . ',' . 'www.' . $host;
insert_options('domains', $domains);

// ===== SECURITY =====

function check_string($data)
{
    return trim(htmlspecialchars(addslashes($data)));
}

function check_path($input)
{
    $input = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $input);
    $input = str_replace(['../', './'], '', $input);
    return check_string($input);
}

function myip()
{
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!empty($_SERVER['HTTP_CLIENT_IP']) && filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP)) {
        $ip_address = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip_list = array_map('trim', $ip_list);
        foreach ($ip_list as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $ip_address = $ip;
                break;
            }
        }
    }
    return filter_var($ip_address, FILTER_VALIDATE_IP) ? $ip_address : '0.0.0.0';
}

function checkBlockIP($type, $time = 15)
{
    global $ToryHub;
    $ip_address = myip();
    $max_attempts = 10;

    $reasons = [
        'LOGIN'     => 'Failed login too many times',
        'ADMIN'     => 'Failed admin login too many times',
        'SCAN'      => 'Too many scan attempts',
    ];
    $reason = $reasons[$type] ?? 'Too many requests';

    $ToryHub->insert_safe("failed_attempts", [
        'ip_address'     => $ip_address,
        'attempts'       => 1,
        'type'           => $type,
        'create_gettime' => gettime()
    ]);

    $attempts = $ToryHub->get_row_safe(
        "SELECT COUNT(*) as total FROM `failed_attempts`
        WHERE `ip_address` = ? AND `type` = ?
        AND `create_gettime` >= DATE_SUB(NOW(), INTERVAL ? MINUTE)",
        [$ip_address, $type, $time]
    );

    if ($attempts['total'] >= $max_attempts) {
        $ToryHub->insert_safe('banned_ips', [
            'ip'             => $ip_address,
            'attempts'       => $attempts['total'],
            'create_gettime' => gettime(),
            'banned'         => 1,
            'reason'         => __($reason)
        ]);
        $ToryHub->remove_safe('failed_attempts', "`ip_address` = ? AND `type` = ?", [$ip_address, $type]);
        return json_encode(['status' => 'error', 'msg' => __('IP blocked. Please try again later.')]);
    }
    return false;
}

function generateUltraSecureToken($length = 32)
{
    return bin2hex(random_bytes($length));
}

function generate_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token)
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// ===== URL & NAVIGATION =====

function base_url($url = '')
{
    global $ToryHub;
    $allowed_domains = array_map('trim', explode(',', $ToryHub->site('domains')));
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (!preg_match('/^[a-zA-Z0-9\-\.\:]+$/', $host)) {
        $host = $allowed_domains[0];
    }
    if (!in_array($host, $allowed_domains)) {
        $host = $allowed_domains[0];
    }
    $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? 'https' : 'http';
    $base = $protocol . '://' . $host;

    // Detect subfolder by comparing SCRIPT_FILENAME with project root
    $subfolder = '';
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $scriptFilename = str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '');
    $projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: '');
    if ($scriptFilename && $projectRoot && strpos($scriptFilename, $projectRoot) === 0) {
        $relativePath = substr($scriptFilename, strlen($projectRoot));
        if ($relativePath && substr($scriptName, -strlen($relativePath)) === $relativePath) {
            $subfolder = substr($scriptName, 0, -strlen($relativePath));
        }
    }
    $subfolder = rtrim($subfolder, '/');

    // Auto-fix: convert first & to ? for proper query string
    $url = ltrim($url, '/');
    if (strpos($url, '?') === false && strpos($url, '&') !== false) {
        $url = preg_replace('/&/', '?', $url, 1);
    }
    return $base . $subfolder . '/' . $url;
}

function redirect($url)
{
    header("Location: " . $url);
    exit();
}

function active_sidebar($action)
{
    foreach ($action as $row) {
        if (isset($_GET['action']) && $_GET['action'] == $row) {
            return 'active';
        }
    }
    return '';
}

function menuopen_sidebar($action)
{
    foreach ($action as $row) {
        if (isset($_GET['action']) && $_GET['action'] == $row) {
            return 'menu-open';
        }
    }
    return '';
}

// ===== INPUT =====

function input_post($key)
{
    return isset($_POST[$key]) ? trim($_POST[$key]) : false;
}

function input_get($key)
{
    return isset($_GET[$key]) ? trim($_GET[$key]) : false;
}

/**
 * Parse product_image field - hỗ trợ JSON array, JSON string, CSV
 */
function parse_product_images($val) {
    if (empty($val)) return [];
    $list = @json_decode($val, true);
    if (is_array($list)) return array_values(array_filter(array_map('trim', $list)));
    if (is_string($list) && trim($list)) return [trim($list)];
    return array_values(array_filter(array_map('trim', explode(',', $val))));
}

function is_submit($key)
{
    return (isset($_POST['request_name']) && $_POST['request_name'] == $key);
}

// ===== PASSWORD =====

function TypePassword($password)
{
    $ToryHub = new DB();
    $type = $ToryHub->site('type_password');
    if ($type == 'bcrypt') {
        return password_hash($password, PASSWORD_BCRYPT);
    }
    if ($type == 'md5') {
        return md5($password);
    }
    return password_hash($password, PASSWORD_BCRYPT);
}

function VerifyPassword($password, $hash)
{
    $ToryHub = new DB();
    $type = $ToryHub->site('type_password');
    if ($type == 'bcrypt') {
        return password_verify($password, $hash);
    }
    if ($type == 'md5') {
        return md5($password) === $hash;
    }
    return password_verify($password, $hash);
}

// ===== USER HELPERS =====

function getUser($id, $row)
{
    $ToryHub = new DB();
    $result = $ToryHub->get_row_safe("SELECT * FROM `users` WHERE `id` = ?", [$id]);
    return $result ? $result[$row] : null;
}

function check_email($data)
{
    return (bool)preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $data);
}

function check_phone($data)
{
    return (bool)preg_match('/^\+?(\d.*){3,}$/', $data);
}

// ===== DATE & TIME =====

function gettime()
{
    return date('Y/m/d H:i:s', time());
}

function timeAgo($time_ago)
{
    $time_ago = empty($time_ago) ? 0 : $time_ago;
    if ($time_ago == 0) return '--';

    $time_ago = date("Y-m-d H:i:s", $time_ago);
    $time_ago = strtotime($time_ago);
    $cur_time = time();
    $time_elapsed = $cur_time - $time_ago;
    $seconds = $time_elapsed;
    $minutes = round($time_elapsed / 60);
    $hours = round($time_elapsed / 3600);
    $days = round($time_elapsed / 86400);
    $weeks = round($time_elapsed / 604800);
    $months = round($time_elapsed / 2600640);
    $years = round($time_elapsed / 31207680);

    if ($seconds <= 60) return "$seconds " . __('giây trước');
    elseif ($minutes <= 60) return "$minutes " . __('phút trước');
    elseif ($hours <= 24) return "$hours " . __('tiếng trước');
    elseif ($days <= 7) return ($days == 1) ? __('Hôm qua') : "$days " . __('ngày trước');
    elseif ($weeks <= 4.3) return "$weeks " . __('tuần trước');
    elseif ($months <= 12) return "$months " . __('tháng trước');
    else return "$years " . __('năm trước');
}

// ===== CURRENCY & FORMATTING =====

function fnum($number, $dec = 2)
{
    $formatted = number_format($number, $dec, ',', '.');
    if ($dec > 0) $formatted = rtrim(rtrim($formatted, '0'), ',');
    return $formatted;
}

function format_cash($number, $suffix = '')
{
    return number_format($number, 0, ',', '.') . "{$suffix}";
}

function format_vnd($amount)
{
    return number_format($amount, 0, ',', '.') . 'đ';
}

function format_cny($amount)
{
    return '¥' . number_format($amount, 2, '.', ',');
}

function get_exchange_rate()
{
    $ToryHub = new DB();
    return floatval($ToryHub->site('exchange_rate_cny_vnd') ?: 3500);
}

function cny_to_vnd($cny_amount)
{
    return $cny_amount * get_exchange_rate();
}

function format_dual($vnd, $cny, $bold = false, $danger = false)
{
    $vndStr = format_vnd($vnd);
    $cnyStr = format_cny($cny);
    if ($danger) $vndStr = '<span class="text-danger">' . $vndStr . '</span>';
    $cls = $bold ? 'fw-bold' : '';
    return '<span class="' . $cls . '">' . $vndStr . '</span><br><small class="text-muted">' . $cnyStr . '</small>';
}

function format_dual_or_dash($amount, $vndVal, $cnyVal, $danger = false)
{
    if ($amount <= 0) return '<span class="text-muted">-</span>';
    return format_dual($vndVal, $cnyVal, false, $danger);
}

// ===== ORDER HELPERS =====

function generate_order_code()
{
    return 'DH' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function generate_customer_code()
{
    $ToryHub = new DB();
    $last = $ToryHub->get_row_safe("SELECT `id` FROM `customers` ORDER BY `id` DESC LIMIT 1", []);
    $next_id = $last ? ($last['id'] + 1) : 1;
    return 'KH' . str_pad($next_id, 4, '0', STR_PAD_LEFT);
}

function calculate_shipping_fee($weight, $method = 'road')
{
    $ToryHub = new DB();
    $rate = floatval($ToryHub->site('shipping_rate_road') ?: 25000);
    return $weight * $rate;
}

function calculate_volume_weight($length, $width, $height)
{
    $ToryHub = new DB();
    $divisor = floatval($ToryHub->site('volume_divisor') ?: 6000);
    return ($length * $width * $height) / $divisor;
}

function calculate_charged_weight($actual, $volume)
{
    return max($actual, $volume);
}

// ===== DISPLAY HELPERS =====

function display_order_status($status, $suffix = '')
{
    $statuses = [
        'pending'       => ['label' => 'Chờ xử lý',       'bg' => 'warning-subtle',   'text' => 'warning',   'icon' => 'ri-time-line'],
        'purchased'     => ['label' => 'Đã mua hàng',     'bg' => 'info-subtle',      'text' => 'info',      'icon' => 'ri-shopping-bag-line'],
        'cn_shipped'    => ['label' => 'Shop đã gửi',     'bg' => 'info-subtle',      'text' => 'info',      'icon' => 'ri-truck-line'],
        'cn_warehouse'  => ['label' => 'Kho TQ',              'bg' => 'info-subtle',      'text' => 'info',      'icon' => 'ri-building-line'],
        'packed'        => ['label' => 'Đã đóng bao',     'bg' => 'dark-subtle',      'text' => 'dark',      'icon' => 'ri-archive-drawer-line'],
        'loading'       => ['label' => 'Xếp xe',          'bg' => 'secondary-subtle', 'text' => 'secondary', 'icon' => 'ri-truck-line'],
        'shipping'      => ['label' => 'Vận chuyển',      'bg' => 'primary-subtle',   'text' => 'primary',   'icon' => 'ri-truck-line'],
        'vn_warehouse'  => ['label' => 'Kho VN',          'bg' => 'success-subtle',   'text' => 'success',   'icon' => 'ri-home-4-line'],
        'delivered'     => ['label' => 'Đã giao',         'bg' => 'success-subtle',   'text' => 'success',   'icon' => 'ri-check-double-line'],
        'cancelled'     => ['label' => 'Đã hủy',          'bg' => 'danger-subtle',    'text' => 'danger',    'icon' => 'ri-close-circle-line'],
        'returned'      => ['label' => 'Hoàn hàng',        'bg' => 'secondary-subtle', 'text' => 'secondary', 'icon' => 'ri-arrow-go-back-line'],
        'damaged'       => ['label' => 'Hỏng hàng',        'bg' => 'danger-subtle',    'text' => 'danger',    'icon' => 'ri-alert-line'],
    ];
    $s = $statuses[$status] ?? ['label' => $status, 'bg' => 'secondary-subtle', 'text' => 'secondary', 'icon' => 'ri-question-line'];
    $extra = $suffix ? ' ' . $suffix : '';
    return '<span class="badge bg-' . $s['bg'] . ' text-' . $s['text'] . ' fs-12 px-2 py-1"><i class="' . $s['icon'] . ' align-middle me-1"></i>' . __($s['label']) . $extra . '</span>';
}

function display_package_status($status)
{
    $statuses = [
        'pending'      => ['label' => 'Chờ xử lý',          'bg' => 'warning-subtle',   'text' => 'warning',   'icon' => 'ri-time-line'],
        'cn_warehouse' => ['label' => 'Kho TQ',              'bg' => 'info-subtle',      'text' => 'info',      'icon' => 'ri-building-line'],
        'packed'       => ['label' => 'Đã đóng bao',      'bg' => 'dark-subtle',      'text' => 'dark',      'icon' => 'ri-archive-drawer-line'],
        'loading'      => ['label' => 'Xếp xe',           'bg' => 'secondary-subtle', 'text' => 'secondary', 'icon' => 'ri-truck-line'],
        'shipping'     => ['label' => 'Vận chuyển',       'bg' => 'primary-subtle',   'text' => 'primary',   'icon' => 'ri-truck-line'],
        'vn_warehouse' => ['label' => 'Kho VN',           'bg' => 'success-subtle',   'text' => 'success',   'icon' => 'ri-home-4-line'],
        'delivered'    => ['label' => 'Đã giao',          'bg' => 'success-subtle',   'text' => 'success',   'icon' => 'ri-check-double-line'],
        'returned'     => ['label' => 'Hoàn hàng',        'bg' => 'secondary-subtle', 'text' => 'secondary', 'icon' => 'ri-arrow-go-back-line'],
        'damaged'      => ['label' => 'Hỏng hàng',        'bg' => 'danger-subtle',    'text' => 'danger',    'icon' => 'ri-alert-line'],
    ];
    $s = $statuses[$status] ?? ['label' => $status, 'bg' => 'secondary-subtle', 'text' => 'secondary', 'icon' => 'ri-question-line'];
    return '<span class="badge bg-' . $s['bg'] . ' text-' . $s['text'] . ' fs-12 px-2 py-1"><i class="' . $s['icon'] . ' align-middle me-1"></i>' . __($s['label']) . '</span>';
}

function display_customer_type($type)
{
    $types = [
        'normal' => ['label' => 'Thường',   'class' => 'bg-secondary'],
        'vip'    => ['label' => 'VIP',      'class' => 'bg-warning'],
        'agent'  => ['label' => 'Đại lý',  'class' => 'bg-primary'],
    ];
    $t = $types[$type] ?? ['label' => $type, 'class' => 'bg-secondary'];
    return '<span class="badge ' . $t['class'] . '">' . __($t['label']) . '</span>';
}

function display_cargo_type($type)
{
    $types = [
        'easy'      => ['label' => 'Hàng dễ vận chuyển',  'bg' => 'success-subtle', 'text' => 'success'],
        'difficult' => ['label' => 'Hàng khó vận chuyển', 'bg' => 'danger-subtle',  'text' => 'danger'],
    ];
    if (!$type) return '';
    $t = $types[$type] ?? ['label' => $type, 'bg' => 'secondary-subtle', 'text' => 'secondary'];
    return '<span class="badge bg-' . $t['bg'] . ' text-' . $t['text'] . ' fs-12 px-2 py-1">' . __($t['label']) . '</span>';
}

function display_platform($platform)
{
    $platforms = [
        'taobao'  => ['label' => 'Taobao',  'class' => 'bg-orange'],
        '1688'    => ['label' => '1688',     'class' => 'bg-info'],
        'alibaba' => ['label' => 'Alibaba',  'class' => 'bg-warning'],
        'other'   => ['label' => 'Khác',     'class' => 'bg-secondary'],
    ];
    $p = $platforms[$platform] ?? ['label' => $platform, 'class' => 'bg-secondary'];
    return '<span class="badge ' . $p['class'] . '">' . $p['label'] . '</span>';
}


function display_banned($banned)
{
    if ($banned != 1) {
        return '<span class="badge bg-success">Active</span>';
    }
    return '<span class="badge bg-danger">Banned</span>';
}

function display_online($time)
{
    if (time() - $time <= 300) {
        return '<span class="badge bg-success">Online</span>';
    }
    return '<span class="badge bg-danger">Offline</span>';
}

function display_role($role)
{
    $roles = [
        'admin'     => ['label' => 'Admin',          'class' => 'bg-danger'],
        'staffcn'    => ['label' => 'Nhân viên Kho Trung Quốc', 'class' => 'bg-warning'],
        'finance_cn' => ['label' => 'Tài chính Kho Trung Quốc', 'class' => 'bg-primary'],
        'staffvn'    => ['label' => 'Nhân viên Kho Việt Nam', 'class' => 'bg-info'],
        'finance_vn' => ['label' => 'Tài chính Kho Việt Nam', 'class' => 'bg-success'],
    ];
    $r = $roles[$role] ?? ['label' => $role, 'class' => 'bg-secondary'];
    return '<span class="badge ' . $r['class'] . '">' . $r['label'] . '</span>';
}

function display_payment_status($is_paid)
{
    if ($is_paid) {
        return '<span class="badge bg-success">' . __('Đã thanh toán') . '</span>';
    }
    return '<span class="badge bg-warning">' . __('Chưa thanh toán') . '</span>';
}

// ===== FILE UPLOAD =====

function upload_image($file, $folder = 'products', $max_size = 5242880)
{
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['status' => 'error', 'msg' => __('Lỗi upload file')];
    }

    if ($file['size'] > $max_size) {
        return ['status' => 'error', 'msg' => __('File quá lớn (tối đa 5MB)')];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed_types)) {
        return ['status' => 'error', 'msg' => __('Chỉ chấp nhận ảnh JPG, PNG, GIF, WebP')];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_exts)) {
        return ['status' => 'error', 'msg' => __('Định dạng file không hợp lệ')];
    }

    $upload_dir = __DIR__ . '/../uploads/' . $folder . '/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $filepath = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['status' => 'error', 'msg' => __('Không thể lưu file')];
    }

    return ['status' => 'success', 'filename' => $filename, 'path' => 'uploads/' . $folder . '/' . $filename];
}

function delete_uploaded_file($path)
{
    if (empty($path)) return;
    $fullpath = __DIR__ . '/../' . $path;
    if (file_exists($fullpath) && strpos(realpath($fullpath), realpath(__DIR__ . '/../uploads/')) === 0) {
        unlink($fullpath);
    }
}

function get_upload_url($path)
{
    if (empty($path)) return '';
    if (strpos($path, 'http') === 0) return $path;
    return base_url($path);
}

// ===== COOKIE =====

function setSecureCookie($name, $value)
{
    global $ToryHub;
    return setcookie($name, $value, time() + ($ToryHub->site('session_login') ?: 86400), "/", "", false, true);
}

// ===== LOGGING =====

function add_log($user_id, $action, $description = '')
{
    $ToryHub = new DB();
    $ToryHub->insert_safe('logs', [
        'user_id'     => $user_id,
        'action'      => $action,
        'description' => $description,
        'ip'          => myip(),
        'create_date' => gettime()
    ]);
}
