<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Contract;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
    }

    /**
     * The index offered status=inactive but Patient had no inactive() scope.
     */
    public function test_inactive_filter_lists_only_inactive_patients(): void
    {
        Patient::factory()->create(['name' => 'Active Patient', 'is_active' => true]);
        Patient::factory()->create(['name' => 'Dormant Patient', 'is_active' => false]);

        $this->actingAs($this->admin)->get('/admin/patients?status=inactive')
            ->assertOk()
            ->assertSee('Dormant Patient')
            ->assertDontSee('Active Patient');
    }

    /**
     * Custom-duration contracts can have no end date, and the amount column
     * is "amount" (the view read a non-existent total_amount and showed ₹0).
     */
    public function test_patient_page_handles_open_ended_contracts_and_shows_the_amount(): void
    {
        $patient = Patient::factory()->create();
        Contract::factory()->create([
            'patient_id' => $patient->id,
            'duration_type' => 'custom',
            'duration_value' => null,
            'end_date' => null,
            'amount' => 12345,
        ]);

        $this->actingAs($this->admin)->get("/admin/patients/{$patient->id}")
            ->assertOk()
            ->assertSee('Open-ended')
            ->assertSee('₹12,345', false);
    }

    /**
     * The nightly job moves a past-end-date contract to "expired" without
     * releasing its staff, so activeContracts() alone let the delete through.
     */
    public function test_patient_with_an_expired_but_staffed_contract_cannot_be_deleted(): void
    {
        $patient = Patient::factory()->create();
        $contract = Contract::factory()->expired()->create(['patient_id' => $patient->id, 'status' => 'expired']);
        Assignment::factory()->create(['contract_id' => $contract->id]);
        Payment::factory()->create(['contract_id' => $contract->id, 'amount' => $contract->amount]);

        $this->actingAs($this->admin)
            ->from('/admin/patients')
            ->delete("/admin/patients/{$patient->id}")
            ->assertRedirect('/admin/patients')
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($patient);
    }

    /**
     * Paid in full and unstaffed is still not enough: until it is closed, the
     * contract can take new staff and payments.
     */
    public function test_an_unclosed_contract_blocks_deletion_even_when_paid_and_unstaffed(): void
    {
        $patient = Patient::factory()->create();
        $contract = Contract::factory()->expired()->create(['patient_id' => $patient->id, 'status' => 'expired']);
        Payment::factory()->create(['contract_id' => $contract->id, 'amount' => $contract->amount]);

        $this->actingAs($this->admin)
            ->deleteJson("/admin/patients/{$patient->id}")
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertNotSoftDeleted($patient);
    }

    public function test_a_closed_contract_with_staff_still_placed_blocks_deletion(): void
    {
        $patient = Patient::factory()->create();
        $contract = Contract::factory()->closed()->create(['patient_id' => $patient->id]);
        Assignment::factory()->create(['contract_id' => $contract->id]);

        $this->actingAs($this->admin)
            ->deleteJson("/admin/patients/{$patient->id}")
            ->assertStatus(422);

        $this->assertNotSoftDeleted($patient);
    }

    public function test_patient_whose_contracts_are_closed_and_paid_can_be_deleted(): void
    {
        $patient = Patient::factory()->create();
        $contract = Contract::factory()->closed()->create(['patient_id' => $patient->id, 'amount' => 10000]);
        Assignment::factory()->inactive()->create(['contract_id' => $contract->id]);
        Payment::factory()->create(['contract_id' => $contract->id, 'amount' => 10000]);

        $this->actingAs($this->admin)
            ->delete("/admin/patients/{$patient->id}")
            ->assertRedirect('/admin/patients')
            ->assertSessionHas('success');

        $this->assertSoftDeleted($patient);
    }

    /**
     * Closing is the settlement point: it records no write-off and payments
     * are refused afterwards, so a balance left on a closed contract must not
     * make the patient undeletable forever.
     */
    public function test_a_balance_left_on_a_closed_contract_does_not_block_deletion(): void
    {
        $patient = Patient::factory()->create();
        $contract = Contract::factory()->closed()->create(['patient_id' => $patient->id, 'amount' => 10000]);
        Payment::factory()->create(['contract_id' => $contract->id, 'amount' => 4000]);

        $this->actingAs($this->admin)
            ->deleteJson("/admin/patients/{$patient->id}")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted($patient);
    }

    /**
     * A deleted patient cannot be restored from the UI, so their email must
     * not block adding them again.
     */
    public function test_a_deleted_patients_email_can_be_used_again(): void
    {
        $old = Patient::factory()->create(['email' => 'returning@example.com']);
        $old->delete();

        $this->actingAs($this->admin)
            ->post('/admin/patients', $this->patientPayload(['email' => 'returning@example.com']))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(1, Patient::where('email', 'returning@example.com')->count());
        $this->assertSame(2, Patient::withTrashed()->where('email', 'returning@example.com')->count());
    }

    public function test_a_live_patients_email_is_still_unique_on_create_and_update(): void
    {
        Patient::factory()->create(['email' => 'taken@example.com']);
        $other = Patient::factory()->create(['email' => 'other@example.com']);

        $this->actingAs($this->admin)
            ->post('/admin/patients', $this->patientPayload(['email' => 'taken@example.com']))
            ->assertSessionHasErrors('email');

        $this->actingAs($this->admin)
            ->put("/admin/patients/{$other->id}", $this->patientPayload(['email' => 'taken@example.com']))
            ->assertSessionHasErrors('email');

        // Keeping one's own email is fine.
        $this->actingAs($this->admin)
            ->put("/admin/patients/{$other->id}", $this->patientPayload(['email' => 'other@example.com']))
            ->assertSessionHasNoErrors();
    }

    private function patientPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Returning Patient',
            'email' => 'patient@example.com',
            'phone' => '9876543210',
            'address1' => '12 Lake Road',
            'city' => 'Chennai',
            'state' => 'Tamil Nadu',
            'country' => 'India',
            'pincode' => '600001',
            'is_active' => '1',
        ], $overrides);
    }
}
