@extends('layouts.admin')

@section('title', 'Assignment Report')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Assignment Report</h2>
            <p class="text-muted mb-0">{{ $data->count() }} {{ Str::plural('record', $data->count()) }} found</p>
        </div>
        <a href="{{ route('admin.reports.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Reports
        </a>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.reports.assignments') }}" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="patient_id" class="form-label">Patient</label>
                    <select class="form-select" id="patient_id" name="patient_id">
                        <option value="">All Patients</option>
                        @foreach ($patients as $patient)
                            <option value="{{ $patient->id }}"
                                @selected((string) ($filters['patient_id'] ?? '') === (string) $patient->id)>
                                {{ $patient->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="employee_id" class="form-label">Employee</label>
                    <select class="form-select" id="employee_id" name="employee_id">
                        <option value="">All Employees</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}"
                                @selected((string) ($filters['employee_id'] ?? '') === (string) $employee->id)>
                                {{ trim($employee->getField('first_name') . ' ' . $employee->getField('last_name')) ?: '#' . $employee->id }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="start_date" class="form-label">Starts On/After</label>
                    <input type="date" class="form-control" id="start_date" name="start_date"
                           value="{{ $filters['start_date'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label for="end_date" class="form-label">Ends On/Before</label>
                    <input type="date" class="form-control" id="end_date" name="end_date"
                           value="{{ $filters['end_date'] ?? '' }}">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-info text-white flex-grow-1">
                        <i class="fas fa-search me-2"></i>Apply
                    </button>
                    <a href="{{ route('admin.reports.assignments') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Results</h5>
            @if ($data->isNotEmpty())
                <a href="{{ route('admin.reports.assignments', array_merge(array_filter($filters), ['export' => 1])) }}"
                   class="btn btn-sm btn-success">
                    <i class="fas fa-file-csv me-2"></i>Export CSV
                </a>
            @endif
        </div>
        <div class="card-body p-0">
            @include('admin.reports.partials.table', ['data' => $data])
        </div>
    </div>
</div>
@endsection
