<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Contract;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Razorpay\Api\Api;
use Exception;

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
                        : 'Amount exceeds the outstanding balance of ₹' . number_format($balance, 2) . '.',
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
                        : 'Amount exceeds the ₹' . number_format($available, 2) . ' still available to charge.',
                ],
            ]);
        }
    }

    /**
     * Release orders that were opened but never paid.
     *
     * Marked failed rather than deleted so an abandoned checkout stays visible
     * in the payment history.
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
            'receipt' => 'contract_' . $contract->id,
            'notes' => [
                'contract_id' => $contract->id,
                'patient_id' => $contract->patient_id,
                'type' => 'contract_payment'
            ]
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

        $open = $contract->payments()
            ->where('status', 'pending')
            ->orderBy('id')
            ->get();

        foreach ($open as $order) {
            if ((float) $order->amount > $remaining + self::EPSILON) {
                $order->update(['status' => 'failed']);

                continue;
            }

            $remaining -= (float) $order->amount;
        }
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
                'receipt' => 'employee_' . $employee->id,
                'notes' => [
                    'employee_id' => $employee->id,
                    'type' => 'employee_activation'
                ]
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
     * The signature is the per-payment HMAC Razorpay hands to the client, so
     * it has to be checked here before the row is completed.
     */
    public function processSuccessfulPayment(string $orderId, string $paymentId, string $signature): bool
    {
        $payment = $this->findOpenPayment($orderId);

        if (!$payment instanceof Payment) {
            return $payment;
        }

        if (!$this->verifyPayment($orderId, $paymentId, $signature)) {
            $payment->update(['status' => 'failed']);
            return false;
        }

        return $this->completePayment($payment, $paymentId, $signature);
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
        $payment = $this->findOpenPayment($orderId);

        if (!$payment instanceof Payment) {
            return $payment;
        }

        return $this->completePayment($payment, $paymentId);
    }

    /**
     * Locate the payment for an order, short-circuiting one already completed.
     *
     * Returns the Payment to act on, or a bool that the caller should return
     * as-is: false when the order is unknown, true when it is already done.
     *
     * The callback and the webhook can both land for the same order;
     * completing it twice would double-count against the contract total.
     */
    protected function findOpenPayment(string $orderId): Payment|bool
    {
        $payment = Payment::where('razorpay_order_id', $orderId)->first();

        if (!$payment) {
            return false;
        }

        return $payment->status === 'completed' ? true : $payment;
    }

    /**
     * Mark a verified payment completed and run its post-payment actions.
     */
    protected function completePayment(Payment $payment, string $paymentId, ?string $signature = null): bool
    {
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

