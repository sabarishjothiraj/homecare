<?php

namespace App\Services\Otp;

use App\Models\Config;
use App\Services\Sms\SmsSender;

/**
 * Texts the OTP to the single owner phone number configured in the admin
 * panel. The transport itself lives behind SmsSender, so the provider is a
 * config change rather than a code change.
 */
class SmsOtpNotifier implements OtpNotifier
{
    public const CONFIG_KEY = 'otp_phone_number';

    public function __construct(private readonly SmsSender $sms)
    {
    }

    public function destination(): ?string
    {
        $number = trim((string) Config::getValue(self::CONFIG_KEY, ''));

        return $number === '' ? null : $number;
    }

    public function send(OtpMessage $message): bool
    {
        $to = $this->destination();

        if ($to === null) {
            return false;
        }

        return $this->sms->send($to, $message->toPlainText());
    }
}
