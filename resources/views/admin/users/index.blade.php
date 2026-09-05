@extends('layouts.admin')

@section('title', 'Users')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Users</h2>
            <p class="text-muted mb-0">Manage admin and super admin users</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New User
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

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.users.index') }}" class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, username, or email..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <option value="">All Roles</option>
                        <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                        <option value="super_admin" {{ request('role') === 'super_admin' ? 'selected' : '' }}>Super Admin</option>
                    </select>
                </div>
                <div class="col-md-2">
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

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if($users->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $user)
                            <tr>
                                <td><strong>{{ $user->name }}</strong></td>
                                <td>{{ $user->username ?: 'N/A' }}</td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    <span class="badge {{ $user->role === 'super_admin' ? 'bg-danger' : 'bg-info' }}">
                                        {{ str_replace('_', ' ', ucfirst($user->role)) }}
                                    </span>
                                </td>
                                <td>
                                    @if($user->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td><small class="text-muted">{{ $user->created_at->format('M d, Y') }}</small></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('admin.users.show', $user) }}" class="btn btn-outline-primary" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        @if($user->is_active)
                                            <form action="{{ route('admin.users.deactivate', $user) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-outline-secondary" title="Deactivate">
                                                    <i class="fas fa-user-slash"></i>
                                                </button>
                                            </form>
                                        @else
                                            <form action="{{ route('admin.users.activate', $user) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-outline-success" title="Activate">
                                                    <i class="fas fa-user-check"></i>
                                                </button>
                                            </form>
                                        @endif
                                        @if(auth()->id() !== $user->id)
                                            <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
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

                <div class="card-footer bg-white">
                    {{ $users->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="fas fa-user-shield fa-4x text-muted mb-3 opacity-25"></i>
                    <h5 class="text-muted">No users found</h5>
                    <p class="text-muted">Start by creating your first user</p>
                    <a href="{{ route('admin.users.create') }}" class="btn btn-primary mt-3">
                        <i class="fas fa-plus me-2"></i>Add New User
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
