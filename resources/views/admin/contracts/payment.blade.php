@extends('layouts.admin')

@section('title', 'Contract Payment')

@php
    $paid = $contract->paidAmount();
    $balance = $contract->balanceDue();
    $paymentStatus = $contract->paymentStatus();
    // Nothing more is collected once a contract is closed (the server refuses it too)
    $isClosed = $contract->status === 'closed';
@endphp

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Payment</h2>
            <p class="text-muted mb-0">Collect payment for contract {{ $contract->contract_number }}</p>
        </div>
        <div>
            @if($contract->pdf_path)
                <a href="{{ route('admin.contracts.print-pdf', $contract) }}" class="btn btn-outline-primary" target="_blank">
                    <i class="fas fa-print me-2"></i>Print PDF
                </a>
            @endif
            <a href="{{ route('admin.contracts.show', $contract) }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-right me-2"></i>Done / View Contract
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-3">
        <!-- Payment Calculation -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Payment Calculation</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Employee</th>
                                    <th>Service</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($contract->assignments->where('is_active', true)->whereNull('cover_for_assignment_id') as $assignment)
                                    <tr>
                                        <td>
                                            <strong>{{ $assignment->employee->getField('name', 'N/A') }}</strong><br>
                                            <small class="text-muted">{{ $assignment->employee->getField('phone', '') }}</small>
                                        </td>
                                        <td>{{ $assignment->service->name ?? $assignment->contractItem->service->name ?? ($contract->service->name ?? 'N/A') }}</td>
                                        <td class="text-end">₹{{ number_format($assignment->line_amount ?? $assignment->contractItem->amount ?? 0, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3">No active assignments</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <th colspan="2" class="text-end">Contract Total</th>
                                    <th class="text-end">₹{{ number_format($contract->amount, 2) }}</th>
                                </tr>
                                <tr>
                                    <td colspan="2" class="text-end text-success">Paid</td>
                                    <td class="text-end text-success">₹{{ number_format($paid, 2) }}</td>
                                </tr>
                                <tr>
                                    @if($isClosed)
                                        <td colspan="2" class="text-end fw-bold text-muted">Uncollected (contract closed)</td>
                                        <td class="text-end fw-bold text-muted">₹{{ number_format($balance, 2) }}</td>
                                    @else
                                        <td colspan="2" class="text-end fw-bold {{ $balance > 0 ? 'text-danger' : 'text-success' }}">Balance Due</td>
                                        <td class="text-end fw-bold {{ $balance > 0 ? 'text-danger' : 'text-success' }}">₹{{ number_format($balance, 2) }}</td>
                                    @endif
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Payment History -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Payment History</h5>
                </div>
                <div class="card-body p-0">
                    @if($contract->payments->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Reference</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($contract->payments->sortByDesc('created_at') as $payment)
                                        <tr>
                                            <td>{{ optional($payment->paid_at ?? $payment->created_at)->format('M d, Y') }}</td>
                                            <td><strong>₹{{ number_format($payment->amount, 2) }}</strong></td>
                                            <td>{{ ucfirst(str_replace('_', ' ', $payment->method)) }}</td>
                                            <td>{{ $payment->reference ?? '—' }}</td>
                                            <td>
                                                @if($payment->status === 'completed')
                                                    <span class="badge bg-success">Completed</span>
                                                @elseif($payment->status === 'pending')
                                                    <span class="badge bg-warning">Pending</span>
                                                @elseif($payment->status === 'failed')
                                                    <span class="badge bg-danger">Failed</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ ucfirst($payment->status) }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-receipt fa-3x text-muted mb-3 opacity-25"></i>
                            <p class="text-muted mb-0">No payments recorded yet</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Collect Payment -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Collect Payment</h5>
                    @if($paymentStatus === 'paid')
                        <span class="badge bg-success">Fully Paid</span>
                    @elseif($paymentStatus === 'partial')
                        <span class="badge bg-warning">Partially Paid</span>
                    @else
                        <span class="badge bg-secondary">Unpaid</span>
                    @endif
                </div>
                <div class="card-body">
                    @if($isClosed)
                        <div class="alert alert-secondary mb-0">
                            <i class="fas fa-lock me-2"></i>This contract is closed. No further payments can be collected.
                        </div>
                    @elseif($balance <= 0)
                        <div class="alert alert-success mb-0">
                            <i class="fas fa-check-circle me-2"></i>This contract is fully paid.
                        </div>
                    @else
                        @if($razorpayEnabled)
                        <ul class="nav nav-tabs mb-3" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#offline-pane" type="button">
                                    <i class="fas fa-hand-holding-usd me-1"></i>Offline
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#online-pane" type="button">
                                    <i class="fas fa-globe me-1"></i>Online (Razorpay)
                                </button>
                            </li>
                        </ul>
                        @endif

                        <div class="tab-content">
                            <!-- Offline / Manual -->
                            <div class="tab-pane fade show active" id="offline-pane">
                                <form action="{{ route('admin.contracts.payment.store', $contract) }}" method="POST">
                                    @csrf
                                    <div class="mb-3">
                                        <label for="amount" class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                                        <input type="number" step="0.01" min="0.01" max="{{ $balance }}" class="form-control @error('amount') is-invalid @enderror" id="amount" name="amount" value="{{ old('amount', $balance) }}" required>
                                        <small class="text-muted">Outstanding balance: ₹{{ number_format($balance, 2) }}. Enter a smaller value for a partial / advance payment.</small>
                                        @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>

                                    <div class="mb-3">
                                        <label for="method" class="form-label">Method <span class="text-danger">*</span></label>
                                        <select class="form-select @error('method') is-invalid @enderror" id="method" name="method" required>
                                            <option value="cash" {{ old('method', $contract->payment_method) === 'cash' ? 'selected' : '' }}>Cash</option>
                                            <option value="cheque" {{ old('method', $contract->payment_method) === 'cheque' ? 'selected' : '' }}>Cheque</option>
                                            <option value="bank_transfer" {{ old('method', $contract->payment_method) === 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                            <option value="online" {{ old('method', $contract->payment_method) === 'online' ? 'selected' : '' }}>Online (other)</option>
                                        </select>
                                        @error('method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>

                                    <div class="mb-3">
                                        <label for="reference" class="form-label">Reference No. (Optional)</label>
                                        <input type="text" class="form-control" id="reference" name="reference" value="{{ old('reference') }}" placeholder="Cheque / txn / receipt number">
                                    </div>

                                    <div class="mb-3">
                                        <label for="paid_at" class="form-label">Payment Date</label>
                                        <input type="date" class="form-control" id="paid_at" name="paid_at" value="{{ old('paid_at', date('Y-m-d')) }}">
                                    </div>

                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Notes (Optional)</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="2">{{ old('notes') }}</textarea>
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-save me-2"></i>Record Payment
                                    </button>
                                </form>
                            </div>

                            <!-- Online / Razorpay -->
                            @if($razorpayEnabled)
                            <div class="tab-pane fade" id="online-pane">
                                <div class="mb-3">
                                        <label for="rzp_amount" class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                                        <input type="number" step="0.01" min="0.01" max="{{ $balance }}" class="form-control" id="rzp_amount" value="{{ $balance }}">
                                        <small class="text-muted">Outstanding balance: ₹{{ number_format($balance, 2) }}.</small>
                                    </div>
                                    <button type="button" class="btn btn-success w-100" id="rzp-pay-btn"
                                            data-order-url="{{ route('admin.contracts.payment.razorpay-order', $contract) }}"
                                            data-verify-url="{{ route('admin.contracts.payment.razorpay-verify', $contract) }}"
                                            data-name="{{ setting('company_name', 'Home Nursing Care') }}"
                                            data-description="Contract {{ $contract->contract_number }}"
                                            data-prefill-name="{{ $contract->patient->name ?? '' }}"
                                            data-prefill-contact="{{ $contract->patient->phone ?? '' }}"
                                            data-prefill-email="{{ $contract->patient->email ?? '' }}">
                                        <i class="fas fa-lock me-2"></i>Pay Online
                                    </button>
                                    <div id="rzp-status" class="small mt-2"></div>
                            </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <!-- Patient Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-user me-2"></i>Patient</h6>
                </div>
                <div class="card-body">
                    <p class="small mb-2"><strong>Name:</strong> {{ $contract->patient->name ?? 'N/A' }}</p>
                    <p class="small mb-2"><strong>Phone:</strong> {{ $contract->patient->phone ?? 'N/A' }}</p>
                    <p class="small mb-0"><strong>Default Method:</strong> {{ ucfirst(str_replace('_', ' ', $contract->payment_method)) }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@if($razorpayEnabled && $balance > 0 && ! $isClosed)
@push('scripts')
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const payBtn = document.getElementById('rzp-pay-btn');
    if (!payBtn) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const statusEl = document.getElementById('rzp-status');

    function setStatus(message, type) {
        statusEl.textContent = message;
        statusEl.className = 'small mt-2 text-' + (type || 'muted');
    }

    payBtn.addEventListener('click', async function () {
        const amountInput = document.getElementById('rzp_amount');
        const amount = parseFloat(amountInput.value);
        if (!amount || amount <= 0) {
            setStatus('Enter a valid amount.', 'danger');
            return;
        }

        payBtn.disabled = true;
        setStatus('Creating secure order…');

        try {
            const orderResp = await fetch(payBtn.dataset.orderUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ amount: amount })
            });
            const order = await orderResp.json();

            if (!order.success) {
                setStatus(order.error || 'Could not create order.', 'danger');
                payBtn.disabled = false;
                return;
            }

            const options = {
                key: order.key,
                amount: Math.round(order.amount * 100),
                currency: 'INR',
                name: payBtn.dataset.name,
                description: payBtn.dataset.description,
                order_id: order.order_id,
                prefill: {
                    name: payBtn.dataset.prefillName,
                    email: payBtn.dataset.prefillEmail,
                    contact: payBtn.dataset.prefillContact
                },
                handler: async function (response) {
                    setStatus('Verifying payment…');
                    const verifyResp = await fetch(payBtn.dataset.verifyUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                        body: JSON.stringify({
                            razorpay_order_id: response.razorpay_order_id,
                            razorpay_payment_id: response.razorpay_payment_id,
                            razorpay_signature: response.razorpay_signature
                        })
                    });
                    const verify = await verifyResp.json();
                    if (verify.success) {
                        window.location.href = verify.redirect;
                    } else {
                        setStatus(verify.message || 'Verification failed.', 'danger');
                        payBtn.disabled = false;
                    }
                },
                modal: {
                    ondismiss: function () {
                        setStatus('Payment cancelled.', 'warning');
                        payBtn.disabled = false;
                    }
                }
            };

            const rzp = new Razorpay(options);
            rzp.on('payment.failed', function (response) {
                setStatus('Payment failed: ' + (response.error && response.error.description ? response.error.description : 'unknown error'), 'danger');
                payBtn.disabled = false;
            });
            rzp.open();
        } catch (err) {
            setStatus('Something went wrong. Please try again.', 'danger');
            payBtn.disabled = false;
        }
    });
});
</script>
@endpush
@endif
