@extends('layouts.admin')

@section('title', 'Add New Employee')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Add New Employee</h2>
            <p class="text-muted mb-0">Create a new employee record</p>
        </div>
        <a href="{{ route('admin.employees.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Employees
        </a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-user-nurse me-2"></i>Employee Information</h5>
                </div>
                <div class="card-body">
                    @include('admin.employees._form', [
                        'formAction' => route('admin.employees.store'),
                        'formMethod' => 'POST',
                        'submitLabel' => 'Create Employee',
                        'cancelUrl' => route('admin.employees.index'),
                        'employee' => null,
                    ])
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
                        New employees will have "Pending" status
                    </p>
                    <p class="small text-muted mb-2">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Upload all required documents
                    </p>
                    <p class="small text-muted mb-0">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Add at least 2 emergency contacts and required experience details
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

