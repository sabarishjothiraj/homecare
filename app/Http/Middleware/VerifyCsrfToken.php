<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Both endpoints are called by Razorpay, not by a browser holding a
        // session token. Each authenticates itself instead: the webhook by its
        // body HMAC, the callback by the per-payment signature it carries.
        'api/razorpay/webhook',
        'api/razorpay/callback',
    ];
}
