<div class="card border mb-3 line-item-row">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">Service Line</h6>
            <button type="button" class="btn btn-sm btn-outline-danger remove-line-item-btn">Remove</button>
        </div>
        <input type="hidden" name="line_items[{{ $index }}][item_id]" value="{{ old("line_items.$index.item_id", $line['item_id'] ?? '') }}">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Service <span class="text-danger">*</span></label>
                <select class="form-select line-service-select @error("line_items.$index.service_id") is-invalid @enderror" name="line_items[{{ $index }}][service_id]" required>
                    <option value="">Select Service</option>
                    @foreach($services as $service)
                        <option value="{{ $service->id }}" {{ (string) old("line_items.$index.service_id", $line['service_id'] ?? '') === (string) $service->id ? 'selected' : '' }}>{{ $service->name }}</option>
                    @endforeach
                </select>
                @error("line_items.$index.service_id")
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label class="form-label">Employee <span class="text-danger">*</span></label>
                <select class="form-select line-employee-select @error("line_items.$index.employee_id") is-invalid @enderror" name="line_items[{{ $index }}][employee_id]" required>
                    <option value="">Select Employee</option>
                    @foreach($employees as $employee)
                        @php
                            $serviceIds = $employee->services->pluck('id')->join(',');
                        @endphp
                        <option value="{{ $employee->id }}"
                                data-service-ids="{{ $serviceIds }}"
                                {{ (string) old("line_items.$index.employee_id", $line['employee_id'] ?? '') === (string) $employee->id ? 'selected' : '' }}>
                            {{ $employee->getField('name', 'Employee #' . $employee->id) }}
                            @if($employee->phone)
                                - {{ $employee->phone }}
                            @endif
                        </option>
                    @endforeach
                </select>
                @error("line_items.$index.employee_id")
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                <input type="number" class="form-control @error("line_items.$index.amount") is-invalid @enderror" name="line_items[{{ $index }}][amount]" step="0.01" min="0" value="{{ old("line_items.$index.amount", $line['amount'] ?? '') }}" required>
                @error("line_items.$index.amount")
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12">
                <label class="form-label">Note (Optional)</label>
                <input type="text" class="form-control @error("line_items.$index.note") is-invalid @enderror" name="line_items[{{ $index }}][note]" value="{{ old("line_items.$index.note", $line['note'] ?? '') }}" maxlength="1000">
                @error("line_items.$index.note")
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
</div>
