<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'razorpay' => [
        'key' => env('RAZORPAY_KEY'),
        'secret' => env('RAZORPAY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        // How long an opened-but-unpaid checkout keeps holding the balance
        // before it is treated as abandoned and released.
        'order_ttl_minutes' => (int) env('RAZORPAY_ORDER_TTL_MINUTES', 30),
        'employee_payment_enabled' => env('EMPLOYEE_PAYMENT_ENABLED', false),
        'employee_payment_amount' => env('EMPLOYEE_PAYMENT_AMOUNT', 1000),
        'employee_payment_validity_months' => env('EMPLOYEE_PAYMENT_VALIDITY_MONTHS', 12),
    ],

];

