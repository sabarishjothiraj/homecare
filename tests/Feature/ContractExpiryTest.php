<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\Setting;
use App\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What happens to staff when work runs out on its own.
 *
 * Availability everywhere keys on is_active, so an assignment left active on a
 * finished contract (or past its own end date) keeps the employee flagged as
 * placed and unable to take new work.
 */
class ContractExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_expiry_releases_every_assignment_on_the_contract(): void
    {
        $contract = Contract::factory()->expired()->create(['status' => 'active']);
        $primary = Assignment::factory()->create(['contract_id' => $contract->id, 'is_active' => true, 'end_date' => null]);
        $cover = Assignment::factory()->create([
            'contract_id' => $contract->id,
            'cover_for_assignment_id' => $primary->id,
            'is_active' => true,
            'end_date' => now()->addWeek()->toDateString(),
        ]);

        $contract->updateStatus();

        $this->assertSame('expired', $contract->fresh()->status);
        $this->assertFalse((bool) $primary->fresh()->is_active);
        $this->assertFalse((bool) $cover->fresh()->is_active);
    }

    public function test_released_assignments_end_on_the_contract_end_date_at_the_latest(): void
    {
        $contract = Contract::factory()->expired()->create(['status' => 'active']);
        $open = Assignment::factory()->create(['contract_id' => $contract->id, 'is_active' => true, 'end_date' => null]);
        $earlier = Assignment::factory()->create([
            'contract_id' => $contract->id,
            'is_active' => true,
            'end_date' => $contract->end_date->copy()->subDays(5)->toDateString(),
        ]);

        $contract->updateStatus();

        $this->assertSame($contract->end_date->toDateString(), $open->fresh()->end_date->toDateString());
        $this->assertSame($contract->end_date->copy()->subDays(5)->toDateString(), $earlier->fresh()->end_date->toDateString());
    }

    public function test_an_employee_on_an_expired_contract_becomes_available_again(): void
    {
        $contract = Contract::factory()->expired()->create(['status' => 'active']);
        $employee = Employee::factory()->create();
        Assignment::factory()->create(['contract_id' => $contract->id, 'employee_id' => $employee->id, 'is_active' => true]);

        app(ContractService::class)->updateContractStatuses();

        $this->assertTrue(Employee::available()->whereKey($employee->id)->exists());
    }

    public function test_the_nightly_run_ends_a_primary_past_its_own_end_date(): void
    {
        $contract = Contract::factory()->create([
            'status' => 'active',
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
        ]);
        $lapsed = Assignment::factory()->create([
            'contract_id' => $contract->id,
            'is_active' => true,
            'end_date' => now()->subDay()->toDateString(),
        ]);
        $cover = Assignment::factory()->create([
            'contract_id' => $contract->id,
            'cover_for_assignment_id' => $lapsed->id,
            'is_active' => true,
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
        ]);
        $running = Assignment::factory()->create([
            'contract_id' => $contract->id,
            'is_active' => true,
            'end_date' => now()->toDateString(),
        ]);

        app(ContractService::class)->updateContractStatuses();

        $this->assertFalse((bool) $lapsed->fresh()->is_active);
        $this->assertFalse((bool) $cover->fresh()->is_active, 'A cover ends with the assignment it covers.');
        $this->assertTrue((bool) $running->fresh()->is_active, 'Its last day is today.');
    }

    public function test_a_line_whose_only_employee_lapsed_reads_as_not_assigned(): void
    {
        $contract = Contract::factory()->create([
            'status' => 'active',
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
        ]);
        Assignment::factory()->create([
            'contract_id' => $contract->id,
            'is_active' => true,
            'end_date' => now()->subDay()->toDateString(),
        ]);

        app(ContractService::class)->updateContractStatuses();

        $this->assertSame('not_assigned', $contract->fresh()->status);
    }

    /**
     * The command used to print "Successfully updated  contract(s)." with no
     * number, because the service method returned void.
     */
    public function test_the_status_run_reports_how_many_statuses_changed(): void
    {
        Contract::factory()->expired()->create(['status' => 'active']);
        Contract::factory()->expired()->create(['status' => 'active']);
        $unchanged = Contract::factory()->create([
            'status' => 'not_assigned',
            'end_date' => now()->addYear()->toDateString(),
        ]);

        $this->assertSame(2, app(ContractService::class)->updateContractStatuses());
        $this->assertSame(0, app(ContractService::class)->updateContractStatuses());

        $this->artisan('contracts:update-status')
            ->expectsOutput('Successfully updated 0 contract(s).')
            ->assertSuccessful();

        $this->assertSame('not_assigned', $unchanged->fresh()->status);
    }

    /**
     * end_date is a DATE: compared against now() (with the time of day), a
     * contract ending today dropped out of the dashboard's Expiring Soon list.
     */
    public function test_expiring_soon_includes_a_contract_ending_today(): void
    {
        Setting::set('contract_alert_days', 7);

        $today = Contract::factory()->create(['status' => 'active', 'end_date' => now()->toDateString()]);
        $lastAlertDay = Contract::factory()->create(['status' => 'active', 'end_date' => now()->addDays(7)->toDateString()]);
        $tooFar = Contract::factory()->create(['status' => 'active', 'end_date' => now()->addDays(8)->toDateString()]);
        $past = Contract::factory()->create(['status' => 'active', 'end_date' => now()->subDay()->toDateString()]);

        $ids = app(ContractService::class)->getExpiringContracts()->pluck('id');

        $this->assertTrue($ids->contains($today->id));
        $this->assertTrue($ids->contains($lastAlertDay->id));
        $this->assertFalse($ids->contains($tooFar->id));
        $this->assertFalse($ids->contains($past->id));
    }
}
