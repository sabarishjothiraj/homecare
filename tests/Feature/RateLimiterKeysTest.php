<?php

namespace Tests\Feature;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * The named rate limiters in RouteServiceProvider.
 *
 * Laravel counts each limit under md5(limiter name . key). Two limits of one
 * limiter with the same key therefore share a counter: every request counts
 * twice, and the longer window resets with the shorter one.
 */
class RateLimiterKeysTest extends TestCase
{
    private function request(): Request
    {
        $request = Request::create('/any', 'POST', [
            'email' => 'Someone@Example.com',
            'username' => 'superadmin',
            'otp_token' => 'token-value',
        ], [], [], ['REMOTE_ADDR' => '203.0.113.5']);

        $request->setLaravelSession($this->app['session.store']);

        return $request;
    }

    public function test_every_limit_of_a_limiter_has_its_own_counter(): void
    {
        foreach (['otp', 'admin-login', 'admin-otp', 'public-forms'] as $name) {
            $keys = collect(RateLimiter::limiter($name)($this->request()))->pluck('key');

            $this->assertGreaterThan(1, $keys->count(), $name);
            $this->assertSame($keys->count(), $keys->unique()->count(), "Limiter '{$name}' reuses a key: ".$keys->implode(', '));
        }
    }

    public function test_login_limiters_count_per_account_whatever_the_case(): void
    {
        $request = $this->request();
        $request->merge(['email' => 'someone@example.com']);

        $lower = collect(RateLimiter::limiter('admin-login')($request))->pluck('key');
        $mixed = collect(RateLimiter::limiter('admin-login')($this->request()))->pluck('key');

        $this->assertContains('account:someone@example.com', $mixed->all());
        $this->assertSame($lower->all(), $mixed->all());
    }

    public function test_the_public_forms_limit_sends_the_visitor_back_with_a_message(): void
    {
        foreach (RateLimiter::limiter('public-forms')($this->request()) as $limit) {
            $response = ($limit->responseCallback)($this->request(), ['Retry-After' => 60]);

            $this->assertInstanceOf(RedirectResponse::class, $response);
            $this->assertSame('60', $response->headers->get('Retry-After'));
            $this->assertStringContainsString('Please wait a few minutes', session('error'));
        }
    }
}
