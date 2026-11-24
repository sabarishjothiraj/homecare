<?php

namespace Tests\Feature;

use App\Exceptions\PayrollException;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeePayout;
use App\Models\EmployeeSalary;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\CarbonPeriod;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * April 2026 has 30 days, so a ₹24,000 monthly salary is ₹800 a day.
 */
class PayrollSafeguardsTest extends TestCase
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function employeeWithSalary(float $amount = 24000, string $from = '2026-01-01', array $attributes = []): Employee
    {
        $employee = Employee::factory()->create($attributes);
        EmployeeSalary::create(['employee_id' => $employee->id, 'pay_type' => 'monthly', 'amount' => $amount, 'effective_from' => $from]);

        return $employee;
    }

    private function mark(Employee $employee, string $status, string $from, ?string $to = null): void
    {
        foreach (CarbonPeriod::create($from, $to ?? $from) as $day) {
            Attendance::updateOrCreate(['employee_id' => $employee->id, 'date' => $day->toDateString()], ['status' => $status]);
        }
    }

    private function aprilPayslip(Employee $employee): EmployeePayout
    {
        $this->payroll->generateForMonth($this->april);

        return EmployeePayout::where('employee_id', $employee->id)->firstOrFail();
    }

    private function pay(EmployeePayout $payout, array $extra = [])
    {
        return $this->actingAs($this->admin)->post("/admin/payouts/{$payout->id}/mark-paid", $extra + [
            'payment_method' => 'bank_transfer',
            'paid_at' => '2026-05-02',
        ]);
    }

    public function test_a_month_is_parsed_as_that_month_even_on_the_31st(): void
    {
        Carbon::setTestNow('2026-10-31 10:00:00');
        $this->assertSame('2026-09-01 00:00:00', PayrollService::parseMonth('2026-09')->toDateTimeString());

        Carbon::setTestNow('2026-03-30 10:00:00');
        $this->assertSame('2026-02-01', PayrollService::parseMonth('2026-02')->toDateString());
        $this->assertNull(PayrollService::parseMonth('2026-13'));
        $this->assertNull(PayrollService::parseMonth('garbage'));
    }

    public function test_september_payroll_can_be_generated_and_viewed_on_october_31(): void
    {
        Carbon::setTestNow('2026-10-31 10:00:00');
        $employee = $this->employeeWithSalary();
        $this->mark($employee, 'present', '2026-09-01', '2026-09-30');

        $this->actingAs($this->admin)
            ->post('/admin/payouts/generate', ['month' => '2026-09'])
            ->assertRedirect('/admin/payouts?month=2026-09');

        $payout = EmployeePayout::firstOrFail();
        $this->assertSame('2026-09-01', $payout->period_start->toDateString());
        $this->assertSame('24000.00', $payout->amount);

        $this->actingAs($this->admin)->get('/admin/payouts?month=2026-09')
            ->assertOk()
            ->assertSee('Payslips — September 2026');

        Carbon::setTestNow('2026-03-31 10:00:00');
        $this->actingAs($this->admin)->get('/admin/attendance/register?month=2026-02')
            ->assertOk()
            ->assertSee('February 2026');
    }

    public function test_payroll_cannot_be_generated_for_a_month_that_has_not_started(): void
    {
        $this->employeeWithSalary();
        $next = now()->startOfMonth()->addMonth()->format('Y-m');

        $this->actingAs($this->admin)
            ->post('/admin/payouts/generate', ['month' => $next])
            ->assertSessionHas('error');

        $this->assertSame(0, EmployeePayout::count());
    }

    public function test_the_database_allows_only_one_live_payslip_per_employee_and_period(): void
    {
        $employee = Employee::factory()->create();
        $period = ['employee_id' => $employee->id, 'rate' => 24000, 'period_start' => '2026-04-01', 'period_end' => '2026-04-30', 'amount' => 24000];

        EmployeePayout::create($period + ['status' => 'cancelled']);
        EmployeePayout::create($period + ['status' => 'pending']); // a cancelled one doesn't count

        $this->expectException(QueryException::class);
        EmployeePayout::create($period + ['status' => 'pending']);
    }

    public function test_a_deduction_larger_than_the_net_pay_is_refused(): void
    {
        $employee = $this->employeeWithSalary();
        $this->mark($employee, 'present', '2026-04-01', '2026-04-30');
        $payout = $this->aprilPayslip($employee);

        $this->actingAs($this->admin)->post("/admin/payouts/{$payout->id}/items", ['type' => 'deduction', 'label' => 'Advance recovery', 'amount' => 25000])
            ->assertSessionHas('error', fn ($message) => str_contains($message, '₹24,000.00'));
        $this->assertSame(0, $payout->items()->count());

        $this->actingAs($this->admin)->post("/admin/payouts/{$payout->id}/items", ['type' => 'deduction', 'label' => 'Advance recovery', 'amount' => 24000])
            ->assertSessionHas('success');
        $this->assertSame('0.00', $payout->fresh()->amount);
    }

    public function test_a_payslip_whose_deductions_exceed_its_earnings_cannot_be_paid(): void
    {
        $employee = $this->employeeWithSalary();
        $this->mark($employee, 'present', '2026-04-01', '2026-04-30');
        $payout = $this->aprilPayslip($employee);
        $this->actingAs($this->admin)->post("/admin/payouts/{$payout->id}/items", ['type' => 'deduction', 'label' => 'Advance recovery', 'amount' => 20000]);

        // Gross falls to ₹16,000 after 10 absences; the ₹20,000 deduction now exceeds it
        $this->mark($employee, 'absent', '2026-04-01', '2026-04-10');
        $this->payroll->generateForMonth($this->april);
        $payout->refresh();
        $this->assertSame('0.00', $payout->amount);
        $this->assertSame(4000.0, $payout->shortfall());

        $this->actingAs($this->admin)->get("/admin/payouts/{$payout->id}")->assertSee('Deductions exceed earnings by ₹4,000.00');
        $this->pay($payout)->assertSessionHas('error', fn ($message) => str_contains($message, '₹4,000.00'));
        $this->assertSame('pending', $payout->fresh()->status);
    }

    public function test_mark_paid_recalculates_and_refuses_when_attendance_changed_since_generation(): void
    {
        $employee = $this->employeeWithSalary();
        $this->mark($employee, 'present', '2026-04-01', '2026-04-30');
        $payout = $this->aprilPayslip($employee);
        $this->assertSame('24000.00', $payout->amount);

        $this->mark($employee, 'absent', '2026-04-01', '2026-04-05');

        $this->pay($payout, ['expected_amount' => '24000.00'])
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'recalculated') && str_contains($message, '₹20,000.00'));
        $payout->refresh();
        $this->assertSame('pending', $payout->status);
        $this->assertSame('20000.00', $payout->amount);

        $this->pay($payout, ['expected_amount' => '20000.00'])->assertSessionHas('success');
        $this->assertSame('paid', $payout->fresh()->status);
        $this->assertSame('20000.00', $payout->fresh()->amount);
    }

    public function test_mark_paid_refuses_an_amount_other_than_the_one_confirmed(): void
    {
        $employee = $this->employeeWithSalary();
        $this->mark($employee, 'present', '2026-04-01', '2026-04-30');
        $payout = $this->aprilPayslip($employee);

        $this->pay($payout, ['expected_amount' => '23000.00'])->assertSessionHas('error');
        $this->assertSame('pending', $payout->fresh()->status);
    }

    public function test_an_incomplete_payslip_needs_an_explicit_pay_anyway(): void
    {
        $employee = $this->employeeWithSalary();
        $this->mark($employee, 'present', '2026-04-01', '2026-04-28');
        $payout = $this->aprilPayslip($employee);

        $this->actingAs($this->admin)->get("/admin/payouts/{$payout->id}")->assertSee('Pay anyway');
        $this->pay($payout)->assertSessionHas('error', fn ($message) => str_contains($message, '2 days not marked'));
        $this->assertSame('pending', $payout->fresh()->status);

        $this->pay($payout, ['allow_incomplete' => '1'])->assertSessionHas('success');
        $this->assertSame('paid', $payout->fresh()->status);
    }

    public function test_a_month_that_has_not_ended_needs_an_explicit_pay_anyway(): void
    {
        Carbon::setTestNow('2026-04-20 10:00:00');
        $employee = $this->employeeWithSalary();
        $this->mark($employee, 'present', '2026-04-01', '2026-04-20');

        $this->actingAs($this->admin)->post('/admin/payouts/generate', ['month' => '2026-04'])->assertSessionHas('success');
        $payout = EmployeePayout::firstOrFail();

        $this->pay($payout, ['paid_at' => '2026-04-20'])
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'the period runs until Apr 30, 2026'));
        $this->assertSame('pending', $payout->fresh()->status);
    }

    public function test_regenerating_zeroes_a_draft_whose_salary_was_removed(): void
    {
        $employee = $this->employeeWithSalary(24000, '2026-01-01', ['first_name' => 'Wrong', 'last_name' => 'Person']);
        $this->mark($employee, 'present', '2026-04-01', '2026-04-30');
        $payout = $this->aprilPayslip($employee);
        $this->assertSame('24000.00', $payout->amount);

        EmployeeSalary::where('employee_id', $employee->id)->delete();

        $this->actingAs($this->admin)
            ->post('/admin/payouts/generate', ['month' => '2026-04'])
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'recalculated to ₹0') && str_contains($message, 'Wrong Person'));

        $payout->refresh();
        $this->assertSame('0.00', $payout->amount);
        $this->assertSame('0.00', $payout->gross_amount);
        $this->assertSame(1, EmployeePayout::count());
    }

    public function test_writes_recheck_the_status_so_a_payslip_paid_meanwhile_is_left_alone(): void
    {
        $employee = $this->employeeWithSalary();
        $this->mark($employee, 'present', '2026-04-01', '2026-04-30');
        $stale = $this->aprilPayslip($employee); // loaded while still pending

        // Another request pays it
        EmployeePayout::whereKey($stale->id)->update(['status' => 'paid']);
        $this->mark($employee, 'absent', '2026-04-01', '2026-04-10');

        foreach ([
            fn () => $this->payroll->recalculate($stale),
            fn () => $this->payroll->addItem($stale, ['type' => 'earning', 'label' => 'Bonus', 'amount' => 500]),
            fn () => $this->payroll->markPaid($stale, ['payment_method' => 'cash']),
            fn () => $this->payroll->deleteDraft($stale),
        ] as $write) {
            try {
                $write();
                $this->fail('A write to a paid payslip went through.');
            } catch (PayrollException) {
                // refused, as expected
            }
        }

        $fresh = $stale->fresh();
        $this->assertSame('24000.00', $fresh->amount);
        $this->assertSame('24000.00', $fresh->gross_amount);
        $this->assertNull($fresh->payment_method);
        $this->assertSame(0, $fresh->items()->count());
    }
}
