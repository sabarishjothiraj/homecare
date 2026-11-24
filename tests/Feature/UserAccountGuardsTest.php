<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminMiddleware;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The user-management screens in the admin panel.
 *
 * users.manage can be handed to a restricted role, so these screens must not
 * let its holder reach above that role, and must not let anyone lock the
 * platform out of its own administration.
 */
class UserAccountGuardsTest extends TestCase
{
    use RefreshDatabase;

    private Role $superAdminRole;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdminRole = Role::create(['name' => 'Super Admin', 'slug' => 'super_admin']);
        Role::create(['name' => 'Admin', 'slug' => 'admin']);

        $this->owner = User::factory()->superAdmin()->create(['email' => 'owner@example.com']);
        $this->owner->roles()->attach($this->superAdminRole->id);
    }

    /**
     * A staff account whose only role is a custom one holding users.manage.
     */
    private function hrUser(): User
    {
        $role = Role::create(['name' => 'HR', 'slug' => 'hr']);
        $role->permissions()->attach(Permission::create(['name' => 'Users', 'slug' => 'users.manage'])->id);

        $user = User::factory()->create(['email' => 'hr@example.com']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function payload(User $user, array $overrides = []): array
    {
        return $overrides + [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->isSuperAdmin() ? 'super_admin' : 'admin',
            'is_active' => '1',
        ];
    }

    public function test_a_users_manage_holder_cannot_create_a_super_admin(): void
    {
        $this->actingAs($this->hrUser())
            ->post(route('admin.users.store'), [
                'name' => 'Sneaky',
                'email' => 'sneaky@example.com',
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
                'role' => 'super_admin',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function test_a_users_manage_holder_cannot_promote_anyone(): void
    {
        $hr = $this->hrUser();
        $other = User::factory()->create();

        $this->actingAs($hr)
            ->put(route('admin.users.update', $other), $this->payload($other, ['role' => 'super_admin']))
            ->assertSessionHasErrors('role');

        // On their own account the role field is ignored altogether.
        $this->actingAs($hr)
            ->put(route('admin.users.update', $hr), $this->payload($hr, ['role' => 'super_admin']));

        $this->assertFalse($other->fresh()->isSuperAdmin());
        $this->assertFalse($hr->fresh()->isSuperAdmin());
        $this->assertSame('admin', $hr->fresh()->role);
    }

    public function test_a_users_manage_holder_cannot_take_over_a_super_admin_account(): void
    {
        $this->actingAs($this->hrUser())
            ->put(route('admin.users.update', $this->owner), $this->payload($this->owner, [
                'email' => 'attacker@example.com',
                'password' => 'taken-over',
                'password_confirmation' => 'taken-over',
            ]))
            ->assertForbidden();

        $owner = $this->owner->fresh();
        $this->assertSame('owner@example.com', $owner->email);
        $this->assertTrue(Hash::check('password', $owner->password));
    }

    public function test_a_users_manage_holder_cannot_deactivate_or_delete_a_super_admin(): void
    {
        $hr = $this->hrUser();

        $this->actingAs($hr)->patch(route('admin.users.deactivate', $this->owner))->assertForbidden();
        $this->actingAs($hr)->delete(route('admin.users.destroy', $this->owner))->assertForbidden();

        $this->assertTrue($this->owner->fresh()->is_active);
        $this->assertNull($this->owner->fresh()->deleted_at);
    }

    public function test_nobody_can_deactivate_or_demote_themselves_through_the_edit_form(): void
    {
        // is_active left out, as an unticked checkbox would be.
        $this->actingAs($this->owner)
            ->put(route('admin.users.update', $this->owner), [
                'name' => 'Owner Renamed',
                'email' => 'owner@example.com',
                'role' => 'admin',
            ])
            ->assertRedirect(route('admin.users.show', $this->owner));

        $owner = $this->owner->fresh();
        $this->assertSame('Owner Renamed', $owner->name);
        $this->assertTrue($owner->is_active);
        $this->assertTrue($owner->isSuperAdmin());
    }

    /**
     * Case A of the finding: promoted through the roles API, then demoted in
     * the panel, which used to write only users.role.
     */
    public function test_demoting_in_the_panel_removes_a_super_admin_granted_through_the_api(): void
    {
        $promoted = User::factory()->create(['role' => 'admin']);
        $promoted->roles()->attach($this->superAdminRole->id);
        $promoted->createToken('api');
        $this->assertTrue($promoted->isSuperAdmin());

        $this->actingAs($this->owner)
            ->put(route('admin.users.update', $promoted), $this->payload($promoted, ['role' => 'admin']))
            ->assertSessionHasNoErrors();

        $promoted = $promoted->fresh();
        $this->assertFalse($promoted->isSuperAdmin());
        $this->assertFalse($promoted->roles()->whereKey($this->superAdminRole->id)->exists());
        $this->assertSame(0, $promoted->tokens()->count());
    }

    public function test_promoting_in_the_panel_writes_the_pivot_too(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($this->owner)
            ->put(route('admin.users.update', $staff), $this->payload($staff, ['role' => 'super_admin']))
            ->assertSessionHasNoErrors();

        $staff = $staff->fresh();
        $this->assertSame('super_admin', $staff->role);
        $this->assertTrue($staff->roles()->whereKey($this->superAdminRole->id)->exists());
        $this->assertTrue($staff->isSuperAdmin());
    }

    public function test_a_password_reset_revokes_tokens_and_remember_me(): void
    {
        $staff = User::factory()->create();
        $staff->forceFill(['remember_token' => 'old-remember-token'])->save();
        $staff->createToken('api');

        $this->actingAs($this->owner)
            ->put(route('admin.users.update', $staff), $this->payload($staff, [
                'password' => 'brand-new-pass',
                'password_confirmation' => 'brand-new-pass',
            ]))
            ->assertSessionHasNoErrors();

        $staff = $staff->fresh();
        $this->assertTrue(Hash::check('brand-new-pass', $staff->password));
        $this->assertNotSame('old-remember-token', $staff->remember_token);
        $this->assertSame(0, $staff->tokens()->count());
    }

    public function test_a_password_reset_ends_the_users_existing_sessions(): void
    {
        $other = User::factory()->superAdmin()->create();
        $other->roles()->attach($this->superAdminRole->id);
        $hashAtLogin = $other->password;

        $this->actingAs($this->owner)
            ->put(route('admin.users.update', $other), $this->payload($other, [
                'password' => 'brand-new-pass',
                'password_confirmation' => 'brand-new-pass',
            ]));

        // The other user's browser, still holding the session it signed in with.
        $this->actingAs($other->fresh())
            ->withSession([AdminMiddleware::PASSWORD_HASH_KEY => $hashAtLogin])
            ->get(route('admin.users.index'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_changing_your_own_password_keeps_your_session(): void
    {
        $this->actingAs($this->owner)->get(route('admin.users.index'))->assertOk();

        $this->put(route('admin.users.update', $this->owner), $this->payload($this->owner, [
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ]))->assertSessionHasNoErrors();

        // A real next request loads the user afresh; the test guard would
        // otherwise keep the instance with the old hash.
        $this->actingAs($this->owner->fresh())
            ->get(route('admin.users.index'))
            ->assertOk();
        $this->assertAuthenticatedAs($this->owner);
    }

    public function test_deactivating_a_user_revokes_their_api_tokens(): void
    {
        $staff = User::factory()->create();
        $staff->createToken('api');

        $this->actingAs($this->owner)->patch(route('admin.users.deactivate', $staff));

        $this->assertFalse($staff->fresh()->is_active);
        $this->assertSame(0, $staff->tokens()->count());
    }
}
