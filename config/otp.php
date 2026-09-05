<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OTP
    |--------------------------------------------------------------------------
    |
    | The destination is NOT configured here - it is set from the admin panel
    | (Settings -> Login OTP) and stored in the configs table, so it can be
    | changed without a deploy.
    |
    */

    /*
    | Delivery channel: "mail", "sms", or "log" (writes to laravel.log).
    */
    'channel' => env('OTP_CHANNEL', 'mail'),

    'length' => (int) env('OTP_LENGTH', 6),

    'expiry_minutes' => (int) env('OTP_EXPIRY_MINUTES', 5),

    // Wrong codes allowed against one OTP session before it is burned.
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
];
