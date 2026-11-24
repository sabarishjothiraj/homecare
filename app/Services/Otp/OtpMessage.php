<?php

namespace App\Services\Otp;

use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Everything a channel needs to render one OTP.
 *
 * The code alone is not enough. The owner receiving it has to decide whether
 * to pass it on, and they can only do that if they can see who is asking,
 * when, and from where - otherwise the approval step can only ever say yes.
 */
class OtpMessage
{
    public function __construct(
        public readonly User $user,
        public readonly string $code,
        public readonly int $expiresInMinutes,
        public readonly CarbonInterface $requestedAt,
        public readonly ?string $ipAddress = null,
    ) {}

    /**
     * Best available display name for the person logging in.
     */
    public function requesterName(): string
    {
        return $this->user->name ?: ($this->user->username ?: $this->user->email);
    }

    /**
     * Single-line rendering, for channels with no formatting (SMS, log).
     */
    public function toPlainText(): string
    {
        return sprintf(
            '%s (%s) is signing in to %s at %s%s. OTP: %s (valid %d min). Share it only if you recognise this request.',
            $this->requesterName(),
            $this->user->email,
            config('app.name', 'Home Nursing'),
            $this->requestedAt->format('d M Y, g:i A'),
            $this->ipAddress ? ' from '.$this->ipAddress : '',
            $this->code,
            $this->expiresInMinutes,
        );
    }
}
