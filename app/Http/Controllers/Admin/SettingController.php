<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Config;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    /**
     * Display settings page
     */
    public function index()
    {
        $settings = Setting::all()->groupBy('group');

        // Lives in the configs table rather than settings, because the
        // super-admin API (/api/configs/otp) already owns that key. Both
        // surfaces read and write the same row.
        $otpPhoneNumber = Config::getValue('otp_phone_number', '');
        $otpEmail = Config::getValue('otp_email', '');

        return view('admin.settings.index', compact('settings', 'otpPhoneNumber', 'otpEmail'));
    }

    /**
     * Update settings
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'company_email' => 'required|email|max:255',
            'company_phone' => 'required|string|max:20',
            'company_address' => 'required|string|max:500',
            'company_description' => 'nullable|string',
            'company_logo' => 'nullable|image|max:2048',
            'website_title' => 'nullable|string|max:255',
            'home_intro_title' => 'nullable|string|max:255',
            'home_intro_content' => 'nullable|string',
            'about_content' => 'nullable|string',
            'join_benefits' => 'nullable|string',
            'contract_alert_days' => 'required|integer|min:1|max:30',
            'contract_max_assignments' => 'required|integer|min:1|max:50',
            'notification_enabled' => 'boolean',
            // Digits with an optional leading +, so a country code survives.
            'otp_phone_number' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]{6,15}$/'],
            'otp_email' => ['nullable', 'email', 'max:255'],
        ]);

        // Handle logo upload
        if ($request->hasFile('company_logo')) {
            // Delete old logo
            $oldLogo = Setting::get('company_logo');
            if ($oldLogo && Storage::disk('public')->exists($oldLogo)) {
                Storage::disk('public')->delete($oldLogo);
            }

            // Upload new logo
            $path = $request->file('company_logo')->store('logos', 'public');
            $validated['company_logo'] = $path;
        }

        // The OTP number is not a Setting row - hand it to the configs table
        // and keep it out of the settings loop below.
        foreach (['otp_phone_number', 'otp_email'] as $configKey) {
            if (array_key_exists($configKey, $validated)) {
                Config::setValue($configKey, trim((string) $validated[$configKey]));
                unset($validated[$configKey]);
            }
        }

        // Update each setting
        foreach ($validated as $key => $value) {
            if ($key === 'company_logo' && !$request->hasFile('company_logo')) {
                continue; // Skip if no new logo uploaded
            }

            // Goes through Setting::set so a key with no row yet gets the
            // generated label the NOT NULL column requires.
            Setting::set($key, $value);
        }

        // Clear settings cache
        Cache::flush();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully.',
                'reload' => true
            ]);
        }

        return redirect()->back()
            ->with('success', 'Settings updated successfully.');
    }
}

