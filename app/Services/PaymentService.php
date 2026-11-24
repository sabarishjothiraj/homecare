<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Payment;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Razorpay\Api\Api;

class PaymentService
{
    /**
     * Tolerance for float comparison on rupee amounts.
     */
    protected const EPSILON = 0.01;

    protected ?Api $razorpay = null;

    /**
     * Resolve the Razorpay client lazily.
     *
     * Building it in the constructor forced every injection of this service —
     * including cash-only flows that never touch Razorpay — to require valid
     * API credentials.
     */
    protected function razorpay(): Api
    {
        if ($this->razorpay === null) {
            $this->razorpay = new Api(
                config('services.razorpay.key'),
                config('services.razorpay.secret')
            );
        }

        return $this->razorpay;
    }

    /**
     * Reject an amount that would take the contract past its total.
     *
     * Must be called inside a transaction that already holds a lock on the
     * contract row, otherwise two concurrent collections can both pass.
     */
    protected function assertWithinBalance(Contract $contract, float $amount): void
    {
        $balance = $contract->balanceDue();

        if ($amount > $balance + self::EPSILON) {
            throw ValidationException::withMessages([
                'amount' => [
                    $balance <= 0
                        ? 'This contract is already fully paid.'
                        : 'Amount exceeds the outstanding balance of ₹'.number_format($balance, 2).'.',
                ],
            ]);
        }
    }

    /**
     * Reject an amount that no longer has unreserved balance behind it.
     *
     * Opening an order is not the same as collecting: it must also clear the
     * amount already held by other open orders, or two checkouts can be
     * started for one balance and both captured.
     */
    protected function assertChargeable(Contract $contract, float $amount): void
    {
        $available = $contract->availableToCharge();

        if ($amount > $available + self::EPSILON) {
            throw ValidationException::withMessages([
                'amount' => [
                    $available <= 0
                        ? 'There is no unpaid balance available to charge. An open payment may already cover it.'
                        : 'Amount exceeds the ₹'.number_format($available, 2).' still available to charge.',
                ],
            ]);
        }
    }

