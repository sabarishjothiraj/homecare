@extends('layouts.admin')

@section('title', 'Contracts')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Contracts</h2>
            <p class="text-muted mb-0">Manage patient service contracts</p>
        </div>
        <a href="{{ route('admin.contracts.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Create New Contract
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

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.contracts.index') }}" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search by contract number or patient name..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Patient</label>
                    <select name="patient_id" class="form-select">
                        <option value="">All Patients</option>
                        @foreach($patients as $patient)
                            <option value="{{ $patient->id }}" {{ request('patient_id') == $patient->id ? 'selected' : '' }}>
                                {{ $patient->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="not_assigned" {{ request('status') === 'not_assigned' ? 'selected' : '' }}>Not Assigned</option>
                        <option value="closing_soon" {{ request('status') === 'closing_soon' ? 'selected' : '' }}>Closing Soon</option>
                        <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>Expired</option>
                        <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Contracts Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if($contracts->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Contract #</th>
                                <th>Patient</th>
                                <th>Service</th>
                                <th>Duration</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Created By</th>
                                <th>Employees</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($contracts as $contract)
                            <tr>
                                <td>
                                    <strong class="text-primary">{{ $contract->contract_number }}</strong>
                                    <br>
                                    <small class="text-muted">{{ $contract->start_date->format('M d, Y') }}</small>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-2">
                                            <i class="fas fa-user text-primary"></i>
                                        </div>
                                        <div>
                                            <strong>{{ $contract->patient->name ?? 'N/A' }}</strong>
                                            <br>
                                            <small class="text-muted">{{ $contract->patient->community ?? '' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong>{{ $contract->service->name ?? 'N/A' }}</strong>
                                </td>
                                <td>
                                    @if($contract->duration_type === 'custom')
                                        <span class="badge bg-info">Custom</span>
                                    @else
                                        <span class="badge bg-secondary">
                                            {{ $contract->duration_value }} {{ ucfirst($contract->duration_type) }}
                                        </span>
                                        @if($contract->end_date)
                                            <br><small class="text-muted">Until {{ $contract->end_date->format('M d, Y') }}</small>
                                        @endif
                                    @endif
                                </td>
                                <td>
                                    <strong>₹{{ number_format($contract->amount, 0) }}</strong>
                                    <br>
                                    <small class="text-muted">{{ ucfirst(str_replace('_', ' ', $contract->payment_method)) }}</small>
                                </td>
                                <td>
                                    @if($contract->status === 'active')
                                        <span class="badge bg-success">Active</span>
                                    @elseif($contract->status === 'not_assigned')
                                        <span class="badge bg-warning">Not Assigned</span>
                                    @elseif($contract->status === 'closing_soon')
                                        <span class="badge bg-warning">Closing Soon</span>
                                    @elseif($contract->status === 'expired')
                                        <span class="badge bg-danger">Expired</span>
                                    @elseif($contract->status === 'closed')
                                        <span class="badge bg-secondary">Closed</span>
                                    @else
                                        <span class="badge bg-secondary">{{ ucfirst($contract->status) }}</span>
                                    @endif
                                </td>
                                <td>
                                    <small>{{ $contract->creator->name ?? 'System' }}</small>
                                </td>
                                <td>
                                    @if($contract->activeAssignments->count() > 0)
                                        @foreach($contract->activeAssignments->take(2) as $activeAssignment)
                                            <span class="badge bg-success mb-1 d-inline-block">
                                                <i class="fas fa-user-nurse me-1"></i>
                                                {{ $activeAssignment->employee->getField('name', 'Assigned') }}
                                            </span>
                                        @endforeach
                                        @if($contract->activeAssignments->count() > 2)
                                            <span class="badge bg-secondary">+{{ $contract->activeAssignments->count() - 2 }} more</span>
                                        @endif
                                    @else
                                        <span class="badge bg-secondary">Not Assigned</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('admin.contracts.show', $contract) }}" class="btn btn-outline-primary" title="View Contract Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @if($contract->pdf_path)
                                            <a href="{{ route('admin.contracts.download', $contract) }}" class="btn btn-outline-success" title="Download PDF">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        @endif
                                        @if($contract->status !== 'closed')
                                            <a href="{{ route('admin.contracts.edit', $contract) }}" class="btn btn-outline-warning" title="Edit">
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
                    {{ $contracts->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="fas fa-file-contract fa-4x text-muted mb-3 opacity-25"></i>
                    <h5 class="text-muted">No contracts found</h5>
                    <p class="text-muted">Start by creating your first contract</p>
                    <a href="{{ route('admin.contracts.create') }}" class="btn btn-primary mt-3">
                        <i class="fas fa-plus me-2"></i>Create New Contract
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

