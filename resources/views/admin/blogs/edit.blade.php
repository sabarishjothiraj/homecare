@extends('layouts.admin')

@section('title', 'Edit Blog Post')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Edit Blog Post</h2>
            <p class="text-muted mb-0">{{ $blog->title }}</p>
        </div>
        <a href="{{ route('admin.blogs.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Blogs
        </a>
    </div>

    <form action="{{ route('admin.blogs.update', $blog) }}" method="POST" enctype="multipart/form-data" data-ajax="true">
        @csrf
        @method('PUT')
        <div class="row">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-blog me-2"></i>Post Content</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <!-- Title -->
                            <div class="col-12">
                                <label for="title" class="form-label">
                                    Title <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       class="form-control @error('title') is-invalid @enderror"
                                       id="title"
                                       name="title"
                                       value="{{ old('title', $blog->title) }}"
                                       required maxlength="255">
                                @error('title')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Excerpt -->
                            <div class="col-12">
                                <label for="excerpt" class="form-label">Excerpt</label>
                                <textarea class="form-control @error('excerpt') is-invalid @enderror" 
                                          id="excerpt" 
                                          name="excerpt" 
                                          rows="2"
                                          maxlength="500"
                                          placeholder="Short summary (max 500 characters)">{{ old('excerpt', $blog->excerpt) }}</textarea>
                                @error('excerpt')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Content -->
                            <div class="col-12">
                                <label for="content" class="form-label">
                                    Content <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control @error('content') is-invalid @enderror" 
                                          id="content" 
                                          name="content" 
                                          rows="15"
                                          required>{{ old('content', $blog->content) }}</textarea>
                                @error('content')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-cog me-2"></i>Publish Settings</h6>
                    </div>
                    <div class="card-body">
                        <!-- Status -->
                        <div class="mb-3">
                            <label for="status" class="form-label">
                                Status <span class="text-danger">*</span>
                            </label>
                            <select class="form-select @error('status') is-invalid @enderror" 
                                    id="status" 
                                    name="status" 
                                    required>
                                <option value="draft" {{ old('status', $blog->status) === 'draft' ? 'selected' : '' }}>Draft</option>
                                <option value="published" {{ old('status', $blog->status) === 'published' ? 'selected' : '' }}>Published</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Published At -->
                        <div class="mb-3">
                            <label for="published_at" class="form-label">Publish Date</label>
                            <input type="datetime-local" 
                                   class="form-control @error('published_at') is-invalid @enderror" 
                                   id="published_at" 
                                   name="published_at" 
                                   value="{{ old('published_at', $blog->published_at ? $blog->published_at->format('Y-m-d\TH:i') : '') }}">
                            @error('published_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <hr>
                        <p class="small mb-1"><strong>Views:</strong> {{ $blog->views ?? 0 }}</p>
                        <p class="small mb-1"><strong>Created:</strong> {{ $blog->created_at->format('M d, Y') }}</p>
                        <p class="small mb-0"><strong>Updated:</strong> {{ $blog->updated_at->diffForHumans() }}</p>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-image me-2"></i>Featured Image</h6>
                    </div>
                    <div class="card-body">
                        @if($blog->featured_image)
                            <img src="{{ $blog->featured_image_url }}" 
                                 alt="{{ $blog->title }}" 
                                 class="img-fluid rounded mb-3">
                        @endif
                        <div class="mb-3">
                            <input type="file"
                                   class="form-control @error('featured_image') is-invalid @enderror"
                                   id="featured_image"
                                   name="featured_image"
                                   accept="image/*"
                                   data-validate="image"
                                   data-max-size="2048">
                            @error('featured_image')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Max size: 2MB. Leave blank to keep current image.</small>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <button type="submit" class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-save me-2"></i>Update Post
                        </button>
                        <a href="{{ route('admin.blogs.index') }}" class="btn btn-outline-secondary w-100">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

