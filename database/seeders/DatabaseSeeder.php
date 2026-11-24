<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RbacSeeder::class,
            UserSeeder::class,
            ConfigSeeder::class,
            SettingSeeder::class,
            EmployeeFieldSeeder::class,
            CasteSeeder::class,
            BannerSeeder::class,
            ServiceSeeder::class,
            BlogSeeder::class,
        ]);
    }
}
