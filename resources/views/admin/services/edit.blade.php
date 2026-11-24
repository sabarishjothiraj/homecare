@extends('layouts.admin')

@section('title', 'Edit Service')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Edit Service</h2>
            <p class="text-muted mb-0">Update service information - {{ $service->name }}</p>
        </div>
        <a href="{{ route('admin.services.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Services
        </a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-briefcase-medical me-2"></i>Service Information</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.services.update', $service) }}" method="POST" data-ajax="true">
                        @csrf
                        @method('PUT')

                        <div class="row g-3">
                            <!-- Service Name -->
                            <div class="col-md-6">
                                <label for="name" class="form-label">
                                    Service Name <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       class="form-control @error('name') is-invalid @enderror"
                                       id="name"
                                       name="name"
                                       value="{{ old('name', $service->name) }}"
                                       required maxlength="255">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Icon -->
                            <div class="col-md-6">
                                <label for="icon" class="form-label">
                                    Icon Class <small class="text-muted">(Font Awesome)</small>
                                </label>
                                <input type="text"
                                       class="form-control @error('icon') is-invalid @enderror"
                                       id="icon"
                                       name="icon"
                                       value="{{ old('icon', $service->icon) }}"
                                       placeholder="e.g., fas fa-heartbeat"
                                       maxlength="100">
                                @error('icon')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Visit <a href="https://fontawesome.com/icons" target="_blank">Font Awesome</a> for icon classes</small>
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
                                       value="{{ old('display_order', $service->order) }}"
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
                                           {{ old('is_active', $service->is_active) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">
                                        <strong>Active Service</strong>
                                        <small class="text-muted d-block">Service is available for contracts</small>
                                    </label>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="col-12">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control @error('description') is-invalid @enderror" 
                                          id="description" 
                                          name="description" 
                                          rows="5"
                                          placeholder="Enter detailed description of the service">{{ old('description', $service->description) }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Service
                            </button>
                            <a href="{{ route('admin.services.index') }}" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Service Details</h6>
                </div>
                <div class="card-body">
                    <p class="small mb-2"><strong>Service ID:</strong> #{{ $service->id }}</p>
                    <p class="small mb-2"><strong>Status:</strong> 
                        @if($service->is_active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </p>
                    <p class="small mb-2"><strong>Created:</strong> {{ $service->created_at->format('M d, Y') }}</p>
                    <p class="small mb-0"><strong>Last Updated:</strong> {{ $service->updated_at->diffForHumans() }}</p>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-icons me-2"></i>Icon Examples</h6>
                </div>
                <div class="card-body">
                    <p class="small mb-2"><i class="fas fa-heartbeat text-danger me-2"></i> <code>fas fa-heartbeat</code></p>
                    <p class="small mb-2"><i class="fas fa-user-nurse text-primary me-2"></i> <code>fas fa-user-nurse</code></p>
                    <p class="small mb-2"><i class="fas fa-stethoscope text-success me-2"></i> <code>fas fa-stethoscope</code></p>
                    <p class="small mb-2"><i class="fas fa-hospital text-info me-2"></i> <code>fas fa-hospital</code></p>
                    <p class="small mb-0"><i class="fas fa-briefcase-medical text-warning me-2"></i> <code>fas fa-briefcase-medical</code></p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

