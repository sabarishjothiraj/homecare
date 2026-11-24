<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Notification;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A Razorpay capture may only credit an order that is still open.
 *
 * Razorpay orders never expire, so a customer can still pay one this app has
 * already cancelled (cash settled the balance, or a newer order replaced it).
 * Crediting that capture collects twice. The money was really taken, though,
 * so it has to be flagged for a refund rather than dropped.
 */
class PaymentCaptureGuardTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'test_webhook_secret';

    private PaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->payments = app(PaymentService::class);
    }

    private function postCapture(string $orderId, string $paymentId)
    {
        $body = json_encode([
            'event' => 'payment.captured',
            'payload' => ['payment' => ['entity' => ['id' => $paymentId, 'order_id' => $orderId]]],
        ]);

        return $this->call('POST', '/api/razorpay/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, self::WEBHOOK_SECRET),
        ], $body);
    }

    /**
     * The checkout signature Razorpay hands the browser: HMAC of
     * "order_id|payment_id" keyed on the API secret.
     */
    private function checkoutSignature(string $orderId, string $paymentId): string
    {
        return hash_hmac('sha256', $orderId.'|'.$paymentId, config('services.razorpay.secret'));
    }

    private function openOrder(Contract $contract, float $amount, string $orderId = 'order_OPEN1'): Payment
    {
        return Payment::factory()->pending()->of($amount)->create([
            'contract_id' => $contract->id,
            'razorpay_order_id' => $orderId,
        ]);
    }

    public function test_a_capture_for_an_order_cancelled_by_cash_is_not_credited(): void
    {
        $contract = Contract::factory()->worth(10000)->create();
        $order = $this->openOrder($contract, 10000);

        $this->payments->recordManualContractPayment($contract, ['amount' => 10000, 'method' => 'cash']);
        $this->assertSame('failed', $order->fresh()->status);

        $this->postCapture('order_OPEN1', 'pay_LATE1')->assertOk();

        $order->refresh();
        $this->assertSame('failed', $order->status);
        $this->assertNull($order->paid_at);
        $this->assertSame(10000.0, $contract->fresh()->paidAmount(), 'The cancelled order was credited on top of the cash.');
    }

    public function test_an_uncredited_capture_is_flagged_for_refund(): void
    {
        $contract = Contract::factory()->worth(10000)->create();
        $order = $this->openOrder($contract, 10000);
        $this->payments->recordManualContractPayment($contract, ['amount' => 10000, 'method' => 'cash']);

        $this->postCapture('order_OPEN1', 'pay_LATE1')->assertOk();

        $order->refresh();
        $this->assertTrue($order->needsRefund());
        $this->assertStringContainsString('pay_LATE1', $order->notes);
        $this->assertSame('pay_LATE1', $order->razorpay_payment_id);

        $alert = Notification::where('type', 'payment_refund_required')->sole();
        $this->assertSame($contract->id, $alert->contract_id);
        $this->assertStringContainsString('pay_LATE1', $alert->message);
        $this->assertSame(route('admin.payments.show', $order), $alert->link);
    }

    /**
     * Razorpay retries webhooks, and verify and webhook both report one
     * capture: the refund alert must be raised once.
     */
    public function test_a_repeated_uncredited_capture_is_flagged_once(): void
    {
        $contract = Contract::factory()->worth(10000)->create();
        $order = $this->openOrder($contract, 10000);
        $this->payments->recordManualContractPayment($contract, ['amount' => 10000, 'method' => 'cash']);

        $this->postCapture('order_OPEN1', 'pay_LATE1')->assertOk();
        $this->postCapture('order_OPEN1', 'pay_LATE1')->assertOk();
        $this->payments->processSuccessfulPayment('order_OPEN1', 'pay_LATE1', $this->checkoutSignature('order_OPEN1', 'pay_LATE1'));

        $this->assertSame(1, Notification::where('type', 'payment_refund_required')->count());
        $this->assertSame(1, substr_count($order->fresh()->notes, Payment::REFUND_MARKER));
    }

    /**
     * Finding scenario 2: an order left open past its TTL is expired by a
     * newer one, then both checkouts get paid.
     */
    public function test_a_capture_for_an_expired_order_is_not_credited(): void
    {
        $contract = Contract::factory()->worth(10000)->create();
        $stale = $this->openOrder($contract, 10000, 'order_STALE');
        Payment::whereKey($stale->id)->update(['created_at' => now()->subHours(2)]);

        $this->payments->expireStalePendingOrders($contract);
        $fresh = $this->openOrder($contract, 10000, 'order_FRESH');

        $this->assertTrue($this->payments->markPaymentCaptured('order_FRESH', 'pay_FRESH'));
        $this->assertFalse($this->payments->processSuccessfulPayment(
            'order_STALE', 'pay_STALE', $this->checkoutSignature('order_STALE', 'pay_STALE')
        ));

        $this->assertSame('completed', $fresh->fresh()->status);
        $this->assertSame('failed', $stale->fresh()->status);
        $this->assertTrue($stale->fresh()->needsRefund());
        $this->assertSame(10000.0, $contract->fresh()->paidAmount());
    }

    public function test_a_capture_that_would_overshoot_the_contract_is_not_credited(): void
    {
        $contract = Contract::factory()->worth(10000)->create();
        Payment::factory()->of(8000)->create(['contract_id' => $contract->id]);
        $order = $this->openOrder($contract, 4000);

        $this->assertFalse($this->payments->markPaymentCaptured('order_OPEN1', 'pay_OVER'));

        $this->assertSame('failed', $order->fresh()->status);
        $this->assertTrue($order->fresh()->needsRefund());
        $this->assertSame(8000.0, $contract->fresh()->paidAmount());
    }

    public function test_a_second_capture_on_a_paid_order_is_flagged_not_counted(): void
    {
        $contract = Contract::factory()->worth(10000)->create();
        $order = $this->openOrder($contract, 4000);

        $this->assertTrue($this->payments->markPaymentCaptured('order_OPEN1', 'pay_FIRST'));
        $this->assertTrue($this->payments->markPaymentCaptured('order_OPEN1', 'pay_FIRST'));
        $this->assertSame(0, Notification::count(), 'A replay of the same capture is not a refund case.');

        $this->assertFalse($this->payments->markPaymentCaptured('order_OPEN1', 'pay_SECOND'));

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame('pay_FIRST', $order->razorpay_payment_id);
        $this->assertStringContainsString('pay_SECOND', $order->notes);
        $this->assertSame(4000.0, $contract->fresh()->paidAmount());
        $this->assertSame(1, Notification::where('type', 'payment_refund_required')->count());
    }

    public function test_an_open_order_within_the_balance_still_completes(): void
    {
        $contract = Contract::factory()->worth(10000)->create();
        Payment::factory()->of(6000)->create(['contract_id' => $contract->id]);
        $order = $this->openOrder($contract, 4000);

        $this->assertTrue($this->payments->processSuccessfulPayment(
            'order_OPEN1', 'pay_OK', $this->checkoutSignature('order_OPEN1', 'pay_OK')
        ));

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertFalse($order->fresh()->needsRefund());
        $this->assertSame(0.0, $contract->fresh()->balanceDue());
    }

    /**
     * A stale order is older, so it sorts first by id. It must not claim the
     * room left after cash and push a live checkout into cancellation.
     */
    public function test_cash_does_not_cancel_a_live_order_because_of_a_stale_one(): void
    {
        $contract = Contract::factory()->worth(10000)->create();
        $stale = $this->openOrder($contract, 6000, 'order_STALE');
        Payment::whereKey($stale->id)->update(['created_at' => now()->subHours(2)]);
        $live = $this->openOrder($contract, 4000, 'order_LIVE');

        $this->payments->recordManualContractPayment($contract, ['amount' => 2000, 'method' => 'cash']);

        $this->assertSame('pending', $live->fresh()->status, 'The live checkout was cancelled to make room for an abandoned one.');
        $this->assertSame('failed', $stale->fresh()->status);

        $this->assertTrue($this->payments->markPaymentCaptured('order_LIVE', 'pay_LIVE'));
        $this->assertFalse($live->fresh()->needsRefund());
        $this->assertSame(6000.0, $contract->fresh()->paidAmount());
    }

    public function test_a_stale_order_that_still_fits_is_left_open(): void
    {
        $contract = Contract::factory()->worth(10000)->create();
        $stale = $this->openOrder($contract, 3000, 'order_STALE');
        Payment::whereKey($stale->id)->update(['created_at' => now()->subHours(2)]);

        $this->payments->recordManualContractPayment($contract, ['amount' => 2000, 'method' => 'cash']);

        $this->assertSame('pending', $stale->fresh()->status);
    }

    /**
     * Orders left open on contracts closed before closing cancelled them.
     */
    public function test_a_capture_on_a_closed_contract_is_not_credited(): void
    {
        $contract = Contract::factory()->worth(10000)->closed()->create();
        $order = $this->openOrder($contract, 4000);

        $this->assertFalse($this->payments->markPaymentCaptured('order_OPEN1', 'pay_CLOSED'));

        $this->assertSame('failed', $order->fresh()->status);
        $this->assertTrue($order->fresh()->needsRefund());
        $this->assertSame(0.0, $contract->fresh()->paidAmount());
        $this->assertStringContainsString('closed', Notification::where('type', 'payment_refund_required')->sole()->message);
    }

    public function test_closing_a_contract_can_cancel_all_its_open_orders(): void
    {
        $contract = Contract::factory()->worth(10000)->create();
        $first = $this->openOrder($contract, 3000, 'order_A');
        $second = $this->openOrder($contract, 2000, 'order_B');
        $paid = Payment::factory()->of(1000)->create(['contract_id' => $contract->id]);
        $elsewhere = $this->openOrder(Contract::factory()->create(), 1000, 'order_OTHER');

        $this->assertSame(2, $this->payments->cancelOpenOrdersForContract($contract));

        $this->assertSame('failed', $first->fresh()->status);
        $this->assertSame('failed', $second->fresh()->status);
        $this->assertSame('completed', $paid->fresh()->status);
        $this->assertSame('pending', $elsewhere->fresh()->status);
    }

    /**
     * The callback is public and CSRF-exempt: a forged signature must not be
     * able to cancel someone's live checkout.
     */
    public function test_a_forged_callback_signature_changes_nothing(): void
    {
        $contract = Contract::factory()->worth(10000)->create();
        $order = $this->openOrder($contract, 10000);

        $this->post('/api/razorpay/callback', [
            'razorpay_order_id' => 'order_OPEN1',
            'razorpay_payment_id' => 'pay_X',
            'razorpay_signature' => 'forged',
        ])->assertRedirect()->assertSessionHas('error', 'Payment verification failed!');

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(0.0, $contract->fresh()->availableToCharge(), 'The open order stopped reserving the balance.');
    }

    public function test_the_callback_does_not_confirm_a_paid_order_without_a_valid_signature(): void
    {
        $contract = Contract::factory()->worth(10000)->create();
        Payment::factory()->of(4000)->create([
            'contract_id' => $contract->id,
            'razorpay_order_id' => 'order_PAID',
            'razorpay_payment_id' => 'pay_PAID',
            'method' => 'razorpay',
        ]);

        $this->post('/api/razorpay/callback', [
            'razorpay_order_id' => 'order_PAID',
            'razorpay_payment_id' => 'pay_X',
            'razorpay_signature' => 'forged',
        ])->assertSessionHas('error', 'Payment verification failed!')
            ->assertSessionMissing('success');
    }

    public function test_a_correctly_signed_callback_completes_the_order(): void
    {
        $contract = Contract::factory()->worth(10000)->create();
        $order = $this->openOrder($contract, 10000);

        $this->post('/api/razorpay/callback', [
            'razorpay_order_id' => 'order_OPEN1',
            'razorpay_payment_id' => 'pay_GOOD',
            'razorpay_signature' => $this->checkoutSignature('order_OPEN1', 'pay_GOOD'),
        ])->assertSessionHas('success', 'Payment completed successfully!');

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(10000.0, $contract->fresh()->paidAmount());
    }
}
