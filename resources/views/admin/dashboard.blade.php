@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
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

    <!-- Statistics Cards Row 1 -->
    <div class="row g-3 mb-4">
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

        <!-- Active Contracts -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1 small">Active Contracts</p>
                            <h3 class="mb-0">{{ $contractStats['active'] ?? 0 }}</h3>
                            <small class="text-warning">
                                <i class="fas fa-clock me-1"></i>{{ $contractStats['pending'] ?? 0 }} Pending
                            </small>
                        </div>
                        <div class="bg-info bg-opacity-10 p-3 rounded">
                            <i class="fas fa-file-contract fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
    </div>

    <!-- Statistics Cards Row 2 -->
    <div class="row g-3 mb-4">
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
    </div>

    <div class="row g-3 mb-4">
        <!-- Monthly Revenue Chart -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Monthly Revenue (Last 6 Months)</h5>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="80"></canvas>
                </div>
            </div>
        </div>

        <!-- Contract Status Distribution -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Contract Status</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Active</span>
                            <strong class="text-success">{{ $contractStats['active'] ?? 0 }}</strong>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success" style="width: {{ ($contractStats['total'] ?? 0) > 0 ? (($contractStats['active'] ?? 0) / ($contractStats['total'] ?? 0) * 100) : 0 }}%"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Pending</span>
                            <strong class="text-warning">{{ $contractStats['pending'] ?? 0 }}</strong>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-warning" style="width: {{ ($contractStats['total'] ?? 0) > 0 ? (($contractStats['pending'] ?? 0) / ($contractStats['total'] ?? 0) * 100) : 0 }}%"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Completed</span>
                            <strong class="text-info">{{ $contractStats['completed'] ?? 0 }}</strong>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-info" style="width: {{ ($contractStats['total'] ?? 0) > 0 ? (($contractStats['completed'] ?? 0) / ($contractStats['total'] ?? 0) * 100) : 0 }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Cancelled</span>
                            <strong class="text-danger">{{ $contractStats['cancelled'] ?? 0 }}</strong>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-danger" style="width: {{ ($contractStats['total'] ?? 0) > 0 ? (($contractStats['cancelled'] ?? 0) / ($contractStats['total'] ?? 0) * 100) : 0 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                                        <td>
                                            @if($contract->status === 'active')
                                                <span class="badge bg-success">Active</span>
                                            @elseif($contract->status === 'pending')
                                                <span class="badge bg-warning">Pending</span>
                                            @elseif($contract->status === 'completed')
                                                <span class="badge bg-info">Completed</span>
                                            @else
                                                <span class="badge bg-danger">{{ ucfirst($contract->status) }}</span>
                                            @endif
                                        </td>
                                        <td>₹{{ number_format($contract->total_amount, 0) }}</td>
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
                                    <tr>
                                        <td>{{ $contract->patient->name ?? 'N/A' }}</td>
                                        <td>{{ $contract->service->name ?? 'N/A' }}</td>
                                        <td>{{ $contract->end_date->format('M d, Y') }}</td>
                                        <td>
                                            <span class="badge bg-danger">
                                                {{ $contract->end_date->diffInDays(now()) }} days
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
                            <a href="{{ route('admin.join-requests.show', $request) }}" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">{{ $request->name }}</h6>
                                        <p class="mb-1 small text-muted">{{ $request->email }} • {{ $request->phone }}</p>
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
</div>

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
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return '₹' + context.parsed.y.toLocaleString('en-IN');
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
@endsection

