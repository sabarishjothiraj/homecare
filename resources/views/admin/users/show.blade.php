@extends('layouts.admin')

@section('title', 'User Details')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">User Details</h2>
            <p class="text-muted mb-0">Account details for {{ $user->name }}</p>
        </div>
        @php($canChange = auth()->user()->isSuperAdmin() || ! $user->isSuperAdmin())
        <div class="d-flex gap-2">
            @if($canChange)
            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-warning">
                <i class="fas fa-edit me-2"></i>Edit
            </a>
            @endif
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Users
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-user-shield me-2"></i>User Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <small class="text-muted d-block">Name</small>
                            <strong>{{ $user->name }}</strong>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Username</small>
                            <strong>{{ $user->username ?: 'N/A' }}</strong>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Email</small>
                            <strong>{{ $user->email }}</strong>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Role</small>
                            <span class="badge {{ $user->isSuperAdmin() ? 'bg-danger' : 'bg-info' }}">
                                {{ $user->isSuperAdmin() ? 'Super admin' : 'Admin' }}
                            </span>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Status</small>
                            @if($user->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Created At</small>
                            <strong>{{ $user->created_at->format('M d, Y h:i A') }}</strong>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Last Updated</small>
                            <strong>{{ $user->updated_at->format('M d, Y h:i A') }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($canChange)
        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body d-grid gap-2">
                    @if($user->is_active)
                        <form action="{{ route('admin.users.deactivate', $user) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-user-slash me-2"></i>Set Inactive
                            </button>
                        </form>
                    @else
                        <form action="{{ route('admin.users.activate', $user) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-outline-success w-100">
                                <i class="fas fa-user-check me-2"></i>Set Active
                            </button>
                        </form>
                    @endif

                    @if(auth()->id() !== $user->id)
                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger w-100">
                                <i class="fas fa-trash me-2"></i>Delete User
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
