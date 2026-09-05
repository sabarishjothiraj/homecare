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
                                <small class="text-muted">{{ $contract->patient->community ?? '' }} - {{ $contract->patient->location ?? '' }}</small>
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
                                    @foreach($contract->assignments as $assignment)
                                    @php
                                        $line = $assignment->contractItem;
                                        $canReplace = $line ? $line->assignment_count < $line->max_assignments : true;
                                        $remaining = $line ? max(0, $line->max_assignments - $line->assignment_count) : null;
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>{{ $assignment->employee->getField('name', 'N/A') }}</strong>
                                            <br>
                                            <small class="text-muted">{{ $assignment->employee->getField('phone', '') }}</small>
                                        </td>
                                        <td>{{ $assignment->service->name ?? $assignment->contractItem->service->name ?? ($contract->service->name ?? 'N/A') }}</td>
                                        <td>₹{{ number_format($assignment->line_amount ?? $assignment->contractItem->amount ?? $contract->amount, 0) }}</td>
                                        <td>{{ $assignment->start_date->format('M d, Y') }}</td>
                                        <td>{{ $assignment->end_date ? $assignment->end_date->format('M d, Y') : 'Ongoing' }}</td>
                                        <td>
                                            @if($assignment->is_active)
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-secondary">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($assignment->is_emergency_replacement)
                                                <span class="badge bg-info">Temporary</span>
                                                @if($assignment->replacedAssignment)
                                                    <br>
                                                    <small class="text-muted">For: {{ $assignment->replacedAssignment->employee->getField('name', 'N/A') }}</small>
                                                @endif
                                            @else
                                                <span class="badge bg-light text-dark">Primary</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($line)
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
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#assignment-actions-{{ $assignment->id }}">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                    @if($contract->status !== 'closed' && $contract->status !== 'expired')
                                    <tr class="bg-light collapse" id="assignment-actions-{{ $assignment->id }}">
                                        <td colspan="9">
                                            <div class="row g-3 p-2">
                                                <div class="col-lg-4">
                                                    <h6 class="mb-2">Add Note</h6>
                                                    <form action="{{ route('admin.contracts.assignments.notes.store', [$contract, $assignment]) }}" method="POST">
                                                        @csrf
                                                        <textarea name="note" class="form-control form-control-sm mb-2" rows="2" placeholder="Add remark for this employee" required></textarea>
                                                        <button type="submit" class="btn btn-sm btn-outline-primary">Save Note</button>
                                                    </form>
                                                </div>
                                                <div class="col-lg-4">
                                                    <h6 class="mb-2">Replace Employee</h6>
                                                    <form action="{{ route('admin.contracts.assignments.replace', [$contract, $assignment]) }}" method="POST">
                                                        @csrf
                                                        <div class="mb-2">
                                                            <select name="employee_id" class="form-select form-select-sm" required {{ !$canReplace ? 'disabled' : '' }}>
                                                                <option value="">Select replacement</option>
                                                                @foreach($employees as $employee)
                                                                    @php
                                                                        $lineServiceId = $line ? (int) $line->service_id : null;
                                                                        $eligible = !$lineServiceId || $employee->services->pluck('id')->contains($lineServiceId);
                                                                    @endphp
                                                                    @if($eligible)
                                                                        <option value="{{ $employee->id }}">{{ $employee->getField('name', 'Employee #' . $employee->id) }}</option>
                                                                    @endif
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="row g-2 mb-2">
                                                            <div class="col-6">
                                                                <input type="date" name="replacement_start_date" class="form-control form-control-sm" value="{{ now()->format('Y-m-d') }}" required {{ !$canReplace ? 'disabled' : '' }}>
                                                            </div>
                                                            <div class="col-6">
                                                                <input type="date" name="replacement_end_date" class="form-control form-control-sm" placeholder="End date (optional)" {{ !$canReplace ? 'disabled' : '' }}>
                                                            </div>
                                                        </div>
                                                        <div class="form-check mb-2">
                                                            <input type="checkbox" class="form-check-input" id="is_temporary_{{ $assignment->id }}" name="is_temporary" value="1" {{ !$canReplace ? 'disabled' : '' }}>
                                                            <label class="form-check-label small" for="is_temporary_{{ $assignment->id }}">Temporary replacement</label>
                                                        </div>
                                                        <textarea name="notes" class="form-control form-control-sm mb-2" rows="2" placeholder="Replacement note (optional)" {{ !$canReplace ? 'disabled' : '' }}></textarea>
                                                        @if($canReplace)
                                                            <button type="submit" class="btn btn-sm btn-outline-warning">Replace</button>
                                                        @else
                                                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Maximum employee changes reached for this service line.">Replace Limit Reached</button>
                                                        @endif
                                                    </form>
                                                </div>
                                                <div class="col-lg-4">
                                                    <h6 class="mb-2">Remove Assignment</h6>
                                                    <form action="{{ route('admin.contracts.assignments.remove', [$contract, $assignment]) }}" method="POST" onsubmit="return confirm('Remove this employee from contract?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Remove Employee</button>
                                                    </form>
                                                </div>

                                                @if($assignment->assignmentNotes->count() > 0)
                                                    <div class="col-12 mt-2">
                                                        <h6 class="mb-2">Notes</h6>
                                                        <ul class="mb-0 small">
                                                            @foreach($assignment->assignmentNotes as $note)
                                                                <li>
                                                                    {{ $note->note }}
                                                                    <span class="text-muted">- {{ $note->creator->name ?? 'System' }}, {{ $note->created_at->format('M d, Y h:i A') }}</span>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endif
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
                            <div class="text-muted small">Balance</div>
                            <strong class="{{ $balance > 0 ? 'text-danger' : 'text-success' }}">₹{{ number_format($balance, 2) }}</strong>
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
                        
                        @if($contract->status !== 'expired')
                            <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#closeContractModal">
                                <i class="fas fa-times-circle me-2"></i>Close Contract
                            </button>
                        @endif
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
                    <p class="small mb-0"><strong>Location:</strong> {{ $contract->patient->location ?? 'N/A' }}</p>
                    <a href="{{ route('admin.patients.show', $contract->patient) }}" class="btn btn-sm btn-outline-primary w-100 mt-3">
                        View Patient Details
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Close Contract Modal -->
@if($contract->status !== 'closed' && $contract->status !== 'expired')
<div class="modal fade" id="closeContractModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.contracts.close', $contract) }}" method="POST">
                @csrf
                @method('PATCH')
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
                        <textarea class="form-control" id="closure_reason" name="closure_reason" rows="3" required placeholder="Enter reason for closing this contract"></textarea>
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
@endsection

