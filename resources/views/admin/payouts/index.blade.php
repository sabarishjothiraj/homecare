@extends('layouts.admin')

@section('title', 'Payroll')

@section('content')
@php
    $canManage = auth()->user()->hasPermission('payouts.manage');
    $statusTones = ['pending' => 'warning', 'paid' => 'success', 'cancelled' => 'secondary'];
    $attendanceStatuses = \App\Models\Attendance::STATUSES;
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="mb-1">Payroll</h2>
            <p class="text-muted mb-0">Monthly salary calculated from each employee's salary and attendance</p>
        </div>
        <a href="{{ route('admin.attendance.register', ['month' => $month->format('Y-m')]) }}" class="btn btn-outline-secondary">
            <i class="fas fa-th me-2"></i>Attendance Register
        </a>
    </div>

    <!-- Month, filters & generate -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-lg-8">
                    <form method="GET" action="{{ route('admin.payouts.index') }}" class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label" for="month">Month</label>
                            <div class="input-group">
                                <a class="btn btn-outline-secondary" href="{{ route('admin.payouts.index', array_merge(request()->except('month'), ['month' => $month->copy()->subMonth()->format('Y-m')])) }}" title="Previous month"><i class="fas fa-chevron-left"></i></a>
                                <input type="month" class="form-control" id="month" name="month" value="{{ $month->format('Y-m') }}" onchange="this.form.submit()">
                                <a class="btn btn-outline-secondary" href="{{ route('admin.payouts.index', array_merge(request()->except('month'), ['month' => $month->copy()->addMonth()->format('Y-m')])) }}" title="Next month"><i class="fas fa-chevron-right"></i></a>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="status">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All</option>
                                @foreach(['pending' => 'Pending', 'paid' => 'Paid', 'cancelled' => 'Cancelled'] as $value => $label)
                                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="employee_id">Employee</label>
                            <select class="form-select" id="employee_id" name="employee_id" onchange="this.form.submit()">
                                <option value="">All Employees</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected((string) request('employee_id') === (string) $employee->id)>{{ $employee->getField('name', 'Employee #'.$employee->id) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </form>
                </div>
                @if($canManage && $canGenerate)
                    <div class="col-lg-4">
                        {{-- Disabled once submitted: a second click would run a second generate alongside the first --}}
                        <form method="POST" action="{{ route('admin.payouts.generate') }}" onsubmit="if (!confirm('Generate / recalculate payroll for {{ $month->format('F Y') }}? Paid payslips are not changed.')) return false; this.querySelector('button[type=submit]').disabled = true; return true;">
                            @csrf
                            <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-calculator me-2"></i>{{ $payouts->isEmpty() ? 'Generate' : 'Regenerate' }} Payroll for {{ $month->format('M Y') }}
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if($employeesWithoutSalary)
        <div class="alert alert-light border d-flex align-items-center">
            <i class="fas fa-info-circle text-primary me-2"></i>
            <span>{{ $employeesWithoutSalary }} active {{ Str::plural('employee', $employeesWithoutSalary) }} {{ $employeesWithoutSalary === 1 ? 'has' : 'have' }} no salary set and will be skipped. Set salary from the employee's page.</span>
        </div>
    @endif

    <!-- Totals -->
    <div class="row g-3 mb-4">
        @foreach([
            ['label' => 'Net Payroll', 'value' => '₹'.number_format($totals['net'], 0), 'tone' => 'primary', 'icon' => 'fa-wallet'],
            ['label' => 'Paid', 'value' => '₹'.number_format($totals['paid'], 0), 'tone' => 'success', 'icon' => 'fa-check-circle'],
            ['label' => 'Pending', 'value' => '₹'.number_format($totals['pending'], 0), 'tone' => 'warning', 'icon' => 'fa-clock'],
            ['label' => 'Unmarked Attendance Days', 'value' => number_format($totals['unmarked']), 'tone' => $totals['unmarked'] ? 'danger' : 'secondary', 'icon' => 'fa-calendar-times'],
        ] as $card)
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small mb-1">{{ $card['label'] }}</div>
                            <div class="fs-4 fw-semibold text-{{ $card['tone'] }}">{{ $card['value'] }}</div>
                        </div>
                        <div class="bg-{{ $card['tone'] }} bg-opacity-10 text-{{ $card['tone'] }} rounded-circle p-3">
                            <i class="fas {{ $card['icon'] }} fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Payslips -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Payslips — {{ $month->format('F Y') }}</h5>
        </div>
        <div class="card-body p-0">
            @if($payouts->isEmpty())
                <div class="text-center py-5">
                    <i class="fas fa-calculator fa-3x text-muted mb-3 opacity-25"></i>
                    <h5 class="text-muted">No payroll for {{ $month->format('F Y') }} yet</h5>
                    <p class="text-muted mb-0">Mark attendance, then use <strong>Generate Payroll</strong> to create payslips.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Employee</th>
                                <th>Salary</th>
                                <th>Attendance</th>
                                <th class="text-center">Payable Days</th>
                                <th class="text-end">Gross</th>
                                <th class="text-end">Adjustments</th>
                                <th class="text-end">Net Pay</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($payouts as $payout)
                                @php $summary = $payout->attendance_summary ?? []; @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $payout->employee?->getField('name', 'N/A') ?? 'N/A' }}</strong>
                                        <br><small class="text-muted">{{ $payout->employee?->getField('phone', '') }}</small>
                                    </td>
                                    <td class="small text-nowrap">
                                        ₹{{ number_format($payout->rate, 0) }} / {{ $payout->pay_type === 'daily' ? 'day' : 'month' }}
                                    </td>
                                    <td class="small text-nowrap">
                                        @foreach($attendanceStatuses as $key => $meta)
                                            @if(!empty($summary[$key]))
                                                <span class="text-{{ $meta['tone'] }} fw-semibold" title="{{ $meta['label'] }}">{{ $meta['code'] }}{{ $summary[$key] }}</span>
                                            @endif
                                        @endforeach
                                        @if(!empty($summary['unmarked']))
                                            <br><span class="badge bg-warning text-dark"><i class="fas fa-exclamation-triangle me-1"></i>{{ $summary['unmarked'] }} unmarked</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        {{ rtrim(rtrim(number_format($payout->payable_days, 1), '0'), '.') ?: '0' }}
                                        <small class="text-muted">/ {{ $payout->days_in_period }}</small>
                                    </td>
                                    <td class="text-end text-nowrap">₹{{ number_format($payout->gross_amount, 2) }}</td>
                                    <td class="text-end text-nowrap {{ $payout->adjustments_total < 0 ? 'text-danger' : ($payout->adjustments_total > 0 ? 'text-success' : 'text-muted') }}">
                                        {{ $payout->adjustments_total == 0 ? '—' : ($payout->adjustments_total > 0 ? '+' : '−').'₹'.number_format(abs($payout->adjustments_total), 2) }}
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <strong>₹{{ number_format($payout->amount, 2) }}</strong>
                                        @if($payout->shortfall())
                                            <br><span class="badge bg-danger" title="Deductions exceed earnings; this payslip can't be paid until that's fixed">₹{{ number_format($payout->shortfall(), 2) }} short</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $statusTones[$payout->status] ?? 'secondary' }}">{{ ucfirst($payout->status) }}</span>
                                        @if($payout->status === 'paid' && $payout->paid_at)
                                            <br><small class="text-muted">{{ $payout->paid_at->format('M d') }}</small>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.payouts.show', $payout) }}" class="btn btn-sm btn-outline-primary" title="Open payslip">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light fw-semibold">
                            <tr>
                                <td colspan="4">Total ({{ $payouts->where('status', '!=', 'cancelled')->count() }})</td>
                                <td class="text-end">₹{{ number_format($payouts->where('status', '!=', 'cancelled')->sum('gross_amount'), 2) }}</td>
                                <td class="text-end">₹{{ number_format($payouts->where('status', '!=', 'cancelled')->sum('adjustments_total'), 2) }}</td>
                                <td class="text-end">₹{{ number_format($totals['net'], 2) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <p class="text-muted small mt-3 mb-0">
        <i class="fas fa-info-circle me-1"></i>
        <strong>How pay is calculated:</strong> Monthly fixed = salary ÷ days in month × payable days, where Present, Paid Leave and Week Off count as 1 day and Half Day as ½.
        Per day = daily rate × payable days (Week Off unpaid). Absent and unmarked days are not paid.
    </p>
</div>
@endsection
