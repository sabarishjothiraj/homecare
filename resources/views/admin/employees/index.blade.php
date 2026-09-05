@extends('layouts.admin')

@section('title', 'Employees')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Employees</h2>
            <p class="text-muted mb-0">Manage nursing staff and caregivers</p>
        </div>
        <a href="{{ route('admin.employees.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Employee
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
            <form method="GET" action="{{ route('admin.employees.index') }}" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search employees..." value="{{ request('search') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
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

    <!-- Employees Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if($employees->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Created By</th>
                                <th>Documents</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($employees as $employee)
                            <tr>
                                <td><strong>#{{ $employee->id }}</strong></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        @php($employeeImagePath = $employee->getField('employee_image_path'))
                                        @if($employeeImagePath)
                                            <img src="{{ Storage::url($employeeImagePath) }}"
                                                 alt="{{ $employee->getField('name', 'Employee') }}"
                                                 class="rounded-circle me-3 object-fit-cover employee-image-thumb"
                                                 style="width: 64px; height: 64px; cursor: pointer;"
                                                 data-image-url="{{ Storage::url($employeeImagePath) }}"
                                                 data-image-name="{{ $employee->getField('name', 'Employee') }}"
                                                 title="Click to enlarge">
                                        @else
                                            <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3"
                                                 style="width: 64px; height: 64px;">
                                                <i class="fas fa-user-nurse text-success fa-lg"></i>
                                            </div>
                                        @endif
                                        <div>
                                            <strong>{{ $employee->getField('name', 'N/A') }}</strong>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small">
                                        @if($employee->getField('email'))
                                            <div><i class="fas fa-envelope me-1 text-muted"></i>{{ $employee->getField('email') }}</div>
                                        @endif
                                        @if($employee->getField('phone'))
                                            <div><i class="fas fa-phone me-1 text-muted"></i>{{ $employee->getField('phone') }}</div>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if($employee->status === 'active')
                                        <span class="badge bg-success">Active</span>
                                    @elseif($employee->status === 'pending')
                                        <span class="badge bg-warning">Pending</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <small>{{ $employee->creator->name ?? 'System' }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-info">{{ $employee->documents->count() }} docs</span>
                                </td>
                                <td>
                                    <small class="text-muted">{{ $employee->created_at->format('M d, Y') }}</small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('admin.employees.show', $employee) }}" class="btn btn-outline-primary" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.employees.edit', $employee) }}" class="btn btn-outline-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        @if($employee->status === 'pending')
                                            <form action="{{ route('admin.employees.activate', $employee) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-outline-success" title="Activate">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        @elseif($employee->status === 'active')
                                            <form action="{{ route('admin.employees.deactivate', $employee) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-outline-danger" title="Deactivate">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            </form>
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
                    {{ $employees->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="fas fa-user-nurse fa-4x text-muted mb-3 opacity-25"></i>
                    <h5 class="text-muted">No employees found</h5>
                    <p class="text-muted">Start by adding your first employee</p>
                    <a href="{{ route('admin.employees.create') }}" class="btn btn-primary mt-3">
                        <i class="fas fa-plus me-2"></i>Add New Employee
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Employee Image Popup -->
<div class="modal fade" id="employeeImageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="employeeImageModalLabel">Employee Image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img src="" id="employeeImageModalImg" class="img-fluid rounded" alt="Employee image">
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const imageModalEl = document.getElementById('employeeImageModal');
        const imageModal = new bootstrap.Modal(imageModalEl);
        const modalImg = document.getElementById('employeeImageModalImg');
        const modalLabel = document.getElementById('employeeImageModalLabel');

        document.querySelectorAll('.employee-image-thumb').forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                modalImg.src = this.dataset.imageUrl;
                modalLabel.textContent = this.dataset.imageName || 'Employee Image';
                imageModal.show();
            });
        });
    });
</script>
@endpush

