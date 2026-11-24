<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The super-admin API: token lifetime, role changes and login throttling.
 */
class ApiAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private Role $superAdminRole;

    private Role $accounts;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdminRole = Role::create(['name' => 'Super Admin', 'slug' => 'super_admin']);
        $this->accounts = Role::create(['name' => 'Accounts', 'slug' => 'accounts']);

        $this->owner = User::factory()->superAdmin()->create(['username' => 'superadmin']);
        $this->owner->roles()->attach($this->superAdminRole->id);
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_an_active_super_admin_token_reaches_the_api(): void
    {
        $this->withToken($this->tokenFor($this->owner))
            ->getJson('/api/users')
            ->assertOk();
    }

    public function test_a_deactivated_users_token_stops_working_at_once(): void
    {
        $token = $this->tokenFor($this->owner);

        $this->owner->update(['is_active' => false]);

        $this->withToken($token)->getJson('/api/users')->assertUnauthorized();
    }

    public function test_tokens_expire(): void
    {
        $this->assertGreaterThan(0, (int) config('sanctum.expiration'));

        $token = $this->tokenFor($this->owner);

        $this->travel((int) config('sanctum.expiration') + 1)->minutes();

        $this->withToken($token)->getJson('/api/users')->assertUnauthorized();
    }

    /**
     * Case B of the finding: the roles API used to leave users.role saying
     * super_admin, which kept every permission.
     */
    public function test_restricting_a_super_admin_through_the_api_sticks(): void
    {
        $other = User::factory()->superAdmin()->create();
        $other->roles()->attach($this->superAdminRole->id);
        $this->tokenFor($other);

        $this->withToken($this->tokenFor($this->owner))
            ->postJson("/api/users/{$other->id}/roles", ['role_ids' => [$this->accounts->id]])
            ->assertOk();

        $other = $other->fresh();
        $this->assertSame('admin', $other->role);
        $this->assertFalse($other->isSuperAdmin());
        $this->assertSame(0, $other->tokens()->count());
    }

    public function test_granting_super_admin_through_the_api_updates_the_column(): void
    {
        $staff = User::factory()->create();

        $this->withToken($this->tokenFor($this->owner))
            ->postJson("/api/users/{$staff->id}/roles", ['role_ids' => [$this->superAdminRole->id]])
            ->assertOk();

        $this->assertSame('super_admin', $staff->fresh()->role);
        $this->assertTrue($staff->fresh()->isSuperAdmin());
    }

    public function test_a_super_admin_cannot_demote_themselves_through_the_api(): void
    {
        $this->withToken($this->tokenFor($this->owner))
            ->postJson("/api/users/{$this->owner->id}/roles", ['role_ids' => [$this->accounts->id]])
            ->assertStatus(422);

        $this->assertTrue($this->owner->fresh()->isSuperAdmin());
    }

    public function test_the_super_admin_role_cannot_be_deactivated_or_deleted(): void
    {
        $token = $this->tokenFor($this->owner);

        $this->withToken($token)
            ->putJson("/api/roles/{$this->superAdminRole->id}", ['is_active' => false])
            ->assertStatus(422);

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->deleteJson("/api/roles/{$this->superAdminRole->id}")
            ->assertStatus(422);

        $this->assertTrue($this->superAdminRole->fresh()->is_active);
        $this->assertNull($this->superAdminRole->fresh()->deleted_at);
    }

    public function test_a_roles_permissions_can_be_emptied_without_deleting_it(): void
    {
        $permission = Permission::create(['name' => 'Payments', 'slug' => 'payments.manage']);
        $this->accounts->permissions()->attach($permission->id);

        $this->withToken($this->tokenFor($this->owner))
            ->postJson("/api/roles/{$this->accounts->id}/permissions", ['permission_ids' => []])
            ->assertOk();

        $this->assertSame(0, $this->accounts->permissions()->count());
    }

    /**
     * The username route has no second factor, so guesses against one
     * account are capped however many addresses they come from.
     */
    public function test_password_guesses_against_one_account_are_capped_across_addresses(): void
    {
        foreach (range(1, 20) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "198.51.100.{$i}"])
                ->postJson('/api/login', ['username' => 'superadmin', 'password' => 'wrong-guess'])
                ->assertStatus(422);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.99'])
            ->postJson('/api/login', ['username' => 'SuperAdmin', 'password' => 'wrong-guess'])
            ->assertStatus(429);
    }

    public function test_an_unknown_username_gets_the_same_answer_as_a_wrong_password(): void
    {
        $unknown = $this->postJson('/api/login', ['username' => 'nobody-here', 'password' => 'wrong-guess']);
        $wrong = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->postJson('/api/login', ['username' => 'superadmin', 'password' => 'wrong-guess']);

        $unknown->assertStatus(422);
        $wrong->assertStatus(422);
        $this->assertSame($unknown->json('errors'), $wrong->json('errors'));
    }
}
