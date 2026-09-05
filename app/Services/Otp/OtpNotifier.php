<?php

namespace App\Services\Otp;

/**
 * A delivery channel for login OTPs.
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
