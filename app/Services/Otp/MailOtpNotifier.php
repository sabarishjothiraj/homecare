<?php

namespace App\Services\Otp;

use App\Mail\OtpMail;
use App\Models\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails the OTP to the single owner address configured in the admin panel.
 */
class MailOtpNotifier implements OtpNotifier
{
    public const CONFIG_KEY = 'otp_email';

    public function destination(): ?string
    {
        $email = trim((string) Config::getValue(self::CONFIG_KEY, ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }

    public function send(OtpMessage $message): bool
    {
        $to = $this->destination();

        if ($to === null) {
            return false;
        }

        try {
            Mail::to($to)->send(new OtpMail($message));

            return true;
        } catch (\Throwable $e) {
            // A dead SMTP host must surface as "could not send the code",
            // never as a 500 halfway through authenticating.
            Log::error('[OTP] Email delivery failed: ' . $e->getMessage(), ['exception' => $e]);

            return false;
        }
    }
}
