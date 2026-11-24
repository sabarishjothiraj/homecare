<?php

namespace Database\Seeders;

use App\Models\Config;
use Illuminate\Database\Seeder;

class ConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Destinations for the login OTP. Both start blank: until one is set
        // from the admin panel, OTP logins are refused rather than bypassed.
        // Only missing rows are created, so re-running db:seed never blanks a
        // destination the owner has set.
        foreach (['otp_phone_number', 'otp_email'] as $key) {
            Config::firstOrCreate(['key' => $key], ['value' => '']);
        }
    }
}
