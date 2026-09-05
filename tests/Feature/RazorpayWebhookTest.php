<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Contract;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Razorpay webhook.
 *
 * Regression cover for a live defect: the controller called
 * processSuccessfulPayment() with two of its three required arguments, and the
 * resulting ArgumentCountError is an Error rather than an Exception, so the
 * surrounding catch (\Exception) never saw it. Every captured payment died as
 * an uncaught fatal and Razorpay retried the hook indefinitely.
 */
class RazorpayWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'test_webhook_secret';

    private function payload(string $orderId, string $paymentId): array
    {
        return [
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => $paymentId,
                        'order_id' => $orderId,
                    ],
                ],
            ],
        ];
    }

    /**
     * Posts a raw body so the signature covers exactly the bytes the
     * controller will hash.
     */
    private function postWebhook(array $payload, ?string $signature = null)
    {
        $body = json_encode($payload);
        $signature ??= hash_hmac('sha256', $body, self::WEBHOOK_SECRET);

        return $this->call(
            'POST',
            '/api/razorpay/webhook',
            [], [], [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
            ],
            $body
        );
    }

    private function pendingPayment(): Payment
    {
        $contract = Contract::factory()->worth(10000)->create();

        return Payment::factory()->pending()->of(4000)->create([
            'contract_id' => $contract->id,
            'razorpay_order_id' => 'order_TEST123',
        ]);
    }

    public function test_a_correctly_signed_capture_completes_the_payment(): void
    {
        $payment = $this->pendingPayment();

        $response = $this->postWebhook($this->payload('order_TEST123', 'pay_ABC999'));

        $response->assertOk()->assertJson(['status' => 'success']);

        $payment->refresh();
        $this->assertSame('completed', $payment->status);
        $this->assertSame('pay_ABC999', $payment->razorpay_payment_id);
        $this->assertNotNull($payment->paid_at);
    }

    public function test_the_completed_payment_counts_towards_the_contract(): void
    {
        $payment = $this->pendingPayment();

        $this->postWebhook($this->payload('order_TEST123', 'pay_ABC999'))->assertOk();

        $contract = $payment->contract->fresh();
        $this->assertSame(4000.0, $contract->paidAmount());
        $this->assertSame(6000.0, $contract->balanceDue());
    }

    public function test_a_forged_signature_is_rejected_and_changes_nothing(): void
    {
        $payment = $this->pendingPayment();

        $response = $this->postWebhook(
            $this->payload('order_TEST123', 'pay_ABC999'),
            'not-a-valid-signature'
        );

        $response->assertStatus(400);
        $this->assertSame('pending', $payment->fresh()->status);
    }

    /**
     * Razorpay retries until it gets a 2xx, so the same capture will arrive
     * more than once. It must not be collected twice.
     */
    public function test_a_replayed_webhook_does_not_collect_twice(): void
    {
        $payment = $this->pendingPayment();
        $payload = $this->payload('order_TEST123', 'pay_ABC999');

        $this->postWebhook($payload)->assertOk();
        $this->postWebhook($payload)->assertOk();

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(4000.0, $payment->contract->fresh()->paidAmount());
    }

    public function test_an_unknown_order_is_acknowledged_without_creating_a_payment(): void
    {
        $this->pendingPayment();

        $this->postWebhook($this->payload('order_UNKNOWN', 'pay_X'))->assertOk();

        $this->assertDatabaseMissing('payments', ['razorpay_order_id' => 'order_UNKNOWN']);
    }

    public function test_events_other_than_capture_are_ignored(): void
    {
        $payment = $this->pendingPayment();

        $payload = $this->payload('order_TEST123', 'pay_ABC999');
        $payload['event'] = 'payment.failed';

        $this->postWebhook($payload)->assertOk();

        $this->assertSame('pending', $payment->fresh()->status);
    }

    /**
     * A malformed body must not reach the payment code as an undefined index.
     */
    public function test_a_payload_without_a_payment_entity_is_handled(): void
    {
        $this->pendingPayment();

        $this->postWebhook(['event' => 'payment.captured'])->assertOk();

        $this->assertDatabaseMissing('payments', ['status' => 'completed']);
    }

    /**
     * With no secret set, every signature would be compared against an HMAC
     * keyed on an empty string and silently rejected. That must read as a
     * server misconfiguration, not as a bad sender.
     */
    public function test_a_missing_webhook_secret_reports_a_server_error(): void
    {
        config(['services.razorpay.webhook_secret' => '']);

        $payment = $this->pendingPayment();

        $this->postWebhook($this->payload('order_TEST123', 'pay_ABC999'))
            ->assertStatus(500);

        $this->assertSame('pending', $payment->fresh()->status);
    }

    /**
     * Both gateway endpoints are called by Razorpay, which holds no session
     * token. Laravel skips CSRF while running tests, so the exemption list is
     * asserted directly rather than over HTTP.
     */
    public function test_both_gateway_endpoints_are_exempt_from_csrf(): void
    {
        $except = (fn () => $this->except)->call(new VerifyCsrfToken(app(), app('encrypter')));

        $this->assertContains('api/razorpay/webhook', $except);
        $this->assertContains('api/razorpay/callback', $except);
    }
}
