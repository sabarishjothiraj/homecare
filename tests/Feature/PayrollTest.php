<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeePayout;
use App\Models\EmployeeSalary;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Salary from attendance.
 *
 * April 2026 has 30 days, so a ₹24,000 monthly salary is ₹800 a day.
 */
class PayrollTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private PayrollService $payroll;

    private Carbon $april;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
        $this->payroll = app(PayrollService::class);
        $this->april = Carbon::create(2026, 4, 1);
    }

    private function employeeWithSalary(float $amount = 24000, string $type = 'monthly', string $from = '2026-01-01'): Employee
    {
        $employee = Employee::factory()->create();
        EmployeeSalary::create(['employee_id' => $employee->id, 'pay_type' => $type, 'amount' => $amount, 'effective_from' => $from]);

        return $employee;
    }

    private function mark(Employee $employee, string $status, string $from, ?string $to = null): void
    {
        foreach (CarbonPeriod::create($from, $to ?? $from) as $day) {
            Attendance::updateOrCreate(['employee_id' => $employee->id, 'date' => $day->toDateString()], ['status' => $status]);
        }
    }

    private function aprilPay(Employee $employee): array
    {
        return $this->payroll->calculate($employee->fresh(), $this->april->copy()->startOfMonth(), $this->april->copy()->endOfMonth());
    }

    public function test_a_fully_present_month_earns_exactly_the_monthly_salary(): void
    {
        $employee = $this->employeeWithSalary();
        $this->mark($employee, 'present', '2026-04-01', '2026-04-30');

        $pay = $this->aprilPay($employee);

        $this->assertSame(24000.0, $pay['gross']);
        $this->assertSame(30.0, $pay['payable_days']);
        $this->assertSame(0, $pay['summary']['unmarked']);
    }

    public function test_absences_reduce_monthly_pay_pro_rata_and_half_days_count_half(): void
    {
        $employee = $this->employeeWithSalary();
        $this->mark($employee, 'present', '2026-04-01', '2026-04-30');
        $this->mark($employee, 'absent', '2026-04-10', '2026-04-11');
        $this->mark($employee, 'half_day', '2026-04-20');

        $pay = $this->aprilPay($employee);

        $this->assertSame(27.5, $pay['payable_days']);
        $this->assertSame(22000.0, $pay['gross']); // 800 × 27.5
    }

    public function test_week_off_and_paid_leave_are_paid_on_monthly_salary(): void
    {
        $employee = $this->employeeWithSalary();
        $this->mark($employee, 'present', '2026-04-01', '2026-04-30');
        $this->mark($employee, 'week_off', '2026-04-05');
        $this->mark($employee, 'paid_leave', '2026-04-06');

        $this->assertSame(24000.0, $this->aprilPay($employee)['gross']);
    }

    public function test_per_day_pay_counts_worked_days_and_leaves_week_off_unpaid(): void
    {
        $employee = $this->employeeWithSalary(900, 'daily');
        $this->mark($employee, 'present', '2026-04-01', '2026-04-20');
        $this->mark($employee, 'week_off', '2026-04-21', '2026-04-24');
        $this->mark($employee, 'half_day', '2026-04-25', '2026-04-26');

        $pay = $this->aprilPay($employee);

        $this->assertSame(21.0, $pay['payable_days']);
        $this->assertSame(18900.0, $pay['gross']);
    }

    public function test_unmarked_days_are_unpaid_and_counted(): void
    {
        $employee = $this->employeeWithSalary();
        $this->mark($employee, 'present', '2026-04-01', '2026-04-25');

        $pay = $this->aprilPay($employee);

        $this->assertSame(5, $pay['summary']['unmarked']);
        $this->assertSame(20000.0, $pay['gross']);
    }

    public function test_a_mid_month_salary_revision_pays_each_day_at_its_own_rate(): void
    {
        $employee = $this->employeeWithSalary();
        EmployeeSalary::create(['employee_id' => $employee->id, 'pay_type' => 'monthly', 'amount' => 30000, 'effective_from' => '2026-04-16']);
        $this->mark($employee, 'present', '2026-04-01', '2026-04-30');

        $pay = $this->aprilPay($employee);

        $this->assertSame(27000.0, $pay['gross']); // 15 × 800 + 15 × 1000
        $this->assertSame('30000.00', $pay['salary']->amount);
    }

    public function test_days_before_the_salary_starts_are_neither_paid_nor_flagged(): void
    {
        $employee = $this->employeeWithSalary(24000, 'monthly', '2026-04-11');
        $this->mark($employee, 'present', '2026-04-11', '2026-04-30');

        $pay = $this->aprilPay($employee);

        $this->assertSame(20, $pay['salaried_days']);
        $this->assertSame(0, $pay['summary']['unmarked']);
        $this->assertSame(16000.0, $pay['gross']);
    }

    public function test_generating_payroll_creates_payslips_and_skips_employees_without_salary(): void
    {
        $paid = $this->employeeWithSalary();
        $this->mark($paid, 'present', '2026-04-01', '2026-04-30');
        Employee::factory()->create(['first_name' => 'No', 'last_name' => 'Salary']);

        $this->actingAs($this->admin)
            ->post('/admin/payouts/generate', ['month' => '2026-04'])
            ->assertRedirect('/admin/payouts?month=2026-04')
            ->assertSessionHas('success')
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'No Salary'));

        $payout = EmployeePayout::where('employee_id', $paid->id)->firstOrFail();
        $this->assertSame('24000.00', $payout->amount);
        $this->assertSame('pending', $payout->status);
        $this->assertSame('2026-04-01', $payout->period_start->toDateString());
        $this->assertSame('2026-04-30', $payout->period_end->toDateString());
        $this->assertSame(1, EmployeePayout::count());
    }

    public function test_regenerating_recalculates_drafts_keeps_adjustments_and_never_touches_paid(): void
    {
        $employee = $this->employeeWithSalary();
        $this->mark($employee, 'present', '2026-04-01', '2026-04-25');
        $this->payroll->generateForMonth($this->april);

        $payout = EmployeePayout::firstOrFail();
        $this->actingAs($this->admin)->post("/admin/payouts/{$payout->id}/items", ['type' => 'earning', 'label' => 'Festival bonus', 'amount' => 1000]);
        $this->assertSame('21000.00', $payout->fresh()->amount); // 20,000 + 1,000

        $this->mark($employee, 'present', '2026-04-26', '2026-04-30');
        $this->payroll->generateForMonth($this->april);
        $this->assertSame('25000.00', $payout->fresh()->amount); // 24,000 + 1,000
        $this->assertSame(1, EmployeePayout::count());

        $payout->update(['status' => 'paid']);
        $this->mark($employee, 'absent', '2026-04-01', '2026-04-10');
        $result = $this->payroll->generateForMonth($this->april);

        $this->assertSame(1, $result['skipped_paid']);
        $this->assertSame('25000.00', $payout->fresh()->amount);
    }

    public function test_deductions_reduce_net_pay_and_removing_a_line_restores_it(): void
    {
        $employee = $this->employeeWithSalary();
        $this->mark($employee, 'present', '2026-04-01', '2026-04-30');
        $this->payroll->generateForMonth($this->april);
        $payout = EmployeePayout::firstOrFail();

        $this->actingAs($this->admin)->post("/admin/payouts/{$payout->id}/items", ['type' => 'deduction', 'label' => 'Advance recovery', 'amount' => 2000]);

        $payout->refresh();
        $this->assertSame('-2000.00', $payout->adjustments_total);
        $this->assertSame('22000.00', $payout->amount);

        $this->actingAs($this->admin)->delete("/admin/payouts/{$payout->id}/items/{$payout->items()->first()->id}");
        $this->assertSame('24000.00', $payout->fresh()->amount);
    }

    public function test_marking_paid_records_payment_and_locks_the_payslip(): void
    {
        $employee = $this->employeeWithSalary();
        $this->mark($employee, 'present', '2026-04-01', '2026-04-30');
        $this->payroll->generateForMonth($this->april);
        $payout = EmployeePayout::firstOrFail();

        $this->actingAs($this->admin)->post("/admin/payouts/{$payout->id}/mark-paid", [
            'payment_method' => 'bank_transfer',
            'transaction_reference' => 'UTR778899',
            'paid_at' => '2026-05-02',
        ])->assertSessionHas('success');

        $payout->refresh();
        $this->assertSame('paid', $payout->status);
        $this->assertSame('bank_transfer', $payout->payment_method);
        $this->assertSame('UTR778899', $payout->transaction_reference);
        $this->assertSame('2026-05-02', $payout->paid_at->toDateString());

        $this->actingAs($this->admin)->post("/admin/payouts/{$payout->id}/items", ['type' => 'earning', 'label' => 'Late bonus', 'amount' => 100])
            ->assertSessionHas('error');
        $this->actingAs($this->admin)->post("/admin/payouts/{$payout->id}/recalculate")->assertSessionHas('error');
        $this->actingAs($this->admin)->delete("/admin/payouts/{$payout->id}")->assertSessionHas('error');
        $this->assertSame('24000.00', $payout->fresh()->amount);
    }

    public function test_salary_is_set_and_revised_from_the_employee_page(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->admin)->post("/admin/employees/{$employee->id}/salaries", [
            'pay_type' => 'monthly', 'amount' => 20000, 'effective_from' => '2026-01-01',
        ])->assertRedirect("/admin/employees/{$employee->id}");

        // Same effective date replaces that revision
        $this->actingAs($this->admin)->post("/admin/employees/{$employee->id}/salaries", [
            'pay_type' => 'monthly', 'amount' => 21000, 'effective_from' => '2026-01-01',
        ]);
        $this->actingAs($this->admin)->post("/admin/employees/{$employee->id}/salaries", [
            'pay_type' => 'daily', 'amount' => 900, 'effective_from' => '2026-06-01',
        ]);

        $employee->refresh();
        $this->assertCount(2, $employee->salaries);
        $this->assertSame('21000.00', $employee->salaryOn('2026-03-15')->amount);
        $this->assertSame('daily', $employee->salaryOn('2026-06-01')->pay_type);

        $this->actingAs($this->admin)->get("/admin/employees/{$employee->id}")
            ->assertOk()
            ->assertSee('Salary')
            ->assertSee('₹900 / day');
    }

    public function test_payroll_pages_render(): void
    {
        $employee = $this->employeeWithSalary();
        $this->mark($employee, 'present', '2026-04-01', '2026-04-28');
        $this->payroll->generateForMonth($this->april);
        $payout = EmployeePayout::firstOrFail();

        $this->actingAs($this->admin)->get('/admin/payouts?month=2026-04')
            ->assertOk()
            ->assertSee('Payslips — April 2026')
            ->assertSee('2 unmarked');

        $this->actingAs($this->admin)->get("/admin/payouts/{$payout->id}")
            ->assertOk()
            ->assertSee('Payslip — April 2026')
            ->assertSee('Basic salary earned')
            ->assertSee('Mark as Paid');
    }
}
