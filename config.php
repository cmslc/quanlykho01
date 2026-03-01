<?php

$config = [
    'project'       => 'ToryHub',
    'version'       => '1.0',
    'max_time_load' => 4,
    'limit_block_login'     => 10,
    'limit_block_ip'        => 10
];

// Shipping rate config
$config_shipping = [
    'road'  => [
        'name'      => 'Đường bộ',
        'name_zh'   => '陆运',
        'rate_per_kg'   => 25000,   // VND per kg
        'min_weight'    => 0.5,
        'days'          => '7-12'
    ],
];

// Shipping rates per cargo type (VND)
$config_shipping_rates = [
    'road' => [
        'easy'      => ['per_kg' => 25000, 'per_cbm' => 6000000],
        'difficult' => ['per_kg' => 35000, 'per_cbm' => 8000000],
    ],
];

// Volume weight divisor (cm³ / divisor = kg)
$config_volume_divisor = 6000;

// Banks for payment
$config_listbank = [
    'Vietcombank'   => 'Ngân hàng TMCP Ngoại Thương Việt Nam',
    'MBBank'        => 'Ngân hàng TMCP Quân đội',
    'ACB'           => 'Ngân hàng TMCP Á Châu',
    'Techcombank'   => 'Ngân hàng TMCP Kỹ thương Việt Nam',
    'TPBank'        => 'Ngân hàng TMCP Tiên Phong',
    'VietinBank'    => 'Ngân hàng TMCP Công thương Việt Nam',
    'BIDV'          => 'Ngân hàng TMCP Đầu tư và Phát triển Việt Nam',
    'Agribank'      => 'Ngân hàng Nông nghiệp và Phát triển Nông thôn',
    'Sacombank'     => 'Ngân hàng TMCP Sài Gòn Thương Tín',
    'VPBank'        => 'Ngân hàng TMCP Việt Nam Thịnh Vượng'
];

// Chinese payment methods
$config_listpay_cn = [
    'Alipay'    => '支付宝 Alipay',
    'WeChat'    => '微信支付 WeChat Pay',
    'UnionPay'  => '银联 UnionPay'
];

$ip_server_black = [];
