<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Refunds are recorded on the contract when it closes; the payments screens
 * must surface them next to the money that came in.
 */
class PaymentRefundDisplayTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Contract $refunded;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();

        $this->refunded = Contract::factory()->create([
            'status' => 'closed',
            'closed_at' => now()->subDays(2),
            'closure_reason' => 'Patient shifted to hospital',
            'refund_amount' => 2500,
            'refund_details' => 'UPI ref 44521',
        ]);
        Payment::factory()->create(['contract_id' => $this->refunded->id, 'amount' => 10000, 'paid_at' => now()->subDays(5)]);
    }

    public function test_payments_tab_is_default_and_shows_refund_totals(): void
    {
        $this->actingAs($this->admin)->get('/admin/payments')
            ->assertOk()
            ->assertSee('Total Refunded')
            ->assertSee('₹2,500')
            ->assertSee('Net Received')
            ->assertSee('₹7,500')
            ->assertSee('tab=refunds', false)
            ->assertSee('Refunded ₹2,500')
            ->assertDontSee('Patient shifted to hospital');
    }

    public function test_refunds_tab_lists_refunds(): void
    {
        $this->actingAs($this->admin)->get('/admin/payments?tab=refunds')
            ->assertOk()
            ->assertSee('Recorded when a contract is closed')
            ->assertSee('UPI ref 44521')
            ->assertSee('Patient shifted to hospital')
            ->assertSee('name="tab" value="refunds"', false);
    }

    public function test_refund_list_follows_the_date_filter(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/payments?tab=refunds&start_date='.now()->toDateString())
            ->assertOk()
            ->assertDontSee('UPI ref 44521')
            ->assertSee('No refunds in this period');
    }

    public function test_payment_detail_shows_the_contract_refund(): void
    {
        $payment = $this->refunded->payments()->first();

        $this->actingAs($this->admin)->get("/admin/payments/{$payment->id}")
            ->assertOk()
            ->assertSee('Refund')
            ->assertSee('₹2,500.00')
            ->assertSee('UPI ref 44521')
            ->assertSee('Reason: Patient shifted to hospital', false);
    }
}
