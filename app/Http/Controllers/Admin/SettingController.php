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
        // Keyed by setting key, not grouped: the seeded group names never
        // matched the ones this form looked under, so it showed blanks and
        // saving wrote those blanks over the real values.
        $settings = Setting::all()->keyBy('key');

        // Lives in the configs table rather than settings, because the
        // super-admin API (/api/configs/otp) already owns that key. Both
        // surfaces read and write the same row.
        $otpPhoneNumber = Config::getValue('otp_phone_number', '');
        $otpEmail = Config::getValue('otp_email', '');
        $canEditOtp = auth()->user()->hasPermission('otp.config.update');

        return view('admin.settings.index', compact('settings', 'otpPhoneNumber', 'otpEmail', 'canEditOtp'));
    }

    /**
     * Update settings
     */
    public function update(Request $request)
    {
        // Where staff login approvals go is its own permission: settings.manage
        // is handed out for content edits, and must not be enough to redirect
        // every approval code to an address of the holder's choosing.
        $canEditOtp = $request->user()->hasPermission('otp.config.update');

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'company_email' => 'required|email|max:255',
            'company_phone' => 'required|string|max:20',
            'company_address' => 'required|string|max:500',
            'company_description' => 'nullable|string',
            // Raster formats only: an SVG can carry script.
            'company_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'website_title' => 'nullable|string|max:255',
            'home_intro_title' => 'nullable|string|max:255',
            'home_intro_content' => 'nullable|string',
            'about_content' => 'nullable|string',
            'join_benefits' => 'nullable|string',
            'contract_alert_days' => 'required|integer|min:1|max:30',
            'contract_max_assignments' => 'required|integer|min:1|max:50',
            'notification_enabled' => 'boolean',
        ] + ($canEditOtp ? [
            // Digits with an optional leading +, so a country code survives.
            'otp_phone_number' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]{6,15}$/'],
            'otp_email' => ['nullable', 'email', 'max:255'],
        ] : []));

        // An unticked checkbox is simply absent, which used to leave the old
        // value in place: once on, notifications could never be turned off.
        $validated['notification_enabled'] = $request->boolean('notification_enabled') ? '1' : '0';

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
            if ($key === 'company_logo' && ! $request->hasFile('company_logo')) {
                continue; // Skip if no new logo uploaded
            }

            // Goes through Setting::set so a key with no row yet gets the
            // generated label the NOT NULL column requires.
            Setting::set($key, $value);
        }

        // Setting::set() has dropped each key's cache; drop the per-group
        // lists too. Not Cache::flush(): the cache also holds the login rate
        // limiters, and any settings save would reset a brute-force lockout.
        Setting::query()->distinct()->pluck('group')
            ->each(fn ($group) => Cache::forget("settings_group_{$group}"));

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully.',
                'reload' => true,
            ]);
        }

        return redirect()->back()
            ->with('success', 'Settings updated successfully.');
    }
}
