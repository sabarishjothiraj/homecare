@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
@php
    // Panels are only built for users who can open the module behind them (see DashboardController)
    $statusColors = [
        'active' => 'success',
        'not_assigned' => 'warning',
        'closing_soon' => 'orange',
        'expired' => 'danger',
        'closed' => 'secondary',
    ];
@endphp
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Dashboard</h2>
            <p class="text-muted mb-0">Welcome back, {{ auth()->user()->name }}!</p>
        </div>
        <div class="text-muted">
            <i class="fas fa-calendar-alt me-2"></i>{{ now()->format('l, F j, Y') }}
        </div>
    </div>

    @if (! in_array(true, $show, true))
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body text-center text-muted py-5">
                <i class="fas fa-th-large fa-3x mb-3 d-block opacity-25"></i>
                <p class="mb-0">There are no dashboard panels for your role. Use the menu to open the sections you work in.</p>
            </div>
        </div>
    @endif

    <!-- Statistics Cards Row 1 -->
    @if ($show['patients'] || $show['employees'] || $show['contracts'] || $show['payments'])
    <div class="row g-3 mb-4">
        @if ($show['patients'])
        <!-- Total Patients -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1 small">Total Patients</p>
                            <h3 class="mb-0">{{ $totalPatients }}</h3>
                            <small class="text-success">
                                <i class="fas fa-check-circle me-1"></i>{{ $activePatients }} Active
                            </small>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-3 rounded">
                            <i class="fas fa-user-injured fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if ($show['employees'])
        <!-- Total Employees -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1 small">Total Employees</p>
                            <h3 class="mb-0">{{ $totalEmployees }}</h3>
                            <small class="text-success">
                                <i class="fas fa-check-circle me-1"></i>{{ $activeEmployees }} Active
                            </small>
                        </div>
                        <div class="bg-success bg-opacity-10 p-3 rounded">
                            <i class="fas fa-user-nurse fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if ($show['contracts'])
        <!-- Active Contracts -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1 small">Active Contracts</p>
                            <h3 class="mb-0">{{ $contractStats['active'] ?? 0 }}</h3>
                            <small class="text-warning">
                                <i class="fas fa-user-clock me-1"></i>{{ $contractStats['not_assigned'] ?? 0 }} Not Assigned
                            </small>
                        </div>
                        <div class="bg-info bg-opacity-10 p-3 rounded">
                            <i class="fas fa-file-contract fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if ($show['payments'])
        <!-- Total Revenue -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1 small">Total Revenue</p>
                            <h3 class="mb-0">₹{{ number_format($totalRevenue, 0) }}</h3>
                            <small class="text-info">
                                <i class="fas fa-calendar-week me-1"></i>₹{{ number_format($last7DaysRevenue, 0) }} (7d)
                            </small>
                        </div>
                        <div class="bg-warning bg-opacity-10 p-3 rounded">
                            <i class="fas fa-rupee-sign fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
    @endif

    <!-- Statistics Cards Row 2 -->
    @if ($show['employees'] || $show['contracts'] || $show['payments'])
    <div class="row g-3 mb-4">
        @if ($show['employees'])
        <!-- Pending Employees -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-user-clock fa-2x text-warning mb-2"></i>
                    <h4 class="mb-0">{{ $pendingEmployees }}</h4>
                    <small class="text-muted">Pending Employees</small>
                </div>
            </div>
        </div>

        <!-- Inactive Employees -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-user-slash fa-2x text-danger mb-2"></i>
                    <h4 class="mb-0">{{ $inactiveEmployees }}</h4>
                    <small class="text-muted">Inactive Employees</small>
                </div>
            </div>
        </div>
        @endif

        @if ($show['contracts'])
        <!-- Expiring Contracts -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                    <h4 class="mb-0">{{ $expiringContracts->count() }}</h4>
                    <small class="text-muted">Expiring Soon</small>
                </div>
            </div>
        </div>
        @endif

        @if ($show['payments'])
        <!-- 30 Days Revenue -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-chart-line fa-2x text-success mb-2"></i>
                    <h4 class="mb-0">₹{{ number_format($last30DaysRevenue, 0) }}</h4>
                    <small class="text-muted">Last 30 Days</small>
                </div>
            </div>
        </div>
        @endif
    </div>
    @endif

    @if ($show['payments'])
    <!-- Refund Statistics -->
    <div class="row g-3 mb-4">
        <!-- Total Refunds -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1 small">Total Refunds</p>
                            <h3 class="mb-0 text-danger">₹{{ number_format($totalRefunds, 0) }}</h3>
                            <small class="text-muted">
                                <i class="fas fa-file-contract me-1"></i>{{ $refundCount }} {{ Str::plural('Contract', $refundCount) }} refunded
                            </small>
                        </div>
                        <div class="bg-danger bg-opacity-10 p-3 rounded">
                            <i class="fas fa-undo-alt fa-2x text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Refunds Last 30 Days -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1 small">Refunds (Last 30 Days)</p>
                            <h3 class="mb-0">₹{{ number_format($last30DaysRefunds, 0) }}</h3>
                            <small class="text-muted">
                                <i class="fas fa-calendar-alt me-1"></i>Based on contract closure date
                            </small>
                        </div>
                        <div class="bg-secondary bg-opacity-10 p-3 rounded">
                            <i class="fas fa-hand-holding-usd fa-2x text-secondary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Net Revenue -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1 small">Net Revenue</p>
                            <h3 class="mb-0 text-success">₹{{ number_format($netRevenue, 0) }}</h3>
                            <small class="text-muted">
                                <i class="fas fa-minus-circle me-1"></i>Revenue after refunds
                            </small>
                        </div>
                        <div class="bg-success bg-opacity-10 p-3 rounded">
                            <i class="fas fa-wallet fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if ($show['payments'] || $show['contracts'])
    <div class="row g-3 mb-4">
        @if ($show['payments'])
        <!-- Monthly Revenue Chart -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Monthly Revenue &amp; Refunds (Last 6 Months)</h5>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="80"></canvas>
                </div>
            </div>
        </div>
        @endif

        @if ($show['contracts'])
        <!-- Contract Status Distribution -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Contract Status</h5>
                    <small class="text-muted">{{ $contractStats['total'] ?? 0 }} total</small>
                </div>
                <div class="card-body contract-status">
                    @php $contractTotal = $contractStats['total'] ?? 0; @endphp
                    @foreach (\App\Services\ReportService::CONTRACT_STATUSES as $status => $label)
                        @php
                            $statusCount = $contractStats[$status] ?? 0;
                            $color = $statusColors[$status] ?? 'secondary';
                        @endphp
                        <div class="{{ $loop->last ? '' : 'mb-3' }}">
                            <div class="d-flex justify-content-between mb-1">
                                <span>{{ $label }}</span>
                                <strong class="text-{{ $color }}">{{ $statusCount }}</strong>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-{{ $color }}" style="width: {{ $contractTotal > 0 ? round($statusCount / $contractTotal * 100, 1) : 0 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
    </div>
    @endif

    @if ($show['contracts'])
    <div class="row g-3 mb-4">
        <!-- Recent Contracts -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-file-contract me-2"></i>Recent Contracts</h5>
                    <a href="{{ route('admin.contracts.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    @if($recentContracts->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Patient</th>
                                        <th>Service</th>
                                        <th>Status</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentContracts as $contract)
                                    <tr>
                                        <td>{{ $contract->patient->name ?? 'N/A' }}</td>
                                        <td>{{ $contract->service->name ?? 'N/A' }}</td>
                                        <td>{!! contract_status_badge($contract->status) !!}</td>
                                        <td>₹{{ number_format((float) $contract->amount, 0) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-inbox fa-3x mb-3 opacity-25"></i>
                            <p>No contracts yet</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Expiring Contracts -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2 text-danger"></i>Expiring Soon</h5>
                    <a href="{{ route('admin.contracts.index') }}" class="btn btn-sm btn-outline-danger">View All</a>
                </div>
                <div class="card-body p-0">
                    @if($expiringContracts->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Patient</th>
                                        <th>Service</th>
                                        <th>End Date</th>
                                        <th>Days Left</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($expiringContracts->take(5) as $contract)
                                    @php
                                        // Whole calendar days from today; diffInDays(now()) floors
                                        // against the current time, so tomorrow showed as 0
                                        $daysLeft = (int) today()->diffInDays($contract->end_date->copy()->startOfDay(), false);
                                    @endphp
                                    <tr>
                                        <td>{{ $contract->patient->name ?? 'N/A' }}</td>
                                        <td>{{ $contract->service->name ?? 'N/A' }}</td>
                                        <td>{{ $contract->end_date->format('M d, Y') }}</td>
                                        <td>
                                            <span class="badge bg-danger">
                                                @if ($daysLeft < 0)
                                                    Ended
                                                @elseif ($daysLeft === 0)
                                                    Ends today
                                                @else
                                                    {{ $daysLeft }} {{ Str::plural('day', $daysLeft) }}
                                                @endif
                                            </span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-check-circle fa-3x mb-3 opacity-25"></i>
                            <p>No expiring contracts</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    @if ($show['queries'])
    <div class="row g-3">
        <!-- Recent Contact Queries -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-envelope me-2"></i>Recent Queries</h5>
                    <a href="{{ route('admin.contact-queries.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    @if($recentQueries->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach($recentQueries as $query)
                            <a href="{{ route('admin.contact-queries.show', $query) }}" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">{{ $query->name }}</h6>
                                        <p class="mb-1 small text-muted">{{ Str::limit($query->message, 60) }}</p>
                                        <small class="text-muted">{{ $query->created_at->diffForHumans() }}</small>
                                    </div>
                                    <span class="badge bg-primary">New</span>
                                </div>
                            </a>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-inbox fa-3x mb-3 opacity-25"></i>
                            <p>No new queries</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Recent Join Requests -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Join Requests</h5>
                    <a href="{{ route('admin.join-requests.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    @if($recentJoinRequests->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach($recentJoinRequests as $request)
                            @php
                                // Applicant details live in field_data, keyed by the configured employee fields
                                $applicantName = trim($request->getField('first_name', '').' '.$request->getField('last_name', ''))
                                    ?: ($request->getField('name') ?: 'Applicant #'.$request->id);
                                $applicantContact = collect([$request->getField('email'), $request->getField('phone')])->filter()->join(' • ');
                            @endphp
                            <a href="{{ route('admin.join-requests.show', $request) }}" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">{{ $applicantName }}</h6>
                                        @if ($applicantContact !== '')
                                            <p class="mb-1 small text-muted">{{ $applicantContact }}</p>
                                        @endif
                                        <small class="text-muted">{{ $request->created_at->diffForHumans() }}</small>
                                    </div>
                                    <span class="badge bg-success">New</span>
                                </div>
                            </a>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-inbox fa-3x mb-3 opacity-25"></i>
                            <p>No new join requests</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

@push('styles')
<style>
    .contract-status .bg-orange { background-color: #fd7e14 !important; }
    .contract-status .text-orange { color: #fd7e14 !important; }
</style>
@endpush

@if ($show['payments'])
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    // Monthly Revenue Chart
    const ctx = document.getElementById('revenueChart');
    const monthlyData = @json($monthlyRevenue);

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: monthlyData.map(item => item.month),
            datasets: [{
                label: 'Revenue (₹)',
                data: monthlyData.map(item => item.revenue),
                backgroundColor: 'rgba(52, 152, 219, 0.8)',
                borderColor: 'rgba(52, 152, 219, 1)',
                borderWidth: 1,
                borderRadius: 5
            }, {
                label: 'Refunds (₹)',
                data: monthlyData.map(item => item.refund),
                backgroundColor: 'rgba(231, 76, 60, 0.8)',
                borderColor: 'rgba(231, 76, 60, 1)',
                borderWidth: 1,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label.replace(' (₹)', '') + ': ₹' + context.parsed.y.toLocaleString('en-IN');
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₹' + value.toLocaleString('en-IN');
                        }
                    }
                }
            }
        }
    });
</script>
@endpush
@endif
@endsection
