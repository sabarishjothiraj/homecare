<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Log;

/**
 * Development channel: writes the OTP to the application log so the whole
 * flow can be exercised with no mail server or SMS account.
 */
class LogOtpNotifier implements OtpNotifier
{
    public function destination(): ?string
    {
        return 'application log';
    }

    public function send(OtpMessage $message): bool
    {
        Log::info('[OTP:log channel] '.$message->toPlainText());

        return true;
    }
}
