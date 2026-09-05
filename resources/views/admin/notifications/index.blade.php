@extends('layouts.admin')

@section('title', 'Notifications')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Notifications</h2>
            <p class="text-muted mb-0">
                {{ $unreadCount }} unread {{ Str::plural('notification', $unreadCount) }}
            </p>
        </div>
        @if ($unreadCount > 0)
            <form action="{{ route('admin.notifications.mark-all-read') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-outline-primary">
                    <i class="fas fa-check-double me-2"></i>Mark All Read
                </button>
            </form>
        @endif
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.notifications.index') }}" method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All</option>
                        <option value="unread" @selected(request('status') === 'unread')>Unread</option>
                        <option value="read" @selected(request('status') === 'read')>Read</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="type" class="form-label">Type</label>
                    <input type="text" class="form-control" id="type" name="type"
                           placeholder="Any type" value="{{ request('type') }}">
                </div>
                <div class="col-md-4 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="fas fa-search me-2"></i>Apply
                    </button>
                    <a href="{{ route('admin.notifications.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="fas fa-bell me-2"></i>All Notifications</h5>
        </div>
        <div class="card-body p-0">
            @if ($notifications->isEmpty())
                <div class="text-center text-muted py-5">
                    <i class="fas fa-bell-slash fa-3x mb-3 d-block opacity-50"></i>
                    <p class="mb-0">No notifications to show.</p>
                </div>
            @else
                <div class="list-group list-group-flush">
                    @foreach ($notifications as $notification)
                        <div class="list-group-item {{ $notification->is_read ? '' : 'bg-light' }}">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        @unless ($notification->is_read)
                                            <span class="badge bg-primary">New</span>
                                        @endunless
                                        <strong>{{ $notification->title }}</strong>
                                        <span class="badge bg-secondary">
                                            {{ Str::of($notification->type)->replace('_', ' ')->title() }}
                                        </span>
                                    </div>
                                    <p class="mb-1 text-muted small">{{ $notification->message }}</p>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>{{ $notification->created_at->diffForHumans() }}
                                    </small>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                    @if ($notification->link)
                                        <a href="{{ $notification->link }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-arrow-right me-1"></i>View
                                        </a>
                                    @endif
                                    @unless ($notification->is_read)
                                        <form action="{{ route('admin.notifications.mark-read', $notification) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-check me-1"></i>Mark Read
                                            </button>
                                        </form>
                                    @endunless
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
        @if ($notifications->hasPages())
            <div class="card-footer bg-white">
                {{ $notifications->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
