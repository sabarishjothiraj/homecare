<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver
    |--------------------------------------------------------------------------
    |
    | "log"  — writes the message to storage/logs/laravel.log. Use until an
    |          SMS account is live; the OTP flow works end to end.
    | "http" — posts to the provider configured below.
    |
    */
    'driver' => env('SMS_DRIVER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | HTTP driver
    |--------------------------------------------------------------------------
    |
    | Field names differ per provider, so they are configuration rather than
    | code. Example for Fast2SMS:
    |
    |   SMS_ENDPOINT=https://www.fast2sms.com/dev/bulkV2
    |   SMS_METHOD=post
    |   SMS_TO_FIELD=numbers
    |   SMS_MESSAGE_FIELD=message
    |   SMS_AUTH_HEADER=your-api-key
    |
    */
    'http' => [
        'endpoint' => env('SMS_ENDPOINT'),
        'method' => env('SMS_METHOD', 'post'),
        'to_field' => env('SMS_TO_FIELD', 'to'),
        'message_field' => env('SMS_MESSAGE_FIELD', 'message'),
        'timeout' => (int) env('SMS_TIMEOUT', 10),

        // Static parameters every request needs (sender id, route, api key...).
        'params' => array_filter([
            'sender_id' => env('SMS_SENDER_ID'),
            'route' => env('SMS_ROUTE'),
            'api_key' => env('SMS_API_KEY'),
        ], fn ($value) => $value !== null && $value !== ''),

        'headers' => array_filter([
            'authorization' => env('SMS_AUTH_HEADER'),
        ], fn ($value) => $value !== null && $value !== ''),
    ],

];
