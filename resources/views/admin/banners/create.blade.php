@extends('layouts.admin')

@section('title', 'Create Banner')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Create New Banner</h2>
        <a href="{{ route('admin.banners.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Banners
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.banners.store') }}" method="POST" enctype="multipart/form-data" data-ajax="true">
                @csrf

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('title') is-invalid @enderror"
                               id="title" name="title" value="{{ old('title') }}" required maxlength="255">
                        @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="subtitle" class="form-label">Subtitle</label>
                        <input type="text" class="form-control @error('subtitle') is-invalid @enderror"
                               id="subtitle" name="subtitle" value="{{ old('subtitle') }}" maxlength="255">
                        @error('subtitle')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="image" class="form-label">Banner Image <span class="text-danger">*</span></label>
                        <input type="file" class="form-control @error('image') is-invalid @enderror"
                               id="image" name="image" accept="image/*" required
                               data-validate="image" data-max-size="2048">
                        <small class="text-muted">Recommended size: 1920x600px. Max size: 2MB</small>
                        @error('image')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="order" class="form-label">Order <span class="text-danger">*</span></label>
                        <input type="number" class="form-control @error('order') is-invalid @enderror"
                               id="order" name="order" value="{{ old('order', 0) }}" min="0" required>
                        <small class="text-muted">Lower numbers appear first</small>
                        @error('order')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="link" class="form-label">Link URL</label>
                        <input type="url" class="form-control @error('link') is-invalid @enderror"
                               id="link" name="link" value="{{ old('link') }}"
                               placeholder="https://example.com" maxlength="255">
                        @error('link')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="button_text" class="form-label">Button Text</label>
                        <input type="text" class="form-control @error('button_text') is-invalid @enderror"
                               id="button_text" name="button_text" value="{{ old('button_text') }}"
                               placeholder="Learn More" maxlength="100">
                        @error('button_text')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_active" 
                               name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">
                            Active
                        </label>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Create Banner
                    </button>
                    <a href="{{ route('admin.banners.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

