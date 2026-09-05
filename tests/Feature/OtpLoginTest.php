<?php

namespace Tests\Feature;

use App\Models\Config;
use App\Models\OtpVerification;
use App\Models\User;
use App\Services\Otp\OtpMessage;
use App\Services\Otp\OtpNotifier;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/**
 * The owner-approval login gate.
 *
 * A worker signs in with a password; the code goes to one owner-controlled
 * destination, and the owner decides whether to pass it on. These tests cover
 * the parts that make that gate real rather than decorative.
 */
class OtpLoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Captures what was sent instead of delivering it, so the code and the
     * request details can both be asserted.
     */
    private function captureNotifier(?string $destination = 'owner@example.com'): object
    {
        $spy = new class($destination) implements OtpNotifier
        {
            public ?OtpMessage $sent = null;

            public bool $shouldSucceed = true;

            public function __construct(private readonly ?string $destination) {}

            public function destination(): ?string
            {
                return $this->destination;
            }

            public function send(OtpMessage $message): bool
            {
                $this->sent = $message;

                return $this->shouldSucceed;
            }
        };

        $this->app->instance(OtpNotifier::class, $spy);

        return $spy;
    }

    private function worker(): User
    {
        return User::factory()->create([
            'email' => 'worker@example.com',
            'role' => 'admin',
        ]);
    }

    public function test_issuing_an_otp_stores_it_hashed_rather_than_in_plain_text(): void
    {
        $spy = $this->captureNotifier();
        $user = $this->worker();

        $verification = app(OtpService::class)->issueFor($user);

        $this->assertNotNull($verification);
        $code = $spy->sent->code;

        $this->assertNotSame($code, $verification->otp_code);
        $this->assertStringStartsWith('$2y$', $verification->otp_code);
        $this->assertTrue(password_verify($code, $verification->otp_code));
    }

    /**
     * The owner can only refuse a request they can identify, so the message
     * has to carry who is asking, not just the code.
     */
    public function test_the_delivered_message_identifies_the_requester(): void
    {
        $spy = $this->captureNotifier();
        $user = $this->worker();

        app(OtpService::class)->issueFor($user, '203.0.113.9');

        $text = $spy->sent->toPlainText();
        $this->assertStringContainsString($user->name, $text);
        $this->assertStringContainsString('worker@example.com', $text);
        $this->assertStringContainsString('203.0.113.9', $text);
        $this->assertStringContainsString($spy->sent->code, $text);
    }

    public function test_the_correct_code_verifies(): void
    {
        $spy = $this->captureNotifier();
        $verification = app(OtpService::class)->issueFor($this->worker());

        $this->assertTrue(app(OtpService::class)->verify($verification, $spy->sent->code));
    }

    public function test_a_wrong_code_is_rejected_and_counted(): void
    {
        $this->captureNotifier();
        $verification = app(OtpService::class)->issueFor($this->worker());

        $this->assertFalse(app(OtpService::class)->verify($verification, '000000'));
        $this->assertSame(1, $verification->fresh()->attempts);
    }

    public function test_the_session_is_burned_after_too_many_wrong_codes(): void
    {
        $this->captureNotifier();
        $service = app(OtpService::class);
        $verification = $service->issueFor($this->worker());

        for ($i = 0; $i < $service->maxAttempts(); $i++) {
            $service->verify($verification->fresh(), '000000');
        }

        $this->assertTrue($verification->fresh()->isConsumed());
        $this->assertSame($service->maxAttempts(), $verification->fresh()->attempts);
    }

    public function test_a_burned_session_rejects_even_the_correct_code(): void
    {
        $spy = $this->captureNotifier();
        $service = app(OtpService::class);
        $verification = $service->issueFor($this->worker());
        $correct = $spy->sent->code;

        for ($i = 0; $i < $service->maxAttempts(); $i++) {
            $service->verify($verification->fresh(), '000000');
        }

        $this->assertFalse($service->verify($verification->fresh(), $correct));
    }

    public function test_an_expired_code_is_rejected(): void
    {
        $spy = $this->captureNotifier();
        $service = app(OtpService::class);
        $verification = $service->issueFor($this->worker());

        $verification->update(['expires_at' => now()->subMinute()]);

        $this->assertFalse($service->verify($verification->fresh(), $spy->sent->code));
    }

    /**
     * Issuing a fresh code must invalidate the previous one, or an old code
     * left on the owner's phone would stay usable.
     */
    public function test_issuing_a_new_code_invalidates_the_previous_one(): void
    {
        $spy = $this->captureNotifier();
        $service = app(OtpService::class);
        $user = $this->worker();

        $first = $service->issueFor($user);
        $firstCode = $spy->sent->code;

        $service->issueFor($user);

        $this->assertDatabaseMissing('otp_verifications', ['id' => $first->id]);
        $this->assertSame(1, OtpVerification::where('user_id', $user->id)->count());
        $this->assertNotSame($firstCode, $spy->sent->code);
    }

    public function test_no_session_is_created_when_no_destination_is_configured(): void
    {
        $this->captureNotifier(destination: null);

        $this->assertNull(app(OtpService::class)->issueFor($this->worker()));
        $this->assertDatabaseCount('otp_verifications', 0);
    }

    /**
     * A delivery failure must not leave a code nobody can ever receive.
     */
    public function test_a_failed_delivery_leaves_no_orphan_session(): void
    {
        $spy = $this->captureNotifier();
        $spy->shouldSucceed = false;

        $this->assertNull(app(OtpService::class)->issueFor($this->worker()));
        $this->assertDatabaseCount('otp_verifications', 0);
    }

    public function test_login_refuses_when_delivery_is_not_configured(): void
    {
        $this->captureNotifier(destination: null);
        $this->worker();

        $this->withoutMiddleware(ThrottleRequests::class)
            ->postJson('/api/login', [
                'email' => 'worker@example.com',
                'password' => 'password',
            ])
            ->assertStatus(503)
            ->assertJsonMissingPath('otp_token');
    }

    public function test_a_full_login_exchanges_the_emailed_code_for_a_token(): void
    {
        $spy = $this->captureNotifier();
        $this->worker();

        $start = $this->withoutMiddleware(ThrottleRequests::class)
            ->postJson('/api/login', [
                'email' => 'worker@example.com',
                'password' => 'password',
            ])
            ->assertOk()
            ->assertJson(['requires_otp' => true]);

        // The plaintext code must never travel back to the client.
        $start->assertJsonMissingPath('otp')->assertJsonMissingPath('otp_hint');

        $this->withoutMiddleware(ThrottleRequests::class)
            ->postJson('/api/verify-otp', [
                'otp_token' => $start->json('otp_token'),
                'otp' => $spy->sent->code,
            ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email']]);
    }

    public function test_a_deactivated_worker_cannot_start_a_login(): void
    {
        $this->captureNotifier();
        User::factory()->inactive()->create(['email' => 'gone@example.com']);

        $this->withoutMiddleware(ThrottleRequests::class)
            ->postJson('/api/login', [
                'email' => 'gone@example.com',
                'password' => 'password',
            ])
            ->assertStatus(403);

        $this->assertDatabaseCount('otp_verifications', 0);
    }

    public function test_the_approver_email_is_read_from_admin_configuration(): void
    {
        Config::setValue('otp_email', 'owner@homenursing.test');

        $this->assertSame(
            'owner@homenursing.test',
            app(\App\Services\Otp\MailOtpNotifier::class)->destination()
        );
    }

    public function test_an_invalid_configured_email_counts_as_unconfigured(): void
    {
        Config::setValue('otp_email', 'not-an-email');

        $this->assertNull(app(\App\Services\Otp\MailOtpNotifier::class)->destination());
    }
}
