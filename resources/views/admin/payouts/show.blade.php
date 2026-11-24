@extends('layouts.admin')

@section('title', 'Payslip #'.$payout->id)

@section('content')
@php
    $canManage = auth()->user()->hasPermission('payouts.manage');
    $editable = $canManage && $payout->isEditable();
    $employee = $payout->employee;
    $summary = $payout->attendance_summary ?? [];
    $statuses = \App\Models\Attendance::STATUSES;
    $weights = \App\Services\PayrollService::PAYABLE_WEIGHTS[$payout->pay_type ?? 'monthly'] ?? \App\Services\PayrollService::PAYABLE_WEIGHTS['monthly'];
    $statusTones = ['pending' => 'warning', 'paid' => 'success', 'cancelled' => 'secondary'];
    $earnings = $payout->items->where('type', 'earning');
    $deductions = $payout->items->where('type', 'deduction');
    $days = fn ($value) => rtrim(rtrim(number_format((float) $value, 1), '0'), '.') ?: '0';
@endphp
<div class="container-fluid">
    @if($errors->any())
        <div class="alert alert-danger d-print-none">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 d-print-none">
        <div>
            <h2 class="mb-1">Payslip — {{ $payout->periodLabel() }}</h2>
            <p class="text-muted mb-0">{{ $employee?->getField('name', 'Employee') }} · #{{ $payout->id }}</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print me-2"></i>Print</button>
            @if($editable)
                <form method="POST" action="{{ route('admin.payouts.recalculate', $payout) }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary" title="Re-read salary and attendance"><i class="fas fa-sync-alt me-2"></i>Recalculate</button>
                </form>
            @endif
            <a href="{{ route('admin.payouts.index', ['month' => $payout->period_start?->format('Y-m')]) }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Payroll
            </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm payslip">
                <div class="card-body p-4">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 border-bottom pb-3 mb-3">
                        <div>
                            <div class="text-muted small text-uppercase">Payslip</div>
                            <h4 class="mb-1">{{ $employee?->getField('name', 'Employee') }}</h4>
                            <div class="small text-muted">
                                {{ $employee?->getField('phone', '') }}
                                @if($employee?->services?->isNotEmpty())
                                    · {{ $employee->services->pluck('name')->join(', ') }}
                                @endif
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="fw-semibold">{{ $payout->periodLabel() }}</div>
                            <div class="small text-muted">{{ $payout->period_start?->format('M d') }} – {{ $payout->period_end?->format('M d, Y') }}</div>
                            <span class="badge bg-{{ $statusTones[$payout->status] ?? 'secondary' }} mt-1">{{ ucfirst($payout->status) }}</span>
                        </div>
                    </div>

                    @if($payout->isEditable() && $payout->shortfall())
                        <div class="alert alert-danger d-flex align-items-center d-print-none">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <div>
                                <strong>Deductions exceed earnings by ₹{{ number_format($payout->shortfall(), 2) }}.</strong>
                                Net pay can't go below ₹0, so that part would never be recovered. Reduce or remove a deduction before paying.
                            </div>
                        </div>
                    @endif

                    @if(!empty($summary['unmarked']) && $payout->isEditable())
                        <div class="alert alert-warning d-flex align-items-center d-print-none">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <div>
                                <strong>{{ $summary['unmarked'] }} {{ Str::plural('day', $summary['unmarked']) }} not marked</strong> and not paid.
                                <a href="{{ route('admin.attendance.register', ['month' => $payout->period_start?->format('Y-m'), 'search' => $employee?->getField('name', '')]) }}">Mark attendance</a>, then Recalculate.
                            </div>
                        </div>
                    @endif

                    <!-- Attendance -->
                    <h6 class="text-uppercase text-muted small mb-2">Attendance</h6>
                    <div class="row g-2 mb-4">
                        @foreach($statuses as $key => $meta)
                            <div class="col-4 col-md">
                                <div class="border rounded text-center py-2 h-100">
                                    <div class="fs-5 fw-semibold text-{{ $meta['tone'] }}">{{ $summary[$key] ?? 0 }}</div>
                                    <div class="small text-muted">{{ $meta['label'] }}</div>
                                    <div class="small text-muted opacity-75">{{ $weights[$key] == 1 ? 'paid' : ($weights[$key] == 0 ? 'unpaid' : '½ paid') }}</div>
                                </div>
                            </div>
                        @endforeach
                        <div class="col-4 col-md">
                            <div class="border rounded text-center py-2 h-100 {{ !empty($summary['unmarked']) ? 'border-warning' : '' }}">
                                <div class="fs-5 fw-semibold {{ !empty($summary['unmarked']) ? 'text-warning' : 'text-muted' }}">{{ $summary['unmarked'] ?? 0 }}</div>
                                <div class="small text-muted">Not marked</div>
                                <div class="small text-muted opacity-75">unpaid</div>
                            </div>
                        </div>
                    </div>

                    <!-- Calculation -->
                    <h6 class="text-uppercase text-muted small mb-2">Earnings & Deductions</h6>
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            <tr>
                                <td>
                                    <strong>Basic salary earned</strong>
                                    <div class="small text-muted">
                                        @if($payout->pay_type === 'daily')
                                            {{ $days($payout->payable_days) }} payable days × ₹{{ number_format($payout->rate, 2) }} per day
                                        @else
                                            ₹{{ number_format($payout->rate, 0) }} / month ÷ {{ $payout->days_in_period }} days × {{ $days($payout->payable_days) }} payable days
                                        @endif
                                        @if($salaryChanges->isNotEmpty())
                                            <br><i class="fas fa-info-circle me-1"></i>Salary changed this period — each day is paid at the rate in force that day.
                                        @endif
                                    </div>
                                </td>
                                <td class="text-end text-nowrap fw-semibold">₹{{ number_format($payout->gross_amount, 2) }}</td>
                                @if($editable)<td style="width: 40px;"></td>@endif
                            </tr>
                            @foreach($earnings as $item)
                                <tr>
                                    <td><i class="fas fa-plus-circle text-success me-1"></i>{{ $item->label }}</td>
                                    <td class="text-end text-nowrap text-success">+₹{{ number_format($item->amount, 2) }}</td>
                                    @if($editable)
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('admin.payouts.items.destroy', [$payout, $item]) }}" onsubmit="return confirm('Remove this line?')">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-link text-danger p-0" title="Remove"><i class="fas fa-times"></i></button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                            @foreach($deductions as $item)
                                <tr>
                                    <td><i class="fas fa-minus-circle text-danger me-1"></i>{{ $item->label }}</td>
                                    <td class="text-end text-nowrap text-danger">−₹{{ number_format($item->amount, 2) }}</td>
                                    @if($editable)
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('admin.payouts.items.destroy', [$payout, $item]) }}" onsubmit="return confirm('Remove this line?')">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-link text-danger p-0" title="Remove"><i class="fas fa-times"></i></button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <td class="fs-5 fw-semibold">Net Pay</td>
                                <td class="text-end fs-5 fw-semibold text-nowrap">₹{{ number_format($payout->amount, 2) }}</td>
                                @if($editable)<td></td>@endif
                            </tr>
                        </tfoot>
                    </table>

                    @if($payout->status === 'paid')
                        <div class="border-top mt-3 pt-3 small">
                            <i class="fas fa-check-circle text-success me-1"></i>
                            Paid on <strong>{{ $payout->paid_at?->format('M d, Y') }}</strong>
                            @if($payout->paymentMethodLabel()) via <strong>{{ $payout->paymentMethodLabel() }}</strong>@endif
                            @if($payout->transaction_reference) · Ref: <strong>{{ $payout->transaction_reference }}</strong>@endif
                        </div>
                    @endif
                    @if($payout->notes)
                        <div class="small text-muted mt-2" style="white-space: pre-line;">{{ $payout->notes }}</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4 d-print-none">
            @if($editable)
                <!-- Adjustments -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-sliders-h me-2"></i>Add Earning / Deduction</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.payouts.items.store', $payout) }}">
                            @csrf
                            <div class="btn-group w-100 mb-3" role="group">
                                <input type="radio" class="btn-check" name="type" id="item-earning" value="earning" checked>
                                <label class="btn btn-outline-success" for="item-earning"><i class="fas fa-plus me-1"></i>Earning</label>
                                <input type="radio" class="btn-check" name="type" id="item-deduction" value="deduction">
                                <label class="btn btn-outline-danger" for="item-deduction"><i class="fas fa-minus me-1"></i>Deduction</label>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold" for="item-label">Description</label>
                                <input type="text" class="form-control" id="item-label" name="label" placeholder="e.g. Bonus, Advance recovery" maxlength="255" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold" for="item-amount">Amount (₹)</label>
                                <input type="number" class="form-control" id="item-amount" name="amount" min="0.01" step="0.01" required>
                            </div>
                            <button type="submit" class="btn btn-outline-primary w-100">Add Line</button>
                        </form>
                    </div>
                </div>

                <!-- Mark paid -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-money-check-alt me-2"></i>Mark as Paid</h6>
                    </div>
                    <div class="card-body">
                        @if($incomplete)
                            <p class="small text-warning mb-2"><i class="fas fa-exclamation-triangle me-1"></i>This payslip is incomplete: {{ implode('; ', $incomplete) }}.</p>
                        @endif
                        <form method="POST" action="{{ route('admin.payouts.mark-paid', $payout) }}" onsubmit="return confirm('Mark ₹{{ number_format($payout->amount, 2) }} as paid? The payslip will be locked.')">
                            @csrf
                            {{-- The amount confirmed here; payment is refused if the payslip changed since --}}
                            <input type="hidden" name="expected_amount" value="{{ $payout->amount }}">
                            <div class="mb-2">
                                <label class="form-label small fw-semibold" for="payment_method">Payment Method</label>
                                <select class="form-select" id="payment_method" name="payment_method" required>
                                    @foreach(\App\Models\EmployeePayout::PAYMENT_METHODS as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold" for="transaction_reference">Reference <span class="text-muted fw-normal">(optional)</span></label>
                                <input type="text" class="form-control" id="transaction_reference" name="transaction_reference" placeholder="UTR / cheque no." maxlength="255">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold" for="paid_at">Paid On</label>
                                <input type="date" class="form-control" id="paid_at" name="paid_at" value="{{ now()->toDateString() }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold" for="paid_notes">Note <span class="text-muted fw-normal">(optional)</span></label>
                                <textarea class="form-control" id="paid_notes" name="paid_notes" rows="2" maxlength="1000"></textarea>
                            </div>
                            @if($incomplete)
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="allow_incomplete" value="1" id="allow_incomplete">
                                    <label class="form-check-label small" for="allow_incomplete">Pay anyway. Days not marked by now stay unpaid, and the payslip can't be changed after it is paid.</label>
                                </div>
                            @endif
                            <button type="submit" class="btn btn-success w-100"><i class="fas fa-check me-2"></i>Mark Paid · ₹{{ number_format($payout->amount, 2) }}</button>
                        </form>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.payouts.destroy', $payout) }}" onsubmit="return confirm('Delete this draft payslip? It can be generated again.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-link text-danger w-100"><i class="fas fa-trash me-1"></i>Delete draft payslip</button>
                </form>
            @endif

            <!-- Salary basis -->
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-rupee-sign me-2"></i>Salary Basis</h6>
                </div>
                <div class="card-body small">
                    <p class="mb-1"><strong>{{ \App\Models\EmployeeSalary::PAY_TYPES[$payout->pay_type] ?? 'Monthly Fixed' }}</strong> · ₹{{ number_format($payout->rate, 0) }} / {{ $payout->pay_type === 'daily' ? 'day' : 'month' }}</p>
                    <p class="text-muted mb-2">Present{{ $weights['paid_leave'] ? ', Paid Leave' : '' }}{{ $weights['week_off'] ? ', Week Off' : '' }} count as a full day; Half Day as ½. Absent and unmarked days are unpaid.</p>
                    @if($employee)
                        <a href="{{ route('admin.employees.show', $employee) }}#salary" class="text-decoration-none">View salary history <i class="fas fa-arrow-right ms-1"></i></a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    @media print {
        .sidebar, nav.navbar, .top-navbar, .alert { display: none !important; }
        .payslip { box-shadow: none !important; border: 1px solid #dee2e6 !important; }
        .col-lg-8 { width: 100% !important; }
    }
</style>
@endpush
@endsection
