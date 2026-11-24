<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Contract;
use App\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Closing a contract.
 *
 * Closing is what happens when the work ends, so it has to release the staff
 * as well as mark the paperwork - otherwise employees stay flagged as placed
 * and unavailable for new contracts.
 */
class ContractClosureTest extends TestCase
{
    use RefreshDatabase;

    private ContractService $contracts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contracts = app(ContractService::class);
    }

    public function test_closing_records_the_reason_and_timestamp(): void
    {
        $contract = Contract::factory()->create(['status' => 'active']);

        $this->contracts->closeContract($contract, 'Patient recovered');

        $contract->refresh();
        $this->assertSame('closed', $contract->status);
        $this->assertSame('Patient recovered', $contract->closure_reason);
        $this->assertNotNull($contract->closed_at);
    }

    public function test_closing_releases_every_active_assignment(): void
    {
        $contract = Contract::factory()->create(['status' => 'active']);
        $a = Assignment::factory()->create(['contract_id' => $contract->id, 'is_active' => true]);
        $b = Assignment::factory()->create(['contract_id' => $contract->id, 'is_active' => true]);

        $this->contracts->closeContract($contract, 'Ended early');

        $this->assertFalse((bool) $a->fresh()->is_active);
        $this->assertFalse((bool) $b->fresh()->is_active);
        $this->assertNotNull($a->fresh()->end_date);
    }

    public function test_a_released_assignment_is_dated_to_the_closure(): void
    {
        $contract = Contract::factory()->create(['status' => 'active']);
        $assignment = Assignment::factory()->create([
            'contract_id' => $contract->id,
            'is_active' => true,
            'end_date' => null,
        ]);

        $this->contracts->closeContract($contract, 'Ended');

        $this->assertSame(
            now()->toDateString(),
            $assignment->fresh()->end_date->toDateString()
        );
    }

    public function test_assignments_on_other_contracts_are_untouched(): void
    {
        $contract = Contract::factory()->create(['status' => 'active']);
        Assignment::factory()->create(['contract_id' => $contract->id, 'is_active' => true]);

        $other = Contract::factory()->create(['status' => 'active']);
        $untouched = Assignment::factory()->create(['contract_id' => $other->id, 'is_active' => true]);

        $this->contracts->closeContract($contract, 'Ended');

        $this->assertTrue((bool) $untouched->fresh()->is_active);
    }

    /**
     * A closed contract must not be recomputed back into an active state by
     * the nightly status job just because its staff were released.
     */
    public function test_a_closed_contract_stays_closed_after_the_nightly_recompute(): void
    {
        $contract = Contract::factory()->create(['status' => 'active']);
        Assignment::factory()->create(['contract_id' => $contract->id, 'is_active' => true]);

        $this->contracts->closeContract($contract, 'Ended');
        $this->contracts->updateContractStatuses();

        $this->assertSame('closed', $contract->fresh()->status);
    }
}
