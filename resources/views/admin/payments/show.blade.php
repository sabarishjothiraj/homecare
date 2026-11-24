@extends('layouts.admin')

@section('title', 'Payment Details')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Payment #{{ $payment->id }}</h2>
            <p class="text-muted mb-0">Payment Details</p>
        </div>
        <a href="{{ route('admin.payments.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to List
        </a>
    </div>

    <div class="row g-3">
        <!-- Payment Information -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Payment Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Payment ID</label>
                            <p class="mb-0"><strong>#{{ $payment->id }}</strong></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Payment Type</label>
                            <p class="mb-0">
                                @if($payment->payment_type === 'contract')
                                    <span class="badge bg-primary">Contract Payment</span>
                                @else
                                    <span class="badge bg-info">Employee Activation</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Amount</label>
                            <p class="mb-0"><strong class="text-success fs-4">₹{{ number_format($payment->amount, 2) }}</strong></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Status</label>
                            <p class="mb-0">
                                @if($payment->status === 'completed')
                                    <span class="badge bg-success">Completed</span>
                                @elseif($payment->status === 'pending')
                                    <span class="badge bg-warning">Pending</span>
                                @elseif($payment->status === 'failed')
                                    <span class="badge bg-danger">Failed</span>
                                @else
                                    <span class="badge bg-secondary">{{ ucfirst($payment->status) }}</span>
                                @endif
                                @if($payment->needsRefund())
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle">Refund required</span>
                                @endif
                            </p>
                        </div>
                        @if($payment->needsRefund())
                            <div class="col-12">
                                <div class="alert alert-danger mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Razorpay captured money for this order that was not credited to the contract. Refund it from the Razorpay dashboard; the details are in the notes below.
                                </div>
                            </div>
                        @endif
                        <div class="col-md-6">
                            <label class="text-muted small">Payment Method</label>
                            <p class="mb-0">{{ ucfirst(str_replace('_', ' ', $payment->method)) }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Paid At</label>
                            <p class="mb-0">
                                @if($payment->paid_at)
                                    {{ $payment->paid_at->format('M d, Y h:i A') }}
                                @else
                                    <span class="text-muted">Not yet paid</span>
                                @endif
                            </p>
                        </div>
                        @if($payment->razorpay_order_id)
                        <div class="col-md-6">
                            <label class="text-muted small">Razorpay Order ID</label>
                            <p class="mb-0"><code>{{ $payment->razorpay_order_id }}</code></p>
                        </div>
                        @endif
                        @if($payment->razorpay_payment_id)
                        <div class="col-md-6">
                            <label class="text-muted small">Razorpay Payment ID</label>
                            <p class="mb-0"><code>{{ $payment->razorpay_payment_id }}</code></p>
                        </div>
                        @endif
                        <div class="col-md-6">
                            <label class="text-muted small">Created</label>
                            <p class="mb-0">{{ $payment->created_at->format('M d, Y h:i A') }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Last Updated</label>
                            <p class="mb-0">{{ $payment->updated_at->diffForHumans() }}</p>
                        </div>
                        @if($payment->reference)
                            <div class="col-md-6">
                                <label class="text-muted small">Reference</label>
                                <p class="mb-0">{{ $payment->reference }}</p>
                            </div>
                        @endif
                        @if($payment->notes)
                            <div class="col-12">
                                <label class="text-muted small">Notes</label>
                                <p class="mb-0">{!! nl2br(e($payment->notes)) !!}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Contract Information (if contract payment) -->
            @if($payment->contract)
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-file-contract me-2"></i>Contract Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Contract Number</label>
                            <p class="mb-0">
                                <strong class="text-primary">{{ $payment->contract->contract_number }}</strong>
                                @if($payment->contract->trashed())
                                    <span class="badge bg-secondary ms-1">Deleted</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Contract Status</label>
                            <p class="mb-0">
                                @if($payment->contract->status === 'active')
                                    <span class="badge bg-success">Active</span>
                                @elseif($payment->contract->status === 'not_assigned')
                                    <span class="badge bg-warning">Not Assigned</span>
                                @elseif($payment->contract->status === 'closed')
                                    <span class="badge bg-secondary">Closed</span>
                                @else
                                    <span class="badge bg-secondary">{{ ucfirst($payment->contract->status) }}</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Service</label>
                            <p class="mb-0"><strong>{{ $payment->contract->service->name ?? 'N/A' }}</strong></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Contract Amount</label>
                            <p class="mb-0"><strong class="text-success">₹{{ number_format($payment->contract->amount, 0) }}</strong></p>
                        </div>
                        @if($payment->contract->refund_amount !== null || $payment->contract->refund_details)
                            <div class="col-12">
                                <div class="border border-danger-subtle bg-danger bg-opacity-10 rounded p-3">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                        <div>
                                            <div class="text-danger fw-semibold mb-1"><i class="fas fa-undo-alt me-1"></i>Refund</div>
                                            @if($payment->contract->refund_amount !== null)
                                                <div class="fs-5 fw-semibold text-danger">₹{{ number_format($payment->contract->refund_amount, 2) }}</div>
                                            @endif
                                            @if($payment->contract->refund_details)
                                                <div class="small">{{ $payment->contract->refund_details }}</div>
                                            @endif
                                        </div>
                                        <div class="text-end small text-muted">
                                            @if($payment->contract->closed_at)
                                                Refunded on {{ $payment->contract->closed_at->format('M d, Y') }}<br>
                                            @endif
                                            @if($payment->contract->closure_reason)
                                                Reason: {{ $payment->contract->closure_reason }}
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                        @unless($payment->contract->trashed())
                            <div class="col-12">
                                <a href="{{ route('admin.contracts.show', $payment->contract) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye me-1"></i>View Full Contract
                                </a>
                            </div>
                        @endunless
                    </div>
                </div>
            </div>

            <!-- Patient Information -->
            @php $patient = $payment->contract->patient; @endphp
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>Patient Information</h5>
                </div>
                <div class="card-body">
                    @if($patient)
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="text-muted small">Name</label>
                                <p class="mb-0">
                                    <strong>{{ $patient->name }}</strong>
                                    @if($patient->trashed())
                                        <span class="badge bg-secondary ms-1">Deleted</span>
                                    @endif
                                </p>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted small">Phone</label>
                                <p class="mb-0">{{ $patient->phone ?: 'N/A' }}</p>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted small">Email</label>
                                <p class="mb-0">{{ $patient->email ?? 'N/A' }}</p>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted small">Address</label>
                                <p class="mb-0">{{ $patient->full_address ?: 'N/A' }}</p>
                            </div>
                            @unless($patient->trashed())
                                <div class="col-12">
                                    <a href="{{ route('admin.patients.show', $patient) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i>View Patient Details
                                    </a>
                                </div>
                            @endunless
                        </div>
                    @else
                        <p class="text-muted mb-0">Patient record not available.</p>
                    @endif
                </div>
            </div>
            @endif

            <!-- Employee Information (if employee activation payment) -->
            @if($payment->employee)
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-user-nurse me-2"></i>Employee Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Name</label>
                            <p class="mb-0"><strong>{{ $payment->employee->getField('name', 'N/A') }}</strong></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Phone</label>
                            <p class="mb-0">{{ $payment->employee->getField('phone', 'N/A') }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Email</label>
                            <p class="mb-0">{{ $payment->employee->getField('email', 'N/A') }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Employee Status</label>
                            <p class="mb-0">
                                @if($payment->employee->status === 'active')
                                    <span class="badge bg-success">Active</span>
                                @elseif($payment->employee->status === 'pending')
                                    <span class="badge bg-warning">Pending</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </p>
                        </div>
                        @unless($payment->employee->trashed())
                            <div class="col-12">
                                <a href="{{ route('admin.employees.show', $payment->employee) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye me-1"></i>View Employee Details
                                </a>
                            </div>
                        @endunless
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="col-md-4">
            <!-- Payment Summary -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-receipt me-2"></i>Payment Summary</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Amount:</span>
                        <strong class="text-success">₹{{ number_format($payment->amount, 2) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Method:</span>
                        <strong>{{ ucfirst(str_replace('_', ' ', $payment->method)) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Type:</span>
                        <strong>{{ ucfirst(str_replace('_', ' ', $payment->payment_type)) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Status:</span>
                        @if($payment->status === 'completed')
                            <span class="badge bg-success">Completed</span>
                        @elseif($payment->status === 'pending')
                            <span class="badge bg-warning">Pending</span>
                        @elseif($payment->status === 'failed')
                            <span class="badge bg-danger">Failed</span>
                        @else
                            <span class="badge bg-secondary">{{ ucfirst($payment->status) }}</span>
                        @endif
                    </div>
                    @if($payment->paid_at)
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Paid On:</span>
                        <strong>{{ $payment->paid_at->format('M d, Y') }}</strong>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

