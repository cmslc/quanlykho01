<?php
define('IN_SITE', true);
require_once(__DIR__.'/../libs/db.php');

$ToryHub = new DB();
$ToryHub->connect();

$results = [];

// 1. Add product_type column
$check = $ToryHub->query("SHOW COLUMNS FROM `orders` LIKE 'product_type'");
if ($check && $check->num_rows == 0) {
    $r = $ToryHub->query("ALTER TABLE `orders` ADD COLUMN `product_type` ENUM('retail','wholesale') NOT NULL DEFAULT 'retail' AFTER `order_type`");
    $results[] = $r ? '[OK] Added column product_type' : '[FAIL] product_type: ' . mysqli_error($ToryHub->ketnoi);
} else {
    $results[] = '[SKIP] Column product_type already exists';
}

// 2. Add product_code column
$check = $ToryHub->query("SHOW COLUMNS FROM `orders` LIKE 'product_code'");
if ($check && $check->num_rows == 0) {
    $r = $ToryHub->query("ALTER TABLE `orders` ADD COLUMN `product_code` VARCHAR(100) DEFAULT NULL AFTER `product_type`");
    $results[] = $r ? '[OK] Added column product_code' : '[FAIL] product_code: ' . mysqli_error($ToryHub->ketnoi);
} else {
    $results[] = '[SKIP] Column product_code already exists';
}

// 3. Modify customer_id to allow NULL
$check = $ToryHub->query("SHOW COLUMNS FROM `orders` LIKE 'customer_id'");
if ($check && $row = $check->fetch_assoc()) {
    if ($row['Null'] === 'NO') {
        $ToryHub->query("SET FOREIGN_KEY_CHECKS = 0");
        $r = $ToryHub->query("ALTER TABLE `orders` MODIFY COLUMN `customer_id` INT DEFAULT NULL");
        $ToryHub->query("SET FOREIGN_KEY_CHECKS = 1");
        $results[] = $r ? '[OK] Modified customer_id to allow NULL' : '[FAIL] customer_id: ' . mysqli_error($ToryHub->ketnoi);
    } else {
        $results[] = '[SKIP] customer_id already allows NULL';
    }
}

// 4. Add cargo_type column
$check = $ToryHub->query("SHOW COLUMNS FROM `orders` LIKE 'cargo_type'");
if ($check && $check->num_rows == 0) {
    $r = $ToryHub->query("ALTER TABLE `orders` ADD COLUMN `cargo_type` VARCHAR(20) DEFAULT NULL AFTER `product_type`");
    $results[] = $r ? '[OK] Added column cargo_type' : '[FAIL] cargo_type: ' . mysqli_error($ToryHub->ketnoi);
} else {
    $results[] = '[SKIP] Column cargo_type already exists';
}

echo "<h2>Migration Results</h2><pre>" . implode("\n", $results) . "</pre>";
echo "<br><a href='javascript:history.back()'>Back</a>";
