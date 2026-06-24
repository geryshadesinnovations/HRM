<?php

declare(strict_types=1);

return [
    // Active gateway; switchable without code changes. See docs/04-BILLING.md.
    'default_gateway' => env('BILLING_DEFAULT_GATEWAY', 'razorpay'),

    'tax' => [
        'country' => env('TAX_COUNTRY', 'IN'),
        'company_gstin' => env('COMPANY_GSTIN'),
        'gst_rate' => 18.0,
    ],

    'gateways' => [
        'razorpay' => [
            'key_id' => env('RAZORPAY_KEY_ID'),
            'key_secret' => env('RAZORPAY_KEY_SECRET'),
            'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        ],
        'cashfree' => [
            'app_id' => env('CASHFREE_APP_ID'),
            'secret' => env('CASHFREE_SECRET'),
            'webhook_secret' => env('CASHFREE_WEBHOOK_SECRET'),
        ],
        'payu' => [
            'merchant_key' => env('PAYU_MERCHANT_KEY'),
            'salt' => env('PAYU_SALT'),
        ],
    ],
];
