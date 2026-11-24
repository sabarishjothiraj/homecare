@extends('layouts.admin')

@section('title', 'Attendance')

@section('content')
@php
    $canManage = auth()->user()->hasPermission('employees.manage');
    $statuses = \App\Models\Attendance::STATUSES;
    // Rows that can post a change: not on a paid payslip, and on a future date only a mark to clear
    $editableRows = $canManage
        ? $employees->reject(fn ($employee) => $lockedIds->contains($employee->id))
            ->filter(fn ($employee) => !$isFuture || $marks->has($employee->id))
            ->count()
        : 0;
@endphp
<div class="container-fluid">
    @include('admin.attendance._tabs')
    @include('admin.attendance._errors')

    <!-- Date & filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.attendance.index') }}" class="row g-3 align-items-end">
                <div class="col-lg-4">
                    <label class="form-label" for="date">Date</label>
                    <div class="input-group">
                        <a class="btn btn-outline-secondary" href="{{ route('admin.attendance.index', array_merge(request()->except('date'), ['date' => $date->copy()->subDay()->toDateString()])) }}" title="Previous day">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <input type="date" class="form-control" id="date" name="date" value="{{ $date->toDateString() }}" onchange="this.form.submit()">
                        <a class="btn btn-outline-secondary" href="{{ route('admin.attendance.index', array_merge(request()->except('date'), ['date' => $date->copy()->addDay()->toDateString()])) }}" title="Next day">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                    <small class="text-muted">{{ $date->format('l') }}{{ $date->isToday() ? ' · Today' : '' }}</small>
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

    @if($isFuture)
        <div class="alert alert-light border d-flex align-items-center">
            <i class="fas fa-info-circle text-primary me-2"></i>
            <span>This date is in the future. Unmarked days are unpaid, so attendance can only be marked on or after the day.</span>
        </div>
    @endif

    <!-- Day summary -->
    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach($statuses as $key => $meta)
            <span class="badge rounded-pill bg-{{ $meta['tone'] }} bg-opacity-10 text-{{ $meta['tone'] }} border border-{{ $meta['tone'] }}-subtle px-3 py-2">
                {{ $meta['label'] }}: <strong>{{ $counts[$key] }}</strong>
            </span>
        @endforeach
        <span class="badge rounded-pill {{ $unmarked ? 'bg-warning text-dark' : 'bg-light text-muted border' }} px-3 py-2">
            Not marked: <strong>{{ $unmarked }}</strong>
        </span>
    </div>

    <form method="POST" action="{{ route('admin.attendance.store') }}" id="attendance-sheet">
        @csrf
        <input type="hidden" name="date" value="{{ $date->toDateString() }}">
        <input type="hidden" name="search" value="{{ request('search') }}">
        <input type="hidden" name="placement" value="{{ request('placement') }}">

        <div class="card border-0 shadow-sm">
            @if($editableRows && !$isFuture)
                <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
                    <span class="small text-muted me-1"><span id="selected-count">0</span> selected — mark as:</span>
                    @foreach($statuses as $key => $meta)
                        <button type="button" class="btn btn-sm btn-outline-{{ $meta['tone'] }} js-bulk-mark" data-status="{{ $key }}" disabled>{{ $meta['label'] }}</button>
                    @endforeach
                    <span class="vr mx-1"></span>
                    <button type="button" class="btn btn-sm btn-success" id="mark-unmarked-present">
                        <i class="fas fa-check-double me-1"></i>Mark all unmarked as Present
                    </button>
                </div>
            @endif

            <div class="card-body p-0">
                @if($employees->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-user-nurse fa-3x mb-3 opacity-25"></i>
                        <p class="mb-0">No employees match these filters.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 attendance-sheet">
                            <thead class="table-light">
                                <tr>
                                    @if($canManage)
                                        <th style="width: 40px;"><input type="checkbox" class="form-check-input" id="select-all" title="Select all"></th>
                                    @endif
                                    <th>Employee</th>
                                    <th>Current Placement</th>
                                    <th>Status</th>
                                    <th style="min-width: 180px;">Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($employees as $employee)
                                    @php
                                        $mark = $marks->get($employee->id);
                                        $locked = $lockedIds->contains($employee->id);
                                        $editable = $canManage && !$locked;
                                    @endphp
                                    <tr class="{{ $mark ? '' : 'table-warning bg-opacity-10' }}" data-row>
                                        @if($canManage)
                                            <td>
                                                @if($editable && !$isFuture)
                                                    <input type="checkbox" class="form-check-input js-row-select">
                                                @endif
                                            </td>
                                        @endif
                                        <td>
                                            <strong>{{ $employee->getField('name', 'Employee #'.$employee->id) }}</strong>
                                            <br><small class="text-muted">{{ $employee->getField('phone', '') }}</small>
                                        </td>
                                        <td class="small">
                                            @if($placements->has($employee->id))
                                                <i class="fas fa-user-injured text-muted me-1"></i>{{ $placements[$employee->id] }}
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-nowrap">
                                            @if($editable)
                                                {{-- The mark as shown, so saving this page later can't undo someone else's newer mark --}}
                                                <input type="hidden" name="records[{{ $employee->id }}][version]" value="{{ $mark?->version() }}">
                                            @endif
                                            <div class="btn-group btn-group-sm" role="group" aria-label="Attendance status">
                                                @foreach($statuses as $key => $meta)
                                                    <input type="radio" class="btn-check" name="records[{{ $employee->id }}][status]" id="att-{{ $employee->id }}-{{ $key }}" value="{{ $key }}" @checked($mark?->status === $key) @disabled(!$editable || $isFuture)>
                                                    <label class="btn btn-outline-{{ $meta['tone'] }} fw-semibold" for="att-{{ $employee->id }}-{{ $key }}" title="{{ $meta['label'] }}">{{ $meta['code'] }}</label>
                                                @endforeach
                                                {{-- On a future date a mark can still be cleared, just not set --}}
                                                <input type="radio" class="btn-check" name="records[{{ $employee->id }}][status]" id="att-{{ $employee->id }}-none" value="" @checked(!$mark) @disabled(!$editable || ($isFuture && !$mark))>
                                                <label class="btn btn-outline-light text-muted border" for="att-{{ $employee->id }}-none" title="Not marked">–</label>
                                            </div>
                                            @if($locked)
                                                <span class="badge bg-light text-muted border ms-1" title="This date is on a paid payslip, so its attendance can't change"><i class="fas fa-lock me-1"></i>Paid</span>
                                            @endif
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm" name="records[{{ $employee->id }}][notes]" value="{{ $mark?->notes }}" maxlength="255" placeholder="Optional" @disabled(!$editable || $isFuture)>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            @if($canManage && $employees->isNotEmpty() && !$editableRows && $lockedIds->isNotEmpty())
                <div class="card-footer bg-white small text-muted">
                    <i class="fas fa-lock me-1"></i>Every employee listed is on a paid payslip for this date, so nothing here can be changed.
                </div>
            @endif

            @if($editableRows)
                <div class="card-footer bg-white d-flex justify-content-between align-items-center sticky-bottom border-top">
                    <small class="text-muted">
                        @foreach($statuses as $meta)
                            <span class="me-2"><strong class="text-{{ $meta['tone'] }}">{{ $meta['code'] }}</strong> {{ $meta['label'] }}</span>
                        @endforeach
                        <span><strong>–</strong> Not marked</span>
                    </small>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-save me-1"></i>Save Attendance
                    </button>
                </div>
            @endif
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sheet = document.getElementById('attendance-sheet');
    if (!sheet) return;

    const rows = () => Array.from(sheet.querySelectorAll('[data-row]'));
    const selectableRows = () => rows().filter(row => row.querySelector('.js-row-select'));
    const selectAll = document.getElementById('select-all');
    const bulkButtons = sheet.querySelectorAll('.js-bulk-mark');
    const selectedCount = document.getElementById('selected-count');

    function setStatus(row, status) {
        const radio = row.querySelector(`input[type="radio"][value="${status}"]`);
        if (!radio || radio.disabled) return;
        radio.checked = true;
        row.classList.toggle('table-warning', status === '');
    }

    function refreshSelection() {
        const selected = rows().filter(row => row.querySelector('.js-row-select')?.checked);
        if (selectedCount) selectedCount.textContent = selected.length;
        bulkButtons.forEach(btn => btn.disabled = selected.length === 0);
        if (selectAll) selectAll.checked = selected.length > 0 && selected.length === selectableRows().length;
        return selected;
    }

    selectAll?.addEventListener('change', function () {
        selectableRows().forEach(row => { row.querySelector('.js-row-select').checked = selectAll.checked; });
        refreshSelection();
    });

    sheet.addEventListener('change', function (e) {
        if (e.target.classList.contains('js-row-select')) refreshSelection();
        if (e.target.type === 'radio') e.target.closest('[data-row]')?.classList.toggle('table-warning', e.target.value === '');
    });

    bulkButtons.forEach(btn => btn.addEventListener('click', function () {
        refreshSelection().forEach(row => setStatus(row, btn.dataset.status));
    }));

    document.getElementById('mark-unmarked-present')?.addEventListener('click', function () {
        rows().forEach(row => {
            const none = row.querySelector('input[type="radio"][value=""]');
            if (none?.checked) setStatus(row, 'present');
        });
    });
});
</script>
@endpush

@push('styles')
<style>
    .attendance-sheet .btn-group-sm > .btn { min-width: 2.1rem; }
    .attendance-sheet tr.table-warning > td { --bs-table-bg-state: rgba(255, 193, 7, .08); }
</style>
@endpush
@endsection
