@extends('layouts.admin')

@section('title', 'Patients')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Patients</h2>
            <p class="text-muted mb-0">Manage patient records</p>
        </div>
        <a href="{{ route('admin.patients.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Patient
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
            <form method="GET" action="{{ route('admin.patients.index') }}" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, email, or phone..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
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

    <!-- Patients Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if($patients->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Location</th>
                                <th>Created By</th>
                                <th>Contracts</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($patients as $patient)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-2">
                                            <i class="fas fa-user-injured text-primary"></i>
                                        </div>
                                        <div>
                                            <strong>{{ $patient->name }}</strong>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small">
                                        @if($patient->email)
                                            <div><i class="fas fa-envelope me-1 text-muted"></i>{{ $patient->email }}</div>
                                        @endif
                                        <div><i class="fas fa-phone me-1 text-muted"></i>{{ $patient->phone }}</div>
                                    </div>
                                </td>
                                <td>{{ Str::limit($patient->short_address, 30) ?: 'N/A' }}</td>
                                <td><small>{{ $patient->creator->name ?? 'System' }}</small></td>
                                <td>
                                    <span class="badge bg-info">{{ $patient->contracts_count }} Total</span>
                                    @if($patient->active_contracts_count > 0)
                                        <span class="badge bg-success">{{ $patient->active_contracts_count }} Active</span>
                                    @endif
                                </td>
                                <td>
                                    @if($patient->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('admin.patients.show', $patient) }}" class="btn btn-outline-primary" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.patients.edit', $patient) }}" class="btn btn-outline-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" title="Delete" 
                                                onclick="confirmDelete({{ $patient->id }})">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    <form id="delete-form-{{ $patient->id }}" 
                                          action="{{ route('admin.patients.destroy', $patient) }}" 
                                          method="POST" class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="card-footer bg-white">
                    {{ $patients->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="fas fa-user-injured fa-4x text-muted mb-3 opacity-25"></i>
                    <h5 class="text-muted">No patients found</h5>
                    <p class="text-muted">Start by adding your first patient</p>
                    <a href="{{ route('admin.patients.create') }}" class="btn btn-primary mt-3">
                        <i class="fas fa-plus me-2"></i>Add New Patient
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
function confirmDelete(id) {
    if (confirm('Are you sure you want to delete this patient? This action cannot be undone.')) {
        document.getElementById('delete-form-' + id).submit();
    }
}
</script>
@endpush
@endsection

