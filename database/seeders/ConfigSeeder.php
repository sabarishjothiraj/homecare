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
        Config::setValue('otp_phone_number', '');
        Config::setValue('otp_email', '');
    }
}
