<?php
/**
 * API Upload endpoint
 * POST /api/v1/upload.php - Upload ảnh
 */

require_once(__DIR__.'/bootstrap.php');
require_once(__DIR__.'/jwt.php');

$user = api_auth();
api_method('POST');

if (empty($_FILES['image'])) {
    api_error('Không có file upload');
}

$file = $_FILES['image'];
$type = $_POST['type'] ?? 'order';
$ref_id = intval($_POST['ref_id'] ?? 0);

// Validate file
$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file['type'], $allowed)) {
    api_error('Chỉ chấp nhận file ảnh (jpg, png, gif, webp)');
}

$maxSize = 10 * 1024 * 1024; // 10MB
if ($file['size'] > $maxSize) {
    api_error('File quá lớn (tối đa 10MB)');
}

// Create upload directory
$uploadDir = __DIR__ . '/../../uploads/' . $type . 's/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
$filename = $type . '_' . $ref_id . '_' . date('ymd_His') . '_' . substr(uniqid(), -4) . '.' . $ext;

// Move file
$filepath = $uploadDir . $filename;
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    api_error('Lỗi lưu file');
}

$relativePath = 'uploads/' . $type . 's/' . $filename;

// Save to database if order image
if ($type === 'order' && $ref_id > 0) {
    $order = $ToryHub->get_row_safe("SELECT `product_image` FROM `orders` WHERE `id` = ?", [$ref_id]);
    if ($order) {
        $existingImages = $order['product_image'] ? json_decode($order['product_image'], true) : [];
        if (!is_array($existingImages)) $existingImages = $order['product_image'] ? [$order['product_image']] : [];
        $existingImages[] = $relativePath;
        $ToryHub->update_safe('orders', [
            'product_image' => json_encode($existingImages),
            'update_date' => gettime()
        ], "`id` = ?", [$ref_id]);
    }
}

api_success([
    'url' => $relativePath,
    'filename' => $filename
], 'Upload thành công');
