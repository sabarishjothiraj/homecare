<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Display payment listing
     */
    public function index(Request $request)
    {
        // A payment is a money record and outlives its contract or patient
        // being deleted, so both are loaded with their trashed rows.
        $query = Payment::with([
            'contract' => fn ($q) => $q->withTrashed(),
            'contract.patient' => fn ($q) => $q->withTrashed(),
        ]);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by payment type
        if ($request->filled('payment_type')) {
            $query->where('payment_type', $request->payment_type);
        }

        // Filter by method
        if ($request->filled('method')) {
            $query->where('method', $request->method);
        }

        // Date range filter
        if ($request->filled('start_date')) {
            $query->whereDate('paid_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('paid_at', '<=', $request->end_date);
        }

        $payments = $query->latest('paid_at')->paginate(20)->withQueryString();

        // Calculate totals
        $totalAmount = Payment::completed()->sum('amount');
        $pendingAmount = Payment::pending()->sum('amount');
        $refundedAmount = Contract::whereNotNull('refund_amount')->sum('refund_amount');
        $netAmount = $totalAmount - $refundedAmount;

        // Refunds are recorded on the contract when it is closed, dated by closed_at
        $refundQuery = Contract::with(['patient' => fn ($q) => $q->withTrashed()])
            ->whereNotNull('refund_amount')
            ->where('refund_amount', '>', 0);

        if ($request->input('payment_type') === 'employee_activation') {
            $refundQuery->whereRaw('1 = 0');
        }

        if ($request->filled('start_date')) {
            $refundQuery->whereDate('closed_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $refundQuery->whereDate('closed_at', '<=', $request->end_date);
        }

        $refunds = $refundQuery->latest('closed_at')->paginate(20, ['*'], 'refunds_page')->withQueryString();

        $tab = $request->input('tab') === 'refunds' ? 'refunds' : 'payments';

        return view('admin.payments.index', compact('payments', 'totalAmount', 'pendingAmount', 'refundedAmount', 'netAmount', 'refunds', 'tab'));
    }

    /**
     * Show payment details
     */
    public function show(Payment $payment)
    {
        // Trashed rows are loaded so a historical payment still shows who
        // paid; the view drops the links to records that no longer open.
        $payment->load([
            'contract' => fn ($q) => $q->withTrashed(),
            'contract.patient' => fn ($q) => $q->withTrashed(),
            'contract.service' => fn ($q) => $q->withTrashed(),
            'employee' => fn ($q) => $q->withTrashed(),
        ]);

        return view('admin.payments.show', compact('payment'));
    }

    /**
     * Handle Razorpay webhook
     */
    public function webhook(Request $request)
    {
        try {
            $webhookSecret = config('services.razorpay.webhook_secret');

            // Without a configured secret every signature would be compared
            // against an HMAC keyed on an empty string, silently rejecting
            // real traffic. Fail loudly instead of looking like a bad sender.
            if (empty($webhookSecret)) {
                Log::error('Razorpay webhook rejected: RAZORPAY_WEBHOOK_SECRET is not configured.');

                return response()->json(['error' => 'Webhook not configured'], 500);
            }

            $webhookSignature = (string) $request->header('X-Razorpay-Signature');
            $webhookBody = $request->getContent();

            // Verify webhook signature. hash_equals keeps the comparison
            // constant-time so a forged signature cannot be tuned byte by byte.
            $expectedSignature = hash_hmac('sha256', $webhookBody, $webhookSecret);

            if (! hash_equals($expectedSignature, $webhookSignature)) {
                return response()->json(['error' => 'Invalid signature'], 400);
            }

            $payload = $request->all();
            $entity = data_get($payload, 'payload.payment.entity');

            // Handle payment success
            if (data_get($payload, 'event') === 'payment.captured' && $entity) {
                $razorpayPaymentId = data_get($entity, 'id');
                $razorpayOrderId = data_get($entity, 'order_id');

                if ($razorpayPaymentId && $razorpayOrderId) {
                    // The body HMAC above already authenticated this request,
                    // so there is no per-payment signature left to check.
                    $this->paymentService->markPaymentCaptured($razorpayOrderId, $razorpayPaymentId);
                }
            }

            return response()->json(['status' => 'success']);

        } catch (\Throwable $e) {
            // Throwable, not Exception: a TypeError or ArgumentCountError in
            // the payment path would otherwise escape as an uncaught fatal and
            // leave Razorpay retrying the hook indefinitely.
            Log::error('Razorpay webhook error: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle payment callback
     */
    public function callback(Request $request)
    {
        try {
            $validated = $request->validate([
                'razorpay_order_id' => 'required|string',
                'razorpay_payment_id' => 'required|string',
                'razorpay_signature' => 'required|string',
            ]);

            // processSuccessfulPayment verifies the signature itself, so the
            // separate pre-check that used to sit here only duplicated work.
            $success = $this->paymentService->processSuccessfulPayment(
                $validated['razorpay_order_id'],
                $validated['razorpay_payment_id'],
                $validated['razorpay_signature']
            );

            return redirect()->route('admin.payments.index')->with(
                $success ? 'success' : 'error',
                $success ? 'Payment completed successfully!' : 'Payment verification failed!'
            );

        } catch (\Throwable $e) {
            Log::error('Payment callback error: '.$e->getMessage(), ['exception' => $e]);

            // The raw message can carry gateway internals; keep it in the log.
            return redirect()->route('admin.payments.index')
                ->with('error', 'Payment processing failed. Please contact support.');
        }
    }
}
