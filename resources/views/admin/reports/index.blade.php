@extends('layouts.admin')

@section('title', 'Reports')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Reports & Analytics</h2>
            <p class="text-muted mb-0">Generate and export various reports</p>
        </div>
    </div>

    <!-- Report Cards -->
    <div class="row">
        <!-- Patient Report -->
        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                         style="width: 80px; height: 80px;">
                        <i class="fas fa-users fa-2x text-primary"></i>
                    </div>
                    <h5 class="card-title">Patient Report</h5>
                    <p class="card-text text-muted small">
                        View patient statistics, demographics, and activity
                    </p>
                    <a href="{{ route('admin.reports.patients') }}" class="btn btn-primary w-100">
                        <i class="fas fa-chart-bar me-2"></i>View Report
                    </a>
                </div>
            </div>
        </div>

        <!-- Employee Report -->
        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                         style="width: 80px; height: 80px;">
                        <i class="fas fa-user-nurse fa-2x text-success"></i>
                    </div>
                    <h5 class="card-title">Employee Report</h5>
                    <p class="card-text text-muted small">
                        View employee statistics, status, and performance
                    </p>
                    <a href="{{ route('admin.reports.employees') }}" class="btn btn-success w-100">
                        <i class="fas fa-chart-bar me-2"></i>View Report
                    </a>
                </div>
            </div>
        </div>

        <!-- Assignment Report -->
        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="bg-info bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                         style="width: 80px; height: 80px;">
                        <i class="fas fa-clipboard-list fa-2x text-info"></i>
                    </div>
                    <h5 class="card-title">Assignment Report</h5>
                    <p class="card-text text-muted small">
                        View assignment statistics and trends
                    </p>
                    <a href="{{ route('admin.reports.assignments') }}" class="btn btn-info w-100">
                        <i class="fas fa-chart-bar me-2"></i>View Report
                    </a>
                </div>
            </div>
        </div>

        <!-- Revenue Report -->
        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="bg-warning bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                         style="width: 80px; height: 80px;">
                        <i class="fas fa-dollar-sign fa-2x text-warning"></i>
                    </div>
                    <h5 class="card-title">Revenue Report</h5>
                    <p class="card-text text-muted small">
                        View revenue statistics and payment trends
                    </p>
                    <a href="{{ route('admin.reports.revenue') }}" class="btn btn-warning w-100">
                        <i class="fas fa-chart-bar me-2"></i>View Report
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Export Section -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="fas fa-download me-2"></i>Quick Export</h5>
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">Export reports directly without viewing them first</p>
            <form action="{{ route('admin.reports.export') }}" method="POST" class="row g-3">
                @csrf
                <div class="col-md-4">
                    <label for="report_type" class="form-label">Report Type</label>
                    <select class="form-select" id="report_type" name="report_type" required>
                        <option value="">Select Report Type</option>
                        <option value="patients">Patient Report</option>
                        <option value="employees">Employee Report</option>
                        <option value="assignments">Assignment Report</option>
                        <option value="revenue">Revenue Report</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="filters[start_date]">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="filters[end_date]">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-file-export me-2"></i>Export CSV
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Information -->
    <div class="alert alert-info mt-4">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Note:</strong> All reports can be filtered by date range and exported to CSV format for further analysis.
    </div>
</div>
@endsection

