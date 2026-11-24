<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Employee;
use App\Models\EmployeePayout;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * reports.view alone must not read around the permissions guarding the data a
 * report is built from (payroll, payments, patient details).
 */
class ReportPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $permissionSlugs): User
    {
        $role = Role::create(['name' => 'Operations', 'slug' => 'operations']);

        foreach ($permissionSlugs as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user = User::factory()->create(['role' => 'admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_reports_without_their_source_permission_are_forbidden_including_export(): void
    {
        $user = $this->staff(['dashboard.view', 'contracts.view', 'reports.view']);
        Contract::factory()->create();

        $this->actingAs($user)->get('/admin/reports/payouts')->assertForbidden();
        $this->actingAs($user)->get('/admin/reports/payouts?export=1')->assertForbidden();
        $this->actingAs($user)->get('/admin/reports/revenue')->assertForbidden();

        // The quick export only sits behind reports.view, so it checks itself
        $this->actingAs($user)->post('/admin/reports/export', ['report_type' => 'payouts'])->assertForbidden();
        $this->actingAs($user)->post('/admin/reports/export', ['report_type' => 'revenue'])->assertForbidden();
        $this->actingAs($user)->post('/admin/reports/export', ['report_type' => 'patients'])->assertForbidden();

        $this->actingAs($user)->post('/admin/reports/export', ['report_type' => 'contracts'])->assertOk();
        $this->actingAs($user)->get('/admin/reports/contracts')->assertOk();
    }

    public function test_the_reports_index_only_offers_reports_the_user_can_open(): void
    {
        $user = $this->staff(['contracts.view', 'reports.view']);

        $this->actingAs($user)->get('/admin/reports')
            ->assertOk()
            ->assertSee('Contract Report')
            ->assertSee('Assignment Report')
            ->assertDontSee('Payout Report')
            ->assertDontSee('Revenue Report')
            ->assertDontSee('Patient Report')
            ->assertDontSee('Employee Report');
    }

    public function test_the_employee_report_hides_payout_columns_without_a_payout_permission(): void
    {
        $employee = Employee::factory()->create(['first_name' => 'Latha', 'last_name' => 'S']);
        EmployeePayout::create(['employee_id' => $employee->id, 'frequency' => 'monthly', 'rate' => 100, 'period_start' => now()->subMonth(), 'period_end' => now(), 'amount' => 4321, 'status' => 'pending']);

        $withoutPayouts = $this->staff(['employees.view', 'reports.view']);

        $this->actingAs($withoutPayouts)->get('/admin/reports/employees')
            ->assertOk()
            ->assertSee('Latha S')
            ->assertDontSee('Payouts Pending')
            ->assertDontSee('Payouts Paid')
            ->assertDontSee('4,321');

        $csv = $this->actingAs($withoutPayouts)->get('/admin/reports/employees?export=1')->streamedContent();
        $this->assertStringContainsString('Latha S', $csv);
        $this->assertStringNotContainsString('Payouts', $csv);
        $this->assertStringNotContainsString('4321', $csv);

        // The session is pinned to its user; start a fresh one for the next
        $this->flushSession();
        $withPayouts = User::factory()->superAdmin()->create();

        $this->actingAs($withPayouts)->get('/admin/reports/employees')
            ->assertOk()
            ->assertSee('Payouts Pending (₹)')
            ->assertSee('4,321.00');
    }

    public function test_a_payments_role_can_open_the_revenue_report(): void
    {
        Payment::factory()->create(['amount' => 777]);
        $user = $this->staff(['payments.view', 'reports.view']);

        $this->actingAs($user)->get('/admin/reports/revenue')->assertOk()->assertSee('777.00');
        $this->actingAs($user)->post('/admin/reports/export', ['report_type' => 'revenue'])->assertOk();
        $this->actingAs($user)->get('/admin/reports/contracts')->assertForbidden();
    }
}
