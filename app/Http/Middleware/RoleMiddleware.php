<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Tokens of deactivated accounts are already refused at authentication
        // (AuthServiceProvider); this keeps the guard safe on its own.
        if (! $user->is_active) {
            return response()->json(['message' => 'Your account is deactivated.'], 403);
        }

        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'Forbidden. Required role missing.'], 403);
    }
}
