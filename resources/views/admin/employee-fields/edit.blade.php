@extends('layouts.admin')

@section('title', 'Edit Employee Field')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Edit Employee Field</h2>
            <p class="text-muted mb-0">{{ $employeeField->label }}</p>
        </div>
        <a href="{{ route('admin.employee-fields.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Fields
        </a>
    </div>

    @if($employeeField->is_mandatory)
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Warning:</strong> This is a mandatory system field. You can only activate/deactivate it or change its display order, but cannot modify its configuration.
        </div>
    @endif

    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Field Configuration</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.employee-fields.update', $employeeField) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row g-3">
                            <!-- Field Name -->
                            <div class="col-md-6">
                                <label for="name" class="form-label">
                                    Field Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="form-control @error('name') is-invalid @enderror" 
                                       id="name" 
                                       name="name" 
                                       value="{{ old('name', $employeeField->name) }}"
                                       {{ $employeeField->is_mandatory ? 'readonly' : 'required' }}>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Label -->
                            <div class="col-md-6">
                                <label for="label" class="form-label">
                                    Field Label <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="form-control @error('label') is-invalid @enderror" 
                                       id="label" 
                                       name="label" 
                                       value="{{ old('label', $employeeField->label) }}"
                                       {{ $employeeField->is_mandatory ? 'readonly' : 'required' }}>
                                @error('label')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Type -->
                            <div class="col-md-6">
                                <label for="type" class="form-label">
                                    Field Type <span class="text-danger">*</span>
                                </label>
                                <select class="form-select @error('type') is-invalid @enderror" 
                                        id="type" 
                                        name="type" 
                                        {{ $employeeField->is_mandatory ? 'disabled' : 'required' }}>
                                    <option value="text" {{ old('type', $employeeField->type) === 'text' ? 'selected' : '' }}>Text</option>
                                    <option value="textarea" {{ old('type', $employeeField->type) === 'textarea' ? 'selected' : '' }}>Textarea</option>
                                    <option value="email" {{ old('type', $employeeField->type) === 'email' ? 'selected' : '' }}>Email</option>
                                    <option value="phone" {{ old('type', $employeeField->type) === 'phone' ? 'selected' : '' }}>Phone</option>
                                    <option value="number" {{ old('type', $employeeField->type) === 'number' ? 'selected' : '' }}>Number</option>
                                    <option value="date" {{ old('type', $employeeField->type) === 'date' ? 'selected' : '' }}>Date</option>
                                    <option value="select" {{ old('type', $employeeField->type) === 'select' ? 'selected' : '' }}>Select Dropdown</option>
                                    <option value="file" {{ old('type', $employeeField->type) === 'file' ? 'selected' : '' }}>File Upload</option>
                                </select>
                                @error('type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Display Order -->
                            <div class="col-md-6">
                                <label for="display_order" class="form-label">Display Order</label>
                                <input type="number" 
                                       class="form-control @error('display_order') is-invalid @enderror" 
                                       id="display_order" 
                                       name="display_order" 
                                       value="{{ old('display_order', $employeeField->order) }}"
                                       min="0">
                                @error('display_order')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Options (for select type) -->
                            <div class="col-12" id="options-container" style="display: {{ old('type', $employeeField->type) === 'select' ? 'block' : 'none' }};">
                                <label for="options" class="form-label">Options (for Select type)</label>
                                <textarea class="form-control @error('options') is-invalid @enderror" 
                                          id="options" 
                                          name="options" 
                                          rows="3"
                                          {{ $employeeField->is_mandatory ? 'readonly' : '' }}
                                          placeholder="Enter options separated by commas">{{ old('options', $employeeField->type === 'select' ? implode(', ', $employeeField->optionList()) : '') }}</textarea>
                                @error('options')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Checkboxes -->
                            <div class="col-12">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           id="is_required" 
                                           name="is_required" value="1" 
                                           {{ old('is_required', $employeeField->is_required) ? 'checked' : '' }}
                                           {{ $employeeField->is_mandatory ? 'disabled' : '' }}>
                                    <label class="form-check-label" for="is_required">
                                        <strong>Required Field</strong>
                                    </label>
                                </div>

                                <div class="form-check mb-2">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           id="is_document" 
                                           name="is_document" value="1" 
                                           {{ old('is_document', $employeeField->is_document) ? 'checked' : '' }}
                                           {{ $employeeField->is_mandatory ? 'disabled' : '' }}>
                                    <label class="form-check-label" for="is_document">
                                        <strong>Document Field</strong>
                                    </label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           id="is_active" 
                                           name="is_active" value="1" 
                                           {{ old('is_active', $employeeField->is_active) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">
                                        <strong>Active</strong>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Field
                            </button>
                            <a href="{{ route('admin.employee-fields.index') }}" class="btn btn-outline-secondary">
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
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Field Details</h6>
                </div>
                <div class="card-body">
                    <p class="small mb-2"><strong>Field ID:</strong> #{{ $employeeField->id }}</p>
                    <p class="small mb-2"><strong>Mandatory:</strong> {{ $employeeField->is_mandatory ? 'Yes' : 'No' }}</p>
                    <p class="small mb-2"><strong>Created:</strong> {{ $employeeField->created_at->format('M d, Y') }}</p>
                    <p class="small mb-0"><strong>Last Updated:</strong> {{ $employeeField->updated_at->diffForHumans() }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('type').addEventListener('change', function() {
    const optionsContainer = document.getElementById('options-container');
    if (this.value === 'select') {
        optionsContainer.style.display = 'block';
    } else {
        optionsContainer.style.display = 'none';
    }
});
</script>
@endsection

