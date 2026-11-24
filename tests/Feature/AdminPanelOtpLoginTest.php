<?php

namespace Tests\Feature;

use App\Models\OtpVerification;
use App\Models\User;
use App\Services\Otp\OtpMessage;
use App\Services\Otp\OtpNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/**
 * The owner-approval gate on the admin panel's own login form.
 *
 * A correct password is only the first step for staff: the session is not
 * logged in until the code the owner received is entered.
 */
class AdminPanelOtpLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function captureNotifier(?string $destination = 'owner@example.com'): object
    {
        $spy = new class($destination) implements OtpNotifier
        {
            public ?OtpMessage $sent = null;

            public function __construct(private readonly ?string $destination) {}

            public function destination(): ?string
            {
                return $this->destination;
            }

            public function send(OtpMessage $message): bool
            {
                $this->sent = $message;

                return true;
            }
        };

        $this->app->instance(OtpNotifier::class, $spy);

        return $spy;
    }

    private function staff(array $attributes = []): User
    {
        return User::factory()->create($attributes + [
            'email' => 'staff@example.com',
            'role' => 'admin',
        ]);
    }

    private function submitPassword(string $email = 'staff@example.com', string $password = 'password')
    {
        return $this->post(route('admin.login.post'), [
            'email' => $email,
            'password' => $password,
        ]);
    }

    public function test_a_staff_password_alone_does_not_log_in(): void
    {
        $spy = $this->captureNotifier();
        $staff = $this->staff();

        $this->submitPassword()->assertRedirect(route('admin.login.otp'));

        $this->assertGuest();
        $this->assertNotNull($spy->sent, 'The owner should have been sent a code.');
        $this->assertSame($staff->id, $spy->sent->user->id);
        $this->assertSame($staff->id, session('admin_login.user_id'));
    }

    public function test_the_correct_code_logs_the_staff_member_in(): void
    {
        $spy = $this->captureNotifier();
        $staff = $this->staff();

        $this->submitPassword();

        $this->get(route('admin.login.otp'))->assertOk()->assertSee('Login Code');

        $this->post(route('admin.login.otp.verify'), ['otp' => $spy->sent->code])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($staff);
        $this->assertNull(session('admin_login'));
        $this->assertTrue(OtpVerification::where('user_id', $staff->id)->first()->isConsumed());
    }

    public function test_a_wrong_code_does_not_log_in(): void
    {
        $this->captureNotifier();
        $this->staff();

        $this->submitPassword();

        $this->from(route('admin.login.otp'))
            ->post(route('admin.login.otp.verify'), ['otp' => '000000'])
            ->assertRedirect(route('admin.login.otp'))
            ->assertSessionHasErrors('otp');

        $this->assertGuest();
    }

    public function test_too_many_wrong_codes_end_the_pending_login(): void
    {
        $spy = $this->captureNotifier();
        $this->staff();

        $this->submitPassword();

        foreach (range(1, config('otp.max_attempts')) as $ignored) {
            $this->post(route('admin.login.otp.verify'), ['otp' => '000000']);
        }

        // Even the right code is refused now, and the user starts over.
        $this->post(route('admin.login.otp.verify'), ['otp' => $spy->sent->code])
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
        $this->assertNull(session('admin_login'));
    }

    public function test_the_code_form_needs_a_pending_login(): void
    {
        $this->get(route('admin.login.otp'))->assertRedirect(route('admin.login'));

        $this->post(route('admin.login.otp.verify'), ['otp' => '123456'])
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_a_super_admin_logs_in_directly(): void
    {
        $spy = $this->captureNotifier();
        $owner = User::factory()->superAdmin()->create(['email' => 'owner@example.com']);

        $this->submitPassword('owner@example.com')->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($owner);
        $this->assertNull($spy->sent);
        $this->assertDatabaseCount('otp_verifications', 0);
    }

    public function test_an_inactive_user_is_refused_before_any_code_is_sent(): void
    {
        $spy = $this->captureNotifier();
        $this->staff(['is_active' => false]);

        $this->from(route('admin.login'))
            ->submitPassword()
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertNull($spy->sent);
    }

    public function test_a_wrong_password_is_refused(): void
    {
        $spy = $this->captureNotifier();
        $this->staff();

        $this->from(route('admin.login'))
            ->submitPassword(password: 'not-the-password')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertNull($spy->sent);
    }

    /**
     * The Settings page promises that blanking the approver blocks staff
     * logins; the panel has to keep that promise.
     */
    public function test_staff_cannot_log_in_when_no_approver_is_configured(): void
    {
        $this->captureNotifier(destination: null);
        $this->staff();

        $this->from(route('admin.login'))
            ->submitPassword()
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseCount('otp_verifications', 0);
    }
}
