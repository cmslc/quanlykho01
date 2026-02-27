<?php
ob_start();
define('IN_SITE', true);
require_once(__DIR__.'/../../libs/db.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../libs/lang.php');
require_once(__DIR__.'/../../libs/helper.php');
require_once(__DIR__.'/../../libs/session.php');
require_once(__DIR__.'/../../libs/role.php');
require_once(__DIR__.'/../../libs/csrf.php');
require_once(__DIR__.'/../../libs/database/orders.php');
require_once(__DIR__.'/../../libs/database/packages.php');
require_once(__DIR__.'/../../models/is_admin.php');

header('Content-Type: application/json; charset=utf-8');

$csrf = new Csrf();
if (!$csrf->validate()) {
    ob_end_clean(); echo json_encode(['status' => 'error', 'msg' => 'Token không hợp lệ']);
    exit;
}

$request = input_post('request_name');
$Orders = new Orders();

// ======== ADD ========
if ($request === 'add') {
    $customer_id = input_post('customer_id') ? intval(input_post('customer_id')) : null;
    $product_type = in_array(input_post('product_type'), ['retail', 'wholesale']) ? input_post('product_type') : 'retail';
    $order_type = input_post('order_type') ?: 'purchase';
    if (!in_array($order_type, ['purchase', 'shipping'])) {
        $order_type = 'purchase';
    }
    $product_name = trim(input_post('product_name'));
    $quantity = 1;
    $unit_price_cny = floatval(input_post('unit_price_cny'));
    $exchange_rate = floatval(input_post('exchange_rate'));

    // Hàng lô requires customer and product name
    if ($product_type === 'wholesale') {
        if (!$customer_id) {
            ob_end_clean(); echo json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn khách hàng')]);
            exit;
        }
        if (empty($product_name)) {
            ob_end_clean(); echo json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập tên sản phẩm')]);
            exit;
        }
    }
    if ($order_type === 'shipping') {
        $unit_price_cny = 0;
    }

    // Check customer exists (if provided)
    $customer = null;
    if ($customer_id) {
        $customer = $ToryHub->get_row_safe("SELECT * FROM `customers` WHERE `id` = ?", [$customer_id]);
        if (!$customer) {
            ob_end_clean(); echo json_encode(['status' => 'error', 'msg' => __('Khách hàng không tồn tại')]);
            exit;
        }
    }

    // Check duplicate product_code (mã hàng)
    $product_code = strtoupper(trim(input_post('product_code')));
    if ($product_code !== '') {
        $existOrder = $ToryHub->get_row_safe("SELECT `id` FROM `orders` WHERE `product_code` = ?", [$product_code]);
        if ($existOrder) {
            ob_end_clean(); echo json_encode(['status' => 'error', 'msg' => __('Mã hàng đã tồn tại') . ': ' . $product_code]);
            exit;
        }
    }

    // Check duplicate tracking_cn (mã vận đơn TQ)
    $packages_input = isset($_POST['packages']) ? $_POST['packages'] : [];
    if (!empty($packages_input)) {
        foreach ($packages_input as $pkg) {
            $tracking = strtoupper(trim($pkg['tracking_cn'] ?? ''));
            if ($tracking !== '') {
                $existPkg = $ToryHub->get_row_safe("SELECT `id` FROM `packages` WHERE `tracking_cn` = ?", [$tracking]);
                if ($existPkg) {
                    ob_end_clean(); echo json_encode(['status' => 'error', 'msg' => __('Mã vận đơn đã tồn tại') . ': ' . $tracking]);
                    exit;
                }
            }
        }
    }

    if ($exchange_rate <= 0) {
        $exchange_rate = get_exchange_rate();
    }

    // Initial status
    $status = input_post('status') ?: 'cn_warehouse';
    $validStatuses = ['cn_warehouse', 'packed', 'loading', 'shipping', 'vn_warehouse', 'delivered'];
    if (!in_array($status, $validStatuses)) {
        $status = 'cn_warehouse';
    }

    // Calculate fees
    $total_cny = $quantity * $unit_price_cny;
    $total_vnd = $total_cny * $exchange_rate;
    $service_fee = 0;
    if ($order_type === 'purchase') {
        $service_fee_percent = floatval($ToryHub->site('service_fee_percent') ?: 3);
        $service_fee = round($total_vnd * $service_fee_percent / 100);
    }

    $shipping_fee_cn_cny = floatval(input_post('shipping_fee_cn'));
    $shipping_fee_cn = round($shipping_fee_cn_cny * $exchange_rate);
    $packing_fee = floatval(input_post('packing_fee'));
    $insurance_fee = floatval(input_post('insurance_fee'));
    $other_fee = floatval(input_post('other_fee'));

    $total_fee = $service_fee + $shipping_fee_cn + $packing_fee + $insurance_fee + $other_fee;
    $grand_total = round($total_vnd + $total_fee);

    // Weight & dimensions
    $weight_actual = floatval(input_post('weight_actual'));
    $length_cm = floatval(input_post('length_cm'));
    $width_cm = floatval(input_post('width_cm'));
    $height_cm = floatval(input_post('height_cm'));
    $weight_volume = calculate_volume_weight($length_cm, $width_cm, $height_cm);
    $weight_charged = calculate_charged_weight($weight_actual, $weight_volume);
    $dimensions = ($length_cm > 0 || $width_cm > 0 || $height_cm > 0) ? $length_cm . 'x' . $width_cm . 'x' . $height_cm : '';

    // Handle image upload (multiple)
    $product_image = '';
    if (!empty($_FILES['product_images']['name'][0])) {
        $paths = [];
        foreach ($_FILES['product_images']['name'] as $i => $name) {
            if ($_FILES['product_images']['error'][$i] !== UPLOAD_ERR_OK || empty($name)) continue;
            $file = [
                'name'     => $_FILES['product_images']['name'][$i],
                'type'     => $_FILES['product_images']['type'][$i],
                'tmp_name' => $_FILES['product_images']['tmp_name'][$i],
                'error'    => $_FILES['product_images']['error'][$i],
                'size'     => $_FILES['product_images']['size'][$i],
            ];
            $upload = upload_image($file, 'products');
            if ($upload['status'] === 'error') {
                ob_end_clean(); echo json_encode(['status' => 'error', 'msg' => $upload['msg']]);
                exit;
            }
            $paths[] = $upload['path'];
        }
        $product_image = implode(',', $paths);
    }

    $cargo_type = in_array(input_post('cargo_type'), ['easy', 'difficult']) ? input_post('cargo_type') : null;

    $insertResult = $ToryHub->insert_safe("orders", [
        'order_code' => '', // Will update
        'customer_id' => $customer_id,
        'order_type' => $order_type,
        'product_type' => $product_type,
        'cargo_type' => $product_type === 'wholesale' ? $cargo_type : null,
        'product_code' => $product_code,
        'platform' => $order_type === 'purchase' ? (input_post('platform') ?: 'taobao') : 'other',
        'source_url' => trim(input_post('source_url')),
        'status' => $status,
        'product_name' => $product_name,
        'product_image' => $product_image,
        'quantity' => $quantity,
        'unit_price_cny' => $unit_price_cny,
        'total_cny' => $total_cny,
        'exchange_rate' => $exchange_rate,
        'total_vnd' => round($total_vnd),
        'service_fee' => $service_fee,
        'shipping_fee_cn' => $shipping_fee_cn,
        'shipping_fee_intl' => 0,
        'packing_fee' => $packing_fee,
        'insurance_fee' => $insurance_fee,
        'other_fee' => $other_fee,
        'total_fee' => $total_fee,
        'grand_total' => $grand_total,
        'cn_tracking' => '',
        'intl_tracking' => '',
        'vn_tracking' => '',
        'weight_actual' => $weight_actual,
        'weight_volume' => $weight_volume,
        'weight_charged' => $weight_charged,
        'dimensions' => $dimensions,
        'note' => trim(input_post('note')),
        'note_internal' => trim(input_post('note_internal')),
        'created_by' => $getUser['id'],
        'updated_by' => $getUser['id'],
        'create_date' => gettime(),
        'update_date' => gettime()
    ]);

    if (!$insertResult) {
        ob_end_clean(); echo json_encode(['status' => 'error', 'msg' => __('Lỗi tạo đơn hàng. Vui lòng kiểm tra cấu trúc database.')]);
        exit;
    }

    $newId = $ToryHub->insert_id();
    $orderCode = str_pad($newId, 6, '0', STR_PAD_LEFT);
    $ToryHub->update_safe("orders", ['order_code' => $orderCode], "id = ?", [$newId]);

    // Update customer total_orders
    if ($customer_id) {
        $ToryHub->cong_safe("customers", "total_orders", 1, "id = ?", [$customer_id]);
    }

    // Log initial status
    $ToryHub->insert_safe("order_status_history", [
        'order_id' => $newId,
        'old_status' => '',
        'new_status' => $status,
        'note' => __('Tạo đơn hàng mới'),
        'changed_by' => $getUser['id'],
        'create_date' => gettime()
    ]);

    add_log($getUser['id'], 'add_order', 'Tạo đơn hàng: ' . $orderCode);

    // Create packages from form
    $packages = isset($_POST['packages']) ? $_POST['packages'] : [];
    $createdPackages = 0;
    if (!empty($packages)) {
        $Packages = new Packages();
        $volume_divisor = floatval($ToryHub->site('volume_divisor') ?: 6000);
        foreach ($packages as $pkg) {
            $pkg_qty = max(1, intval($pkg['qty'] ?? 1));
            $pkg_weight = floatval($pkg['weight'] ?? 0);
            $pkg_length = floatval($pkg['length_cm'] ?? 0);
            $pkg_width = floatval($pkg['width_cm'] ?? 0);
            $pkg_height = floatval($pkg['height_cm'] ?? 0);
            $pkg_vol_weight = ($pkg_length > 0 && $pkg_width > 0 && $pkg_height > 0) ? ($pkg_length * $pkg_width * $pkg_height) / $volume_divisor : 0;
            $pkg_charged = max($pkg_weight, $pkg_vol_weight);

            $pkgData = [
                'tracking_cn'     => strtoupper(trim($pkg['tracking_cn'] ?? '')),
                'tracking_intl'   => '',
                'tracking_vn'     => '',
                'weight_actual'   => $pkg_weight,
                'length_cm'       => $pkg_length,
                'width_cm'        => $pkg_width,
                'height_cm'       => $pkg_height,
                'weight_volume'   => round($pkg_vol_weight, 2),
                'weight_charged'  => round($pkg_charged, 2),
                'status'          => 'cn_warehouse',
                'note'            => '',
                'created_by'      => $getUser['id'],
            ];
            for ($i = 0; $i < $pkg_qty; $i++) {
                $result = $Packages->createPackage($pkgData, [$newId]);
                if ($result) {
                    $createdPackages++;
                } else {
                    $pkgError = $Packages->getLastError();
                }
            }
        }
    }

    // Update order total_packages
    if ($createdPackages > 0) {
        $ToryHub->update_safe("orders", ['total_packages' => $createdPackages], "id = ?", [$newId]);
    }

    $msg = __('Tạo đơn hàng thành công');
    if ($createdPackages > 0) {
        $msg .= ' (' . $createdPackages . ' ' . __('kiện') . ')';
    } elseif (!empty($packages) && $createdPackages === 0) {
        $msg .= ' - ' . __('Lỗi tạo kiện') . ': ' . ($pkgError ?? 'Unknown');
    }

    // Send JSON response FIRST, before notifications
    ob_end_clean(); echo json_encode(['status' => 'success', 'msg' => $msg, 'order_id' => $newId, 'order_code' => $orderCode]);

    // Notifications after response (won't affect client)
    try {
        require_once(__DIR__.'/../../libs/telegram.php');
        $bot = new TelegramBot();
        $bot->notifyNewOrder([
            'order_code' => $orderCode,
            'product_name' => $product_name,
            'quantity' => $quantity,
            'total_cny' => $total_cny,
            'grand_total' => $grand_total,
            'platform' => input_post('platform') ?: 'taobao'
        ], $customer ? $customer['fullname'] : '');
    } catch (Exception $e) { error_log('Telegram notify error: ' . $e->getMessage()); }

    try {
        if ($customer_id && $customer && !empty($customer['email'])) {
            require_once(__DIR__.'/../../libs/email.php');
            email_notify('notifyNewOrder', [
                'order_code' => $orderCode,
                'product_name' => $product_name,
                'quantity' => $quantity,
                'grand_total' => $grand_total
            ], $customer['email']);
        }
    } catch (Exception $e) { error_log('Email notify error: ' . $e->getMessage()); }

    exit;
}

