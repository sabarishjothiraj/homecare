<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\User;
use App\Services\ContractService;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Closing a contract through the UI, and what closing means for money.
 *
 * ContractClosureTest drives the service directly; these go through the HTTP
 * layer, because the close form itself was what broke (it spoofed PATCH onto
 * a POST-only route and every close returned 405).
 */
class ContractCloseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // No mPDF render on the queued PDF job.
        $pdf = Mockery::mock(ContractService::class)->makePartial();
        $pdf->shouldReceive('generateContractPDF')->andReturn('contracts/stub.pdf');
        $this->app->instance(ContractService::class, $pdf);

        $this->admin = User::factory()->superAdmin()->create();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Pull the close form out of the rendered contract page: its method, its
     * action and every field it would submit (hidden fields included, so a
     * `_method` spoof is sent exactly as a browser would send it).
     *
     * @return array{method: string, action: string, fields: array<string, string>}
     */
    private function closeForm(Contract $contract): array
    {
        $html = $this->actingAs($this->admin)->get(route('admin.contracts.show', $contract))
            ->assertOk()
            ->getContent();

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $form = (new DOMXPath($dom))->query('//form[@action="'.route('admin.contracts.close', $contract).'"]')->item(0);
        $this->assertNotNull($form, 'The contract page has no close form.');

        $fields = [];
        foreach ((new DOMXPath($dom))->query('.//input[@name]', $form) as $input) {
            $fields[$input->getAttribute('name')] = $input->getAttribute('value');
        }

        return [
            'method' => strtoupper($form->getAttribute('method')),
            'action' => $form->getAttribute('action'),
            'fields' => $fields,
        ];
    }

    private function submitCloseForm(Contract $contract, array $input)
    {
        $form = $this->closeForm($contract);

        return $this->actingAs($this->admin)->call($form['method'], $form['action'], array_merge($form['fields'], $input));
    }

    public function test_the_close_form_on_the_contract_page_closes_the_contract(): void
    {
        $contract = Contract::factory()->create(['status' => 'active']);
        $assignment = Assignment::factory()->create(['contract_id' => $contract->id, 'is_active' => true]);

        $this->submitCloseForm($contract, ['closure_reason' => 'Patient recovered'])
            ->assertRedirect(route('admin.contracts.show', $contract))
            ->assertSessionHas('success', 'Contract closed successfully.');

        $contract->refresh();
        $this->assertSame('closed', $contract->status);
        $this->assertSame('Patient recovered', $contract->closure_reason);
        $this->assertFalse((bool) $assignment->fresh()->is_active);
    }

    public function test_the_close_form_does_not_spoof_a_method_the_route_rejects(): void
    {
        $contract = Contract::factory()->create(['status' => 'active']);

        $form = $this->closeForm($contract);

        $this->assertSame('POST', $form['method']);
        $this->assertArrayNotHasKey('_method', $form['fields']);
    }

    /**
     * An expired contract still needs closing to record the outcome and any
     * refund, so the page must offer it.
     */
    public function test_an_expired_contract_can_be_closed_from_its_page(): void
    {
        $contract = Contract::factory()->expired()->create(['status' => 'expired']);

        $this->submitCloseForm($contract, ['closure_reason' => 'Ran its course'])
            ->assertSessionHas('success');

        $this->assertSame('closed', $contract->fresh()->status);
    }

    /**
     * Closing is final: a second close used to overwrite the refund and move
     * closed_at, rewriting refund figures already reported for past months.
     */
    public function test_a_closed_contract_cannot_be_closed_again(): void
    {
        $contract = Contract::factory()->create(['status' => 'active']);
        Payment::factory()->of(5000)->create(['contract_id' => $contract->id]);

        app(ContractService::class)->closeContract($contract, 'First close', ['refund_amount' => 1500]);
        $closedAt = $contract->fresh()->closed_at;

        $this->actingAs($this->admin)
            ->post(route('admin.contracts.close', $contract), ['closure_reason' => 'Second close', 'refund_amount' => 0])
            ->assertSessionHas('error', 'This contract is already closed.');

        $contract->refresh();
        $this->assertSame('First close', $contract->closure_reason);
        $this->assertSame(1500.0, (float) $contract->refund_amount);
        $this->assertTrue($closedAt->equalTo($contract->closed_at));
    }

    public function test_the_refund_cannot_exceed_what_was_collected(): void
    {
        $contract = Contract::factory()->create(['status' => 'active']);
        Payment::factory()->of(2000)->create(['contract_id' => $contract->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.contracts.close', $contract), ['closure_reason' => 'Ended', 'refund_amount' => 50000])
            ->assertSessionHas('error', 'Refund cannot exceed the ₹2,000.00 collected on this contract.');

        $this->assertNotSame('closed', $contract->fresh()->status);
    }

    public function test_a_refund_up_to_the_amount_collected_is_recorded(): void
    {
        $contract = Contract::factory()->create(['status' => 'active']);
        Payment::factory()->of(2000)->create(['contract_id' => $contract->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.contracts.close', $contract), ['closure_reason' => 'Ended', 'refund_amount' => 2000])
            ->assertSessionHas('success');

        $this->assertSame(2000.0, (float) $contract->fresh()->refund_amount);
    }

    /**
     * A checkout opened before closure must not be able to capture afterwards.
     */
    public function test_closing_cancels_open_gateway_orders(): void
    {
        $contract = Contract::factory()->create(['status' => 'active']);
        $order = Payment::factory()->pending()->of(3000)->create(['contract_id' => $contract->id]);
        $collected = Payment::factory()->of(1000)->create(['contract_id' => $contract->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.contracts.close', $contract), ['closure_reason' => 'Ended']);

        $this->assertSame('failed', $order->fresh()->status);
        $this->assertSame('completed', $collected->fresh()->status);
    }

    public function test_a_closed_contract_refuses_a_manual_payment(): void
    {
        $contract = Contract::factory()->worth(30000)->closed()->create();
        Payment::factory()->of(10000)->create(['contract_id' => $contract->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.contracts.payment.store', $contract), ['amount' => 20000, 'method' => 'cash'])
            ->assertSessionHas('error', 'This contract is closed. No further payments can be collected.');

        $this->assertSame(10000.0, $contract->paidAmount());
    }

    public function test_a_closed_contract_refuses_a_new_gateway_order(): void
    {
        $contract = Contract::factory()->worth(30000)->closed()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.contracts.payment.razorpay-order', $contract), ['amount' => 5000])
            ->assertStatus(422)
            ->assertJson(['success' => false, 'error' => 'This contract is closed. No further payments can be collected.']);

        $this->assertSame(0, $contract->payments()->count());
    }

    public function test_the_payment_page_of_a_closed_contract_offers_no_collection_form(): void
    {
        $contract = Contract::factory()->worth(30000)->closed()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.contracts.payment', $contract))
            ->assertOk()
            ->assertSee('This contract is closed. No further payments can be collected.')
            ->assertDontSee(route('admin.contracts.payment.store', $contract), false);
    }

    public function test_an_open_contract_still_accepts_a_manual_payment(): void
    {
        $contract = Contract::factory()->worth(30000)->create(['status' => 'active']);

        $this->actingAs($this->admin)
            ->post(route('admin.contracts.payment.store', $contract), ['amount' => 5000, 'method' => 'cash'])
            ->assertSessionHas('success');

        $this->assertSame(5000.0, $contract->paidAmount());
    }

    /**
     * The verify endpoint is per contract; an order from another contract
     * must not be confirmed through it.
     */
    public function test_verifying_an_order_from_another_contract_is_refused(): void
    {
        $contract = Contract::factory()->create(['status' => 'active']);
        $foreignOrder = Payment::factory()->pending()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.contracts.payment.razorpay-verify', $contract), [
                'razorpay_order_id' => $foreignOrder->razorpay_order_id,
                'razorpay_payment_id' => 'pay_test',
                'razorpay_signature' => 'sig',
            ])
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertSame('pending', $foreignOrder->fresh()->status);
    }
}
