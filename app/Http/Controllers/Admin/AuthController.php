<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AdminMiddleware;
use App\Models\OtpVerification;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Session key holding a login whose password was right but whose
     * owner-approval code has not been entered yet.
     */
    private const PENDING = 'admin_login';

    public function __construct(private readonly OtpService $otpService) {}

    /**
     * Show login form
     */
    public function showLogin()
    {
        return view('admin.auth.login');
    }

    /**
     * Handle login request
     *
     * A correct password alone logs in only a super admin - the owner the
     * approval code would otherwise be sent to. Everyone else gets a code
     * issued to the owner's destination and is logged in by verifyOtp().
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Checks the password without logging in: nothing may be
        // authenticated before the approval step.
        $user = User::findByCredentials('email', $request->input('email'), $request->input('password'));

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account is deactivated.'],
            ]);
        }

        if (! $user->isAdmin()) {
            throw ValidationException::withMessages([
                'email' => ['You do not have admin access.'],
            ]);
        }

        $remember = $request->filled('remember');

        if ($user->isSuperAdmin()) {
            return $this->completeLogin($request, $user, $remember);
        }

        $verification = $this->otpService->issueFor($user, $request->ip());

        if (! $verification) {
            throw ValidationException::withMessages([
                'email' => [$this->otpService->destination() === null
                    ? 'Login approval is not configured, so staff sign-in is disabled. Please contact your administrator.'
                    : 'Could not send the login code right now. Please try again shortly.'],
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put(self::PENDING, [
            'user_id' => $user->id,
            'remember' => $remember,
            'otp_token' => $verification->otp_token,
        ]);

        return redirect()->route('admin.login.otp');
    }

    /**
     * Show the approval code form for a pending login.
     */
    public function showOtp(Request $request)
    {
        if (! $this->pendingLogin($request)) {
            return redirect()->route('admin.login')
                ->with('error', 'Please sign in with your email and password first.');
        }

        return view('admin.auth.otp', [
            'expiresInMinutes' => $this->otpService->expiryMinutes(),
        ]);
    }

    /**
     * Check the approval code and complete the pending login.
     */
    public function verifyOtp(Request $request)
    {
        $validated = $request->validate([
            'otp' => 'required|string|max:16',
        ]);

        $pending = $this->pendingLogin($request);

        if (! $pending) {
            return redirect()->route('admin.login')
                ->with('error', 'Your sign-in has expired. Please sign in again.');
        }

        $verification = OtpVerification::with('user')
            ->where('otp_token', $pending['otp_token'])
            ->where('user_id', $pending['user_id'])
            ->first();

        $user = $verification?->user;

        if (! $user || ! $user->is_active || ! $user->isAdmin()) {
            return $this->abandonLogin($request, 'Your sign-in has expired. Please sign in again.');
        }

        // verify() redeems the code atomically, so it can be used only once.
        if (! $this->otpService->verify($verification, $validated['otp'])) {
            $current = $verification->fresh();

            if (! $current || $current->isConsumed() || $current->isExpired()) {
                return $this->abandonLogin($request, match (true) {
                    ! $current || $current->isVerified() => 'That code is no longer valid. Please sign in again.',
                    $current->isConsumed() => 'Too many incorrect codes. Please sign in again to request a new one.',
                    default => 'That code has expired. Please sign in again to request a new one.',
                });
            }

            throw ValidationException::withMessages([
                'otp' => ['The code is incorrect.'],
            ]);
        }

        $request->session()->forget(self::PENDING);

        return $this->completeLogin($request, $user, (bool) $pending['remember']);
    }

    /**
     * Handle logout request
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')
            ->with('success', 'You have been logged out successfully.');
    }

    private function completeLogin(Request $request, User $user, bool $remember)
    {
        Auth::loginUsingId($user->id, $remember);
        $request->session()->regenerate();
        AdminMiddleware::rememberPasswordHash($request, $user);

        return redirect()->intended(route('admin.dashboard'))
            ->with('success', 'Welcome back, '.$user->name.'!');
    }

    /**
     * @return array{user_id: int, remember: bool, otp_token: string}|null
     */
    private function pendingLogin(Request $request): ?array
    {
        $pending = $request->session()->get(self::PENDING);

        return is_array($pending) && ! empty($pending['user_id']) && ! empty($pending['otp_token'])
            ? $pending
            : null;
    }

    private function abandonLogin(Request $request, string $message)
    {
        $request->session()->forget(self::PENDING);

        return redirect()->route('admin.login')->with('error', $message);
    }
}
