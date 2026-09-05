@extends('layouts.admin')

@section('title', 'Payments')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Payments</h2>
            <p class="text-muted mb-0">View all contract and employee activation payments</p>
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

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-1">Total Completed</h6>
                            <h3 class="mb-0">₹{{ number_format($totalAmount, 0) }}</h3>
                        </div>
                        <div class="bg-white bg-opacity-25 rounded-circle p-3">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-1">Total Pending</h6>
                            <h3 class="mb-0">₹{{ number_format($pendingAmount, 0) }}</h3>
                        </div>
                        <div class="bg-white bg-opacity-25 rounded-circle p-3">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.payments.index') }}" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Payment Type</label>
                    <select name="payment_type" class="form-select">
                        <option value="">All Types</option>
                        <option value="contract" {{ request('payment_type') === 'contract' ? 'selected' : '' }}>Contract</option>
                        <option value="employee_activation" {{ request('payment_type') === 'employee_activation' ? 'selected' : '' }}>Employee Activation</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Method</label>
                    <select name="method" class="form-select">
                        <option value="">All Methods</option>
                        <option value="razorpay" {{ request('method') === 'razorpay' ? 'selected' : '' }}>Razorpay</option>
                        <option value="cash" {{ request('method') === 'cash' ? 'selected' : '' }}>Cash</option>
                        <option value="bank_transfer" {{ request('method') === 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if($payments->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Contract/Patient</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Paid At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($payments as $payment)
                            <tr>
                                <td><strong>#{{ $payment->id }}</strong></td>
                                <td>
                                    @if($payment->payment_type === 'contract')
                                        <span class="badge bg-primary">Contract</span>
                                    @else
                                        <span class="badge bg-info">Employee Activation</span>
                                    @endif
                                </td>
                                <td>
                                    @if($payment->contract)
                                        <div>
                                            <strong>{{ $payment->contract->contract_number }}</strong>
                                            <br>
                                            <small class="text-muted">{{ $payment->contract->patient->name ?? 'N/A' }}</small>
                                        </div>
                                    @elseif($payment->employee)
                                        <div>
                                            <strong>{{ $payment->employee->getField('name', 'N/A') }}</strong>
                                            <br>
                                            <small class="text-muted">Employee Activation</small>
                                        </div>
                                    @else
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </td>
                                <td><strong class="text-success">₹{{ number_format($payment->amount, 0) }}</strong></td>
                                <td>{{ ucfirst(str_replace('_', ' ', $payment->method)) }}</td>
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
                                <td>
                                    @if($payment->paid_at)
                                        {{ $payment->paid_at->format('M d, Y') }}
                                        <br>
                                        <small class="text-muted">{{ $payment->paid_at->format('h:i A') }}</small>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.payments.show', $payment) }}" class="btn btn-sm btn-outline-primary" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="card-footer bg-white">
                    {{ $payments->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="fas fa-credit-card fa-4x text-muted mb-3 opacity-25"></i>
                    <h5 class="text-muted">No payments found</h5>
                    <p class="text-muted">Payments will appear here when customers make payments</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

