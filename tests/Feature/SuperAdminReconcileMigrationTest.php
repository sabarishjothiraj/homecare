<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The one-off repair of accounts whose users.role and role_user disagreed
 * about super admin, left behind by surfaces that wrote only one of them.
 */
class SuperAdminReconcileMigrationTest extends TestCase
{
    use RefreshDatabase;

    private Role $superAdminRole;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdminRole = Role::create(['name' => 'Super Admin', 'slug' => 'super_admin']);
        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
    }

    private function migrate(): void
    {
        (require database_path('migrations/2026_09_23_000201_reconcile_super_admin_role_stores.php'))->up();
    }

    private function consistentOwner(): User
    {
        $owner = User::factory()->superAdmin()->create();
        $owner->roles()->attach($this->superAdminRole->id);

        return $owner;
    }

    public function test_disagreeing_accounts_are_resolved_to_the_lesser_role(): void
    {
        $owner = $this->consistentOwner();

        // Demoted in the panel after being promoted through the API.
        $demotedInPanel = User::factory()->create(['role' => 'admin']);
        $demotedInPanel->roles()->attach($this->superAdminRole->id);

        // Restricted through the API while the column said super_admin.
        $accounts = Role::create(['name' => 'Accounts', 'slug' => 'accounts']);
        $restrictedByApi = User::factory()->superAdmin()->create();
        $restrictedByApi->roles()->attach($accounts->id);

        $this->migrate();

        $demotedInPanel = $demotedInPanel->fresh();
        $this->assertSame('admin', $demotedInPanel->role);
        $this->assertSame(['admin'], $demotedInPanel->roles()->pluck('slug')->all());
        $this->assertFalse($demotedInPanel->isSuperAdmin());

        $restrictedByApi = $restrictedByApi->fresh();
        $this->assertSame('admin', $restrictedByApi->role);
        $this->assertSame(['accounts'], $restrictedByApi->roles()->pluck('slug')->all());

        $this->assertTrue($owner->fresh()->isSuperAdmin());
    }

    public function test_accounts_that_agree_or_predate_the_pivot_are_untouched(): void
    {
        $owner = $this->consistentOwner();
        $legacy = User::factory()->superAdmin()->create();
        $staff = User::factory()->create();
        $staff->roles()->attach($this->adminRole->id);

        $this->migrate();

        $this->assertSame('super_admin', $owner->fresh()->role);
        $this->assertSame('super_admin', $legacy->fresh()->role);
        $this->assertSame(0, $legacy->roles()->count());
        $this->assertSame(['admin'], $staff->roles()->pluck('slug')->all());
    }

    public function test_the_platform_is_never_left_without_an_active_super_admin(): void
    {
        $onlyOne = User::factory()->create(['role' => 'admin']);
        $onlyOne->roles()->attach($this->superAdminRole->id);

        $this->migrate();

        $onlyOne = $onlyOne->fresh();
        $this->assertSame('super_admin', $onlyOne->role);
        $this->assertTrue($onlyOne->isSuperAdmin());
    }
}
