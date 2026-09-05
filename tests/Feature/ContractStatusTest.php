<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Contract;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The contract status state machine.
 *
 * Status drives what the office sees as actionable, and it is recomputed
 * nightly by a scheduled command, so the precedence between the states is the
 * thing worth pinning down.
 */
class ContractStatusTest extends TestCase
{
    use RefreshDatabase;

    private function staff(Contract $contract): Assignment
    {
        return Assignment::factory()->create([
            'contract_id' => $contract->id,
            'is_active' => true,
        ]);
    }

    public function test_a_contract_with_no_active_assignment_is_not_assigned(): void
    {
        $contract = Contract::factory()->create([
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);

        $contract->updateStatus();

        $this->assertSame('not_assigned', $contract->fresh()->status);
    }

    public function test_a_staffed_contract_well_before_its_end_date_is_active(): void
    {
        $contract = Contract::factory()->create([
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);
        $this->staff($contract);

        $contract->updateStatus();

        $this->assertSame('active', $contract->fresh()->status);
    }

    public function test_a_staffed_contract_inside_the_alert_window_is_closing_soon(): void
    {
        Setting::set('contract_alert_days', 7);

        $contract = Contract::factory()->create([
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
        ]);
        $this->staff($contract);

        $contract->updateStatus();

        $this->assertSame('closing_soon', $contract->fresh()->status);
    }

    public function test_a_contract_past_its_end_date_is_expired(): void
    {
        $contract = Contract::factory()->expired()->create();
        $this->staff($contract);

        $contract->updateStatus();

        $this->assertSame('expired', $contract->fresh()->status);
    }

    /**
     * Expiry outranks staffing: a finished contract is not "unstaffed work"
     * that someone should try to fill.
     */
    public function test_expiry_takes_precedence_over_missing_staff(): void
    {
        $contract = Contract::factory()->expired()->create();

        $contract->updateStatus();

        $this->assertSame('expired', $contract->fresh()->status);
    }

    /**
     * Staffing outranks the alert window, so an unstaffed contract stays
     * actionable even when it happens to be close to its end date.
     */
    public function test_missing_staff_takes_precedence_over_the_alert_window(): void
    {
        Setting::set('contract_alert_days', 7);

        $contract = Contract::factory()->create([
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
        ]);

        $contract->updateStatus();

        $this->assertSame('not_assigned', $contract->fresh()->status);
    }

    /**
     * A closed contract is a final state - the nightly recompute must not
     * quietly reopen it.
     */
    public function test_a_closed_contract_is_never_reopened(): void
    {
        $contract = Contract::factory()->closed()->create([
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);
        $this->staff($contract);

        $contract->updateStatus();

        $this->assertSame('closed', $contract->fresh()->status);
    }

    public function test_an_inactive_assignment_does_not_count_as_staffing(): void
    {
        $contract = Contract::factory()->create([
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);
        Assignment::factory()->inactive()->create(['contract_id' => $contract->id]);

        $contract->updateStatus();

        $this->assertSame('not_assigned', $contract->fresh()->status);
    }

    /**
     * A contract with no end date currently never expires. Documented here so
     * the behaviour is a decision rather than an accident.
     */
    public function test_a_contract_without_an_end_date_does_not_expire(): void
    {
        $contract = Contract::factory()->create([
            'duration_type' => 'custom',
            'end_date' => null,
        ]);
        $this->staff($contract);

        $contract->updateStatus();

        $this->assertFalse($contract->fresh()->isExpired());
        $this->assertSame('active', $contract->fresh()->status);
    }
}
