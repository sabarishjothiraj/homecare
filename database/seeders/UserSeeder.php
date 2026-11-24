<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Starter accounts for a development install.
 *
 * Never resets an account that already exists, so re-running db:seed cannot
 * restore a known password or reactivate a disabled account. Passwords come
 * from SEED_ADMIN_PASSWORD or are generated and printed once.
 */
class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('UserSeeder skipped: it does not run in production. Create the first super admin by hand.');

            return;
        }

        $this->seedAccount([
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'email' => 'admin@homenursing.com',
        ], 'super_admin');

        $this->seedAccount([
            'name' => 'Admin User',
            'email' => 'user@homenursing.com',
        ], 'admin');
    }

    private function seedAccount(array $attributes, string $role): void
    {
        // Includes soft-deleted rows: their email is still taken, and a
        // deleted account must stay deleted.
        if (User::withTrashed()->where('email', $attributes['email'])->exists()) {
            $this->command?->line("Skipped {$attributes['email']}: the account already exists and was left unchanged.");

            return;
        }

        if (isset($attributes['username']) && User::withTrashed()->where('username', $attributes['username'])->exists()) {
            unset($attributes['username']);
        }

        [$password, $generated] = $this->password();

        $user = User::create($attributes + [
            'password' => $password,
            'role' => $role,
            'is_active' => true,
        ]);

        $user->assignPanelRole($role);

        if ($generated) {
            $this->command?->warn("Created {$user->email} ({$role}) with password: {$password}");
            $this->command?->warn('It is shown only this once. Change it after signing in.');
        } else {
            $this->command?->info("Created {$user->email} ({$role}) with the password from SEED_ADMIN_PASSWORD.");
        }
    }

    /**
     * @return array{0: string, 1: bool} the password, and whether it was generated
     */
    private function password(): array
    {
        $configured = (string) env('SEED_ADMIN_PASSWORD', '');

        if ($configured === '') {
            return [Str::password(16, symbols: false), true];
        }

        if (strlen($configured) < 8) {
            throw new RuntimeException('SEED_ADMIN_PASSWORD must be at least 8 characters long.');
        }

        return [$configured, false];
    }
}
