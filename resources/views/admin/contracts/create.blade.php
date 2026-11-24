@extends('layouts.admin')

@section('title', 'Create New Contract')

@section('content')
@php
    $lineItems = old('line_items', [[]]);
    if (empty($lineItems)) {
        $lineItems = [[]];
    }
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Create New Contract</h2>
            <p class="text-muted mb-0">One contract with multiple service lines</p>
        </div>
        <a href="{{ route('admin.contracts.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Contracts
        </a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-file-contract me-2"></i>Contract Information</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.contracts.store') }}" method="POST" data-ajax="true">
                        @csrf

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="patient_id" class="form-label">Patient <span class="text-danger">*</span></label>
                                <select class="form-select @error('patient_id') is-invalid @enderror" id="patient_id" name="patient_id" required>
                                    <option value="">Select Patient</option>
                                    @foreach($patients as $patient)
                                        <option value="{{ $patient->id }}" {{ old('patient_id', request('patient_id')) == $patient->id ? 'selected' : '' }}>
                                            {{ $patient->name }} - {{ $patient->phone }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('patient_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control @error('start_date') is-invalid @enderror" id="start_date" name="start_date" value="{{ old('start_date', date('Y-m-d')) }}" required>
                                @error('start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="duration_type" class="form-label">Duration Type <span class="text-danger">*</span></label>
                                <select class="form-select @error('duration_type') is-invalid @enderror" id="duration_type" name="duration_type" required>
                                    <option value="">Select Duration Type</option>
                                    <option value="daily" {{ old('duration_type') === 'daily' ? 'selected' : '' }}>Daily</option>
                                    <option value="weekly" {{ old('duration_type') === 'weekly' ? 'selected' : '' }}>Weekly</option>
                                    <option value="monthly" {{ old('duration_type') === 'monthly' ? 'selected' : '' }}>Monthly</option>
                                    <option value="yearly" {{ old('duration_type') === 'yearly' ? 'selected' : '' }}>Yearly</option>
                                    <option value="custom" {{ old('duration_type') === 'custom' ? 'selected' : '' }}>Custom</option>
                                </select>
                                @error('duration_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6" id="duration_value_container">
                                <label for="duration_value" class="form-label">Duration Value <span class="text-danger">*</span></label>
                                <input type="number" class="form-control @error('duration_value') is-invalid @enderror" id="duration_value" name="duration_value" value="{{ old('duration_value') }}" min="1" placeholder="Enter number of days/months">
                                @error('duration_value')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6" id="custom_end_date_container">
                                <label for="custom_end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control @error('custom_end_date') is-invalid @enderror" id="custom_end_date" name="custom_end_date" value="{{ old('custom_end_date') }}">
                                @error('custom_end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="payment_method" class="form-label">Payment Method <span class="text-danger">*</span></label>
                                <select class="form-select @error('payment_method') is-invalid @enderror" id="payment_method" name="payment_method" required>
                                    <option value="">Select Payment Method</option>
                                    <option value="online" {{ old('payment_method') === 'online' ? 'selected' : '' }}>Online</option>
                                    <option value="cash" {{ old('payment_method') === 'cash' ? 'selected' : '' }}>Cash</option>
                                    <option value="cheque" {{ old('payment_method') === 'cheque' ? 'selected' : '' }}>Cheque</option>
                                    <option value="bank_transfer" {{ old('payment_method') === 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                </select>
                                @error('payment_method')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6" id="payment-details-wrapper" hidden>
                                <label for="payment_details" class="form-label"><span id="payment-details-label">Payment Details</span> <span class="text-danger" id="payment-details-required">*</span></label>
                                <input type="text" class="form-control @error('payment_details') is-invalid @enderror" id="payment_details" name="payment_details" value="{{ old('payment_details') }}" maxlength="500">
                                @error('payment_details')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="terms" class="form-label">Terms and Conditions</label>
                                <textarea class="form-control @error('terms') is-invalid @enderror" id="terms" name="terms" rows="4" placeholder="Enter any special terms or conditions for this contract">{{ old('terms') }}</textarea>
                                @error('terms')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 mt-2">
                                <hr>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0"><i class="fas fa-layer-group me-2"></i>Service Lines</h6>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="add-line-item-btn">
                                        <i class="fas fa-plus me-1"></i>Add Service Line
                                    </button>
                                </div>
                                {{-- Anchor for AJAX saves: admin-forms.js shows the "line_items" error next to the field of that name --}}
                                <div><input type="hidden" name="line_items" disabled></div>
                                @error('line_items')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                                <div id="line-items-container" data-next-index="{{ count($lineItems) }}">
                                    @foreach($lineItems as $index => $line)
                                        @include('admin.contracts.partials.line-item-row', ['index' => $index, 'line' => $line, 'services' => $services, 'employees' => $employees])
                                    @endforeach
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="assignment_start_date" class="form-label">Line Start Date</label>
                                <input type="date" class="form-control @error('assignment_start_date') is-invalid @enderror" id="assignment_start_date" name="assignment_start_date" value="{{ old('assignment_start_date', date('Y-m-d')) }}">
                                @error('assignment_start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="assignment_end_date" class="form-label">Line End Date (Optional)</label>
                                <input type="date" class="form-control @error('assignment_end_date') is-invalid @enderror" id="assignment_end_date" name="assignment_end_date" value="{{ old('assignment_end_date') }}">
                                @error('assignment_end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Create Contract
                            </button>
                            <a href="{{ route('admin.contracts.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Information</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2"><i class="fas fa-check-circle text-success me-2"></i>Each service line has its own employee and amount</p>
                    <p class="small text-muted mb-2"><i class="fas fa-check-circle text-success me-2"></i>Employee list is filtered by selected service category</p>
                    <p class="small text-muted mb-0"><i class="fas fa-check-circle text-success me-2"></i>Contract total is the sum of all service lines</p>
                </div>
            </div>
        </div>
    </div>
</div>

<template id="line-item-template">
    @include('admin.contracts.partials.line-item-row', ['index' => '__INDEX__', 'line' => [], 'services' => $services, 'employees' => $employees])
</template>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const durationTypeSelect = document.getElementById('duration_type');
    const durationValueContainer = document.getElementById('duration_value_container');
    const durationValueInput = document.getElementById('duration_value');
    const customEndContainer = document.getElementById('custom_end_date_container');
    const customEndInput = document.getElementById('custom_end_date');
    const lineItemsContainer = document.getElementById('line-items-container');
    const addLineItemBtn = document.getElementById('add-line-item-btn');
    const lineTemplate = document.getElementById('line-item-template').innerHTML;

    function updateDurationVisibility() {
        if (durationTypeSelect.value === 'custom') {
            durationValueInput.value = '';
            durationValueInput.removeAttribute('required');
            durationValueContainer.style.display = 'none';
            customEndInput.setAttribute('required', 'required');
            customEndContainer.style.display = 'block';
        } else {
            durationValueInput.setAttribute('required', 'required');
            durationValueContainer.style.display = 'block';
            customEndInput.value = '';
            customEndInput.removeAttribute('required');
            customEndContainer.style.display = 'none';
        }
    }

    function filterEmployees(row) {
        const serviceSelect = row.querySelector('.line-service-select');
        const employeeSelect = row.querySelector('.line-employee-select');
        if (!serviceSelect || !employeeSelect) {
            return;
        }

        const selectedServiceId = serviceSelect.value;
        const options = employeeSelect.querySelectorAll('option[data-service-ids]');

        options.forEach(option => {
            const serviceIds = (option.getAttribute('data-service-ids') || '').split(',').filter(Boolean);
            const shouldShow = !selectedServiceId || serviceIds.includes(selectedServiceId);
            option.hidden = !shouldShow;
            if (!shouldShow && option.selected) {
                option.selected = false;
            }
        });
    }

    function bindLineRow(row) {
        const removeBtn = row.querySelector('.remove-line-item-btn');
        const serviceSelect = row.querySelector('.line-service-select');

        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                if (lineItemsContainer.querySelectorAll('.line-item-row').length > 1) {
                    row.remove();
                }
            });
        }

        if (serviceSelect) {
            serviceSelect.addEventListener('change', function() {
                filterEmployees(row);
            });
            filterEmployees(row);
        }
    }

    addLineItemBtn.addEventListener('click', function() {
        const nextIndex = parseInt(lineItemsContainer.getAttribute('data-next-index'), 10) || 0;
        const html = lineTemplate.replaceAll('__INDEX__', String(nextIndex));
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        const row = wrapper.firstElementChild;
        lineItemsContainer.appendChild(row);
        lineItemsContainer.setAttribute('data-next-index', String(nextIndex + 1));
        bindLineRow(row);
    });

    lineItemsContainer.querySelectorAll('.line-item-row').forEach(bindLineRow);

    durationTypeSelect.addEventListener('change', updateDurationVisibility);
    updateDurationVisibility();
});
</script>
@include('admin.contracts._payment-details-script')
@include('admin.contracts._line-end-date-script')
@endpush
@endsection