// ======== EDIT ========
if ($request === 'edit') {
    $id = intval(input_post('id'));
    $order = $ToryHub->get_row_safe("SELECT * FROM `orders` WHERE `id` = ?", [$id]);
    if (!$order) {
        ob_end_clean(); echo json_encode(['status' => 'error', 'msg' => __('Đơn hàng không tồn tại')]);
        exit;
    }

    $product_type = in_array(input_post('product_type'), ['retail', 'wholesale']) ? input_post('product_type') : 'retail';
    $customer_id = input_post('customer_id') ? intval(input_post('customer_id')) : null;

    // Wholesale requires customer
    if ($product_type === 'wholesale' && !$customer_id) {
        ob_end_clean(); echo json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn khách hàng')]);
        exit;
    }

    // Validate status
    $status = input_post('status') ?: $order['status'];
    $validStatuses = ['cn_warehouse', 'packed', 'loading', 'shipping', 'vn_warehouse', 'delivered', 'cancelled'];
    if (!in_array($status, $validStatuses)) {
        $status = $order['status'];
    }

    // Check duplicate product_code (exclude current order)
    $product_code = strtoupper(trim(input_post('product_code')));
    if ($product_code !== '') {
        $existOrder = $ToryHub->get_row_safe("SELECT `id` FROM `orders` WHERE `product_code` = ? AND `id` != ?", [$product_code, $id]);
        if ($existOrder) {
            ob_end_clean(); echo json_encode(['status' => 'error', 'msg' => __('Mã hàng đã tồn tại') . ': ' . $product_code]);
            exit;
        }
    }

    // Handle image: keep current + add new
    $currentImages = trim(input_post('current_images'));
    $keptImages = $currentImages !== '' ? array_filter(array_map('trim', explode(',', $currentImages))) : [];

    $newPaths = [];
    if (!empty($_FILES['product_images']['name'][0])) {
        foreach ($_FILES['product_images']['name'] as $i => $name) {
            if ($_FILES['product_images']['error'][$i] !== UPLOAD_ERR_OK || empty($name)) continue;
            $file = [
                'name'     => $_FILES['product_images']['name'][$i],
                'type'     => $_FILES['product_images']['type'][$i],
                'tmp_name' => $_FILES['product_images']['tmp_name'][$i],
                'error'    => $_FILES['product_images']['error'][$i],
                'size'     => $_FILES['product_images']['size'][$i],
            ];
            $upload = upload_image($file, 'products');
            if ($upload['status'] === 'error') {
                ob_end_clean(); echo json_encode(['status' => 'error', 'msg' => $upload['msg']]);
                exit;
            }
            $newPaths[] = $upload['path'];
        }
    }

    // Delete removed images (old images not in kept list)
    if (!empty($order['product_image'])) {
        $oldImages = array_filter(array_map('trim', explode(',', $order['product_image'])));
        foreach ($oldImages as $old) {
            if (!in_array($old, $keptImages) && strpos($old, 'uploads/') === 0) {
                delete_uploaded_file($old);
            }
        }
    }

    $product_image = implode(',', array_merge($keptImages, $newPaths));

    // Log status change
    if ($status !== $order['status']) {
        $ToryHub->insert_safe("order_status_history", [
            'order_id' => $id,
            'old_status' => $order['status'],
            'new_status' => $status,
            'note' => __('Cập nhật trạng thái'),
            'changed_by' => $getUser['id'],
            'create_date' => gettime()
        ]);
    }

    // Update customer tracking
    $oldCustomerId = $order['customer_id'];
    if ($oldCustomerId != $customer_id) {
        if ($oldCustomerId) {
            $ToryHub->tru_safe("customers", "total_orders", 1, "id = ?", [$oldCustomerId]);
        }
        if ($customer_id) {
            $ToryHub->cong_safe("customers", "total_orders", 1, "id = ?", [$customer_id]);
        }
    }

    $cargo_type = in_array(input_post('cargo_type'), ['easy', 'difficult']) ? input_post('cargo_type') : null;

    $edit_weight_actual = floatval(input_post('weight_actual'));
    $edit_length_cm = floatval(input_post('length_cm'));
    $edit_width_cm = floatval(input_post('width_cm'));
    $edit_height_cm = floatval(input_post('height_cm'));
    $edit_weight_volume = calculate_volume_weight($edit_length_cm, $edit_width_cm, $edit_height_cm);
    $edit_weight_charged = calculate_charged_weight($edit_weight_actual, $edit_weight_volume);
    $edit_dimensions = ($edit_length_cm > 0 || $edit_width_cm > 0 || $edit_height_cm > 0) ? $edit_length_cm . 'x' . $edit_width_cm . 'x' . $edit_height_cm : '';
    $ToryHub->update_safe("orders", [
        'customer_id' => $customer_id,
        'order_type' => 'shipping',
        'product_type' => $product_type,
        'cargo_type' => $product_type === 'wholesale' ? $cargo_type : null,
        'product_code' => $product_code,
        'status' => $status,
        'product_name' => trim(input_post('product_name')),
        'product_image' => $product_image,
        'weight_actual' => $edit_weight_actual,
        'weight_volume' => $edit_weight_volume,
        'weight_charged' => $edit_weight_charged,
        'dimensions' => $edit_dimensions,
        'note' => trim(input_post('note')),
        'note_internal' => trim(input_post('note_internal')),
        'updated_by' => $getUser['id'],
        'update_date' => gettime()
    ], "id = ?", [$id]);

    // Update inline package data submitted with the form
    $edit_packages = isset($_POST['edit_packages']) ? $_POST['edit_packages'] : [];
    if (!empty($edit_packages)) {
        $linked = $ToryHub->get_list_safe(
            "SELECT p.id FROM `packages` p JOIN `package_orders` po ON p.id = po.package_id WHERE po.order_id = ?", [$id]
        );
        $linkedIds = array_column($linked, 'id');
        $volume_divisor = floatval($ToryHub->site('volume_divisor') ?: 6000);
        foreach ($edit_packages as $pkgId => $pkgData) {
            $pkgId = intval($pkgId);
            if (!$pkgId || !in_array($pkgId, $linkedIds)) continue;
            $pkg_length = floatval($pkgData['length_cm'] ?? 0);
            $pkg_width  = floatval($pkgData['width_cm']  ?? 0);
            $pkg_height = floatval($pkgData['height_cm'] ?? 0);
            $pkg_vol    = ($pkg_length > 0 && $pkg_width > 0 && $pkg_height > 0)
                ? ($pkg_length * $pkg_width * $pkg_height) / $volume_divisor : 0;
            $pkgUpdate = [
                'length_cm'     => $pkg_length,
                'width_cm'      => $pkg_width,
                'height_cm'     => $pkg_height,
                'weight_volume' => round($pkg_vol, 2),
            ];
            if (array_key_exists('weight_actual', $pkgData)) {
                $pkg_w = floatval($pkgData['weight_actual']);
                $pkgUpdate['weight_actual']  = $pkg_w;
                $pkgUpdate['weight_charged'] = round(max($pkg_w, $pkg_vol), 2);
            }
            if (array_key_exists('tracking_cn', $pkgData)) {
                $pkgUpdate['tracking_cn'] = strtoupper(trim($pkgData['tracking_cn']));
            }
            $ToryHub->update_safe("packages", $pkgUpdate, "id = ?", [$pkgId]);
        }
    }

    add_log($getUser['id'], 'edit_order', 'Sửa đơn hàng #' . $id);
    ob_end_clean(); echo json_encode(['status' => 'success', 'msg' => __('Cập nhật thành công')]);
    exit;
}

