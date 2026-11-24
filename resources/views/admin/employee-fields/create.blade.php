@extends('layouts.admin')

@section('title', 'Create Employee Field')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Create Employee Field</h2>
            <p class="text-muted mb-0">Add a new dynamic field to employee forms</p>
        </div>
        <a href="{{ route('admin.employee-fields.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Fields
        </a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Field Configuration</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.employee-fields.store') }}" method="POST">
                        @csrf

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
                                       value="{{ old('name') }}"
                                       required
                                       placeholder="e.g., experience_years">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Use lowercase with underscores (e.g., field_name)</small>
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
                                       value="{{ old('label') }}"
                                       required
                                       placeholder="e.g., Years of Experience">
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
                                        required>
                                    <option value="">Select Type</option>
                                    <option value="text" {{ old('type') === 'text' ? 'selected' : '' }}>Text</option>
                                    <option value="textarea" {{ old('type') === 'textarea' ? 'selected' : '' }}>Textarea</option>
                                    <option value="email" {{ old('type') === 'email' ? 'selected' : '' }}>Email</option>
                                    <option value="phone" {{ old('type') === 'phone' ? 'selected' : '' }}>Phone</option>
                                    <option value="number" {{ old('type') === 'number' ? 'selected' : '' }}>Number</option>
                                    <option value="date" {{ old('type') === 'date' ? 'selected' : '' }}>Date</option>
                                    <option value="select" {{ old('type') === 'select' ? 'selected' : '' }}>Select Dropdown</option>
                                    <option value="file" {{ old('type') === 'file' ? 'selected' : '' }}>File Upload</option>
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
                                       value="{{ old('display_order') }}"
                                       min="0"
                                       placeholder="Auto-assigned if left blank">
                                @error('display_order')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Options (for select type) -->
                            <div class="col-12" id="options-container" style="display: none;">
                                <label for="options" class="form-label">Options (for Select type)</label>
                                <textarea class="form-control @error('options') is-invalid @enderror" 
                                          id="options" 
                                          name="options" 
                                          rows="3"
                                          placeholder="Enter options separated by commas (e.g., Option 1, Option 2, Option 3)">{{ old('options') }}</textarea>
                                @error('options')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Checkboxes -->
                            <div class="col-12">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="is_required" name="is_required" value="1" {{ old('is_required') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_required">
                                        <strong>Required Field</strong>
                                        <small class="text-muted d-block">Field must be filled</small>
                                    </label>
                                </div>

                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="is_document" name="is_document" value="1" {{ old('is_document') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_document">
                                        <strong>Document Field</strong>
                                        <small class="text-muted d-block">Field stores uploaded documents</small>
                                    </label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">
                                        <strong>Active</strong>
                                        <small class="text-muted d-block">Field is visible in forms</small>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Create Field
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
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Information</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Field name must be unique and use lowercase with underscores
                    </p>
                    <p class="small text-muted mb-2">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Display order determines field position in forms
                    </p>
                    <p class="small text-muted mb-2">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Select type requires comma-separated options
                    </p>
                    <p class="small text-muted mb-0">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Document fields are used for file uploads
                    </p>
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

// Trigger on page load if select is already selected
if (document.getElementById('type').value === 'select') {
    document.getElementById('options-container').style.display = 'block';
}
</script>
@endsection

