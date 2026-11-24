@extends('layouts.admin')

@section('title', 'Reports')

@section('content')
@php
    $reports = [
        ['key' => 'contracts', 'title' => 'Contract Report', 'icon' => 'fa-file-contract', 'tone' => 'primary',
         'text' => 'Contract value, collected, balance, payment details, refunds and closures'],
        ['key' => 'revenue', 'title' => 'Revenue Report', 'icon' => 'fa-rupee-sign', 'tone' => 'warning',
         'text' => 'Payments received by method and reference, with refunds and net revenue'],
        ['key' => 'patients', 'title' => 'Patient Report', 'icon' => 'fa-users', 'tone' => 'info',
         'text' => 'Patient details, contact, contracts, balance due and current staff'],
        ['key' => 'employees', 'title' => 'Employee Report', 'icon' => 'fa-user-nurse', 'tone' => 'success',
         'text' => 'Services, availability, assignments and payouts per employee'],
        ['key' => 'assignments', 'title' => 'Assignment Report', 'icon' => 'fa-clipboard-list', 'tone' => 'secondary',
         'text' => 'Who worked where and when, with replacements and temporary covers'],
        ['key' => 'payouts', 'title' => 'Payout Report', 'icon' => 'fa-hand-holding-usd', 'tone' => 'danger',
         'text' => 'Employee payouts by period, paid and pending'],
    ];

    // Only reports whose source data this user may see (see ReportController::REPORTS)
    $reports = array_values(array_filter($reports, fn ($report) => in_array($report['key'], $available ?? [], true)));
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Reports & Analytics</h2>
            <p class="text-muted mb-0">View, filter and export business reports</p>
        </div>
    </div>

    @if (empty($reports))
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted py-5">
                <i class="fas fa-lock fa-3x mb-3 d-block opacity-50"></i>
                <p class="mb-0">Your role does not include access to any report's data. Ask an administrator if you need one.</p>
            </div>
        </div>
    @else
    <div class="row g-3 mb-4">
        @foreach ($reports as $report)
            <div class="col-md-6 col-xl-4">
                <a href="{{ route('admin.reports.'.$report['key']) }}" class="card border-0 shadow-sm h-100 text-decoration-none text-body report-card">
                    <div class="card-body d-flex align-items-start gap-3">
                        <div class="bg-{{ $report['tone'] }} bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 56px; height: 56px;">
                            <i class="fas {{ $report['icon'] }} fa-lg text-{{ $report['tone'] }}"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="mb-1">{{ $report['title'] }}</h5>
                            <p class="text-muted small mb-0">{{ $report['text'] }}</p>
                        </div>
                        <i class="fas fa-chevron-right text-muted mt-2"></i>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="fas fa-download me-2"></i>Quick Export</h5>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">Download a report as CSV without opening it. The date range applies to the report's main date (e.g. payment date, contract start date).</p>
            <form action="{{ route('admin.reports.export') }}" method="POST" class="row g-3">
                @csrf
                <div class="col-md-4">
                    <label for="report_type" class="form-label">Report</label>
                    <select class="form-select" id="report_type" name="report_type" required>
                        <option value="">Select report</option>
                        @foreach ($reports as $report)
                            <option value="{{ $report['key'] }}">{{ $report['title'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="export_start_date" class="form-label">From</label>
                    <input type="date" class="form-control" id="export_start_date" name="filters[start_date]">
                </div>
                <div class="col-md-3">
                    <label for="export_end_date" class="form-label">To</label>
                    <input type="date" class="form-control" id="export_end_date" name="filters[end_date]">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-file-export me-2"></i>Export CSV
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>

@push('styles')
<style>
    .report-card { transition: transform .15s ease, box-shadow .15s ease; }
    .report-card:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.08) !important; }
</style>
@endpush
@endsection
