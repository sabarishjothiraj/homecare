@extends('layouts.admin')

@section('title', 'Services')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Services</h2>
            <p class="text-muted mb-0">Manage nursing and healthcare services</p>
        </div>
        <a href="{{ route('admin.services.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Create New Service
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

    <!-- Services Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if($services->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Service Name</th>
                                <th>Icon</th>
                                <th>Description</th>
                                <th>Contracts</th>
                                <th>Display Order</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($services as $service)
                            <tr>
                                <td><strong>#{{ $service->id }}</strong></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        @if($service->icon)
                                            <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-2">
                                                <i class="{{ $service->icon }} text-primary"></i>
                                            </div>
                                        @endif
                                        <strong>{{ $service->name }}</strong>
                                    </div>
                                </td>
                                <td>
                                    @if($service->icon)
                                        <code>{{ $service->icon }}</code>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($service->description)
                                        {{ Str::limit($service->description, 50) }}
                                    @else
                                        <span class="text-muted">No description</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-info">{{ $service->contracts_count }} contracts</span>
                                </td>
                                <td>{{ $service->order }}</td>
                                <td>
                                    @if($service->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('admin.services.edit', $service) }}" class="btn btn-outline-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.services.destroy', $service) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this service?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="card-footer bg-white">
                    {{ $services->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="fas fa-briefcase-medical fa-4x text-muted mb-3 opacity-25"></i>
                    <h5 class="text-muted">No services found</h5>
                    <p class="text-muted">Start by creating your first service</p>
                    <a href="{{ route('admin.services.create') }}" class="btn btn-primary mt-3">
                        <i class="fas fa-plus me-2"></i>Create New Service
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

