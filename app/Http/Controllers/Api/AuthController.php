<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OtpVerification;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otpService)
    {
    }

    /**
     * POST /api/login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'nullable|string',
            'email' => 'nullable|email',
            'password' => 'required|string|min:6',
            'device_name' => 'nullable|string|max:255',
        ]);

        $deviceName = $request->input('device_name', 'api-client');

        if ($request->filled('username')) {
            return $this->handleSuperAdminLogin($request, $deviceName);
        }

        if (!$request->filled('email')) {
            throw ValidationException::withMessages([
                'email' => ['Email is required for non-super-admin login.'],
            ]);
        }

        return $this->handleOtpLoginStart($request);
    }

    /**
     * POST /api/verify-otp
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'otp_token' => 'required|string|exists:otp_verifications,otp_token',
            'otp' => 'required|string',
            'device_name' => 'nullable|string|max:255',
        ]);

        /** @var OtpVerification $verification */
        $verification = OtpVerification::with('user')
            ->where('otp_token', $validated['otp_token'])
            ->firstOrFail();

        if ($verification->isConsumed()) {
            return response()->json([
                'success' => false,
                'message' => 'OTP session already used. Please login again.',
            ], 422);
        }

        if ($verification->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'OTP has expired. Please login again.',
            ], 422);
        }

        $user = $verification->user;

        if (!$user || !$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is deactivated.',
            ], 403);
        }

        if (!$this->otpService->verify($verification, $validated['otp'])) {
            $exhausted = $verification->fresh()->isConsumed();

            return response()->json([
                'success' => false,
                'message' => $exhausted
                    ? 'Too many incorrect attempts. Please login again to get a new OTP.'
                    : 'Invalid OTP.',
            ], 422);
        }

        $verification->update([
            'verified_at' => now(),
            'consumed_at' => now(),
        ]);

        $token = $user->createToken($validated['device_name'] ?? 'api-client')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    private function handleSuperAdminLogin(Request $request, string $deviceName): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        $user = User::with('roles')->where('username', $validated['username'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Invalid credentials.'],
            ]);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is deactivated.',
            ], 403);
        }

        if (!$user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only super admin can login via username.',
            ], 403);
        }

        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Super admin login successful.',
            'requires_otp' => false,
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    private function handleOtpLoginStart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::with('roles')->where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is deactivated.',
            ], 403);
        }

        if ($user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Super admin must login using username and password.',
            ], 422);
        }

        // Issues the code and sends it to the number configured in the admin
        // panel. Null means either no number is configured or the gateway
        // refused it - in both cases there is no usable session to hand back.
        $verification = $this->otpService->issueFor($user, $request->ip());

        if (!$verification) {
            return response()->json([
                'success' => false,
                'message' => $this->otpService->destination() === null
                    ? 'OTP delivery is not configured. Please contact your administrator.'
                    : 'Could not send the OTP right now. Please try again shortly.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP sent to the registered approver for verification.',
            'requires_otp' => true,
            'otp_token' => $verification->otp_token,
            'expires_in_minutes' => $this->otpService->expiryMinutes(),
        ]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'roles' => $user->roles()->pluck('slug')->all(),
            'legacy_role' => $user->role,
        ];
    }
}
