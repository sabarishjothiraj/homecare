@extends('layouts.admin')

@section('title', 'Payout Details')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Payout #{{ $payout->id }}</h2>
            <p class="text-muted mb-0">Payout Details</p>
        </div>
        <div>
            @if($payout->status !== 'paid')
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#markPaidModal">
                    <i class="fas fa-check-circle me-2"></i>Mark as Paid
                </button>
                <a href="{{ route('admin.payouts.edit', $payout) }}" class="btn btn-warning">
                    <i class="fas fa-edit me-2"></i>Edit
                </a>
            @endif
            <a href="{{ route('admin.payouts.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to List
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
        <!-- Payout Information -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Payout Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Payout ID</label>
                            <p class="mb-0"><strong>#{{ $payout->id }}</strong></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Status</label>
                            <p class="mb-0">
                                @if($payout->status === 'paid')
                                    <span class="badge bg-success">Paid</span>
                                @else
                                    <span class="badge bg-warning">Pending</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Amount</label>
                            <p class="mb-0"><strong class="text-success fs-4">₹{{ number_format($payout->amount, 2) }}</strong></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Payout Date</label>
                            <p class="mb-0"><strong>{{ $payout->period_end ? $payout->period_end->format('M d, Y') : 'N/A' }}</strong></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Payment Method</label>
                            <p class="mb-0">{{ ucfirst(str_replace('_', ' ', $payout->frequency ?? 'N/A')) }}</p>
                        </div>
                        @if($payout->status === 'paid' && $payout->paid_at)
                        <div class="col-md-6">
                            <label class="text-muted small">Paid At</label>
                            <p class="mb-0">{{ $payout->paid_at->format('M d, Y h:i A') }}</p>
                        </div>
                        @endif
                        @if($payout->period_start)
                        <div class="col-md-6">
                            <label class="text-muted small">Period Start</label>
                            <p class="mb-0">{{ $payout->period_start->format('M d, Y') }}</p>
                        </div>
                        @endif
                        @if($payout->period_end)
                        <div class="col-md-6">
                            <label class="text-muted small">Period End</label>
                            <p class="mb-0">{{ $payout->period_end->format('M d, Y') }}</p>
                        </div>
                        @endif
                        <div class="col-md-6">
                            <label class="text-muted small">Created</label>
                            <p class="mb-0">{{ $payout->created_at->format('M d, Y h:i A') }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Last Updated</label>
                            <p class="mb-0">{{ $payout->updated_at->diffForHumans() }}</p>
                        </div>
                        @if($payout->notes)
                            <div class="col-12">
                                <label class="text-muted small">Notes</label>
                                <p class="mb-0">{{ $payout->notes }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Employee Information -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-user-nurse me-2"></i>Employee Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Name</label>
                            <p class="mb-0"><strong>{{ $payout->employee->getField('name', 'N/A') }}</strong></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Phone</label>
                            <p class="mb-0">{{ $payout->employee->getField('phone', 'N/A') }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Email</label>
                            <p class="mb-0">{{ $payout->employee->getField('email', 'N/A') }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Employee Status</label>
                            <p class="mb-0">
                                @if($payout->employee->status === 'active')
                                    <span class="badge bg-success">Active</span>
                                @elseif($payout->employee->status === 'pending')
                                    <span class="badge bg-warning">Pending</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-12">
                            <a href="{{ route('admin.employees.show', $payout->employee) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye me-1"></i>View Employee Details
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-md-4">
            <!-- Quick Actions -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-cog me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    @if($payout->status !== 'paid')
                        <button type="button" class="btn btn-outline-success w-100 mb-2" data-bs-toggle="modal" data-bs-target="#markPaidModal">
                            <i class="fas fa-check-circle me-2"></i>Mark as Paid
                        </button>
                        <a href="{{ route('admin.payouts.edit', $payout) }}" class="btn btn-outline-warning w-100 mb-2">
                            <i class="fas fa-edit me-2"></i>Edit Payout
                        </a>
                        <form action="{{ route('admin.payouts.destroy', $payout) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this payout?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger w-100">
                                <i class="fas fa-trash me-2"></i>Delete Payout
                            </button>
                        </form>
                    @else
                        <div class="alert alert-success mb-0">
                            <i class="fas fa-check-circle me-2"></i>
                            This payout has been marked as paid and cannot be modified.
                        </div>
                    @endif
                </div>
            </div>

            <!-- Payment Summary -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-receipt me-2"></i>Payment Summary</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Amount:</span>
                        <strong class="text-success">₹{{ number_format($payout->amount, 2) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Method:</span>
                        <strong>{{ ucfirst(str_replace('_', ' ', $payout->frequency ?? 'N/A')) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Status:</span>
                        @if($payout->status === 'paid')
                            <span class="badge bg-success">Paid</span>
                        @else
                            <span class="badge bg-warning">Pending</span>
                        @endif
                    </div>
                    @if($payout->status === 'paid' && $payout->paid_at)
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Paid On:</span>
                        <strong>{{ $payout->paid_at->format('M d, Y') }}</strong>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mark as Paid Modal -->
@if($payout->status !== 'paid')
<div class="modal fade" id="markPaidModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.payouts.mark-paid', $payout) }}" method="POST" data-ajax="true">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Mark Payout as Paid</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        You are about to mark this payout as paid. This action will record the payment and prevent further modifications.
                    </div>
                    <div class="mb-3">
                        <label for="transaction_reference" class="form-label">Transaction Reference</label>
                        <input type="text" class="form-control" id="transaction_reference" name="transaction_reference" placeholder="e.g., TXN123456, Cheque #789">
                        <small class="text-muted">Optional: Enter transaction ID, cheque number, or reference</small>
                    </div>
                    <div class="mb-3">
                        <label for="paid_notes" class="form-label">Payment Notes</label>
                        <textarea class="form-control" id="paid_notes" name="paid_notes" rows="3" placeholder="Enter any notes about the payment"></textarea>
                    </div>
                    <div class="bg-light p-3 rounded">
                        <p class="mb-1"><strong>Amount:</strong> ₹{{ number_format($payout->amount, 2) }}</p>
                        <p class="mb-1"><strong>Employee:</strong> {{ $payout->employee->getField('name', 'N/A') }}</p>
                        <p class="mb-0"><strong>Payment Method:</strong> {{ ucfirst(str_replace('_', ' ', $payout->frequency ?? 'N/A')) }}</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check-circle me-2"></i>Mark as Paid
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

