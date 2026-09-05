@extends('layouts.admin')

@section('title', 'Employee Payouts')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Employee Payouts</h2>
            <p class="text-muted mb-0">Manage employee payments and payouts</p>
        </div>
        <a href="{{ route('admin.payouts.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Create New Payout
        </a>
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
                            <h6 class="text-white-50 mb-1">Total Paid</h6>
                            <h3 class="mb-0">₹{{ number_format($totalPaid, 0) }}</h3>
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
                            <h3 class="mb-0">₹{{ number_format($totalPending, 0) }}</h3>
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
            <form method="GET" action="{{ route('admin.payouts.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Employee</label>
                    <select name="employee_id" class="form-select">
                        <option value="">All Employees</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" {{ request('employee_id') == $employee->id ? 'selected' : '' }}>
                                {{ $employee->getField('name', 'Employee #' . $employee->id) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Paid</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" class="form-select">
                        <option value="">All Methods</option>
                        <option value="cash" {{ request('payment_method') === 'cash' ? 'selected' : '' }}>Cash</option>
                        <option value="bank_transfer" {{ request('payment_method') === 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                        <option value="cheque" {{ request('payment_method') === 'cheque' ? 'selected' : '' }}>Cheque</option>
                        <option value="online" {{ request('payment_method') === 'online' ? 'selected' : '' }}>Online</option>
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
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Payouts Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if($payouts->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Employee</th>
                                <th>Amount</th>
                                <th>Payout Date</th>
                                <th>Payment Method</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($payouts as $payout)
                            <tr>
                                <td><strong>#{{ $payout->id }}</strong></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-success bg-opacity-10 rounded-circle p-2 me-2">
                                            <i class="fas fa-user-nurse text-success"></i>
                                        </div>
                                        <div>
                                            <strong>{{ $payout->employee->getField('name', 'N/A') }}</strong>
                                            <br>
                                            <small class="text-muted">{{ $payout->employee->getField('phone', '') }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td><strong class="text-success">₹{{ number_format($payout->amount, 0) }}</strong></td>
                                <td>{{ $payout->period_end ? $payout->period_end->format('M d, Y') : 'N/A' }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $payout->frequency ?? 'N/A')) }}</td>
                                <td>
                                    @if($payout->status === 'paid')
                                        <span class="badge bg-success">Paid</span>
                                        @if($payout->paid_at)
                                            <br><small class="text-muted">{{ $payout->paid_at->format('M d, Y') }}</small>
                                        @endif
                                    @else
                                        <span class="badge bg-warning">Pending</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('admin.payouts.show', $payout) }}" class="btn btn-outline-primary" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @if($payout->status !== 'paid')
                                            <a href="{{ route('admin.payouts.edit', $payout) }}" class="btn btn-outline-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="card-footer bg-white">
                    {{ $payouts->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="fas fa-money-bill-wave fa-4x text-muted mb-3 opacity-25"></i>
                    <h5 class="text-muted">No payouts found</h5>
                    <p class="text-muted">Start by creating your first payout</p>
                    <a href="{{ route('admin.payouts.create') }}" class="btn btn-primary mt-3">
                        <i class="fas fa-plus me-2"></i>Create New Payout
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

