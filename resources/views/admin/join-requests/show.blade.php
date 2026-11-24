@extends('layouts.admin')

@section('title', 'Join Request Details')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Join Request #{{ $joinRequest->id }}</h2>
            <p class="text-muted mb-0">Submitted {{ $joinRequest->created_at->format('M d, Y \a\t h:i A') }}</p>
        </div>
        <a href="{{ route('admin.join-requests.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Requests
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

    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>Applicant Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($fields as $field)
                            @if(isset($joinRequest->field_data[$field->name]))
                                <div class="col-md-6 mb-3">
                                    <p class="mb-1"><strong>{{ $field->label }}:</strong></p>
                                    @if($field->is_document && $joinRequest->field_data[$field->name])
                                        <a href="{{ asset('storage/' . $joinRequest->field_data[$field->name]) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-file-download me-1"></i>Download {{ $field->label }}
                                        </a>
                                    @else
                                        <p>{{ $joinRequest->field_data[$field->name] ?? '-' }}</p>
                                    @endif
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>

            @if($joinRequest->admin_notes)
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Admin Notes</h5>
                    </div>
                    <div class="card-body">
                        <div class="bg-light p-3 rounded">
                            {{ $joinRequest->admin_notes }}
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-cog me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <p class="mb-2"><strong>Current Status:</strong></p>
                        <span class="badge bg-{{ $joinRequest->statusTone() }}">{{ $joinRequest->statusLabel() }}</span>
                    </div>

                    @if(in_array($joinRequest->status, ['new', 'reviewing'], true))
                        <button type="button" class="btn btn-success w-100 mb-2" data-bs-toggle="modal" data-bs-target="#approveModal">
                            <i class="fas fa-check me-2"></i>Approve Request
                        </button>
                        <button type="button" class="btn btn-danger w-100 mb-2" data-bs-toggle="modal" data-bs-target="#rejectModal">
                            <i class="fas fa-times me-2"></i>Reject Request
                        </button>
                    @endif

                    @if($joinRequest->status === 'approved' && ! $joinRequest->isConverted())
                        <form action="{{ route('admin.join-requests.convert', $joinRequest) }}" method="POST" onsubmit="return confirm('Are you sure you want to convert this request to an employee?');">
                            @csrf
                            <button type="submit" class="btn btn-primary w-100 mb-2">
                                <i class="fas fa-user-plus me-2"></i>Convert to Employee
                            </button>
                        </form>
                    @endif

                    @if($joinRequest->employee_id)
                        <a href="{{ route('admin.employees.show', $joinRequest->employee_id) }}" class="btn btn-outline-primary w-100">
                            <i class="fas fa-user me-2"></i>View Employee Profile
                        </a>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Information</h6>
                </div>
                <div class="card-body">
                    <p class="small mb-2"><strong>Request ID:</strong> #{{ $joinRequest->id }}</p>
                    <p class="small mb-2"><strong>Submitted:</strong> {{ $joinRequest->created_at->format('M d, Y h:i A') }}</p>
                    <p class="small mb-0"><strong>Last Updated:</strong> {{ $joinRequest->updated_at->diffForHumans() }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.join-requests.approve', $joinRequest) }}" method="POST">
                @csrf
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Approve Join Request</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to approve this join request?</p>
                    <div class="mb-3">
                        <label for="admin_notes" class="form-label">Admin Notes (Optional)</label>
                        <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3" placeholder="Add any notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.join-requests.reject', $joinRequest) }}" method="POST">
                @csrf
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Reject Join Request</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to reject this join request?</p>
                    <div class="mb-3">
                        <label for="reject_notes" class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reject_notes" name="admin_notes" rows="3" required placeholder="Provide a reason for rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Request</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

