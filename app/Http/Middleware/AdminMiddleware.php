<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Session key for the password hash the session was started with. Same
     * name Laravel's AuthenticateSession uses, so the two agree if that
     * middleware is ever added.
     */
    public const PASSWORD_HASH_KEY = 'password_hash_web';

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return redirect()->route('admin.login')
                ->with('error', 'Please login to access the admin panel.');
        }

        if (! auth()->user()->isAdmin()) {
            auth()->logout();

            return redirect()->route('admin.login')
                ->with('error', 'You do not have admin access.');
        }

        if (! auth()->user()->is_active) {
            auth()->logout();

            return redirect()->route('admin.login')
                ->with('error', 'Your account has been deactivated.');
        }

        // A password reset ends every other session of the account: the hash
        // this session started with no longer matches. Sessions live in files,
        // so they cannot be deleted by user id instead.
        $current = (string) auth()->user()->getAuthPassword();
        $started = $request->session()->get(self::PASSWORD_HASH_KEY);

        if ($started === null) {
            $request->session()->put(self::PASSWORD_HASH_KEY, $current);
        } elseif (! hash_equals((string) $started, $current)) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->with('error', 'Your password was changed. Please sign in again.');
        }

        return $next($request);
    }

    /**
     * Record the user's current password hash on this session, so a change
     * made from this session (or a fresh login) does not log it out.
     */
    public static function rememberPasswordHash(Request $request, User $user): void
    {
        $request->session()->put(self::PASSWORD_HASH_KEY, (string) $user->getAuthPassword());
    }
}
