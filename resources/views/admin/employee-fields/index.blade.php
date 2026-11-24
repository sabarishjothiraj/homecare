@extends('layouts.admin')

@section('title', 'Employee Fields')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Employee Fields</h2>
            <p class="text-muted mb-0">Manage dynamic employee form fields</p>
        </div>
        <a href="{{ route('admin.employee-fields.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Field
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Fields Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if($fields->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Order</th>
                                <th>Field Name</th>
                                <th>Label</th>
                                <th>Type</th>
                                <th>Required</th>
                                <th>Document</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($fields as $field)
                            <tr>
                                <td>
                                    <span class="badge bg-secondary">{{ $field->order }}</span>
                                </td>
                                <td>
                                    <code>{{ $field->name }}</code>
                                    @if($field->is_mandatory)
                                        <span class="badge bg-warning text-dark ms-1" title="Mandatory field - cannot be deleted">
                                            <i class="fas fa-lock"></i> Mandatory
                                        </span>
                                    @endif
                                </td>
                                <td><strong>{{ $field->label }}</strong></td>
                                <td>
                                    <span class="badge bg-info">{{ ucfirst($field->type) }}</span>
                                </td>
                                <td>
                                    @if($field->is_required)
                                        <i class="fas fa-check-circle text-success"></i>
                                    @else
                                        <i class="fas fa-times-circle text-muted"></i>
                                    @endif
                                </td>
                                <td>
                                    @if($field->is_document)
                                        <i class="fas fa-file text-primary"></i>
                                    @else
                                        <i class="fas fa-times text-muted"></i>
                                    @endif
                                </td>
                                <td>
                                    @if($field->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('admin.employee-fields.edit', $field) }}" 
                                           class="btn btn-outline-warning" 
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        @if(!$field->is_mandatory)
                                            <form action="{{ route('admin.employee-fields.destroy', $field) }}" 
                                                  method="POST" 
                                                  class="d-inline" 
                                                  onsubmit="return confirm('Are you sure you want to delete this field?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-5">
                    <i class="fas fa-list fa-4x text-muted mb-3 opacity-25"></i>
                    <h5 class="text-muted">No employee fields found</h5>
                    <p class="text-muted">Start by creating your first custom field</p>
                    <a href="{{ route('admin.employee-fields.create') }}" class="btn btn-primary mt-3">
                        <i class="fas fa-plus me-2"></i>Add New Field
                    </a>
                </div>
            @endif
        </div>
    </div>

    <div class="alert alert-info mt-4">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Note:</strong> Mandatory fields (marked with <i class="fas fa-lock"></i>) are system-required and cannot be deleted. You can only activate/deactivate them.
    </div>
</div>
@endsection

