<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Development driver: writes the message to the application log instead of
 * calling a provider. Lets the whole OTP flow be exercised before any SMS
 * account exists.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $phoneNumber, string $message): bool
    {
        Log::info('[SMS:log driver] to ' . $phoneNumber . ' — ' . $message);

        return true;
    }
}
