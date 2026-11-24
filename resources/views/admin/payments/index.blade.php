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
    @php
        $summaryCards = [
            ['label' => 'Total Completed', 'value' => $totalAmount, 'icon' => 'fa-check-circle', 'tone' => 'success'],
            ['label' => 'Total Pending', 'value' => $pendingAmount, 'icon' => 'fa-clock', 'tone' => 'warning'],
            ['label' => 'Total Refunded', 'value' => $refundedAmount, 'icon' => 'fa-undo-alt', 'tone' => 'danger'],
            ['label' => 'Net Received', 'value' => $netAmount, 'icon' => 'fa-wallet', 'tone' => 'primary', 'hint' => 'Completed minus refunds'],
        ];
    @endphp
    <div class="row g-3 mb-4">
        @foreach($summaryCards as $card)
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">{{ $card['label'] }}</h6>
                                <h3 class="mb-0 text-{{ $card['tone'] }}">₹{{ number_format($card['value'], 0) }}</h3>
                                @isset($card['hint'])
                                    <small class="text-muted">{{ $card['hint'] }}</small>
                                @endisset
                            </div>
                            <div class="bg-{{ $card['tone'] }} bg-opacity-10 text-{{ $card['tone'] }} rounded-circle p-3">
                                <i class="fas {{ $card['icon'] }} fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.payments.index') }}" class="row g-3">
                <input type="hidden" name="tab" value="{{ $tab }}">
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
                        @foreach(\App\Services\ReportService::PAYMENT_METHODS as $methodValue => $methodLabel)
                            <option value="{{ $methodValue }}" {{ request('method') === $methodValue ? 'selected' : '' }}>{{ $methodLabel }}</option>
                        @endforeach
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

    <!-- Payments & Refunds -->
    @php $tabParams = request()->except(['page', 'refunds_page', 'tab']); @endphp
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-end flex-wrap gap-2">
            <ul class="nav nav-tabs card-header-tabs">
                <li class="nav-item">
                    <a class="nav-link {{ $tab === 'payments' ? 'active fw-semibold' : 'text-muted' }}" href="{{ route('admin.payments.index', $tabParams) }}">
                        <i class="fas fa-check-circle me-1 text-success"></i>Payments
                        <span class="badge rounded-pill bg-success bg-opacity-10 text-success ms-1">{{ $payments->total() }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $tab === 'refunds' ? 'active fw-semibold' : 'text-muted' }}" href="{{ route('admin.payments.index', array_merge($tabParams, ['tab' => 'refunds'])) }}">
                        <i class="fas fa-undo-alt me-1 text-danger"></i>Refunds
                        <span class="badge rounded-pill bg-danger bg-opacity-10 text-danger ms-1">{{ $refunds->total() }}</span>
                    </a>
                </li>
            </ul>
            @if($tab === 'refunds')
                <small class="text-muted">Recorded when a contract is closed{{ request('start_date') || request('end_date') ? ' · filtered by closure date' : '' }}</small>
            @endif
        </div>

    @if($tab === 'payments')
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
                                            @if($payment->contract->refund_amount > 0)
                                                <br><span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle" title="{{ $payment->contract->refund_details }}">
                                                    <i class="fas fa-undo-alt me-1"></i>Refunded ₹{{ number_format($payment->contract->refund_amount, 0) }}
                                                </span>
                                            @endif
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
                                    @if($payment->needsRefund())
                                        <br><span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle mt-1">Refund required</span>
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
    @else
        <div class="card-body p-0">
            @if($refunds->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Contract/Patient</th>
                                <th>Refund Amount</th>
                                <th>Refund Details</th>
                                <th>Closure Reason</th>
                                <th>Refunded On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($refunds as $refundContract)
                            <tr>
                                <td>
                                    <strong>{{ $refundContract->contract_number }}</strong>
                                    <br>
                                    <small class="text-muted">{{ $refundContract->patient->name ?? 'N/A' }}</small>
                                </td>
                                <td><strong class="text-danger">₹{{ number_format($refundContract->refund_amount, 0) }}</strong></td>
                                <td style="min-width: 200px;">{{ $refundContract->refund_details ?: '—' }}</td>
                                <td style="min-width: 200px;"><small class="text-muted">{{ $refundContract->closure_reason ?: '—' }}</small></td>
                                <td>
                                    @if($refundContract->closed_at)
                                        {{ $refundContract->closed_at->format('M d, Y') }}
                                        <br>
                                        <small class="text-muted">{{ $refundContract->closed_at->format('h:i A') }}</small>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.contracts.show', $refundContract) }}" class="btn btn-sm btn-outline-primary" title="View Contract">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($refunds->hasPages())
                    <div class="card-footer bg-white">
                        {{ $refunds->links() }}
                    </div>
                @endif
            @else
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-undo-alt fa-2x mb-2 opacity-25"></i>
                    <p class="mb-0">No refunds{{ request('start_date') || request('end_date') ? ' in this period' : ' recorded' }}</p>
                </div>
            @endif
        </div>
    @endif
    </div>
</div>
@endsection