    /**
     * Release orders that were opened but never paid.
     *
     * Marked failed rather than deleted so an abandoned checkout stays visible
     * in the payment history. One conditional UPDATE, so a row a capture has
     * just completed is never expired back to failed.
     */
    public function expireStalePendingOrders(Contract $contract): int
    {
        return $contract->payments()
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(Contract::orderTtlMinutes()))
            ->update(['status' => 'failed']);
    }

    /**
     * Create Razorpay order for contract payment.
     *
     * Pass $amount to charge a partial / advance amount; defaults to the
     * balance that is not already held by another open order.
     */
    public function createContractOrder(Contract $contract, ?float $amount = null): array
    {
        try {
            return DB::transaction(function () use ($contract, $amount) {
                // Lock the contract so a concurrent collection cannot open a
                // second order against the same outstanding balance.
                $contract = Contract::whereKey($contract->getKey())->lockForUpdate()->firstOrFail();

                // Abandoned checkouts release their hold before the balance is
                // measured, so one unfinished attempt cannot block payment.
                $this->expireStalePendingOrders($contract);

                $amount = $amount ?? $contract->availableToCharge();

                if ($amount <= 0) {
                    return [
                        'success' => false,
                        'error' => 'There is no outstanding balance to pay.',
                    ];
                }

                $this->assertChargeable($contract, (float) $amount);

                return $this->createRazorpayContractOrder($contract, (float) $amount);
            });
        } catch (ValidationException $e) {
            return [
                'success' => false,
                'error' => $e->validator->errors()->first(),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Open the Razorpay order and its matching pending payment row.
     */
    protected function createRazorpayContractOrder(Contract $contract, float $amount): array
    {
        $order = $this->razorpay()->order->create([
            'amount' => (int) round($amount * 100), // Amount in paise
            'currency' => 'INR',
            'receipt' => 'contract_'.$contract->id,
            'notes' => [
                'contract_id' => $contract->id,
                'patient_id' => $contract->patient_id,
                'type' => 'contract_payment',
            ],
        ]);

        // Create payment record
        $payment = Payment::create([
            'payment_type' => 'contract',
            'contract_id' => $contract->id,
            'razorpay_order_id' => $order->id,
            'amount' => $amount,
            'status' => 'pending',
            'method' => 'razorpay',
        ]);

        return [
            'success' => true,
            'order_id' => $order->id,
            'amount' => $amount,
            'key' => config('services.razorpay.key'),
            'payment_id' => $payment->id,
        ];
    }

    /**
     * Record an offline (manual) contract payment as completed.
     *
     * The balance check and the insert run inside one transaction against a
     * locked contract row: checking the balance outside the write let two
     * concurrent collections both pass and overshoot the contract total.
     *
     * @throws ValidationException when the amount exceeds the outstanding balance
     */
    public function recordManualContractPayment(Contract $contract, array $data): Payment
    {
        return DB::transaction(function () use ($contract, $data) {
            $contract = Contract::whereKey($contract->getKey())->lockForUpdate()->firstOrFail();

            $this->assertWithinBalance($contract, (float) $data['amount']);

            $payment = Payment::create([
                'payment_type' => 'contract',
                'contract_id' => $contract->id,
                'amount' => $data['amount'],
                'status' => 'completed',
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
            ]);

            // Cash in hand settles the balance the customer may also have had
            // a checkout open for. Any open order that the remaining balance
            // can no longer honour is cancelled, so it cannot capture on top.
            $this->cancelUnfundedOrders($contract->fresh());

            return $payment;
        });
    }

    /**
     * Cancel open orders the remaining balance can no longer cover.
     *
     * Called after money arrives by another route. Anything the contract still
     * has room for is left open, so a legitimate part-payment in flight is not
     * cancelled out from under the customer.
     */
    protected function cancelUnfundedOrders(Contract $contract): void
    {
        $remaining = $contract->balanceDue();
        $liveSince = now()->subMinutes(Contract::orderTtlMinutes());

        $open = $contract->payments()
            ->where('status', 'pending')
            ->orderBy('id')
            ->get();

        // Live checkouts claim the remaining balance first, as in
        // reservedAmount(): an older order past its TTL must not push one the
        // customer may be paying right now into cancellation.
        [$stale, $live] = $open->partition(
            fn (Payment $order) => ! $order->created_at || $order->created_at->lt($liveSince)
        );

        foreach ($live->concat($stale) as $order) {
            if ((float) $order->amount > $remaining + self::EPSILON) {
                // Conditional, so a capture that completed the row since it
                // was read is never turned back into 'failed'.
                Payment::whereKey($order->getKey())
                    ->where('status', 'pending')
                    ->update(['status' => 'failed']);

                continue;
            }

            $remaining -= (float) $order->amount;
        }
    }

    /**
     * Cancel every open gateway order on a contract, e.g. when it is closed.
     *
     * Razorpay orders cannot be cancelled at the gateway, so a customer can
     * still pay one later; that capture is then flagged for refund instead of
     * being credited (see settleCapture()).
     *
     * @return int the number of orders cancelled
     */
    public function cancelOpenOrdersForContract(Contract $contract): int
    {
        return DB::transaction(function () use ($contract) {
            // Same lock order as the collection and capture paths: contract
            // first, then its payment rows.
            Contract::withTrashed()->whereKey($contract->getKey())->lockForUpdate()->first();

            return Payment::where('contract_id', $contract->getKey())
                ->where('status', 'pending')
                ->update(['status' => 'failed']);
        });
    }

    /**
     * Create Razorpay order for employee activation
     */
    public function createEmployeeActivationOrder(Employee $employee): array
    {
        try {
            $amount = config('services.razorpay.employee_payment_amount', 1000);

            $order = $this->razorpay()->order->create([
                'amount' => $amount * 100, // Amount in paise
                'currency' => 'INR',
                'receipt' => 'employee_'.$employee->id,
                'notes' => [
                    'employee_id' => $employee->id,
                    'type' => 'employee_activation',
                ],
            ]);

            // Create payment record
            $payment = Payment::create([
                'payment_type' => 'employee_activation',
                'employee_id' => $employee->id,
                'razorpay_order_id' => $order->id,
                'amount' => $amount,
                'status' => 'pending',
                'method' => 'razorpay',
            ]);

            return [
                'success' => true,
                'order_id' => $order->id,
                'amount' => $amount,
                'payment_id' => $payment->id,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify Razorpay payment signature
     */
    public function verifyPayment(string $orderId, string $paymentId, string $signature): bool
    {
        try {
            $attributes = [
                'razorpay_order_id' => $orderId,
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature,
            ];

            $this->razorpay()->utility->verifyPaymentSignature($attributes);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Complete a payment that arrived through the browser checkout flow.
     *
     * The signature is the per-payment HMAC Razorpay hands to the client. The
     * callback that reaches this has no session, so the signature is checked
     * before the order is even looked up: an unverified request must neither
     * change a row nor be told that an order is paid.
     */
    public function processSuccessfulPayment(string $orderId, string $paymentId, string $signature): bool
    {
        if (! $this->verifyPayment($orderId, $paymentId, $signature)) {
            Log::warning('Razorpay payment signature rejected.', [
                'order_id' => $orderId,
                'payment_id' => $paymentId,
            ]);

            return false;
        }

        return $this->settleCapture($orderId, $paymentId, $signature);
    }

    /**
     * Complete a payment confirmed by a Razorpay webhook.
     *
     * A webhook carries no per-payment signature — the whole request body is
     * authenticated by the webhook HMAC before this is reached, so there is
     * nothing further to verify here.
     */
    public function markPaymentCaptured(string $orderId, string $paymentId): bool
    {
        return $this->settleCapture($orderId, $paymentId);
    }

    /**
     * Apply a verified capture to its order's payment row.
     *
     * Returns true when the order is completed (now or already), false when
     * the order is unknown or the capture could not be credited.
     *
     * Only a row that is still pending is completed. A cancelled order can
     * still be paid at Razorpay (its orders never expire), and crediting it
     * on top of the cash or the newer order that replaced it would collect
     * twice. The contract row is locked before the payment row, the order the
     * collection paths use, so a capture cannot interleave with them.
     */
    protected function settleCapture(string $orderId, string $paymentId, ?string $signature = null): bool
    {
        // Looked up before the transaction opens. Under REPEATABLE READ the
        // first plain read inside a transaction fixes its snapshot; taken
        // before the contract lock, it would hide a capture that committed
        // while this one waited for the lock.
        $found = Payment::where('razorpay_order_id', $orderId)->first();

        if (! $found) {
            return false;
        }

        return DB::transaction(function () use ($found, $paymentId, $signature) {
            $contract = $found->contract_id
                ? Contract::withTrashed()->whereKey($found->contract_id)->lockForUpdate()->first()
                : null;

            $payment = Payment::whereKey($found->getKey())->lockForUpdate()->first();

            if ($payment->status === 'completed') {
                // The callback and the webhook both land for one order, and
                // the webhook is retried: the same capture is already counted.
                if ($payment->razorpay_payment_id === null || $payment->razorpay_payment_id === $paymentId) {
                    return true;
                }

                $this->flagUncreditedCapture($payment, $paymentId, "the order was already paid by Razorpay payment {$payment->razorpay_payment_id}");

                return false;
            }

            if ($payment->status !== 'pending') {
                $this->flagUncreditedCapture($payment, $paymentId, $payment->status === 'failed'
                    ? 'the order had already been cancelled'
                    : "the order was already marked {$payment->status}");

                return false;
            }

            if ($contract && ($contract->status === 'closed' || $contract->trashed())) {
                // Covers orders left open on contracts closed before closing
                // cancelled them: a closed contract takes no more money.
                $this->flagUncreditedCapture($payment, $paymentId, $contract->trashed()
                    ? 'the contract had been deleted'
                    : 'the contract had already been closed');

                return false;
            }

            if ($contract) {
                // A locking read returns the latest committed rows, so the
                // check holds even if a caller's transaction took its
                // snapshot before the contract lock was granted.
                $paid = (float) $contract->payments()
                    ->where('status', 'completed')
                    ->lockForUpdate()
                    ->sum('amount');
                $balance = max(0, (float) $contract->amount - $paid);

                if ((float) $payment->amount > $balance + self::EPSILON) {
                    $this->flagUncreditedCapture($payment, $paymentId, 'it would take the contract past its total (balance due ₹'.number_format($balance, 2).')');

                    return false;
                }
            }

            $payment->update(array_filter([
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature,
                'status' => 'completed',
                'paid_at' => now(),
            ], fn ($value) => $value !== null));

            // Handle post-payment actions
            if ($payment->payment_type === 'employee_activation' && $payment->employee) {
                $this->activateEmployee($payment->employee);
            }

            return true;
        });
    }

    /**
     * Record money Razorpay captured that the contract cannot be credited with.
     *
     * The customer really paid, so it cannot just be dropped: the row stays
     * out of the paid totals, carries a refund marker in its notes, and an
     * admin notification points staff at the payment to refund.
     *
     * Must run inside the transaction that holds the payment row lock.
     */
    protected function flagUncreditedCapture(Payment $payment, string $paymentId, string $reason): void
    {
        // A replayed webhook, or verify and webhook for the same capture,
        // must not stack markers or alerts.
        if (str_contains((string) $payment->notes, Payment::REFUND_MARKER." {$paymentId}:")) {
            return;
        }

        $marker = Payment::REFUND_MARKER." {$paymentId}: captured by Razorpay on ".now()->format('d M Y h:i A')
            ." but not credited because {$reason}. Refund it from the Razorpay dashboard.";

        $updates = ['notes' => ltrim($payment->notes."\n".$marker)];

        // An order that was still open stops reserving the balance.
        if ($payment->status === 'pending') {
            $updates['status'] = 'failed';
        }

        // Keeps the id to refund visible on the payment page.
        if (! $payment->razorpay_payment_id) {
            $updates['razorpay_payment_id'] = $paymentId;
        }

        $payment->update($updates);

        Log::error('Razorpay capture not credited; refund required.', [
            'payment_id' => $payment->id,
            'contract_id' => $payment->contract_id,
            'razorpay_order_id' => $payment->razorpay_order_id,
            'razorpay_payment_id' => $paymentId,
            'amount' => $payment->amount,
            'reason' => $reason,
        ]);

        Notification::create([
            'type' => 'payment_refund_required',
            'title' => 'Payment Needs Refund',
            'message' => 'Razorpay payment '.$paymentId.' of ₹'.number_format((float) $payment->amount, 2)
                ." for payment #{$payment->id} was captured but not credited because {$reason}. Refund it from the Razorpay dashboard.",
            'link' => route('admin.payments.show', $payment),
            'contract_id' => $payment->contract_id,
        ]);
    }

    /**
     * Activate employee after payment
     */
    protected function activateEmployee(Employee $employee): void
    {
        $validityMonths = config('services.razorpay.employee_payment_validity_months', 12);

        $employee->update([
            'status' => 'active',
            'activated_at' => now(),
            'payment_valid_until' => now()->addMonths($validityMonths),
        ]);
    }
}
