<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Collecting money against a contract.
 *
 * The rule under test throughout: a contract can never be collected past its
 * own total, whichever route the money arrives by.
 */
class PaymentCollectionTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->payments = app(PaymentService::class);
    }

    public function test_a_manual_payment_is_recorded_as_completed(): void
    {
        $contract = Contract::factory()->worth(10000)->create();

        $payment = $this->payments->recordManualContractPayment($contract, [
            'amount' => 2500,
            'method' => 'cash',
            'reference' => 'RCPT-1',
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'contract_id' => $contract->id,
            'status' => 'completed',
            'method' => 'cash',
            'reference' => 'RCPT-1',
        ]);

        $this->assertSame(2500.0, $contract->fresh()->paidAmount());
        $this->assertNotNull($payment->paid_at);
    }

    public function test_a_payment_larger_than_the_balance_is_rejected(): void
    {
        $contract = Contract::factory()->worth(10000)->create();

        $this->expectException(ValidationException::class);

        $this->payments->recordManualContractPayment($contract, [
            'amount' => 10001,
            'method' => 'cash',
        ]);
    }

    public function test_rejecting_an_oversized_payment_writes_nothing(): void
    {
        $contract = Contract::factory()->worth(10000)->create();

        try {
            $this->payments->recordManualContractPayment($contract, [
                'amount' => 25000,
                'method' => 'cash',
            ]);
        } catch (ValidationException) {
            // expected
        }

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(10000.0, $contract->fresh()->balanceDue());
    }

    public function test_the_exact_outstanding_balance_is_accepted(): void
    {
        $contract = Contract::factory()->worth(10000)->create();
        Payment::factory()->of(7000)->create(['contract_id' => $contract->id]);

        $this->payments->recordManualContractPayment($contract->fresh(), [
            'amount' => 3000,
            'method' => 'cash',
        ]);

        $this->assertSame(0.0, $contract->fresh()->balanceDue());
        $this->assertSame('paid', $contract->fresh()->paymentStatus());
    }

    public function test_a_fully_paid_contract_takes_no_further_payment(): void
    {
        $contract = Contract::factory()->worth(5000)->create();
        Payment::factory()->of(5000)->create(['contract_id' => $contract->id]);

        $this->expectExceptionMessage('This contract is already fully paid.');

        $this->payments->recordManualContractPayment($contract->fresh(), [
            'amount' => 100,
            'method' => 'cash',
        ]);
    }

    /**
     * A sub-paisa rounding artefact must not block a legitimate final
     * settlement - that is what the service's epsilon tolerance is for.
     */
    public function test_a_sub_paisa_overshoot_is_tolerated(): void
    {
        $contract = Contract::factory()->worth(1000)->create();

        $payment = $this->payments->recordManualContractPayment($contract, [
            'amount' => 1000.004,
            'method' => 'cash',
        ]);

        $this->assertNotNull($payment->id);
    }

    public function test_each_accepted_payment_shrinks_the_remaining_balance(): void
    {
        $contract = Contract::factory()->worth(9000)->create();

        foreach ([3000, 3000, 3000] as $amount) {
            $this->payments->recordManualContractPayment($contract->fresh(), [
                'amount' => $amount,
                'method' => 'cash',
            ]);
        }

        $this->assertSame(9000.0, $contract->fresh()->paidAmount());
        $this->assertSame(0.0, $contract->fresh()->balanceDue());

        $this->expectException(ValidationException::class);

        $this->payments->recordManualContractPayment($contract->fresh(), [
            'amount' => 1,
            'method' => 'cash',
        ]);
    }
}
