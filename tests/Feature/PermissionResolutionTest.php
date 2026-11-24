<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Role and permission resolution.
 *
 * These checks run on every guarded route and once per gated link in the admin
 * sidebar, so they have to be both correct and cheap. Accounts that predate
 * the pivot still fall back to the legacy users.role column; once an account
 * has any role row, only its active roles and permissions count.
 */
class PermissionResolutionTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $slug, array $permissionSlugs = []): Role
    {
        $role = Role::create(['name' => ucfirst($slug), 'slug' => $slug]);

        foreach ($permissionSlugs as $permissionSlug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $permissionSlug],
                ['name' => $permissionSlug]
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        return $role;
    }

    public function test_a_user_holds_the_permissions_of_their_assigned_role(): void
    {
        $role = $this->role('coordinator', ['patients.view', 'contracts.view']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $this->assertTrue($user->hasPermission('patients.view'));
        $this->assertTrue($user->hasPermission('contracts.view'));
        $this->assertFalse($user->hasPermission('payouts.manage'));
    }

    public function test_permission_matching_ignores_case_and_padding(): void
    {
        $role = $this->role('coordinator', ['patients.view']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $this->assertTrue($user->hasPermission('  Patients.View  '));
    }

    public function test_a_super_admin_is_never_locked_out(): void
    {
        $this->role('coordinator', ['patients.view']);
        $user = User::factory()->superAdmin()->create();

        $this->assertTrue($user->hasPermission('anything.at.all'));
        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->isAdmin());
    }

    /**
     * Accounts created before the pivot existed still resolve, via the role
     * named on the legacy users.role column.
     */
    public function test_an_account_with_no_pivot_rows_falls_back_to_the_legacy_column(): void
    {
        // The legacy column is enum('admin','super_admin'), so the fallback
        // can only ever resolve to one of those two role slugs.
        $this->role('admin', ['patients.view']);
        $user = User::factory()->create(['role' => 'admin']);

        $this->assertSame(0, $user->roles()->count());
        $this->assertTrue($user->hasPermission('patients.view'));
        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_a_legacy_role_with_no_matching_role_row_holds_nothing(): void
    {
        // 'coordinator' exists as a pivot role but the user is not attached to
        // it, and no Role row matches their legacy 'admin' column.
        $this->role('coordinator', ['patients.view']);
        $user = User::factory()->create(['role' => 'admin']);

        $this->assertFalse($user->hasPermission('patients.view'));
    }

    public function test_pivot_roles_take_precedence_over_the_legacy_column(): void
    {
        $this->role('admin', ['patients.view']);
        $accounts = $this->role('accounts', ['payments.manage']);

        $user = User::factory()->create(['role' => 'admin']);
        $user->roles()->attach($accounts->id);

        $this->assertTrue($user->hasPermission('payments.manage'));
        $this->assertFalse($user->hasPermission('patients.view'));
        $this->assertTrue($user->hasRole('accounts'));
        $this->assertFalse($user->hasRole('admin'));
    }

    /**
     * The reason the caching exists.
     *
     * Every check previously ran two information_schema queries plus two or
     * three lookups. A sidebar gating fifteen links paid that fifteen times
     * over on every page load.
     */
    public function test_many_permission_checks_cost_a_bounded_number_of_queries(): void
    {
        $role = $this->role('coordinator', ['patients.view', 'contracts.view']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        // Warm nothing: this is a fresh instance, as a request would have.
        $user = User::find($user->id);

        DB::flushQueryLog();
        DB::enableQueryLog();

        foreach (range(1, 20) as $ignored) {
            $user->hasPermission('patients.view');
            $user->hasPermission('payouts.manage');
        }

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            3,
            $queries,
            "40 permission checks issued {$queries} queries; they should resolve from cache."
        );
    }

    public function test_forgetting_the_cache_picks_up_a_newly_granted_role(): void
    {
        $this->role('admin', ['patients.view']);
        $accounts = $this->role('accounts', ['payments.manage']);

        $user = User::factory()->create(['role' => 'admin']);
        $this->assertFalse($user->hasPermission('payments.manage'));

        $user->roles()->attach($accounts->id);

        $this->assertTrue($user->forgetRbacCache()->hasPermission('payments.manage'));
    }

    public function test_a_deactivated_role_grants_nothing(): void
    {
        $accounts = $this->role('accounts', ['payments.manage']);
        $user = User::factory()->create();
        $user->roles()->attach($accounts->id);

        $accounts->update(['is_active' => false]);

        $this->assertFalse($user->fresh()->hasPermission('payments.manage'));
        $this->assertFalse($user->fresh()->hasRole('accounts'));
    }

    public function test_a_deactivated_permission_is_not_held(): void
    {
        $accounts = $this->role('accounts', ['payments.manage', 'payments.view']);
        $user = User::factory()->create();
        $user->roles()->attach($accounts->id);

        Permission::where('slug', 'payments.manage')->update(['is_active' => false]);

        $this->assertFalse($user->fresh()->hasPermission('payments.manage'));
        $this->assertTrue($user->fresh()->hasPermission('payments.view'));
    }

    /**
     * Deleting a restricted user's only role used to drop them onto the
     * legacy column, which named the full 'admin' role.
     */
    public function test_deleting_a_users_only_role_does_not_fall_back_to_the_legacy_role(): void
    {
        $this->role('admin', ['patients.manage', 'payouts.manage']);
        $accounts = $this->role('accounts', ['payments.manage']);
        $user = User::factory()->create(['role' => 'admin']);
        $user->roles()->attach($accounts->id);

        $accounts->delete();

        $user = $user->fresh();
        $this->assertFalse($user->hasPermission('payments.manage'));
        $this->assertFalse($user->hasPermission('patients.manage'));
        $this->assertFalse($user->hasRole('admin'));
    }

    public function test_the_legacy_fallback_honours_a_deactivated_role(): void
    {
        $admin = $this->role('admin', ['patients.view']);
        $user = User::factory()->create(['role' => 'admin']);

        $admin->update(['is_active' => false]);

        $this->assertFalse($user->fresh()->hasPermission('patients.view'));
    }

    /**
     * Case B of the finding: restricted through the roles API while the
     * legacy column still said super_admin.
     */
    public function test_the_pivot_decides_super_admin_over_the_legacy_column(): void
    {
        $this->role('super_admin');
        $accounts = $this->role('accounts', ['payments.view']);
        $user = User::factory()->superAdmin()->create();
        $user->roles()->attach($accounts->id);

        $this->assertFalse($user->isSuperAdmin());
        $this->assertFalse($user->hasPermission('payouts.manage'));
        $this->assertTrue($user->hasPermission('payments.view'));
    }

    public function test_an_account_with_no_pivot_rows_is_a_super_admin_by_its_column(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->assertTrue($user->isSuperAdmin());
    }

    public function test_the_super_admin_scope_agrees_with_is_super_admin(): void
    {
        $superRole = $this->role('super_admin');
        $accounts = $this->role('accounts');

        $legacy = User::factory()->superAdmin()->create();
        $viaPivot = User::factory()->create();
        $viaPivot->roles()->attach($superRole->id);
        $restricted = User::factory()->superAdmin()->create();
        $restricted->roles()->attach($accounts->id);
        $plain = User::factory()->create();

        $ids = User::superAdmins()->pluck('id')->all();

        foreach ([$legacy, $viaPivot, $restricted, $plain] as $user) {
            $this->assertSame($user->fresh()->isSuperAdmin(), in_array($user->id, $ids), "User #{$user->id}");
        }
        $this->assertEqualsCanonicalizing([$legacy->id, $viaPivot->id], $ids);
    }

    public function test_assigning_a_panel_role_writes_both_stores(): void
    {
        $superRole = $this->role('super_admin');
        $adminRole = $this->role('admin');
        $user = User::factory()->create();

        $user->assignPanelRole('super_admin');
        $this->assertSame('super_admin', $user->fresh()->role);
        $this->assertTrue($user->roles()->whereKey($superRole->id)->exists());

        $user->assignPanelRole('admin');
        $user = $user->fresh();
        $this->assertSame('admin', $user->role);
        $this->assertFalse($user->isSuperAdmin());
        $this->assertFalse($user->roles()->whereKey($superRole->id)->exists());
        $this->assertTrue($user->roles()->whereKey($adminRole->id)->exists());
    }

    public function test_demoting_keeps_a_custom_restriction(): void
    {
        $superRole = $this->role('super_admin');
        $this->role('admin', ['patients.manage']);
        $accounts = $this->role('accounts', ['payments.view']);
        $user = User::factory()->superAdmin()->create();
        $user->roles()->attach([$superRole->id, $accounts->id]);

        $user->assignPanelRole('admin');

        $user = $user->fresh();
        $this->assertTrue($user->hasPermission('payments.view'));
        $this->assertFalse($user->hasPermission('patients.manage'));
    }
}
