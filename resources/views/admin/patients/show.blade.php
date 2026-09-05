@extends('layouts.admin')

@section('title', 'Patient Details')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">{{ $patient->name }}</h2>
            <p class="text-muted mb-0">Patient Details</p>
        </div>
        <div>
            <a href="{{ route('admin.patients.edit', $patient) }}" class="btn btn-warning">
                <i class="fas fa-edit me-2"></i>Edit
            </a>
            <a href="{{ route('admin.patients.index') }}" class="btn btn-outline-secondary">
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
        <!-- Patient Information -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-user-injured me-2"></i>Patient Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Full Name</label>
                            <p class="mb-0"><strong>{{ $patient->name }}</strong></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Phone Number</label>
                            <p class="mb-0"><i class="fas fa-phone me-2 text-primary"></i>{{ $patient->phone }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Email Address</label>
                            <p class="mb-0">
                                @if($patient->email)
                                    <i class="fas fa-envelope me-2 text-primary"></i>{{ $patient->email }}
                                @else
                                    <span class="text-muted">Not provided</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Area</label>
                            <p class="mb-0">
                                @if($patient->area)
                                    <i class="fas fa-map-marker-alt me-2 text-primary"></i>{{ $patient->area }}
                                @else
                                    <span class="text-muted">Not specified</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">City</label>
                            <p class="mb-0">
                                @if($patient->city)
                                    {{ $patient->city }}
                                @else
                                    <span class="text-muted">Not specified</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-md-12">
                            <label class="text-muted small">Full Address</label>
                            <p class="mb-0">
                                @if($patient->full_address)
                                    {{ $patient->full_address }}
                                @else
                                    <span class="text-muted">Not provided</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Emergency Contact -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-phone-alt me-2"></i>Emergency Contact</h5>
                </div>
                <div class="card-body">
                    @if($patient->emergency_contact_name || $patient->emergency_contact_phone)
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="text-muted small">Contact Name</label>
                                <p class="mb-0">{{ $patient->emergency_contact_name ?? 'Not provided' }}</p>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted small">Contact Phone</label>
                                <p class="mb-0">{{ $patient->emergency_contact_phone ?? 'Not provided' }}</p>
                            </div>
                        </div>
                    @else
                        <p class="text-muted mb-0">No emergency contact information provided</p>
                    @endif
                </div>
            </div>

            <!-- Medical Notes -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-notes-medical me-2"></i>Medical Notes</h5>
                </div>
                <div class="card-body">
                    @if($patient->medical_notes)
                        <p class="mb-0">{{ $patient->medical_notes }}</p>
                    @else
                        <p class="text-muted mb-0">No medical notes recorded</p>
                    @endif
                </div>
            </div>

            <!-- Contracts -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-file-contract me-2"></i>Contracts</h5>
                    <a href="{{ route('admin.contracts.create', ['patient_id' => $patient->id]) }}" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i>New Contract
                    </a>
                </div>
                <div class="card-body p-0">
                    @if($patient->contracts->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Service</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($patient->contracts as $contract)
                                    <tr>
                                        <td>{{ $contract->service->name ?? 'N/A' }}</td>
                                        <td>{{ $contract->start_date->format('M d, Y') }}</td>
                                        <td>{{ $contract->end_date->format('M d, Y') }}</td>
                                        <td>₹{{ number_format($contract->total_amount, 0) }}</td>
                                        <td>
                                            @if($contract->status === 'active')
                                                <span class="badge bg-success">Active</span>
                                            @elseif($contract->status === 'pending')
                                                <span class="badge bg-warning">Pending</span>
                                            @elseif($contract->status === 'completed')
                                                <span class="badge bg-info">Completed</span>
                                            @else
                                                <span class="badge bg-secondary">{{ ucfirst($contract->status) }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.contracts.show', $contract) }}" class="btn btn-sm btn-outline-primary">
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
                            <i class="fas fa-file-contract fa-3x text-muted mb-3 opacity-25"></i>
                            <p class="text-muted">No contracts yet</p>
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
                        <label class="text-muted small">Patient Status</label>
                        <p class="mb-0">
                            @if($patient->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

