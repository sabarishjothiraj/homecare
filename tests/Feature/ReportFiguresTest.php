<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\Employee;
use App\Models\EmployeePayout;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Summary cards, balances and footer totals: they must describe the rows the
 * filters match, whole, and add up only what genuinely adds up.
 */
class ReportFiguresTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ReportService $reports;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
        $this->reports = app(ReportService::class);
    }

    private function cards(string $report, array $filters = [])
    {
        return collect($this->reports->summary($report, $filters))->keyBy('label');
    }

    public function test_revenue_refunds_follow_the_type_filter_and_drop_out_under_a_method_filter(): void
    {
        Payment::factory()->create(['payment_type' => 'employee_activation', 'contract_id' => null, 'employee_id' => Employee::factory(), 'amount' => 3000]);
        Payment::factory()->create(['amount' => 10000, 'method' => 'bank_transfer']);
        Contract::factory()->create(['status' => 'closed', 'closed_at' => now(), 'refund_amount' => 5000]);

        $activation = $this->cards('revenue', ['payment_type' => 'employee_activation']);
        $this->assertSame(format_currency(3000), $activation['Collected']['value']);
        $this->assertSame(format_currency(0), $activation['Refunds']['value']);
        $this->assertSame(format_currency(3000), $activation['Net']['value']);

        $contracts = $this->cards('revenue', ['payment_type' => 'contract']);
        $this->assertSame(format_currency(5000), $contracts['Net']['value']);

        // Refunds carry no payment method, so they are not netted against one
        $cash = $this->cards('revenue', ['payment_method' => 'cash']);
        $this->assertSame(format_currency(3000), $cash['Collected']['value']);
        $this->assertFalse($cash->has('Refunds'));
        $this->assertFalse($cash->has('Net'));
    }

    public function test_closed_contracts_are_settled_and_owe_nothing(): void
    {
        $patient = Patient::factory()->create();
        $closed = Contract::factory()->create(['patient_id' => $patient->id, 'amount' => 30000, 'status' => 'closed', 'closed_at' => now(), 'refund_amount' => 5000]);
        Payment::factory()->create(['contract_id' => $closed->id, 'amount' => 15000]);
        $open = Contract::factory()->create(['patient_id' => $patient->id, 'amount' => 10000, 'status' => 'active']);
        Payment::factory()->create(['contract_id' => $open->id, 'amount' => 4000]);

        $row = $this->reports->getContractReport()->firstWhere('Contract Number', $closed->contract_number);
        $this->assertSame(0.0, $row['Balance (₹)']);
        $this->assertSame('Settled', $row['Payment Status']);

        $contracts = $this->cards('contracts');
        $this->assertSame(format_currency(40000), $contracts['Total Value']['value']);
        $this->assertSame(format_currency(19000), $contracts['Collected']['value']);
        $this->assertSame(format_currency(6000), $contracts['Outstanding']['value']);

        $this->assertSame(6000.0, $this->reports->getPatientReport()->firstWhere('ID', $patient->id)['Balance (₹)']);
        $this->assertSame(format_currency(6000), $this->cards('patients')['Balance Due']['value']);

        $this->assertSame([$closed->contract_number], $this->reports->getContractReport(['payment_status' => 'settled'])->pluck('Contract Number')->all());
        $this->assertSame([$open->contract_number], $this->reports->getContractReport(['payment_status' => 'partial'])->pluck('Contract Number')->all());
    }

    public function test_payment_status_filter_matches_the_status_shown_on_each_row(): void
    {
        $unpaid = Contract::factory()->create(['amount' => 1000, 'status' => 'active']);
        $partial = Contract::factory()->create(['amount' => 1000, 'status' => 'active']);
        Payment::factory()->create(['contract_id' => $partial->id, 'amount' => 400]);
        Payment::factory()->pending()->create(['contract_id' => $partial->id, 'amount' => 600]);
        $paid = Contract::factory()->create(['amount' => 1000, 'status' => 'expired']);
        Payment::factory()->create(['contract_id' => $paid->id, 'amount' => 1200]);

        foreach (['unpaid' => $unpaid, 'partial' => $partial, 'paid' => $paid] as $status => $contract) {
            $rows = $this->reports->getContractReport(['payment_status' => $status]);

            $this->assertSame([$contract->contract_number], $rows->pluck('Contract Number')->all(), $status);
            $this->assertSame(ucfirst($status), $rows->first()['Payment Status']);
            $this->assertSame('1', $this->cards('contracts', ['payment_status' => $status])['Contracts']['value']);
        }
    }

    /**
     * An overpaid contract used to hide the balance owed on another one.
     */
    public function test_patient_balance_is_owed_per_contract(): void
    {
        $patient = Patient::factory()->create();
        $overpaid = Contract::factory()->create(['patient_id' => $patient->id, 'amount' => 5000, 'status' => 'active']);
        Payment::factory()->create(['contract_id' => $overpaid->id, 'amount' => 8000]);
        Contract::factory()->create(['patient_id' => $patient->id, 'amount' => 5000, 'status' => 'active']);

        $row = $this->reports->getPatientReport()->firstWhere('ID', $patient->id);

        $this->assertSame(5000.0, $row['Balance (₹)']);
        $this->assertSame(format_currency(5000), $this->cards('patients')['Balance Due']['value']);
    }

    public function test_footer_totals_skip_line_amounts_and_cancelled_payouts(): void
    {
        // One contract line held by two employees in turn: the line amount repeats
        $contract = Contract::factory()->create(['amount' => 30000]);
        $item = ContractItem::factory()->create(['contract_id' => $contract->id, 'amount' => 30000]);
        $first = Assignment::factory()->create(['contract_id' => $contract->id, 'contract_item_id' => $item->id, 'line_amount' => 30000, 'is_active' => false, 'end_date' => now()]);
        Assignment::factory()->create(['contract_id' => $contract->id, 'contract_item_id' => $item->id, 'line_amount' => 30000, 'replaced_assignment_id' => $first->id]);

        $this->assertSame([], $this->reports->totals('assignments')['values']);
        $this->actingAs($this->admin)->get('/admin/reports/assignments')
            ->assertOk()
            ->assertDontSee('60,000.00');

        $employee = Employee::factory()->create();
        $month = 0;
        foreach (['paid' => 900, 'pending' => 400, 'cancelled' => 7777] as $status => $amount) {
            $start = now()->startOfMonth()->subMonths(++$month);
            EmployeePayout::create(['employee_id' => $employee->id, 'frequency' => 'monthly', 'rate' => 100, 'period_start' => $start, 'period_end' => $start->copy()->endOfMonth(), 'amount' => $amount, 'gross_amount' => $amount, 'status' => $status]);
        }

        $totals = $this->reports->totals('payouts');
        $this->assertSame(1300.0, $totals['values']['Net Pay (₹)']);
        $this->assertSame(1300.0, $totals['values']['Gross (₹)']);
        $this->assertArrayNotHasKey('Salary Rate (₹)', $totals['values']);
        $this->assertSame(format_currency(1300), $this->cards('payouts')['Net Payroll']['value']);

        $this->actingAs($this->admin)->get('/admin/reports/payouts')
            ->assertOk()
            ->assertSee('Total (excl. cancelled)')
            ->assertSee('1,300.00')
            ->assertDontSee('9,077.00');
    }

    public function test_report_pages_are_paginated_while_totals_and_exports_cover_everything(): void
    {
        $contract = Contract::factory()->create();
        Payment::factory()->count(60)->create(['contract_id' => $contract->id, 'amount' => 100]);

        $this->actingAs($this->admin)->get('/admin/reports/revenue')
            ->assertOk()
            ->assertSee('60 records found')
            ->assertSee(format_currency(6000))
            ->assertSee('6,000.00')
            ->assertViewHas('data', fn ($data) => $data->count() === 50 && $data->total() === 60);

        $this->actingAs($this->admin)->get('/admin/reports/revenue?page=2')
            ->assertOk()
            ->assertViewHas('data', fn ($data) => $data->count() === 10);

        $csv = $this->actingAs($this->admin)->get('/admin/reports/revenue?export=1')->streamedContent();
        // Header plus one line per payment
        $this->assertCount(61, explode("\n", trim($csv)));
    }
}
