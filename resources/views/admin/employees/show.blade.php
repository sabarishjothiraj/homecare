@extends('layouts.admin')

@section('title', 'Employee Details')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">{{ $employee->getField('name', 'Employee #' . $employee->id) }}</h2>
            <p class="text-muted mb-0">Employee Details</p>
        </div>
        <div>
            <a href="{{ route('admin.employees.edit', $employee) }}" class="btn btn-warning">
                <i class="fas fa-edit me-2"></i>Edit
            </a>
            @if($employee->status === 'pending')
                <form action="{{ route('admin.employees.activate', $employee) }}" method="POST" class="d-inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>Activate
                    </button>
                </form>
            @elseif($employee->status === 'active')
                <form action="{{ route('admin.employees.deactivate', $employee) }}" method="POST" class="d-inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-ban me-2"></i>Deactivate
                    </button>
                </form>
            @endif
            <a href="{{ route('admin.employees.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to List
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-3">
        <!-- Employee Information -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-user-nurse me-2"></i>Employee Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach($fields->where('is_document', false) as $field)
                            <div class="col-md-6">
                                <label class="text-muted small">{{ $field->label }}</label>
                                <p class="mb-0">
                                    @if($employee->getField($field->name))
                                        <strong>{{ $employee->getField($field->name) }}</strong>
                                    @else
                                        <span class="text-muted">Not provided</span>
                                    @endif
                                </p>
                            </div>
                        @endforeach

                        <!-- Caste Information -->
                        <div class="col-md-6">
                            <label class="text-muted small">Caste</label>
                            <p class="mb-0">
                                @if($employee->caste)
                                    <strong>{{ $employee->caste->name }}</strong>
                                @else
                                    <span class="text-muted">Not specified</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Subcaste</label>
                            <p class="mb-0">
                                @if($employee->subcaste)
                                    <strong>{{ $employee->subcaste }}</strong>
                                @else
                                    <span class="text-muted">Not specified</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Documents -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Documents</h5>
                </div>
                <div class="card-body">
                    @php $uploadedDocuments = $employee->uploadedDocuments(); @endphp
                    @if(count($uploadedDocuments) > 0 || $employee->documents->count() > 0)
                        <div class="row g-3">
                            {{-- Documents uploaded through the employee form (field_data) --}}
                            @foreach($uploadedDocuments as $index => $document)
                                <div class="col-md-6">
                                    <div class="border rounded p-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">{{ $document['type_name'] ?? 'Document' }}</h6>
                                                <p class="small text-muted mb-0">{{ $document['file_name'] ?? basename((string) ($document['file_path'] ?? '')) }}</p>
                                            </div>
                                            <a href="{{ route('admin.employees.documents.show', [$employee, $index]) }}" target="_blank" class="btn btn-sm btn-primary" title="Open document">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            {{-- Older records kept in the employee_documents table --}}
                            @foreach($employee->documents as $document)
                                <div class="col-md-6">
                                    <div class="border rounded p-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">{{ $document->field->label ?? 'Document' }}</h6>
                                                <p class="small text-muted mb-2">{{ $document->file_name }}</p>
                                                <small class="text-muted">
                                                    {{ number_format($document->file_size / 1024, 2) }} KB
                                                </small>
                                            </div>
                                            <a href="{{ Storage::url($document->file_path) }}" target="_blank" class="btn btn-sm btn-primary">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted mb-0">No documents uploaded</p>
                    @endif
                </div>
            </div>

            <!-- Assignments -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Assignment History</h5>
                    @php $activePlacements = $employee->assignments->where('is_active', true); @endphp
                    @if($activePlacements->isNotEmpty())
                        <span class="badge bg-info text-dark">
                            {{ $activePlacements->contains(fn ($a) => !$a->isCover()) ? 'Currently assigned' : 'Currently covering' }}
                        </span>
                    @elseif($employee->status === 'active')
                        <span class="badge bg-success">Available</span>
                    @endif
                </div>
                <div class="card-body p-0">
                    @if($employee->assignments->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Contract</th>
                                        <th>Service</th>
                                        <th>Period</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($employee->assignments as $assignment)
                                    <tr>
                                        <td>
                                            <strong>{{ $assignment->contract->patient->name ?? 'N/A' }}</strong>
                                            <br><small class="text-muted">{{ $assignment->contract->contract_number ?? '' }}</small>
                                        </td>
                                        <td>{{ $assignment->service->name ?? $assignment->contractItem->service->name ?? 'N/A' }}</td>
                                        <td class="text-nowrap">
                                            {{ $assignment->start_date->format('M d, Y') }}
                                            <br><small class="text-muted">to {{ $assignment->end_date ? $assignment->end_date->format('M d, Y') : 'Ongoing' }}</small>
                                        </td>
                                        <td>
                                            @php $type = $assignment->type(); @endphp
                                            <span class="badge {{ ['primary' => 'bg-light text-dark', 'replacement' => 'bg-primary bg-opacity-75', 'temporary' => 'bg-info', 'cover' => 'bg-warning text-dark'][$type] }}">
                                                {{ $assignment->typeLabel() }}
                                            </span>
                                            @if($type === 'cover' && $assignment->coverFor)
                                                <br><small class="text-muted">For: {{ $assignment->coverFor->employeeName() }}</small>
                                            @elseif(in_array($type, ['replacement', 'temporary']) && $assignment->replacedAssignment)
                                                <br><small class="text-muted">Replaced: {{ $assignment->replacedAssignment->employeeName() }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if($assignment->is_active && $assignment->isCover() && $assignment->start_date->isFuture())
                                                <span class="badge bg-primary">Scheduled</span>
                                            @elseif($assignment->is_active)
                                                <span class="badge bg-success">Active</span>
                                                @if($leave = $assignment->currentCover())
                                                    <br><small class="text-muted"><i class="fas fa-user-clock me-1"></i>On leave until {{ $leave->end_date->format('M d') }}</small>
                                                @endif
                                            @else
                                                <span class="badge bg-secondary">Ended</span>
                                                <br><small class="text-muted">{{ $assignment->endReason() }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.contracts.show', $assignment->contract_id) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-tasks fa-3x text-muted mb-3 opacity-25"></i>
                            <p class="text-muted">No assignments yet</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Payouts (pay amounts: payroll permission only) -->
            @if($canViewPay)
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Payslips</h5>
                    <a href="{{ route('admin.attendance.register', ['search' => $employee->getField('name', '')]) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-calendar-check me-1"></i>Attendance
                    </a>
                </div>
                <div class="card-body p-0">
                    @if($employee->payouts->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Period</th>
                                        <th class="text-center">Payable Days</th>
                                        <th class="text-end">Net Pay</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($employee->payouts->take(6) as $payout)
                                    <tr>
                                        <td>{{ $payout->periodLabel() }}</td>
                                        <td class="text-center">{{ rtrim(rtrim(number_format($payout->payable_days, 1), '0'), '.') ?: '0' }} / {{ $payout->days_in_period ?? '—' }}</td>
                                        <td class="text-end">₹{{ number_format($payout->amount, 2) }}</td>
                                        <td>
                                            <span class="badge bg-{{ ['paid' => 'success', 'pending' => 'warning', 'cancelled' => 'secondary'][$payout->status] ?? 'secondary' }}">{{ ucfirst($payout->status) }}</span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('admin.payouts.show', $payout) }}" class="btn btn-sm btn-outline-primary" title="Open payslip"><i class="fas fa-eye"></i></a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-money-bill-wave fa-3x text-muted mb-3 opacity-25"></i>
                            <p class="text-muted mb-0">No payslips yet</p>
                        </div>
                    @endif
                </div>
            </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="col-md-4">
            <!-- Status Card -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Status</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small">Employee Status</label>
                        <p class="mb-0">
                            @if($employee->status === 'active')
                                <span class="badge bg-success">Active</span>
                            @elseif($employee->status === 'pending')
                                <span class="badge bg-warning">Pending</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </p>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Joined</label>
                        <p class="mb-0">{{ $employee->created_at->format('M d, Y') }}</p>
                    </div>
                    @if($employee->activated_at)
                        <div>
                            <label class="text-muted small">Activated</label>
                            <p class="mb-0">{{ $employee->activated_at->format('M d, Y') }}</p>
                        </div>
                    @endif
                </div>
            </div>

            @if($canViewPay)
                @php
                    $currentSalary = $employee->salaryOn(now());
                    $upcomingSalary = $employee->salaries->first(fn ($s) => $s->effective_from->isFuture());
                    $canManageSalary = auth()->user()->hasPermission('payouts.manage');
                @endphp
                <!-- Salary -->
                <div class="card border-0 shadow-sm mb-3" id="salary">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-rupee-sign me-2"></i>Salary</h6>
                    </div>
                    <div class="card-body">
                        @if($currentSalary)
                            <div class="fs-4 fw-semibold">{{ $currentSalary->rateLabel() }}</div>
                            <div class="small text-muted mb-2">{{ $currentSalary->payTypeLabel() }} · since {{ $currentSalary->effective_from->format('M d, Y') }}</div>
                        @else
                            <div class="alert alert-warning small py-2 mb-3">
                                <i class="fas fa-exclamation-triangle me-1"></i>No salary in force{{ $upcomingSalary ? ' yet' : '' }}. This employee is skipped in payroll.
                            </div>
                        @endif
                        @if($upcomingSalary)
                            <div class="small mb-3"><span class="badge bg-info text-dark">Upcoming</span> {{ $upcomingSalary->rateLabel() }} from {{ $upcomingSalary->effective_from->format('M d, Y') }}</div>
                        @endif

                        @if($canManageSalary)
                            <button class="btn btn-sm btn-outline-primary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#salary-form" aria-expanded="{{ $currentSalary ? 'false' : 'true' }}">
                                <i class="fas fa-{{ $currentSalary ? 'edit' : 'plus' }} me-1"></i>{{ $currentSalary ? 'Revise Salary' : 'Set Salary' }}
                            </button>
                            <div class="collapse {{ $currentSalary && !$errors->hasAny(['pay_type', 'amount', 'effective_from']) ? '' : 'show' }} mt-3" id="salary-form">
                                <form method="POST" action="{{ route('admin.employees.salaries.store', $employee) }}">
                                    @csrf
                                    <div class="mb-2">
                                        <label class="form-label small fw-semibold" for="pay_type">Pay Type</label>
                                        <select class="form-select form-select-sm @error('pay_type') is-invalid @enderror" id="pay_type" name="pay_type" required>
                                            @foreach(\App\Models\EmployeeSalary::PAY_TYPES as $value => $label)
                                                <option value="{{ $value }}" @selected(old('pay_type', $currentSalary?->pay_type ?? 'monthly') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('pay_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-semibold" for="amount">Amount (₹)</label>
                                        <input type="number" class="form-control form-control-sm @error('amount') is-invalid @enderror" id="amount" name="amount" min="1" step="0.01" value="{{ old('amount', $currentSalary?->amount) }}" placeholder="e.g. 24000" required>
                                        @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-semibold" for="effective_from">Effective From</label>
                                        <input type="date" class="form-control form-control-sm @error('effective_from') is-invalid @enderror" id="effective_from" name="effective_from" value="{{ old('effective_from', $currentSalary ? now()->startOfMonth()->addMonth()->toDateString() : ($employee->activated_at ?? now())->copy()->toDateString()) }}" required>
                                        @error('effective_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <small class="text-muted">Earlier periods stay on the old salary.</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold" for="salary_notes">Note <span class="text-muted fw-normal">(optional)</span></label>
                                        <input type="text" class="form-control form-control-sm" id="salary_notes" name="notes" maxlength="500" value="{{ old('notes') }}" placeholder="e.g. Annual increment">
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-save me-1"></i>Save Salary</button>
                                </form>
                            </div>
                        @endif

                        @if($employee->salaries->count() > 0)
                            <hr>
                            <div class="small fw-semibold text-muted mb-2">History</div>
                            <ul class="list-unstyled small mb-0">
                                @foreach($employee->salaries as $salary)
                                    <li class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <strong>{{ $salary->rateLabel() }}</strong>
                                            <div class="text-muted">from {{ $salary->effective_from->format('M d, Y') }}{{ $salary->notes ? ' · '.$salary->notes : '' }}</div>
                                        </div>
                                        @if($canManageSalary)
                                            <form method="POST" action="{{ route('admin.employees.salaries.destroy', [$employee, $salary]) }}" onsubmit="return confirm('Remove this salary revision? Pending payslips will change when recalculated.')">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-link text-danger p-0" title="Remove"><i class="fas fa-times"></i></button>
                                            </form>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

