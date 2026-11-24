@extends('layouts.admin')

@section('title', 'Attendance Register')

@section('content')
@php
    $canManage = auth()->user()->hasPermission('employees.manage');
    $canSeePay = auth()->user()->hasPermission('payouts.view') || auth()->user()->hasPermission('payouts.manage');
    $statuses = \App\Models\Attendance::STATUSES;
    // Days can't be marked ahead, so the range stops at today
    $today = today();
    $bulkEnd = $end->copy()->min($today);
@endphp
<div class="container-fluid">
    @include('admin.attendance._tabs')
    @include('admin.attendance._errors')

    <!-- Month & filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.attendance.register') }}" class="row g-3 align-items-end">
                <div class="col-lg-4">
                    <label class="form-label" for="month">Month</label>
                    <div class="input-group">
                        <a class="btn btn-outline-secondary" href="{{ route('admin.attendance.register', array_merge(request()->except('month'), ['month' => $month->copy()->subMonth()->format('Y-m')])) }}" title="Previous month">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <input type="month" class="form-control" id="month" name="month" value="{{ $month->format('Y-m') }}" onchange="this.form.submit()">
                        <a class="btn btn-outline-secondary" href="{{ route('admin.attendance.register', array_merge(request()->except('month'), ['month' => $month->copy()->addMonth()->format('Y-m')])) }}" title="Next month">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-3">
                    <label class="form-label" for="search">Search</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Name or phone" value="{{ request('search') }}">
                </div>
                <div class="col-lg-3">
                    <label class="form-label" for="placement">Show</label>
                    <select class="form-select" id="placement" name="placement">
                        <option value="">All active employees</option>
                        <option value="placed" @selected(request('placement') === 'placed')>Assigned / Covering only</option>
                        <option value="available" @selected(request('placement') === 'available')>Available only</option>
                    </select>
                </div>
                <div class="col-lg-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Apply</button>
                </div>
            </form>
        </div>
    </div>

    @if($canManage && $employees->isNotEmpty() && $start->lte($today))
        <!-- Bulk mark -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.attendance.bulk') }}" id="bulk-form" class="row g-3 align-items-end">
                    @csrf
                    <input type="hidden" name="return_month" value="{{ $month->format('Y-m') }}">
                    <div class="col-12">
                        <h6 class="mb-0"><i class="fas fa-layer-group me-2"></i>Bulk Mark</h6>
                        <small class="text-muted">Tick employees in the register below, choose a date range and status.</small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold" for="bulk-start">From</label>
                        <input type="date" class="form-control" id="bulk-start" name="start_date" value="{{ $start->toDateString() }}" max="{{ $today->toDateString() }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold" for="bulk-end">To</label>
                        <input type="date" class="form-control" id="bulk-end" name="end_date" value="{{ $bulkEnd->toDateString() }}" max="{{ $today->toDateString() }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold" for="bulk-status">Status</label>
                        <select class="form-select" id="bulk-status" name="status" required>
                            @foreach($statuses as $key => $meta)
                                <option value="{{ $key }}">{{ $meta['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="overwrite" value="1" id="bulk-overwrite">
                            <label class="form-check-label small" for="bulk-overwrite">Overwrite days already marked</label>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100" id="bulk-submit" disabled>
                            <i class="fas fa-check-double me-1"></i>Mark <span id="bulk-count">0</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Register -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0"><i class="fas fa-th me-2"></i>{{ $month->format('F Y') }}</h5>
            <small class="text-muted">
                @foreach($statuses as $meta)
                    <span class="me-2"><span class="register-cell bg-{{ $meta['tone'] }} text-white">{{ $meta['code'] }}</span> {{ $meta['label'] }}</span>
                @endforeach
                <span><span class="register-cell register-empty">·</span> Not marked</span>
            </small>
        </div>
        <div class="card-body p-0">
            @if($employees->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-user-nurse fa-3x mb-3 opacity-25"></i>
                    <p class="mb-0">No employees match these filters.</p>
                </div>
            @else
                <div class="table-responsive register-wrap">
                    <table class="table table-bordered table-sm align-middle mb-0 register-table">
                        <thead class="table-light">
                            <tr>
                                <th class="register-sticky text-nowrap">
                                    @if($canManage)
                                        <input type="checkbox" class="form-check-input me-2" id="register-select-all" title="Select all">
                                    @endif
                                    Employee
                                </th>
                                @foreach($days as $day)
                                    <th class="text-center register-day {{ $day->isSunday() ? 'register-sunday' : '' }} {{ $day->isToday() ? 'register-today' : '' }}">
                                        <a href="{{ route('admin.attendance.index', ['date' => $day->toDateString()]) }}" class="text-decoration-none text-reset" title="Open daily sheet">
                                            <div class="small text-muted">{{ substr($day->format('D'), 0, 2) }}</div>
                                            {{ $day->day }}
                                        </a>
                                    </th>
                                @endforeach
                                @foreach($statuses as $meta)
                                    <th class="text-center text-{{ $meta['tone'] }}" title="{{ $meta['label'] }}">{{ $meta['code'] }}</th>
                                @endforeach
                                <th class="text-center text-warning" title="Not marked"><i class="fas fa-exclamation-triangle"></i></th>
                                <th class="text-center text-nowrap" title="Payable days for payroll">Payable</th>
                                @if($canSeePay)
                                    <th class="text-end text-nowrap">Est. Pay (₹)</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($employees as $employee)
                                @php
                                    $employeeMarks = $marks->get($employee->id, collect());
                                    $total = $totals[$employee->id];
                                @endphp
                                <tr>
                                    <td class="register-sticky text-nowrap">
                                        @if($canManage)
                                            <input type="checkbox" class="form-check-input me-2 js-register-select" name="employee_ids[]" value="{{ $employee->id }}" form="bulk-form">
                                        @endif
                                        <a href="{{ route('admin.employees.show', $employee) }}" class="text-decoration-none fw-semibold">{{ $employee->getField('name', 'Employee #'.$employee->id) }}</a>
                                    </td>
                                    @foreach($days as $day)
                                        @php $mark = $employeeMarks->get($day->toDateString()); @endphp
                                        <td class="text-center p-0 {{ $day->isSunday() ? 'register-sunday' : '' }}">
                                            <a href="{{ route('admin.attendance.index', ['date' => $day->toDateString(), 'search' => $employee->getField('name', '')]) }}"
                                               class="d-block text-decoration-none py-1"
                                               title="{{ $day->format('D, M d') }}: {{ $mark ? $mark->label().($mark->notes ? ' — '.$mark->notes : '') : 'Not marked' }}">
                                                @if($mark)
                                                    <span class="register-cell bg-{{ $statuses[$mark->status]['tone'] ?? 'secondary' }} text-white">{{ $statuses[$mark->status]['code'] ?? '?' }}</span>
                                                @else
                                                    <span class="register-cell register-empty">·</span>
                                                @endif
                                            </a>
                                        </td>
                                    @endforeach
                                    @foreach($statuses as $key => $meta)
                                        <td class="text-center small">{{ $total['summary'][$key] ?: '' }}</td>
                                    @endforeach
                                    <td class="text-center small {{ $total['summary']['unmarked'] ? 'text-warning fw-semibold' : 'text-muted' }}">
                                        {{ $total['salary'] ? ($total['summary']['unmarked'] ?: '') : '' }}
                                    </td>
                                    <td class="text-center small fw-semibold">
                                        {{ $total['salary'] ? rtrim(rtrim(number_format($total['payable_days'], 1), '0'), '.') : '' }}
                                    </td>
                                    @if($canSeePay)
                                        <td class="text-end small text-nowrap">
                                            @if($total['salary'])
                                                {{ number_format($total['gross'], 0) }}
                                            @else
                                                <a href="{{ route('admin.employees.show', $employee) }}#salary" class="text-muted text-decoration-none" title="Set salary">No salary</a>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const boxes = () => Array.from(document.querySelectorAll('.js-register-select'));
    const selectAll = document.getElementById('register-select-all');
    const submit = document.getElementById('bulk-submit');
    const count = document.getElementById('bulk-count');

    function refresh() {
        const n = boxes().filter(b => b.checked).length;
        if (count) count.textContent = n;
        if (submit) submit.disabled = n === 0;
        if (selectAll) selectAll.checked = n > 0 && n === boxes().length;
    }

    selectAll?.addEventListener('change', () => { boxes().forEach(b => b.checked = selectAll.checked); refresh(); });
    document.addEventListener('change', e => { if (e.target.classList.contains('js-register-select')) refresh(); });
});
</script>
@endpush

@push('styles')
<style>
    .register-wrap { max-height: 75vh; }
    .register-table thead th { position: sticky; top: 0; z-index: 2; }
    .register-table .register-sticky { position: sticky; left: 0; z-index: 1; background: #fff; min-width: 190px; }
    .register-table thead .register-sticky { z-index: 3; background: #f8f9fa; }
    .register-table .register-day { min-width: 34px; line-height: 1.1; font-size: .8rem; }
    .register-table .register-sunday { background: #f6f7fb; }
    .register-table .register-today { box-shadow: inset 0 -3px 0 #0d6efd; }
    .register-cell { display: inline-block; width: 22px; height: 22px; line-height: 22px; border-radius: 4px; font-size: .72rem; font-weight: 600; text-align: center; }
    .register-empty { color: #adb5bd; }
</style>
@endpush
@endsection
