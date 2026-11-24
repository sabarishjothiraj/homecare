<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\Employee;
use App\Models\EmployeePayout;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
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

    public function test_every_report_page_loads_with_a_summary(): void
    {
        $this->actingAs($this->admin)->get('/admin/reports')
            ->assertOk()
            ->assertSee('Contract Report')
            ->assertSee('Payout Report');

        foreach (['patients', 'employees', 'contracts', 'assignments', 'revenue', 'payouts'] as $report) {
            $this->actingAs($this->admin)->get("/admin/reports/{$report}")
                ->assertOk()
                ->assertSee('Filters')
                ->assertSee('Results');
        }
    }

    /**
     * "To" dates used to be compared against midnight, dropping that whole day.
     */
    public function test_date_filters_include_the_end_day(): void
    {
        Payment::factory()->create(['amount' => 500, 'paid_at' => now()->setTime(15, 30)]);

        $rows = $this->reports->getRevenueReport([
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        $this->assertCount(1, $rows);
    }

    public function test_revenue_handles_multi_service_contracts_and_activation_payments(): void
    {
        $nursing = Service::factory()->create(['name' => 'Nursing']);
        $physio = Service::factory()->create(['name' => 'Physiotherapy']);
        $contract = Contract::factory()->create(['service_id' => $nursing->id]);
        ContractItem::factory()->create(['contract_id' => $contract->id, 'service_id' => $nursing->id]);
        ContractItem::factory()->create(['contract_id' => $contract->id, 'service_id' => $physio->id]);
        Payment::factory()->create([
            'contract_id' => $contract->id,
            'amount' => 2500,
            'method' => 'bank_transfer',
            'reference' => 'UTR123',
        ]);

        $employee = Employee::factory()->create(['first_name' => 'Asha', 'last_name' => 'K']);
        Payment::factory()->create([
            'payment_type' => 'employee_activation',
            'contract_id' => null,
            'employee_id' => $employee->id,
            'amount' => 300,
        ]);

        $rows = $this->reports->getRevenueReport();

        $bank = $rows->firstWhere('Reference', 'UTR123');
        $this->assertSame('Bank Transfer', $bank['Method']);
        $this->assertSame(2500.0, $bank['Amount (₹)']);
        $this->assertSame('Nursing, Physiotherapy', $bank['Services']);

        $activation = $rows->firstWhere('Type', 'Employee Activation');
        $this->assertSame('Asha K', $activation['Paid By']);
    }

    public function test_revenue_summary_nets_off_refunds(): void
    {
        Payment::factory()->create(['amount' => 10000]);
        Contract::factory()->create(['status' => 'closed', 'closed_at' => now(), 'refund_amount' => 1500]);

        $summary = collect($this->reports->summary('revenue'))->keyBy('label');

        $this->assertSame(format_currency(10000), $summary['Collected']['value']);
        $this->assertSame(format_currency(1500), $summary['Refunds']['value']);
        $this->assertSame(format_currency(8500), $summary['Net']['value']);
    }

    public function test_contract_report_shows_collection_balance_payment_details_and_refund(): void
    {
        $partial = Contract::factory()->create([
            'amount' => 10000,
            'payment_method' => 'cheque',
            'payment_details' => 'Cheque 000123, SBI',
        ]);
        Payment::factory()->create(['contract_id' => $partial->id, 'amount' => 4000]);
        Payment::factory()->pending()->create(['contract_id' => $partial->id, 'amount' => 6000]);

        $closed = Contract::factory()->create([
            'amount' => 5000,
            'status' => 'closed',
            'closed_at' => now(),
            'closure_reason' => 'Patient recovered',
            'refund_amount' => 1000,
            'refund_details' => 'UPI ref 998',
        ]);

        $rows = $this->reports->getContractReport()->keyBy('Contract Number');

        $row = $rows[$partial->contract_number];
        $this->assertSame(4000.0, $row['Paid (₹)']);
        $this->assertSame(6000.0, $row['Balance (₹)']);
        $this->assertSame('Partial', $row['Payment Status']);
        $this->assertSame('Cheque', $row['Payment Method']);
        $this->assertSame('Cheque 000123, SBI', $row['Payment Details']);

        // Closing settles a contract: the unused part is refunded, not owed
        $row = $rows[$closed->contract_number];
        $this->assertSame('Settled', $row['Payment Status']);
        $this->assertSame(0.0, $row['Balance (₹)']);
        $this->assertSame(1000.0, $row['Refund (₹)']);
        $this->assertSame('UPI ref 998', $row['Refund Details']);
        $this->assertSame('Patient recovered', $row['Closure Reason']);

        $this->assertSame(
            [$partial->contract_number],
            $this->reports->getContractReport(['payment_status' => 'partial'])->pluck('Contract Number')->all()
        );
    }

    public function test_patient_report_totals_contract_money_and_current_staff(): void
    {
        $patient = Patient::factory()->create(['emergency_contact_name' => 'Ravi', 'emergency_contact_phone' => '9876543210']);
        $contract = Contract::factory()->create(['patient_id' => $patient->id, 'amount' => 8000, 'status' => 'active']);
        Payment::factory()->create(['contract_id' => $contract->id, 'amount' => 3000]);
        $nurse = Employee::factory()->create(['first_name' => 'Meena', 'last_name' => 'R']);
        Assignment::factory()->create(['contract_id' => $contract->id, 'employee_id' => $nurse->id]);

        $row = $this->reports->getPatientReport()->firstWhere('ID', $patient->id);

        $this->assertSame(8000.0, $row['Contract Value (₹)']);
        $this->assertSame(3000.0, $row['Paid (₹)']);
        $this->assertSame(5000.0, $row['Balance (₹)']);
        $this->assertSame('Meena R', $row['Current Employees']);
        $this->assertSame('Ravi (9876543210)', $row['Emergency Contact']);
    }

    public function test_employee_report_shows_services_availability_and_split_payouts(): void
    {
        $service = Service::factory()->create(['name' => 'Elder Care']);
        $employee = Employee::factory()->create(['first_name' => 'Latha', 'last_name' => 'S']);
        $employee->services()->attach($service->id);
        Employee::factory()->create(['first_name' => 'Free', 'last_name' => 'Nurse']);
        Assignment::factory()->create(['employee_id' => $employee->id]);

        EmployeePayout::create(['employee_id' => $employee->id, 'frequency' => 'monthly', 'rate' => 100, 'period_start' => now()->subMonth(), 'period_end' => now(), 'amount' => 900, 'status' => 'paid']);
        EmployeePayout::create(['employee_id' => $employee->id, 'frequency' => 'monthly', 'rate' => 100, 'period_start' => now(), 'period_end' => now()->addMonth(), 'amount' => 400, 'status' => 'pending']);
        EmployeePayout::create(['employee_id' => $employee->id, 'frequency' => 'monthly', 'rate' => 100, 'period_start' => now(), 'period_end' => now()->addMonth(), 'amount' => 7777, 'status' => 'cancelled']);

        $row = $this->reports->getEmployeeReport()->firstWhere('Name', 'Latha S');

        $this->assertSame('Elder Care', $row['Services']);
        $this->assertStringStartsWith('Assigned: ', $row['Availability']);
        $this->assertSame(900.0, $row['Payouts Paid (₹)']);
        $this->assertSame(400.0, $row['Payouts Pending (₹)']);

        $this->assertSame(['Free Nurse'], $this->reports->getEmployeeReport(['availability' => 'available'])->pluck('Name')->all());
        $this->assertSame(['Latha S'], $this->reports->getEmployeeReport(['service_id' => $service->id])->pluck('Name')->all());
    }

    public function test_payout_report_lists_payouts_with_status_totals(): void
    {
        $employee = Employee::factory()->create(['first_name' => 'Kavya', 'last_name' => 'M']);
        EmployeePayout::create(['employee_id' => $employee->id, 'frequency' => 'weekly', 'rate' => 50, 'period_start' => now()->subWeek(), 'period_end' => now(), 'amount' => 350, 'status' => 'paid', 'paid_at' => now()]);
        EmployeePayout::create(['employee_id' => $employee->id, 'frequency' => 'weekly', 'rate' => 50, 'period_start' => now(), 'period_end' => now()->addWeek(), 'amount' => 350, 'status' => 'pending']);

        $rows = $this->reports->getPayoutReport();
        $summary = collect($this->reports->summary('payouts'))->keyBy('label');

        $this->assertCount(2, $rows);
        $this->assertSame('Kavya M', $rows->first()['Employee']);
        $this->assertSame(format_currency(350), $summary['Paid']['value']);
        $this->assertSame(format_currency(350), $summary['Pending']['value']);
        $this->assertCount(1, $this->reports->getPayoutReport(['status' => 'pending']));
    }

    public function test_csv_export_keeps_amounts_numeric_and_is_excel_friendly(): void
    {
        Payment::factory()->create(['amount' => 1234.5]);

        $response = $this->actingAs($this->admin)->get('/admin/reports/revenue?export=1');
        $response->assertOk();

        // Streamed, not written to a file first
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Amount (₹)', $csv);
        $this->assertStringContainsString(',1234.5,', $csv);
        $this->assertStringNotContainsString('₹1,234', $csv);
    }

    public function test_quick_export_supports_the_new_reports(): void
    {
        Contract::factory()->create();

        $this->actingAs($this->admin)
            ->post('/admin/reports/export', ['report_type' => 'contracts'])
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->actingAs($this->admin)
            ->post('/admin/reports/export', ['report_type' => 'payouts'])
            ->assertOk();
    }
}
