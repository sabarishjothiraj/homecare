@extends('layouts.admin')

@section('title', 'Patient Report')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Patient Report</h2>
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
            <form action="{{ route('admin.reports.patients') }}" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Registered From</label>
                    <input type="date" class="form-control" id="start_date" name="start_date"
                           value="{{ $filters['start_date'] ?? '' }}">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">Registered To</label>
                    <input type="date" class="form-control" id="end_date" name="end_date"
                           value="{{ $filters['end_date'] ?? '' }}">
                </div>
                <div class="col-md-3">
                    <label for="city" class="form-label">City</label>
                    <input type="text" class="form-control" id="city" name="city"
                           placeholder="Any city" value="{{ $filters['city'] ?? '' }}">
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="fas fa-search me-2"></i>Apply
                    </button>
                    <a href="{{ route('admin.reports.patients') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-users me-2"></i>Results</h5>
            @if ($data->isNotEmpty())
                <a href="{{ route('admin.reports.patients', array_merge(array_filter($filters), ['export' => 1])) }}"
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