// ======== DELETE ========
if ($request === 'delete') {
    $id = intval(input_post('id'));
    $order = $ToryHub->get_row_safe("SELECT * FROM `orders` WHERE `id` = ?", [$id]);
    if (!$order) {
        ob_end_clean(); echo json_encode(['status' => 'error', 'msg' => __('Đơn hàng không tồn tại')]);
        exit;
    }

    // Delete uploaded image
    if (!empty($order['product_image']) && strpos($order['product_image'], 'uploads/') === 0) {
        delete_uploaded_file($order['product_image']);
    }

    // Get all packages linked to this order
    $linkedPackages = $ToryHub->get_list_safe(
        "SELECT p.id FROM `packages` p INNER JOIN `package_orders` po ON p.id = po.package_id WHERE po.order_id = ?", [$id]
    );

    foreach ($linkedPackages as $lp) {
        $pkgId = $lp['id'];
        // Check if this package is linked to other orders
        $otherLinks = $ToryHub->num_rows_safe(
            "SELECT po.id FROM `package_orders` po WHERE po.package_id = ? AND po.order_id != ?", [$pkgId, $id]
        );
        if ($otherLinks < 1) {
            // No other orders linked - delete package entirely
            $ToryHub->remove_safe("bag_packages", "package_id = ?", [$pkgId]);
            $ToryHub->remove_safe("package_status_history", "package_id = ?", [$pkgId]);
            $ToryHub->remove_safe("packages", "id = ?", [$pkgId]);
        }
    }

    $ToryHub->remove_safe("package_orders", "order_id = ?", [$id]);
    $ToryHub->remove_safe("order_status_history", "order_id = ?", [$id]);
    $ToryHub->remove_safe("orders", "id = ?", [$id]);

    // Update customer total_orders
    if ($order['customer_id']) {
        $ToryHub->tru_safe("customers", "total_orders", 1, "id = ?", [$order['customer_id']]);
    }

    add_log($getUser['id'], 'delete_order', 'Xóa đơn hàng: ' . $order['order_code']);
    ob_end_clean(); echo json_encode(['status' => 'success', 'msg' => __('Xóa thành công')]);
    exit;
}

ob_end_clean(); echo json_encode(['status' => 'error', 'msg' => 'Invalid request']);
