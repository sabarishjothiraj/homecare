@extends('layouts.admin')

@section('title', 'Edit Caste')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Edit Caste</h2>
            <p class="text-muted mb-0">Update caste information - {{ $caste->name }}</p>
        </div>
        <a href="{{ route('admin.castes.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Castes
        </a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-users me-2"></i>Caste Information</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.castes.update', $caste) }}" method="POST" data-ajax="true">
                        @csrf
                        @method('PUT')

                        <div class="row g-3">
                            <!-- Caste Name -->
                            <div class="col-md-12">
                                <label for="name" class="form-label">
                                    Caste Name <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       class="form-control @error('name') is-invalid @enderror"
                                       id="name"
                                       name="name"
                                       value="{{ old('name', $caste->name) }}"
                                       required maxlength="100"
                                       placeholder="e.g., SC (Scheduled Caste)">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Display Order -->
                            <div class="col-md-6">
                                <label for="display_order" class="form-label">
                                    Display Order
                                </label>
                                <input type="number" 
                                       class="form-control @error('display_order') is-invalid @enderror" 
                                       id="display_order" 
                                       name="display_order" 
                                       value="{{ old('display_order', $caste->display_order) }}"
                                       min="0">
                                @error('display_order')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Active Status -->
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           id="is_active" 
                                           name="is_active" value="1"
                                           {{ old('is_active', $caste->is_active) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">
                                        <strong>Active Caste</strong>
                                        <small class="text-muted d-block">Caste is available for selection</small>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Caste
                            </button>
                            <a href="{{ route('admin.castes.index') }}" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Information</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Required fields are marked with <span class="text-danger">*</span>
                    </p>
                    <p class="small text-muted mb-2">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Display order determines dropdown listing order
                    </p>
                    <p class="small text-muted mb-2">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Only active castes appear in patient/employee forms
                    </p>
                    <p class="small text-muted mb-0">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Caste name must be unique
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

