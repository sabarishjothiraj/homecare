<?php

namespace App\Services;

use App\Models\OtpVerification;
use App\Models\User;
use App\Services\Otp\OtpMessage;
use App\Services\Otp\OtpNotifier;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Login OTP issuing and verification.
 *
 * Every code is delivered to one owner-controlled destination configured from
 * the admin panel (Settings -> Login OTP), not to the account holder. The
 * owner reads the request details, decides whether to pass the code on, and so
 * authorises the login out of band. It is deliberately not per-user 2FA.
 */
class OtpService
{
    public function __construct(private readonly OtpNotifier $notifier)
    {
    }

    /**
     * Where the active channel would deliver, or null when nothing is set up.
     */
    public function destination(): ?string
    {
        return $this->notifier->destination();
    }

    public function expiryMinutes(): int
    {
        return max(1, (int) config('otp.expiry_minutes', 5));
    }

    public function maxAttempts(): int
    {
        return max(1, (int) config('otp.max_attempts', 5));
    }

    /**
     * Issue an OTP for a user and send it to the configured destination.
     *
     * Returns the OtpVerification on success, or null when nothing is
     * configured or delivery failed - the caller must not hand back an
     * otp_token in either case.
     */
    public function issueFor(User $user, ?string $ipAddress = null): ?OtpVerification
    {
        $destination = $this->destination();

        if ($destination === null) {
            return null;
        }

        // Any earlier unused session for this user is void once a new code is
        // issued, so only the most recent code can ever be redeemed.
        OtpVerification::where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->delete();

        $code = $this->generateCode();

        $verification = OtpVerification::create([
            'user_id' => $user->id,
            'otp_token' => Str::random(64),
            // Stored hashed: a leaked database must not yield live codes.
            'otp_code' => Hash::make($code),
            'attempts' => 0,
            'sent_to' => $destination,
            'expires_at' => now()->addMinutes($this->expiryMinutes()),
        ]);

        $message = new OtpMessage(
            user: $user,
            code: $code,
            expiresInMinutes: $this->expiryMinutes(),
            requestedAt: now(),
            ipAddress: $ipAddress,
        );

        if (!$this->notifier->send($message)) {
            // Nothing was delivered, so the session is unusable - drop it
            // rather than leaving a code nobody can receive.
            $verification->delete();

            return null;
        }

        return $verification;
    }

    /**
     * Check a submitted code against one OTP session.
     *
     * Increments the attempt counter on every wrong code and burns the session
     * once the limit is reached, so a six-digit code cannot be enumerated.
     */
    public function verify(OtpVerification $verification, string $otp): bool
    {
        if ($verification->isConsumed() || $verification->isExpired()) {
            return false;
        }

        if ($verification->attempts >= $this->maxAttempts()) {
            return false;
        }

        if (!$verification->otp_code || !Hash::check(trim($otp), $verification->otp_code)) {
            $verification->increment('attempts');

            if ($verification->fresh()->attempts >= $this->maxAttempts()) {
                $verification->update(['consumed_at' => now()]);
            }

            return false;
        }

        return true;
    }

    /**
     * A zero-padded numeric code of the configured length.
     */
    protected function generateCode(): string
    {
        $length = max(4, min(8, (int) config('otp.length', 6)));

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }
}
