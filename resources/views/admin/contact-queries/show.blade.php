@extends('layouts.admin')

@section('title', 'Contact Query Details')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Contact Query #{{ $contactQuery->id }}</h2>
            <p class="text-muted mb-0">Submitted {{ $contactQuery->created_at->format('M d, Y \a\t h:i A') }}</p>
        </div>
        <a href="{{ route('admin.contact-queries.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Queries
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-envelope me-2"></i>Query Details</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Name:</strong></p>
                            <p>{{ $contactQuery->name }}</p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Email:</strong></p>
                            <p><a href="mailto:{{ $contactQuery->email }}">{{ $contactQuery->email }}</a></p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Phone:</strong></p>
                            <p>
                                @if($contactQuery->phone)
                                    <a href="tel:{{ $contactQuery->phone }}">{{ $contactQuery->phone }}</a>
                                @else
                                    <span class="text-muted">Not provided</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Status:</strong></p>
                            <p>
                                @if($contactQuery->status === 'new')
                                    <span class="badge bg-primary">New</span>
                                @elseif($contactQuery->status === 'read')
                                    <span class="badge bg-info">Read</span>
                                @else
                                    <span class="badge bg-success">Responded</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    @if($contactQuery->subject)
                        <div class="mb-3">
                            <p class="mb-1"><strong>Subject:</strong></p>
                            <p>{{ $contactQuery->subject }}</p>
                        </div>
                    @endif

                    <div class="mb-3">
                        <p class="mb-1"><strong>Message:</strong></p>
                        <div class="bg-light p-3 rounded">
                            {{ $contactQuery->message }}
                        </div>
                    </div>

                    @if($contactQuery->response_notes)
                        <div class="mb-3">
                            <p class="mb-1"><strong>Response Notes:</strong></p>
                            <div class="bg-success bg-opacity-10 p-3 rounded">
                                {{ $contactQuery->response_notes }}
                            </div>
                        </div>
                    @endif

                    @if($contactQuery->responded_at)
                        <p class="small text-muted mb-0">
                            <i class="fas fa-check-circle text-success me-1"></i>
                            Responded on {{ $contactQuery->responded_at->format('M d, Y \a\t h:i A') }}
                        </p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-cog me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    @if($contactQuery->status !== 'responded')
                        <button type="button" class="btn btn-success w-100 mb-2" data-bs-toggle="modal" data-bs-target="#respondModal">
                            <i class="fas fa-reply me-2"></i>Mark as Responded
                        </button>
                    @endif

                    <form action="{{ route('admin.contact-queries.update', $contactQuery) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="mb-2">
                            <label class="form-label small">Change Status:</label>
                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="new" {{ $contactQuery->status === 'new' ? 'selected' : '' }}>New</option>
                                <option value="read" {{ $contactQuery->status === 'read' ? 'selected' : '' }}>Read</option>
                                <option value="responded" {{ $contactQuery->status === 'responded' ? 'selected' : '' }}>Responded</option>
                            </select>
                        </div>
                    </form>

                    <hr>

                    <a href="mailto:{{ $contactQuery->email }}" class="btn btn-outline-primary w-100 mb-2">
                        <i class="fas fa-envelope me-2"></i>Send Email
                    </a>

                    @if($contactQuery->phone)
                        <a href="tel:{{ $contactQuery->phone }}" class="btn btn-outline-success w-100">
                            <i class="fas fa-phone me-2"></i>Call
                        </a>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Information</h6>
                </div>
                <div class="card-body">
                    <p class="small mb-2"><strong>Query ID:</strong> #{{ $contactQuery->id }}</p>
                    <p class="small mb-2"><strong>Submitted:</strong> {{ $contactQuery->created_at->format('M d, Y h:i A') }}</p>
                    <p class="small mb-0"><strong>Last Updated:</strong> {{ $contactQuery->updated_at->diffForHumans() }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Respond Modal -->
<div class="modal fade" id="respondModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.contact-queries.respond', $contactQuery) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Mark as Responded</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="response_notes" class="form-label">Response Notes (Optional)</label>
                        <textarea class="form-control" id="response_notes" name="response_notes" rows="4" placeholder="Add any notes about how you responded..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Mark as Responded</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

