<?php

$config = [
    'project'       => 'CMS01',
    'version'       => '1.0',
    'max_time_load' => 4,
    'limit_block_login'     => 10,
    'limit_block_ip'        => 10
];

// Shipping rate config (CNY per kg)
$config_shipping = [
    'road'  => [
        'name'      => 'Đường bộ',
        'name_zh'   => '陆运',
        'rate_per_kg'   => 25000,   // VND per kg
        'min_weight'    => 0.5,
        'days'          => '7-12'
    ],
    'sea'   => [
        'name'      => 'Đường biển',
        'name_zh'   => '海运',
        'rate_per_kg'   => 15000,   // VND per kg
        'min_weight'    => 1,
        'days'          => '15-25'
    ],
    'air'   => [
        'name'      => 'Đường bay',
        'name_zh'   => '空运',
        'rate_per_kg'   => 120000,  // VND per kg
        'min_weight'    => 0.1,
        'days'          => '3-5'
    ]
];

// Shipping rates per cargo type (VND)
$config_shipping_rates = [
    'road' => [
        'easy'      => ['per_kg' => 25000, 'per_cbm' => 6000000],
        'difficult' => ['per_kg' => 35000, 'per_cbm' => 8000000],
    ],
    'sea' => [
        'easy'      => ['per_kg' => 15000, 'per_cbm' => 3500000],
        'difficult' => ['per_kg' => 20000, 'per_cbm' => 5000000],
    ],
    'air' => [
        'easy'      => ['per_kg' => 120000, 'per_cbm' => 25000000],
        'difficult' => ['per_kg' => 150000, 'per_cbm' => 30000000],
    ],
];

// Volume weight divisor (cm³ / divisor = kg)
$config_volume_divisor = 6000;

// Service fee percentage for buying agent
$config_service_fee_percent = 3; // 3% of product value

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
