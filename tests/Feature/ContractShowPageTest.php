<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The contract detail and payment pages for historical contracts.
 *
 * A patient can be deleted once their contracts have ended, and the page used
 * to 500 building the "View Patient Details" link for them.
 */
class ContractShowPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_contract_whose_patient_was_deleted_still_opens(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $contract = Contract::factory()->closed()->create();
        Assignment::factory()->inactive()->create(['contract_id' => $contract->id]);
        $name = $contract->patient->name;
        $contract->patient->delete();

        $this->actingAs($admin)->get(route('admin.contracts.show', $contract))
            ->assertOk()
            ->assertSee($name)
            ->assertSee('The patient record has been deleted.')
            ->assertDontSee(route('admin.patients.show', $contract->patient_id), false);

        $this->actingAs($admin)->get(route('admin.contracts.payment', $contract))->assertOk();
    }

    public function test_a_live_patient_is_linked_from_the_contract_page(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $contract = Contract::factory()->create(['status' => 'active']);

        $this->actingAs($admin)->get(route('admin.contracts.show', $contract))
            ->assertOk()
            ->assertSee(route('admin.patients.show', $contract->patient_id), false);
    }
}
