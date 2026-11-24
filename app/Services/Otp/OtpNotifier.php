<?php

namespace App\Services\Otp;

/**
 * A delivery channel for login OTPs.
 *
 * Delivery is two layers, deliberately separate:
 *
 *   channel   (this interface)      mail | sms | log   - chosen by OTP_CHANNEL
 *   transport (Sms\SmsSender)       http | log         - chosen by SMS_DRIVER,
 *                                                        used only by the sms
 *                                                        channel
 *
 * A channel owns where the message goes and what it looks like; a transport
 * owns how bytes reach a provider. That split is why "log" exists at both
 * layers and means different things: OTP_CHANNEL=log skips delivery entirely,
 * while OTP_CHANNEL=sms with SMS_DRIVER=log exercises the SMS channel without
 * a provider account.
 *
 * Each channel owns its own destination lookup, so adding Telegram later means
 * adding one class rather than teaching OtpService about a new address type.
 */
interface OtpNotifier
{
    /**
     * Where this channel would deliver to, or null when not configured.
     *
     * Used to tell "nobody set this up" apart from "delivery failed", which
     * are different messages to the person trying to log in.
     */
    public function destination(): ?string;

    /**
     * Deliver the OTP. Must return false rather than throw on failure, so an
     * outage never becomes a 500 in the middle of a login.
     */
    public function send(OtpMessage $message): bool;
}
