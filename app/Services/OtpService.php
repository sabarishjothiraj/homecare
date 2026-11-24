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
    public function __construct(private readonly OtpNotifier $notifier) {}

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

        if (! $this->notifier->send($message)) {
            // Nothing was delivered, so the session is unusable - drop it
            // rather than leaving a code nobody can receive.
            $verification->delete();

            return null;
        }

        return $verification;
    }

    /**
     * Check a submitted code against one OTP session, and redeem it on success.
     *
     * Every submission uses up one attempt, and the session is burned once the
     * limit is reached, so a six-digit code cannot be enumerated. A true
     * result means this call consumed the session: the caller may log in.
     *
     * Each step is a conditional UPDATE rather than a check on the loaded
     * model, so parallel requests cannot all read attempts = 0 and get a full
     * set of guesses each, or both redeem the same correct code.
     */
    public function verify(OtpVerification $verification, string $otp): bool
    {
        $session = fn () => OtpVerification::query()->whereKey($verification->getKey());

        $claimed = $session()
            ->whereNull('consumed_at')
            ->where('attempts', '<', $this->maxAttempts())
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->increment('attempts');

        if ($claimed !== 1) {
            return false;
        }

        if (! $verification->otp_code || ! Hash::check(trim($otp), $verification->otp_code)) {
            $session()
                ->whereNull('consumed_at')
                ->where('attempts', '>=', $this->maxAttempts())
                ->update(['consumed_at' => now()]);

            return false;
        }

        return $session()
            ->whereNull('consumed_at')
            ->update(['verified_at' => now(), 'consumed_at' => now()]) === 1;
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
