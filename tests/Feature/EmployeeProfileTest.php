<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Employee;
use App\Models\EmployeePayout;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
    }

    private function userWithPermissions(array $permissionSlugs): User
    {
        $role = Role::create(['name' => 'Coordinator', 'slug' => 'coordinator']);
        foreach ($permissionSlugs as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_payslip_amounts_are_only_shown_with_a_payroll_permission(): void
    {
        $employee = Employee::factory()->create();
        EmployeePayout::create([
            'employee_id' => $employee->id,
            'rate' => 24000,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'days_in_period' => 31,
            'payable_days' => 31,
            'amount' => 24000,
            'status' => 'paid',
        ]);

        $coordinator = $this->userWithPermissions(['employees.view', 'employees.manage']);
        $this->actingAs($coordinator)->get("/admin/employees/{$employee->id}")
            ->assertOk()
            ->assertDontSee('Payslips')
            ->assertDontSee('24,000.00');

        // A new session for the second user (the admin session is bound to
        // the password hash of the account it started with).
        $this->flushSession();
        $this->actingAs($this->admin)->get("/admin/employees/{$employee->id}")
            ->assertOk()
            ->assertSee('Payslips')
            ->assertSee('₹24,000.00', false);
    }

    public function test_an_employee_on_an_active_assignment_cannot_be_deactivated(): void
    {
        $employee = Employee::factory()->create();
        $assignment = Assignment::factory()->create(['employee_id' => $employee->id]);
        $contract = $assignment->contract;

        $this->actingAs($this->admin)
            ->from("/admin/employees/{$employee->id}")
            ->patch("/admin/employees/{$employee->id}/deactivate")
            ->assertRedirect("/admin/employees/{$employee->id}")
            ->assertSessionHas('error', fn ($message) => str_contains($message, $contract->contract_number)
                && str_contains($message, $contract->patient->name));

        $this->assertSame('active', $employee->fresh()->status);
        $this->assertTrue($assignment->fresh()->is_active);

        $this->patchJson("/admin/employees/{$employee->id}/deactivate")
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_an_employee_with_no_active_assignment_is_deactivated(): void
    {
        $employee = Employee::factory()->create();
        Assignment::factory()->inactive()->create(['employee_id' => $employee->id]);

        $this->actingAs($this->admin)
            ->patch("/admin/employees/{$employee->id}/deactivate")
            ->assertSessionHas('success');

        $this->assertSame('inactive', $employee->fresh()->status);
    }
}
