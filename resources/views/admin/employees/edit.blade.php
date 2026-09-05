@extends('layouts.admin')

@section('title', 'Edit Employee')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Edit Employee</h2>
            <p class="text-muted mb-0">Update employee information</p>
        </div>
        <a href="{{ route('admin.employees.show', $employee) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Employee
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
                        'formAction' => route('admin.employees.update', $employee),
                        'formMethod' => 'PUT',
                        'submitLabel' => 'Update Employee',
                        'cancelUrl' => route('admin.employees.show', $employee),
                        'employee' => $employee,
                    ])
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Employee Details</h6>
                </div>
                <div class="card-body">
                    <p class="small mb-2"><strong>Status:</strong> 
                        @if($employee->status === 'active')
                            <span class="badge bg-success">Active</span>
                        @elseif($employee->status === 'pending')
                            <span class="badge bg-warning">Pending</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </p>
                    <p class="small mb-2"><strong>Created:</strong> {{ $employee->created_at->format('M d, Y') }}</p>
                    <p class="small mb-0"><strong>Last Updated:</strong> {{ $employee->updated_at->diffForHumans() }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

