@extends('layouts.admin')

@section('title', 'Contract Details')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">{{ $contract->contract_number }}</h2>
            <p class="text-muted mb-0">Contract Details</p>
        </div>
        <div>
            @if($contract->pdf_path)
                <a href="{{ route('admin.contracts.download', $contract) }}" class="btn btn-success">
                    <i class="fas fa-download me-2"></i>Download PDF
                </a>
                <a href="{{ route('admin.contracts.print-pdf', $contract) }}" class="btn btn-outline-primary" target="_blank">
                    <i class="fas fa-print me-2"></i>Print PDF
                </a>
            @endif
            @if($contract->status !== 'closed')
                <a href="{{ route('admin.contracts.edit', $contract) }}" class="btn btn-warning">
                    <i class="fas fa-edit me-2"></i>Edit
                </a>
            @endif
            <a href="{{ route('admin.contracts.index') }}" class="btn btn-outline-secondary">
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

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-3">
        <!-- Contract Information -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-file-contract me-2"></i>Contract Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Contract Number</label>
                            <p class="mb-0"><strong>{{ $contract->contract_number }}</strong></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Status</label>
                            <p class="mb-0">
                                @if($contract->status === 'active')
                                    <span class="badge bg-success">Active</span>
                                @elseif($contract->status === 'not_assigned')
                                    <span class="badge bg-warning">Not Assigned</span>
                                @elseif($contract->status === 'closing_soon')
                                    <span class="badge bg-warning">Closing Soon</span>
                                @elseif($contract->status === 'expired')
                                    <span class="badge bg-danger">Expired</span>
                                @elseif($contract->status === 'closed')
                                    <span class="badge bg-secondary">Closed</span>
                                @else
                                    <span class="badge bg-secondary">{{ ucfirst($contract->status) }}</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Patient</label>
                            <p class="mb-0">
                                <strong>{{ $contract->patient->name ?? 'N/A' }}</strong><br>
                                <small class="text-muted">{{ $contract->patient->short_address ?? '' }}</small>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Services</label>
                            <p class="mb-0">
                                @if($contract->items->count() > 0)
                                    <strong>{{ $contract->items->pluck('service.name')->filter()->unique()->join(', ') }}</strong>
                                @else
                                    <strong>{{ $contract->service->name ?? 'N/A' }}</strong>
                                @endif
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Start Date</label>
                            <p class="mb-0"><strong>{{ $contract->start_date->format('M d, Y') }}</strong></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">End Date</label>
                            <p class="mb-0">
                                @if($contract->end_date)
                                    <strong>{{ $contract->end_date->format('M d, Y') }}</strong>
                                @else
                                    <span class="badge bg-info">Ongoing</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Duration</label>
                            <p class="mb-0">
                                @if($contract->duration_type === 'custom')
                                    <span class="badge bg-info">Custom</span>
                                @else
                                    <strong>{{ $contract->duration_value }} {{ ucfirst($contract->duration_type) }}</strong>
                                @endif
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Contract Amount</label>
                            <p class="mb-0"><strong class="text-success">₹{{ number_format($contract->amount, 0) }}</strong></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Payment Method</label>
                            <p class="mb-0"><strong>{{ ucfirst(str_replace('_', ' ', $contract->payment_method)) }}</strong></p>
                            @if($contract->payment_details)
                                <small class="text-muted">{{ $contract->payment_details }}</small>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Created</label>
                            <p class="mb-0">{{ $contract->created_at->format('M d, Y h:i A') }}</p>
                        </div>
                        @if($contract->terms)
                            <div class="col-12">
                                <label class="text-muted small">Terms and Conditions</label>
                                <p class="mb-0">{{ $contract->terms }}</p>
                            </div>
                        @endif
                        @if($contract->closure_reason)
                            <div class="col-12">
                                <label class="text-muted small">Closure Reason</label>
                                <p class="mb-0 text-danger">{{ $contract->closure_reason }}</p>
                            </div>
                        @endif
                        @if($contract->refund_amount !== null || $contract->refund_details)
                            <div class="col-md-6">
                                <label class="text-muted small">Refund</label>
                                @if($contract->refund_amount !== null)
                                    <p class="mb-0"><strong>₹{{ number_format($contract->refund_amount, 2) }}</strong></p>
                                @endif
                                @if($contract->refund_details)
                                    <small class="text-muted">{{ $contract->refund_details }}</small>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Employee Assignments -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Employee Assignments</h5>
                </div>
                <div class="card-body p-0">
                    @if($contract->assignments->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Employee</th>
                                        <th>Service</th>
                                        <th>Amount</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                        <th>Type</th>
                                        <th>Limit</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        // Show each temporary cover directly beneath the assignment it covers
                                        $orderedAssignments = collect();
                                        foreach ($contract->assignments->whereNull('cover_for_assignment_id') as $primaryAssignment) {
                                            $orderedAssignments->push($primaryAssignment);
                                            foreach ($contract->assignments->where('cover_for_assignment_id', $primaryAssignment->id)->sortBy('start_date') as $coverAssignment) {
                                                $orderedAssignments->push($coverAssignment);
                                            }
                                        }
                                        foreach ($contract->assignments->whereNotNull('cover_for_assignment_id')->whereNotIn('id', $orderedAssignments->pluck('id')) as $orphanCover) {
                                            $orderedAssignments->push($orphanCover);
                                        }
                                    @endphp
                                    @foreach($orderedAssignments as $assignment)
                                    @php
                                        $line = $assignment->contractItem;
                                        $canReplace = $line ? $line->assignment_count < $line->max_assignments : true;
                                        $remaining = $line ? max(0, $line->max_assignments - $line->assignment_count) : null;
                                        $isCover = $assignment->isCover();
                                        $currentCover = $isCover ? null : $assignment->currentCover();
                                        $upcomingCovers = $isCover ? collect() : $assignment->upcomingCovers();
                                        $coveringEmployeeIds = $isCover ? [] : $assignment->covers->where('is_active', true)->pluck('employee_id')->all();

                                        // Free employees qualified for this line; the current cover may also be made permanent
                                        $lineServiceId = $line ? (int) $line->service_id : null;
                                        $availableEmployees = $employees->filter(fn ($employee) =>
                                            (int) $employee->id !== (int) $assignment->employee_id
                                            && (!$lineServiceId || $employee->services->pluck('id')->contains($lineServiceId))
                                        );
                                        $coverOptions = $availableEmployees->reject(fn ($employee) => in_array($employee->id, $assignedEmployeeIds));
                                        $replacementOptions = $availableEmployees->filter(fn ($employee) =>
                                            !in_array($employee->id, $assignedEmployeeIds) || in_array($employee->id, $coveringEmployeeIds)
                                        );
                                    @endphp
                                    <tr class="{{ $isCover ? 'table-warning bg-opacity-25' : '' }}">
                                        <td>
                                            @if($isCover)
                                                <i class="fas fa-level-up-alt fa-rotate-90 text-muted me-1"></i>
                                            @endif
                                            <strong>{{ $assignment->employee->getField('name', 'N/A') }}</strong>
                                            <br>
                                            <small class="text-muted">{{ $assignment->employee->getField('phone', '') }}</small>
                                        </td>
                                        <td>{{ $assignment->service->name ?? $assignment->contractItem->service->name ?? ($contract->service->name ?? 'N/A') }}</td>
                                        <td>
                                            @if($isCover)
                                                <span class="text-muted">—</span>
                                            @else
                                                ₹{{ number_format($assignment->line_amount ?? $assignment->contractItem->amount ?? $contract->amount, 0) }}
                                            @endif
                                        </td>
                                        <td>{{ $assignment->start_date->format('M d, Y') }}</td>
                                        <td>{{ $assignment->end_date ? $assignment->end_date->format('M d, Y') : 'Ongoing' }}</td>
                                        <td>
                                            @if($isCover)
                                                @if(!$assignment->is_active)
                                                    <span class="badge bg-secondary">Ended</span>
                                                @elseif($assignment->start_date->isFuture())
                                                    <span class="badge bg-primary">Scheduled</span>
                                                @else
                                                    <span class="badge bg-warning text-dark">Covering</span>
                                                @endif
                                            @else
                                                @if($assignment->is_active)
                                                    <span class="badge bg-success">Active</span>
                                                @else
                                                    @php $successor = $assignment->replacedBy(); @endphp
                                                    <span class="badge bg-secondary">{{ $successor ? 'Replaced' : 'Inactive' }}</span>
                                                    <br><small class="text-muted">{{ $assignment->endReason() }}</small>
                                                    @if($successor?->notes)
                                                        <br><small class="text-muted fst-italic" title="Replacement note">“{{ Str::limit($successor->notes, 60) }}”</small>
                                                    @endif
                                                @endif
                                                @if($currentCover)
                                                    <br><span class="badge bg-warning text-dark mt-1">On Leave</span>
                                                    <br><small class="text-muted">until {{ $currentCover->end_date->format('M d') }}</small>
                                                @endif
                                                @foreach($upcomingCovers as $upcoming)
                                                    <br><small class="text-muted"><i class="fas fa-calendar-alt me-1"></i>Leave {{ $upcoming->start_date->format('M d') }}–{{ $upcoming->end_date->format('M d') }}</small>
                                                @endforeach
                                            @endif
                                        </td>
                                        <td>
                                            @if($isCover)
                                                <span class="badge bg-warning text-dark">Temporary Cover</span>
                                                @if($assignment->coverFor)
                                                    <br>
                                                    <small class="text-muted">For: {{ $assignment->coverFor->employee->getField('name', 'N/A') }}</small>
                                                @endif
                                            @elseif($assignment->is_emergency_replacement)
                                                <span class="badge bg-info">Temporary</span>
                                                @if($assignment->replacedAssignment)
                                                    <br>
                                                    <small class="text-muted">For: {{ $assignment->replacedAssignment->employee->getField('name', 'N/A') }}</small>
                                                @endif
                                            @elseif($assignment->replaced_assignment_id)
                                                <span class="badge bg-primary bg-opacity-75">Replacement</span>
                                                @if($assignment->replacedAssignment)
                                                    <br>
                                                    <small class="text-muted">Replaced: {{ $assignment->replacedAssignment->employeeName() }}</small>
                                                @endif
                                            @else
                                                <span class="badge bg-light text-dark">Primary</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($isCover)
                                                <small class="text-muted" title="Temporary covers don't use up the line's change limit.">Not counted</small>
                                            @elseif($line)
                                                <span class="badge {{ $canReplace ? 'bg-info' : 'bg-secondary' }}" title="Initial assignment is included in this count.">
                                                    {{ $line->assignment_count }}/{{ $line->max_assignments }}
                                                </span>
                                                <br>
                                                <small class="text-muted">Remaining: {{ $remaining }}</small>
                                            @else
                                                <span class="text-muted">N/A</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($contract->status !== 'closed' && $contract->status !== 'expired')
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#assignment-actions-{{ $assignment->id }}" aria-expanded="false" aria-controls="assignment-actions-{{ $assignment->id }}" title="Manage assignment">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                    @if($contract->status !== 'closed' && $contract->status !== 'expired')
                                    <tr class="collapse assignment-panel" id="assignment-actions-{{ $assignment->id }}">
                                        <td colspan="9" class="p-3">
                                            @php
                                                $panelId = 'assignment-' . $assignment->id;
                                                $employeeName = $assignment->employee->getField('name', 'This employee');
                                                $lineServiceName = $line->service->name ?? $assignment->service->name ?? 'this service';
                                                $noteCount = $assignment->assignmentNotes->count();
                                            @endphp
                                            <div class="card border shadow-sm">
                                                <div class="card-header bg-white">
                                                    <ul class="nav nav-tabs card-header-tabs" role="tablist">
                                                        <li class="nav-item" role="presentation">
                                                            <button class="nav-link active text-nowrap" data-bs-toggle="tab" data-bs-target="#{{ $panelId }}-notes" type="button" role="tab">
                                                                <i class="fas fa-sticky-note me-1"></i>Notes
                                                                @if($noteCount)
                                                                    <span class="badge rounded-pill bg-secondary ms-1">{{ $noteCount }}</span>
                                                                @endif
                                                            </button>
                                                        </li>
                                                        @if($isCover)
                                                            @if($assignment->is_active)
                                                                <li class="nav-item" role="presentation">
                                                                    <button class="nav-link text-nowrap" data-bs-toggle="tab" data-bs-target="#{{ $panelId }}-end-cover" type="button" role="tab">
                                                                        <i class="fas fa-stop-circle me-1"></i>{{ $assignment->start_date->isFuture() ? 'Cancel Cover' : 'End Cover' }}
                                                                    </button>
                                                                </li>
                                                            @endif
                                                        @elseif($assignment->is_active)
                                                            {{-- Only the line's current employee can be replaced or covered --}}
                                                            <li class="nav-item" role="presentation">
                                                                <button class="nav-link text-nowrap" data-bs-toggle="tab" data-bs-target="#{{ $panelId }}-replace" type="button" role="tab">
                                                                    <i class="fas fa-exchange-alt me-1"></i>Replace
                                                                </button>
                                                            </li>
                                                            <li class="nav-item" role="presentation">
                                                                <button class="nav-link text-nowrap" data-bs-toggle="tab" data-bs-target="#{{ $panelId }}-cover" type="button" role="tab">
                                                                    <i class="fas fa-user-clock me-1"></i>Temporary Cover
                                                                </button>
                                                            </li>
                                                        @endif
                                                        <li class="nav-item ms-auto" role="presentation">
                                                            <button class="nav-link text-danger text-nowrap" data-bs-toggle="tab" data-bs-target="#{{ $panelId }}-remove" type="button" role="tab">
                                                                <i class="fas fa-user-minus me-1"></i>Remove
                                                            </button>
                                                        </li>
                                                    </ul>
                                                </div>

                                                <div class="card-body tab-content">
                                                    {{-- Notes --}}
                                                    <div class="tab-pane fade show active" id="{{ $panelId }}-notes" role="tabpanel">
                                                        <div class="assignment-panel-form">
                                                            @if($noteCount)
                                                                <ul class="list-unstyled small mb-3">
                                                                    @foreach($assignment->assignmentNotes as $note)
                                                                        <li class="border-start border-3 ps-2 mb-2">
                                                                            <div>{{ $note->note }}</div>
                                                                            <div class="text-muted">{{ $note->creator->name ?? 'System' }} · {{ $note->created_at->format('M d, Y h:i A') }}</div>
                                                                        </li>
                                                                    @endforeach
                                                                </ul>
                                                            @else
                                                                <p class="small text-muted mb-3">No notes yet for {{ $employeeName }}.</p>
                                                            @endif
                                                            <form action="{{ route('admin.contracts.assignments.notes.store', [$contract, $assignment]) }}" method="POST">
                                                                @csrf
                                                                <label class="form-label small fw-semibold" for="{{ $panelId }}-note">Add note</label>
                                                                <textarea id="{{ $panelId }}-note" name="note" class="form-control form-control-sm mb-2" rows="2" placeholder="Remark for this employee" required></textarea>
                                                                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-save me-1"></i>Save Note</button>
                                                            </form>
                                                        </div>
                                                    </div>

                                                    @if($isCover)
                                                        {{-- End / cancel cover --}}
                                                        @if($assignment->is_active)
                                                            @php $originalName = $assignment->coverFor?->employee->getField('name', 'The original employee') ?? 'The original employee'; @endphp
                                                            <div class="tab-pane fade" id="{{ $panelId }}-end-cover" role="tabpanel">
                                                                <div class="assignment-panel-form">
                                                                    <p class="small mb-3">
                                                                        @if($assignment->start_date->isFuture())
                                                                            This cover is scheduled for <strong>{{ $assignment->start_date->format('M d') }} – {{ $assignment->end_date->format('M d, Y') }}</strong>.
                                                                            Cancelling it keeps {{ $originalName }} on the contract as normal.
                                                                        @else
                                                                            This cover runs until <strong>{{ $assignment->end_date->format('M d, Y') }}</strong> and ends automatically after that.
                                                                            End it now if {{ $originalName }} is back early.
                                                                        @endif
                                                                    </p>
                                                                    <form action="{{ route('admin.contracts.assignments.cover.end', [$contract, $assignment]) }}" method="POST" onsubmit="return confirm('{{ $assignment->start_date->isFuture() ? 'Cancel this scheduled cover?' : 'End this cover today?' }}')">
                                                                        @csrf
                                                                        <button type="submit" class="btn btn-sm btn-warning">
                                                                            <i class="fas fa-stop-circle me-1"></i>{{ $assignment->start_date->isFuture() ? 'Cancel Cover' : 'End Cover Today' }}
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    @elseif($assignment->is_active)
                                                        {{-- Replace (permanent) --}}
                                                        <div class="tab-pane fade" id="{{ $panelId }}-replace" role="tabpanel">
                                                            <div class="assignment-panel-form">
                                                                <p class="small text-muted mb-3">
                                                                    Permanently hands this service line to another employee. {{ $employeeName }}'s assignment ends on the start date.
                                                                    @if($line)
                                                                        Uses 1 change ({{ $line->assignment_count }} of {{ $line->max_assignments }} used).
                                                                    @endif
                                                                </p>
                                                                @if(!$canReplace)
                                                                    <div class="alert alert-secondary small mb-0">
                                                                        <i class="fas fa-ban me-1"></i>The change limit for this service line has been reached. Use <strong>Temporary Cover</strong> for short absences.
                                                                    </div>
                                                                @elseif($replacementOptions->isEmpty())
                                                                    <div class="alert alert-light border small mb-0">
                                                                        <i class="fas fa-info-circle me-1"></i>No employees available. Only active employees qualified for <strong>{{ $lineServiceName }}</strong> who aren't already placed on a contract can be selected.
                                                                    </div>
                                                                @else
                                                                    <form action="{{ route('admin.contracts.assignments.replace', [$contract, $assignment]) }}" method="POST">
                                                                        @csrf
                                                                        <div class="mb-3">
                                                                            <label class="form-label small fw-semibold" for="{{ $panelId }}-replace-employee">New employee <span class="text-danger">*</span></label>
                                                                            <select id="{{ $panelId }}-replace-employee" name="employee_id" class="form-select form-select-sm" required>
                                                                                <option value="">Select employee</option>
                                                                                @foreach($replacementOptions as $employee)
                                                                                    <option value="{{ $employee->id }}">
                                                                                        {{ $employee->getField('name', 'Employee #' . $employee->id) }}{{ in_array($employee->id, $coveringEmployeeIds) ? ' (current cover)' : '' }}
                                                                                    </option>
                                                                                @endforeach
                                                                            </select>
                                                                        </div>
                                                                        <div class="row g-2 mb-3">
                                                                            <div class="col-sm-6">
                                                                                <label class="form-label small fw-semibold" for="{{ $panelId }}-replace-start">Start date <span class="text-danger">*</span></label>
                                                                                <input type="date" id="{{ $panelId }}-replace-start" name="replacement_start_date" class="form-control form-control-sm" value="{{ now()->format('Y-m-d') }}" required>
                                                                            </div>
                                                                            <div class="col-sm-6">
                                                                                <label class="form-label small fw-semibold" for="{{ $panelId }}-replace-end">End date <span class="text-muted fw-normal">(optional)</span></label>
                                                                                <input type="date" id="{{ $panelId }}-replace-end" name="replacement_end_date" class="form-control form-control-sm">
                                                                            </div>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label small fw-semibold" for="{{ $panelId }}-replace-notes">Note <span class="text-muted fw-normal">(optional)</span></label>
                                                                            <textarea id="{{ $panelId }}-replace-notes" name="notes" class="form-control form-control-sm" rows="2" placeholder="Reason for replacement"></textarea>
                                                                        </div>
                                                                        <button type="submit" class="btn btn-sm btn-warning"><i class="fas fa-exchange-alt me-1"></i>Replace Employee</button>
                                                                    </form>
                                                                @endif
                                                            </div>
                                                        </div>

                                                        {{-- Temporary cover (leave) --}}
                                                        @php
                                                            $coverMinDate = max(now()->format('Y-m-d'), $assignment->start_date->format('Y-m-d'));
                                                            $coverMaxDate = $contract->end_date?->format('Y-m-d');
                                                        @endphp
                                                        <div class="tab-pane fade" id="{{ $panelId }}-cover" role="tabpanel">
                                                            <div class="assignment-panel-form">
                                                                <p class="small text-muted mb-3">
                                                                    For short leave. {{ $employeeName }} stays on the contract and resumes automatically after the last day. Doesn't use up the change limit.
                                                                </p>
                                                                @if($coverOptions->isEmpty())
                                                                    <div class="alert alert-light border small mb-0">
                                                                        <i class="fas fa-info-circle me-1"></i>No employees available. Only active employees qualified for <strong>{{ $lineServiceName }}</strong> who aren't already placed on a contract can cover.
                                                                    </div>
                                                                @else
                                                                    <form action="{{ route('admin.contracts.assignments.cover', [$contract, $assignment]) }}" method="POST">
                                                                        @csrf
                                                                        <div class="mb-3">
                                                                            <label class="form-label small fw-semibold" for="{{ $panelId }}-cover-employee">Cover employee <span class="text-danger">*</span></label>
                                                                            <select id="{{ $panelId }}-cover-employee" name="employee_id" class="form-select form-select-sm" required>
                                                                                <option value="">Select employee</option>
                                                                                @foreach($coverOptions as $employee)
                                                                                    <option value="{{ $employee->id }}">{{ $employee->getField('name', 'Employee #' . $employee->id) }}</option>
                                                                                @endforeach
                                                                            </select>
                                                                        </div>
                                                                        <div class="row g-2 mb-3">
                                                                            <div class="col-sm-6">
                                                                                <label class="form-label small fw-semibold" for="{{ $panelId }}-cover-start">Leave from <span class="text-danger">*</span></label>
                                                                                <input type="date" id="{{ $panelId }}-cover-start" name="cover_start_date" class="form-control form-control-sm" value="{{ $coverMinDate }}" min="{{ $coverMinDate }}" @if($coverMaxDate) max="{{ $coverMaxDate }}" @endif required>
                                                                            </div>
                                                                            <div class="col-sm-6">
                                                                                <label class="form-label small fw-semibold" for="{{ $panelId }}-cover-end">Leave until (last day) <span class="text-danger">*</span></label>
                                                                                <input type="date" id="{{ $panelId }}-cover-end" name="cover_end_date" class="form-control form-control-sm" min="{{ $coverMinDate }}" @if($coverMaxDate) max="{{ $coverMaxDate }}" @endif required autocomplete="off">
                                                                            </div>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label small fw-semibold" for="{{ $panelId }}-cover-notes">Leave reason <span class="text-muted fw-normal">(optional)</span></label>
                                                                            <textarea id="{{ $panelId }}-cover-notes" name="notes" class="form-control form-control-sm" rows="2" placeholder="e.g. Family function, sick leave"></textarea>
                                                                        </div>
                                                                        <button type="submit" class="btn btn-sm btn-info text-white"><i class="fas fa-user-clock me-1"></i>Assign Cover</button>
                                                                    </form>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endif

                                                    {{-- Remove --}}
                                                    <div class="tab-pane fade" id="{{ $panelId }}-remove" role="tabpanel">
                                                        <div class="assignment-panel-form">
                                                            <p class="small mb-3">
                                                                Removes {{ $employeeName }} from this contract{{ !$isCover && $assignment->covers->where('is_active', true)->isNotEmpty() ? ' and ends their temporary cover' : '' }}.
                                                                To swap in someone else, use <strong>{{ $isCover ? 'End Cover' : 'Replace' }}</strong> instead.
                                                            </p>
                                                            <form action="{{ route('admin.contracts.assignments.remove', [$contract, $assignment]) }}" method="POST" onsubmit="return confirm('Remove this employee from contract?')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-user-minus me-1"></i>Remove Employee</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-tasks fa-3x text-muted mb-3 opacity-25"></i>
                            <p class="text-muted">No employees assigned yet</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Payments -->
            @php
                $paid = $contract->paidAmount();
                $balance = $contract->balanceDue();
            @endphp
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Payments</h5>
                    @if($contract->status !== 'closed')
                        <a href="{{ route('admin.contracts.payment', $contract) }}" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus me-1"></i>Record Payment
                        </a>
                    @endif
                </div>
                <div class="card-body">
                    <div class="row text-center mb-3">
                        <div class="col-4">
                            <div class="text-muted small">Total</div>
                            <strong>₹{{ number_format($contract->amount, 2) }}</strong>
                        </div>
                        <div class="col-4">
                            <div class="text-muted small">Paid</div>
                            <strong class="text-success">₹{{ number_format($paid, 2) }}</strong>
                        </div>
                        <div class="col-4">
                            @if($contract->status === 'closed')
                                {{-- Nothing more is collected once a contract is closed --}}
                                <div class="text-muted small">Uncollected (closed)</div>
                                <strong class="text-muted">₹{{ number_format($balance, 2) }}</strong>
                            @else
                                <div class="text-muted small">Balance</div>
                                <strong class="{{ $balance > 0 ? 'text-danger' : 'text-success' }}">₹{{ number_format($balance, 2) }}</strong>
                            @endif
                        </div>
                    </div>
                    @if($contract->payments->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Reference</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($contract->payments as $payment)
                                    <tr>
                                        <td>{{ optional($payment->paid_at ?? $payment->created_at)->format('M d, Y') }}</td>
                                        <td><strong>₹{{ number_format($payment->amount, 2) }}</strong></td>
                                        <td>{{ ucfirst(str_replace('_', ' ', $payment->method)) }}</td>
                                        <td>{{ $payment->reference ?? '—' }}</td>
                                        <td>
                                            @if($payment->status === 'completed')
                                                <span class="badge bg-success">Completed</span>
                                            @elseif($payment->status === 'pending')
                                                <span class="badge bg-warning">Pending</span>
                                            @elseif($payment->status === 'failed')
                                                <span class="badge bg-danger">Failed</span>
                                            @else
                                                <span class="badge bg-secondary">{{ ucfirst($payment->status) }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-money-bill-wave fa-3x text-muted mb-3 opacity-25"></i>
                            <p class="text-muted mb-0">No payments recorded</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-md-4">
            <!-- Actions Card -->
            @if($contract->status !== 'closed')
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-cog me-2"></i>Actions</h6>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.contracts.regenerate-pdf', $contract) }}" method="POST" class="mb-2">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary w-100">
                                <i class="fas fa-sync me-2"></i>Regenerate PDF
                            </button>
                        </form>
                        
                        {{-- Expired contracts are closed from here too, to record the outcome and any refund --}}
                        <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#closeContractModal">
                            <i class="fas fa-times-circle me-2"></i>Close Contract
                        </button>
                    </div>
                </div>
            @endif

            <!-- Patient Info Card -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-user me-2"></i>Patient Information</h6>
                </div>
                <div class="card-body">
                    <p class="small mb-2"><strong>Name:</strong> {{ $contract->patient->name ?? 'N/A' }}</p>
                    <p class="small mb-2"><strong>Phone:</strong> {{ $contract->patient->phone ?? 'N/A' }}</p>
                    <p class="small mb-2"><strong>Email:</strong> {{ $contract->patient->email ?? 'N/A' }}</p>
                    <p class="small mb-0"><strong>Address:</strong> {{ $contract->patient?->full_address ?: 'N/A' }}</p>
                    @if($contract->patient && ! $contract->patient->trashed())
                        <a href="{{ route('admin.patients.show', $contract->patient) }}" class="btn btn-sm btn-outline-primary w-100 mt-3">
                            View Patient Details
                        </a>
                    @else
                        <p class="small text-muted mt-3 mb-0"><i class="fas fa-info-circle me-1"></i>The patient record has been deleted.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Close Contract Modal -->
@if($contract->status !== 'closed')
<div class="modal fade" id="closeContractModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            {{-- Plain POST: the close route accepts nothing else --}}
            <form action="{{ route('admin.contracts.close', $contract) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Close Contract</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This action will close the contract and end all active assignments.
                    </div>
                    <div class="mb-3">
                        <label for="closure_reason" class="form-label">Closure Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="closure_reason" name="closure_reason" rows="3" required placeholder="Enter reason for closing this contract">{{ old('closure_reason') }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label for="refund_amount" class="form-label">Refund Amount (₹) <span class="text-muted small">(Optional)</span></label>
                        <input type="number" class="form-control" id="refund_amount" name="refund_amount" min="0" max="{{ $paid }}" step="0.01" placeholder="0.00" value="{{ old('refund_amount') }}">
                        <small class="text-muted">Up to ₹{{ number_format($paid, 2) }} collected on this contract. Closing is final and cannot be redone.</small>
                    </div>
                    <div class="mb-3">
                        <label for="refund_details" class="form-label">Refund Details <span class="text-muted small">(Optional)</span></label>
                        <textarea class="form-control" id="refund_details" name="refund_details" rows="2" maxlength="1000" placeholder="Refund mode, transaction / cheque no., date">{{ old('refund_details') }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Close Contract</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@push('styles')
<style>
    /* Expanded manage-assignment row: flat background, no table hover flicker */
    .assignment-panel > td {
        background-color: #f4f6f9 !important;
        box-shadow: none !important;
    }
    .assignment-panel .nav-tabs .nav-link {
        font-size: .875rem;
        padding: .5rem .85rem;
        color: #495057;
    }
    .assignment-panel .nav-tabs .nav-link.active {
        font-weight: 600;
    }
    .assignment-panel .nav-tabs .nav-link.text-danger.active {
        color: #dc3545 !important;
    }
    .assignment-panel-form {
        max-width: 560px;
    }
</style>
@endpush
@endsection
