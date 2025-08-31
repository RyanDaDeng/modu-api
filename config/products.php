<?php

return [
    'products' => [
        'monthly' => [
            'name' => 'VIP月卡',
            'price' => 29,
            'decimal_price' => 3900,  // price * 100 for payment gateway
            'original_price' => 39,
            'value' => 30,
            'type' => 'vip',
            'days' => 30
        ],
        'quarterly' => [
            'name' => 'VIP季卡',
            'price' => 69,
            'decimal_price' => 9900,  // price * 100 for payment gateway
            'original_price' => 99,
            'value' => 90,
            'type' => 'vip',
            'days' => 90
        ],
        'yearly' => [
            'name' => 'VIP年卡',
            'price' => 239,
            'decimal_price' => 29900,  // price * 100 for payment gateway
            'original_price' => 360,
            'value' => 365,
            'type' => 'vip',
            'days' => 365
        ]
    ]
];
