<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" location.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/admin/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // OTP endpoints. The generic api limiter (60/min) is far too loose
        // for issuing codes to one phone or for guessing a six-digit code.
        RateLimiter::for('otp', function (Request $request) {
            $limits = [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perMinute(10)->by('otp-token:'.$request->input('otp_token', $request->ip())),
            ];

            // Password guesses against one account, from any number of
            // addresses. The username route has no second factor and its
            // account name ("superadmin") is predictable.
            if (($account = $this->loginIdentifier($request, 'username', 'email')) !== '') {
                $limits[] = Limit::perHour(20)->by('account:'.$account);
            }

            return $limits;
        });

        // Admin login. Keyed on the submitted email *and* the IP so that
        // hammering one account from one host cannot lock its real owner out,
        // while a single host still cannot spray many accounts. The looser
        // per-account cap stops a spread-out attack: super admins sign in
        // here with a password alone. It can be used to lock an account out
        // for up to an hour, which is the price of not allowing unlimited guesses.
        RateLimiter::for('admin-login', function (Request $request) {
            $email = $this->loginIdentifier($request, 'email');

            $limits = [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];

            if ($email !== '') {
                $limits[] = Limit::perHour(20)->by('account:'.$email);
            }

            return $limits;
        });

        // Second step of the admin login. Keyed on the pending login held in
        // the session as well as the IP, so one pending code cannot be
        // brute-forced from many addresses.
        RateLimiter::for('admin-otp', function (Request $request) {
            return [
                Limit::perMinute(5)->by('admin-otp:'.$request->session()->get('admin_login.user_id', $request->ip())),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        // Public contact and join forms. Loose enough for a visitor who fixes
        // a validation error and resubmits, tight enough to stop flooding.
        // Each limit needs its own key: Laravel stores a limiter's limits under
        // md5(name.key), so two limits keyed on the bare IP would share one
        // counter and the daily cap would reset every minute.
        RateLimiter::for('public-forms', function (Request $request) {
            // A visitor gets the form back with a message, not a bare 429 page.
            $tooMany = fn (Request $request, array $headers) => back()
                ->withInput()
                ->with('error', 'You have sent several messages in a short time. Please wait a few minutes and try again.')
                ->withHeaders($headers);

            return [
                Limit::perMinute(5)->by('minute:'.$request->ip())->response($tooMany),
                Limit::perDay(30)->by('day:'.$request->ip())->response($tooMany),
            ];
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * The first non-empty login name among the given fields, lower-cased.
     *
     * Ignores non-string input, so a crafted array cannot break the limiter.
     */
    private function loginIdentifier(Request $request, string ...$fields): string
    {
        foreach ($fields as $field) {
            $value = $request->input($field);

            if (is_string($value) && trim($value) !== '') {
                return strtolower(trim($value));
            }
        }

        return '';
    }
}
