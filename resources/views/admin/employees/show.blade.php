@extends('layouts.admin')

@section('title', 'Employee Details')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">{{ $employee->getField('name', 'Employee #' . $employee->id) }}</h2>
            <p class="text-muted mb-0">Employee Details</p>
        </div>
        <div>
            <a href="{{ route('admin.employees.edit', $employee) }}" class="btn btn-warning">
                <i class="fas fa-edit me-2"></i>Edit
            </a>
            @if($employee->status === 'pending')
                <form action="{{ route('admin.employees.activate', $employee) }}" method="POST" class="d-inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>Activate
                    </button>
                </form>
            @elseif($employee->status === 'active')
                <form action="{{ route('admin.employees.deactivate', $employee) }}" method="POST" class="d-inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-ban me-2"></i>Deactivate
                    </button>
                </form>
            @endif
            <a href="{{ route('admin.employees.index') }}" class="btn btn-outline-secondary">
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

    <div class="row g-3">
        <!-- Employee Information -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-user-nurse me-2"></i>Employee Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach($fields->where('is_document', false) as $field)
                            <div class="col-md-6">
                                <label class="text-muted small">{{ $field->label }}</label>
                                <p class="mb-0">
                                    @if($employee->getField($field->name))
                                        <strong>{{ $employee->getField($field->name) }}</strong>
                                    @else
                                        <span class="text-muted">Not provided</span>
                                    @endif
                                </p>
                            </div>
                        @endforeach

                        <!-- Caste Information -->
                        <div class="col-md-6">
                            <label class="text-muted small">Caste</label>
                            <p class="mb-0">
                                @if($employee->caste)
                                    <strong>{{ $employee->caste->name }}</strong>
                                @else
                                    <span class="text-muted">Not specified</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Subcaste</label>
                            <p class="mb-0">
                                @if($employee->subcaste)
                                    <strong>{{ $employee->subcaste }}</strong>
                                @else
                                    <span class="text-muted">Not specified</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Documents -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Documents</h5>
                </div>
                <div class="card-body">
                    @if($employee->documents->count() > 0)
                        <div class="row g-3">
                            @foreach($employee->documents as $document)
                                <div class="col-md-6">
                                    <div class="border rounded p-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">{{ $document->field->label ?? 'Document' }}</h6>
                                                <p class="small text-muted mb-2">{{ $document->file_name }}</p>
                                                <small class="text-muted">
                                                    {{ number_format($document->file_size / 1024, 2) }} KB
                                                </small>
                                            </div>
                                            <a href="{{ Storage::url($document->file_path) }}" target="_blank" class="btn btn-sm btn-primary">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted mb-0">No documents uploaded</p>
                    @endif
                </div>
            </div>

            <!-- Assignments -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Assignments</h5>
                </div>
                <div class="card-body p-0">
                    @if($employee->assignments->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Contract</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($employee->assignments as $assignment)
                                    <tr>
                                        <td>{{ $assignment->contract->patient->name ?? 'N/A' }}</td>
                                        <td>{{ $assignment->start_date->format('M d, Y') }}</td>
                                        <td>{{ $assignment->end_date ? $assignment->end_date->format('M d, Y') : 'Ongoing' }}</td>
                                        <td>
                                            @if($assignment->is_active)
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-secondary">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.contracts.show', $assignment->contract_id) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-tasks fa-3x text-muted mb-3 opacity-25"></i>
                            <p class="text-muted">No assignments yet</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Payouts -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Payouts</h5>
                </div>
                <div class="card-body p-0">
                    @if($employee->payouts->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($employee->payouts->take(5) as $payout)
                                    <tr>
                                        <td>{{ $payout->created_at->format('M d, Y') }}</td>
                                        <td>₹{{ number_format($payout->amount, 0) }}</td>
                                        <td>
                                            <span class="badge bg-success">Paid</span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-money-bill-wave fa-3x text-muted mb-3 opacity-25"></i>
                            <p class="text-muted">No payouts yet</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-md-4">
            <!-- Status Card -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Status</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small">Employee Status</label>
                        <p class="mb-0">
                            @if($employee->status === 'active')
                                <span class="badge bg-success">Active</span>
                            @elseif($employee->status === 'pending')
                                <span class="badge bg-warning">Pending</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </p>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Joined</label>
                        <p class="mb-0">{{ $employee->created_at->format('M d, Y') }}</p>
                    </div>
                    @if($employee->activated_at)
                        <div>
                            <label class="text-muted small">Activated</label>
                            <p class="mb-0">{{ $employee->activated_at->format('M d, Y') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

