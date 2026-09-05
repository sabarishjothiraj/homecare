<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Open gateway orders must reserve the balance they are for.
 *
 * paidAmount() counts only completed payments, so without a reservation two
 * checkout orders can each be opened for the whole outstanding balance and
 * both go on to capture - collecting twice for one contract.
 */
class PaymentReservationTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();

        // Opens the pending row exactly as the real method does, without
        // calling out to Razorpay.
        $this->payments = new class extends PaymentService
        {
            protected function createRazorpayContractOrder(Contract $contract, float $amount): array
            {
                $payment = Payment::create([
                    'payment_type' => 'contract',
                    'contract_id' => $contract->id,
                    'razorpay_order_id' => 'order_'.Str::random(10),
                    'amount' => $amount,
                    'status' => 'pending',
                    'method' => 'razorpay',
                ]);

                return [
                    'success' => true,
                    'order_id' => $payment->razorpay_order_id,
                    'amount' => $amount,
                    'payment_id' => $payment->id,
                ];
            }
        };
    }

    public function test_a_first_order_opens_for_the_full_balance(): void
    {
        $contract = Contract::factory()->worth(10000)->create();

        $result = $this->payments->createContractOrder($contract);

        $this->assertTrue($result['success']);
        $this->assertSame(10000.0, (float) $result['amount']);
    }

    /**
     * The defect this suite exists for.
     */
    public function test_a_second_order_cannot_be_opened_against_a_reserved_balance(): void
    {
        $contract = Contract::factory()->worth(10000)->create();

        $this->payments->createContractOrder($contract)['success'];
        $second = $this->payments->createContractOrder($contract->fresh());

        $this->assertFalse($second['success'], 'A second order was opened against a balance already reserved by an open order.');
        $this->assertSame(1, Payment::where('contract_id', $contract->id)->count());
    }

    public function test_an_open_order_reserves_only_its_own_amount(): void
    {
        $contract = Contract::factory()->worth(10000)->create();

        $this->payments->createContractOrder($contract, 4000);
        $second = $this->payments->createContractOrder($contract->fresh(), 6000);

        $this->assertTrue($second['success'], 'The remaining unreserved balance should still be chargeable.');
        $this->assertSame(2, Payment::where('contract_id', $contract->id)->count());
    }

    public function test_an_order_beyond_the_unreserved_balance_is_refused(): void
    {
        $contract = Contract::factory()->worth(10000)->create();

        $this->payments->createContractOrder($contract, 7000);
        $second = $this->payments->createContractOrder($contract->fresh(), 4000);

        $this->assertFalse($second['success']);
    }

    public function test_completed_and_reserved_amounts_both_reduce_what_can_be_charged(): void
    {
        $contract = Contract::factory()->worth(10000)->create();
        Payment::factory()->of(3000)->create(['contract_id' => $contract->id]);

        $this->payments->createContractOrder($contract->fresh(), 5000);

        $this->assertSame(2000.0, $contract->fresh()->availableToCharge());

        $tooMuch = $this->payments->createContractOrder($contract->fresh(), 2500);
        $this->assertFalse($tooMuch['success']);
    }

    /**
     * An abandoned checkout must not lock the balance up forever.
     */
    public function test_a_stale_order_stops_reserving(): void
    {
        $contract = Contract::factory()->worth(10000)->create();

        $this->payments->createContractOrder($contract);

        Payment::where('contract_id', $contract->id)->update([
            'created_at' => now()->subHours(2),
        ]);

        $retry = $this->payments->createContractOrder($contract->fresh());

        $this->assertTrue($retry['success'], 'An expired order should release its reservation.');
        $this->assertSame('failed', Payment::where('contract_id', $contract->id)
            ->orderBy('id')->first()->status);
    }

    /**
     * Cash in hand is real money and must be accepted, but the abandoned
     * online attempt must not survive to be captured on top of it.
     */
    public function test_taking_cash_cancels_an_open_order_for_the_same_balance(): void
    {
        $contract = Contract::factory()->worth(10000)->create();
        $this->payments->createContractOrder($contract);

        $this->payments->recordManualContractPayment($contract->fresh(), [
            'amount' => 10000,
            'method' => 'cash',
        ]);

        $this->assertSame(10000.0, $contract->fresh()->paidAmount());
        $this->assertSame(0.0, $contract->fresh()->balanceDue());
        $this->assertSame(0, Payment::where('contract_id', $contract->id)
            ->where('status', 'pending')->count());
    }

    public function test_a_failed_order_does_not_reserve(): void
    {
        $contract = Contract::factory()->worth(10000)->create();
        Payment::factory()->pending()->of(10000)->create([
            'contract_id' => $contract->id,
            'status' => 'failed',
        ]);

        $this->assertSame(10000.0, $contract->fresh()->availableToCharge());
    }
}
