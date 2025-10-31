<?php

return [
    'products' => [
        'monthly' => [
            'name' => '漫画VIP月卡',
            'price' => 39,
            'decimal_price' => 3900,  // price * 100 for payment gateway
            'original_price' => 39,
            'value' => 30,
            'type' => 'vip',
            'days' => 30
        ],
        'quarterly' => [
            'name' => '漫画VIP季卡',
            'price' => 99,
            'decimal_price' => 9900,  // price * 100 for payment gateway
            'original_price' => 117,
            'value' => 90,
            'type' => 'vip',
            'days' => 90
        ],
        'yearly' => [
            'name' => '漫画VIP年卡',
            'price' => 299,
            'decimal_price' => 29900,  // price * 100 for payment gateway
            'original_price' => 468,
            'value' => 365,
            'type' => 'vip',
            'days' => 365
        ],
        'monthly-video' => [
            'name' => '视频VIP月卡',
            'price' => 50,
            'decimal_price' => 5000,  // price * 100 for payment gateway
            'original_price' => 50,
            'value' => 30,
            'type' => 'video-vip',
            'days' => 30
        ],
        'quarterly-video' => [
            'name' => '视频VIP季卡',
            'price' => 99,
            'decimal_price' => 9900,  // price * 100 for payment gateway
            'original_price' => 117,
            'value' => 90,
            'type' => 'video-vip',
            'days' => 90
        ],
        'yearly-video' => [
            'name' => '视频VIP年卡',
            'price' => 299,
            'decimal_price' => 29900,  // price * 100 for payment gateway
            'original_price' => 468,
            'value' => 365,
            'type' => 'video-vip',
            'days' => 365
        ]
    ]
];
