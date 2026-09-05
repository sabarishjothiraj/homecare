@extends('layouts.admin')

@section('title', 'Banners')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Banners</h2>
        <a href="{{ route('admin.banners.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Banner
        </a>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="card">
        <div class="card-body">
            @if($banners->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Image</th>
                            <th>Title</th>
                            <th>Subtitle</th>
                            <th style="width: 100px;">Order</th>
                            <th style="width: 100px;">Status</th>
                            <th style="width: 200px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($banners as $banner)
                        <tr>
                            <td>
                                <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}" 
                                     class="img-thumbnail" style="width: 60px; height: 40px; object-fit: cover;">
                            </td>
                            <td>{{ $banner->title }}</td>
                            <td>{{ Str::limit($banner->subtitle, 50) }}</td>
                            <td>
                                <span class="badge bg-secondary">{{ $banner->order }}</span>
                            </td>
                            <td>
                                @if($banner->is_active)
                                <span class="badge bg-success">Active</span>
                                @else
                                <span class="badge bg-danger">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <form action="{{ route('admin.banners.toggle-status', $banner) }}" 
                                      method="POST" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-{{ $banner->is_active ? 'warning' : 'success' }}" 
                                            title="{{ $banner->is_active ? 'Deactivate' : 'Activate' }}">
                                        <i class="fas fa-{{ $banner->is_active ? 'eye-slash' : 'eye' }}"></i>
                                    </button>
                                </form>
                                <a href="{{ route('admin.banners.edit', $banner) }}" 
                                   class="btn btn-sm btn-primary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('admin.banners.destroy', $banner) }}" 
                                      method="POST" class="d-inline" 
                                      onsubmit="return confirm('Are you sure you want to delete this banner?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $banners->links() }}
            </div>
            @else
            <div class="text-center py-5">
                <i class="fas fa-image fa-3x text-muted mb-3"></i>
                <p class="text-muted">No banners found. Create your first banner to get started.</p>
                <a href="{{ route('admin.banners.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Add New Banner
                </a>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

