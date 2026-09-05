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
            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perMinute(10)->by('otp-token:' . $request->input('otp_token', $request->ip())),
            ];
        });

        // Admin login. Keyed on the submitted email *and* the IP so that
        // hammering one account cannot lock its real owner out from elsewhere,
        // while a single host still cannot spray many accounts.
        RateLimiter::for('admin-login', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by($email . '|' . $request->ip()),
                Limit::perMinute(20)->by($request->ip()),
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
}

