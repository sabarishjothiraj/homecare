@extends('layouts.admin')

@section('title', 'Edit Payout')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Edit Payout</h2>
            <p class="text-muted mb-0">Update payout information - Payout #{{ $payout->id }}</p>
        </div>
        <a href="{{ route('admin.payouts.show', $payout) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Payout
        </a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Payout Information</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.payouts.update', $payout) }}" method="POST" data-ajax="true">
                        @csrf
                        @method('PUT')

                        <div class="row g-3">
                            <!-- Employee Selection -->
                            <div class="col-md-6">
                                <label for="employee_id" class="form-label">
                                    Employee <span class="text-danger">*</span>
                                </label>
                                <select class="form-select @error('employee_id') is-invalid @enderror" 
                                        id="employee_id" 
                                        name="employee_id" 
                                        required>
                                    <option value="">Select Employee</option>
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee->id }}" {{ old('employee_id', $payout->employee_id) == $employee->id ? 'selected' : '' }}>
                                            {{ $employee->getField('name', 'Employee #' . $employee->id) }}
                                            @if($employee->getField('phone'))
                                                - {{ $employee->getField('phone') }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('employee_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Amount -->
                            <div class="col-md-6">
                                <label for="amount" class="form-label">
                                    Amount (₹) <span class="text-danger">*</span>
                                </label>
                                <input type="number" 
                                       class="form-control @error('amount') is-invalid @enderror" 
                                       id="amount" 
                                       name="amount" 
                                       value="{{ old('amount', $payout->amount) }}"
                                       step="0.01"
                                       min="0"
                                       required>
                                @error('amount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Payout Date -->
                            <div class="col-md-6">
                                <label for="payout_date" class="form-label">
                                    Payout Date <span class="text-danger">*</span>
                                </label>
                                <input type="date" 
                                       class="form-control @error('payout_date') is-invalid @enderror" 
                                       id="payout_date" 
                                       name="payout_date" 
                                       value="{{ old('payout_date', $payout->period_end ? $payout->period_end->format('Y-m-d') : date('Y-m-d')) }}"
                                       required>
                                @error('payout_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Payment Method -->
                            <div class="col-md-6">
                                <label for="payment_method" class="form-label">
                                    Payment Method <span class="text-danger">*</span>
                                </label>
                                <select class="form-select @error('payment_method') is-invalid @enderror" 
                                        id="payment_method" 
                                        name="payment_method" 
                                        required>
                                    <option value="">Select Method</option>
                                    <option value="cash" {{ old('payment_method', $payout->frequency) === 'cash' ? 'selected' : '' }}>Cash</option>
                                    <option value="bank_transfer" {{ old('payment_method', $payout->frequency) === 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                    <option value="cheque" {{ old('payment_method', $payout->frequency) === 'cheque' ? 'selected' : '' }}>Cheque</option>
                                    <option value="online" {{ old('payment_method', $payout->frequency) === 'online' ? 'selected' : '' }}>Online</option>
                                </select>
                                @error('payment_method')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Description -->
                            <div class="col-12">
                                <label for="description" class="form-label">Description</label>
                                <input type="text" 
                                       class="form-control @error('description') is-invalid @enderror" 
                                       id="description" 
                                       name="description" 
                                       value="{{ old('description', '') }}"
                                       maxlength="500"
                                       placeholder="e.g., Salary for January 2024">
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Notes -->
                            <div class="col-12">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control @error('notes') is-invalid @enderror" 
                                          id="notes" 
                                          name="notes" 
                                          rows="3"
                                          placeholder="Enter any additional notes or details">{{ old('notes', $payout->notes) }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Payout
                            </button>
                            <a href="{{ route('admin.payouts.show', $payout) }}" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Payout Details</h6>
                </div>
                <div class="card-body">
                    <p class="small mb-2"><strong>Payout ID:</strong> #{{ $payout->id }}</p>
                    <p class="small mb-2"><strong>Status:</strong> 
                        @if($payout->status === 'paid')
                            <span class="badge bg-success">Paid</span>
                        @else
                            <span class="badge bg-warning">Pending</span>
                        @endif
                    </p>
                    <p class="small mb-2"><strong>Created:</strong> {{ $payout->created_at->format('M d, Y') }}</p>
                    <p class="small mb-0"><strong>Last Updated:</strong> {{ $payout->updated_at->diffForHumans() }}</p>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Note</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-0">
                        Only pending payouts can be edited. Once a payout is marked as paid, it cannot be modified.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

