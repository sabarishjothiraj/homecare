<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // An API token authenticates only while its owner is active, so
        // deactivating an account cuts off the API at once rather than when
        // the token expires.
        Sanctum::authenticateAccessTokensUsing(
            fn ($token, bool $isValid) => $isValid && (bool) $token->tokenable?->is_active
        );
    }
}
