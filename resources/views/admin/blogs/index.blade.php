@extends('layouts.admin')

@section('title', 'Blog Posts')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Blog Posts</h2>
            <p class="text-muted mb-0">Manage blog posts and articles</p>
        </div>
        <a href="{{ route('admin.blogs.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Create New Post
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.blogs.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="published" {{ request('status') === 'published' ? 'selected' : '' }}>Published</option>
                        <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
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

    <!-- Blogs Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if($blogs->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Featured Image</th>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Status</th>
                                <th>Views</th>
                                <th>Published</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($blogs as $blog)
                            <tr>
                                <td>
                                    @if($blog->featured_image)
                                        <img src="{{ $blog->featured_image_url }}" 
                                             alt="{{ $blog->title }}" 
                                             class="rounded"
                                             style="width: 60px; height: 60px; object-fit: cover;">
                                    @else
                                        <div class="bg-secondary bg-opacity-10 rounded d-flex align-items-center justify-content-center" 
                                             style="width: 60px; height: 60px;">
                                            <i class="fas fa-image text-muted"></i>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $blog->title }}</strong>
                                    @if($blog->excerpt)
                                        <br>
                                        <small class="text-muted">{{ Str::limit($blog->excerpt, 60) }}</small>
                                    @endif
                                </td>
                                <td>{{ $blog->author->name ?? 'N/A' }}</td>
                                <td>
                                    @if($blog->status === 'published')
                                        <span class="badge bg-success">Published</span>
                                    @else
                                        <span class="badge bg-secondary">Draft</span>
                                    @endif
                                </td>
                                <td>
                                    <i class="fas fa-eye text-muted me-1"></i>
                                    {{ $blog->views ?? 0 }}
                                </td>
                                <td>
                                    @if($blog->published_at)
                                        {{ $blog->published_at->format('M d, Y') }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('admin.blogs.edit', $blog) }}" class="btn btn-outline-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.blogs.destroy', $blog) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this blog post?');">
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
                    {{ $blogs->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="fas fa-blog fa-4x text-muted mb-3 opacity-25"></i>
                    <h5 class="text-muted">No blog posts found</h5>
                    <p class="text-muted">Start by creating your first blog post</p>
                    <a href="{{ route('admin.blogs.create') }}" class="btn btn-primary mt-3">
                        <i class="fas fa-plus me-2"></i>Create New Post
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

