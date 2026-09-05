<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    public function showOtpConfig(): JsonResponse
    {
        return response()->json([
            'otp_phone_number' => Config::getValue('otp_phone_number', ''),
            'otp_email' => Config::getValue('otp_email', ''),
            'otp_channel' => config('otp.channel', 'mail'),
        ]);
    }

    public function updateOtpConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'otp_phone_number' => 'sometimes|nullable|string|max:20',
            'otp_email' => 'sometimes|nullable|email|max:255',
        ]);

        foreach ($validated as $key => $value) {
            Config::setValue($key, trim((string) $value));
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP configuration updated successfully.',
        ]);
    }
}
