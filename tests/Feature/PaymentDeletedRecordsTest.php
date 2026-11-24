<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A payment is a money record and must stay viewable after the patient or
 * contract it belongs to has been soft-deleted.
 */
class PaymentDeletedRecordsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Contract $contract;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
        $this->contract = Contract::factory()->closed()->create();
        $this->contract->patient->update(['name' => 'Kamala Devi']);
        $this->payment = Payment::factory()->of(10000)->create(['contract_id' => $this->contract->id]);
    }

    public function test_payment_detail_renders_after_the_patient_is_deleted(): void
    {
        $patient = $this->contract->patient;
        $patient->delete();

        $this->actingAs($this->admin)->get("/admin/payments/{$this->payment->id}")
            ->assertOk()
            ->assertSee('Kamala Devi')
            ->assertSee('Deleted')
            ->assertSee(route('admin.contracts.show', $this->contract), false)
            ->assertDontSee(route('admin.patients.show', $patient), false);
    }

    public function test_payment_detail_renders_after_the_contract_is_deleted(): void
    {
        $this->contract->delete();

        $this->actingAs($this->admin)->get("/admin/payments/{$this->payment->id}")
            ->assertOk()
            ->assertSee($this->contract->contract_number)
            ->assertSee('Kamala Devi')
            ->assertDontSee(route('admin.contracts.show', $this->contract), false);
    }

    public function test_payment_list_still_names_a_deleted_patient(): void
    {
        $this->contract->patient->delete();

        $this->actingAs($this->admin)->get('/admin/payments')
            ->assertOk()
            ->assertSee($this->contract->contract_number)
            ->assertSee('Kamala Devi');
    }

    public function test_payment_detail_flags_a_capture_that_needs_refunding(): void
    {
        $this->payment->update([
            'status' => 'failed',
            'notes' => Payment::REFUND_MARKER.' pay_LATE1: captured but not credited.',
        ]);

        $this->actingAs($this->admin)->get("/admin/payments/{$this->payment->id}")
            ->assertOk()
            ->assertSee('Refund required')
            ->assertSee('pay_LATE1');

        $this->actingAs($this->admin)->get('/admin/payments')
            ->assertOk()
            ->assertSee('Refund required');
    }
}
