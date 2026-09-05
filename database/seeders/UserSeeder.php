<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdmin = User::updateOrCreate([
            'email' => 'admin@homenursing.com',
        ], [
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $adminUser = User::updateOrCreate([
            'email' => 'user@homenursing.com',
        ], [
            'name' => 'Admin User',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $superAdminRole = Role::where('slug', 'super_admin')->first();
        $adminRole = Role::where('slug', 'admin')->first();

        if ($superAdminRole) {
            $superAdmin->roles()->syncWithoutDetaching([$superAdminRole->id]);
        }

        if ($adminRole) {
            $adminUser->roles()->syncWithoutDetaching([$adminRole->id]);
        }
    }
}

