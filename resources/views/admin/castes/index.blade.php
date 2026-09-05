@extends('layouts.admin')

@section('title', 'Castes')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Castes</h2>
            <p class="text-muted mb-0">Manage caste categories</p>
        </div>
        <a href="{{ route('admin.castes.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Create New Caste
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

    <!-- Castes Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if($castes->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Display Order</th>
                                <th>Status</th>
                                <th>Employees</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($castes as $caste)
                            <tr>
                                <td><strong>#{{ $caste->id }}</strong></td>
                                <td><strong>{{ $caste->name }}</strong></td>
                                <td>{{ $caste->display_order }}</td>
                                <td>
                                    @if($caste->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-info">{{ $caste->employees_count ?? 0 }}</span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('admin.castes.edit', $caste) }}" class="btn btn-outline-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.castes.destroy', $caste) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this caste?');">
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
                    {{ $castes->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="fas fa-users fa-4x text-muted mb-3 opacity-25"></i>
                    <h5 class="text-muted">No castes found</h5>
                    <p class="text-muted">Start by creating your first caste</p>
                    <a href="{{ route('admin.castes.create') }}" class="btn btn-primary mt-3">
                        <i class="fas fa-plus me-2"></i>Create New Caste
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

