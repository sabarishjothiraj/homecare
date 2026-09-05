@extends('layouts.admin')

@section('title', 'Contact Queries')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Contact Queries</h2>
            <p class="text-muted mb-0">Manage contact form submissions</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-primary bg-opacity-10 rounded p-3">
                                <i class="fas fa-envelope fa-2x text-primary"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">New Queries</h6>
                            <h3 class="mb-0">{{ $newCount }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-success bg-opacity-10 rounded p-3">
                                <i class="fas fa-check-circle fa-2x text-success"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Responded</h6>
                            <h3 class="mb-0">{{ $respondedCount }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.contact-queries.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="new" {{ request('status') === 'new' ? 'selected' : '' }}>New</option>
                        <option value="read" {{ request('status') === 'read' ? 'selected' : '' }}>Read</option>
                        <option value="responded" {{ request('status') === 'responded' ? 'selected' : '' }}>Responded</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Queries Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if($queries->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($queries as $query)
                            <tr class="{{ $query->status === 'new' ? 'table-primary' : '' }}">
                                <td>#{{ $query->id }}</td>
                                <td>
                                    <strong>{{ $query->name }}</strong>
                                    @if($query->status === 'new')
                                        <span class="badge bg-danger ms-1">New</span>
                                    @endif
                                </td>
                                <td>{{ $query->email }}</td>
                                <td>{{ $query->phone ?? '-' }}</td>
                                <td>{{ Str::limit($query->subject ?? 'No Subject', 40) }}</td>
                                <td>
                                    @if($query->status === 'new')
                                        <span class="badge bg-primary">New</span>
                                    @elseif($query->status === 'read')
                                        <span class="badge bg-info">Read</span>
                                    @else
                                        <span class="badge bg-success">Responded</span>
                                    @endif
                                </td>
                                <td>{{ $query->created_at->format('M d, Y') }}</td>
                                <td>
                                    <a href="{{ route('admin.contact-queries.show', $query) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="card-footer bg-white">
                    {{ $queries->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="fas fa-envelope fa-4x text-muted mb-3 opacity-25"></i>
                    <h5 class="text-muted">No contact queries found</h5>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

