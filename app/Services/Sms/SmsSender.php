<?php

namespace App\Services\Sms;

interface SmsSender
{
    /**
     * Deliver a message to a single phone number.
     *
     * Implementations must not throw on provider failure — return false and
     * log instead, so a delivery outage surfaces as a clean "could not send
     * the code" rather than a 500 in the middle of a login.
     */
    public function send(string $phoneNumber, string $message): bool;
}
