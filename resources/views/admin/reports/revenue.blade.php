@extends('layouts.admin')

@section('title', 'Revenue Report')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Revenue Report</h2>
            <p class="text-muted mb-0">{{ $data->count() }} completed {{ Str::plural('payment', $data->count()) }} found</p>
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
            <form action="{{ route('admin.reports.revenue') }}" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="payment_method" class="form-label">Payment Method</label>
                    <select class="form-select" id="payment_method" name="payment_method">
                        <option value="">All Methods</option>
                        @foreach (['cash', 'razorpay', 'online', 'cheque', 'bank_transfer'] as $method)
                            <option value="{{ $method }}" @selected(($filters['payment_method'] ?? '') === $method)>
                                {{ ucfirst(str_replace('_', ' ', $method)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="payment_type" class="form-label">Payment Type</label>
                    <select class="form-select" id="payment_type" name="payment_type">
                        <option value="">All Types</option>
                        @foreach (['contract' => 'Contract', 'employee_activation' => 'Employee Activation'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['payment_type'] ?? '') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="start_date" class="form-label">Paid From</label>
                    <input type="date" class="form-control" id="start_date" name="start_date"
                           value="{{ $filters['start_date'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label for="end_date" class="form-label">Paid To</label>
                    <input type="date" class="form-control" id="end_date" name="end_date"
                           value="{{ $filters['end_date'] ?? '' }}">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-warning flex-grow-1">
                        <i class="fas fa-search me-2"></i>Apply
                    </button>
                    <a href="{{ route('admin.reports.revenue') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-dollar-sign me-2"></i>Results</h5>
            @if ($data->isNotEmpty())
                <a href="{{ route('admin.reports.revenue', array_merge(array_filter($filters), ['export' => 1])) }}"
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
