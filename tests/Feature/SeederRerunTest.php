<?php

namespace Tests\Feature;

use App\Models\Config;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\RbacSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * db:seed is the documented way to pick up permission changes, so every
 * seeder it runs must be safe to run again on a live database.
 */
class SeederRerunTest extends TestCase
{
    use RefreshDatabase;

    private function runSeeder(string $seeder): void
    {
        $this->app->make($seeder)->run();
    }

    public function test_the_user_seeder_never_resets_an_existing_account(): void
    {
        $this->runSeeder(RbacSeeder::class);
        $owner = User::factory()->superAdmin()->create([
            'email' => 'admin@homenursing.com',
            'password' => 'changed-by-owner',
            'is_active' => false,
        ]);

        $this->runSeeder(UserSeeder::class);

        $owner = $owner->fresh();
        $this->assertTrue(Hash::check('changed-by-owner', $owner->password));
        $this->assertFalse($owner->is_active);
    }

    public function test_seeded_accounts_do_not_get_the_documented_password(): void
    {
        $this->runSeeder(RbacSeeder::class);
        $this->runSeeder(UserSeeder::class);

        $owner = User::where('email', 'admin@homenursing.com')->firstOrFail();
        $this->assertFalse(Hash::check('password', $owner->password));
        $this->assertSame('superadmin', $owner->username);
        $this->assertTrue($owner->isSuperAdmin());

        $staff = User::where('email', 'user@homenursing.com')->firstOrFail();
        $this->assertFalse(Hash::check('password', $staff->password));
        $this->assertFalse($staff->isSuperAdmin());
    }

    public function test_the_seed_password_can_come_from_the_environment(): void
    {
        $this->withEnvironmentValue('SEED_ADMIN_PASSWORD', 'from-the-env-123', function () {
            $this->runSeeder(UserSeeder::class);
        });

        $owner = User::where('email', 'admin@homenursing.com')->firstOrFail();
        $this->assertTrue(Hash::check('from-the-env-123', $owner->password));
    }

    public function test_the_user_seeder_does_not_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->runSeeder(UserSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_the_config_seeder_keeps_a_configured_approver(): void
    {
        Config::setValue('otp_email', 'owner@example.com');

        $this->runSeeder(ConfigSeeder::class);

        $this->assertSame('owner@example.com', Config::getValue('otp_email'));
        $this->assertSame('', (string) Config::getValue('otp_phone_number'));
    }

    public function test_the_setting_seeder_can_run_again_without_overwriting(): void
    {
        $this->runSeeder(SettingSeeder::class);
        Setting::set('about_content', 'Edited in the panel');

        $this->runSeeder(SettingSeeder::class);

        $this->assertSame('Edited in the panel', Setting::where('key', 'about_content')->value('value'));
        $this->assertSame(1, Setting::where('key', 'about_content')->count());
    }

    public function test_the_rbac_seeder_does_not_undo_role_decisions(): void
    {
        $this->runSeeder(RbacSeeder::class);

        $accounts = Role::create(['name' => 'Accounts', 'slug' => 'accounts']);
        $restricted = User::factory()->create(['role' => 'admin']);
        $restricted->roles()->attach($accounts->id);
        Role::where('slug', 'admin')->update(['is_active' => false]);

        $this->runSeeder(RbacSeeder::class);

        $this->assertSame(['accounts'], $restricted->roles()->pluck('slug')->all());
        $this->assertFalse(Role::where('slug', 'admin')->first()->is_active);
    }

    public function test_the_rbac_seeder_still_places_accounts_that_have_no_role(): void
    {
        $legacy = User::factory()->create(['role' => 'admin']);

        $this->runSeeder(RbacSeeder::class);

        $this->assertSame(['admin'], $legacy->roles()->pluck('slug')->all());
    }

    private function withEnvironmentValue(string $key, string $value, callable $callback): void
    {
        putenv("{$key}={$value}");
        $_ENV[$key] = $_SERVER[$key] = $value;

        try {
            $callback();
        } finally {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }
}
