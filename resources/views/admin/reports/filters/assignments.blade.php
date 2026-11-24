<div class="col-md-3">
    <label for="patient_id" class="form-label">Patient</label>
    <select class="form-select" id="patient_id" name="patient_id">
        <option value="">All Patients</option>
        @foreach ($patients as $patient)
            <option value="{{ $patient->id }}" @selected((string) ($filters['patient_id'] ?? '') === (string) $patient->id)>
                {{ $patient->name }}{{ $patient->is_active ? '' : ' (inactive)' }}
            </option>
        @endforeach
    </select>
</div>
<div class="col-md-3">
    <label for="employee_id" class="form-label">Employee</label>
    <select class="form-select" id="employee_id" name="employee_id">
        <option value="">All Employees</option>
        @foreach ($employees as $employee)
            <option value="{{ $employee->id }}" @selected((string) ($filters['employee_id'] ?? '') === (string) $employee->id)>
                {{ trim($employee->getField('name', '')) ?: '#' . $employee->id }}{{ $employee->status === 'active' ? '' : ' ('.$employee->status.')' }}
            </option>
        @endforeach
    </select>
</div>
<div class="col-md-3">
    <label for="type" class="form-label">Type</label>
    <select class="form-select" id="type" name="type">
        <option value="">All Types</option>
        @foreach (\App\Models\Assignment::TYPES as $typeKey => $typeLabel)
            <option value="{{ $typeKey }}" @selected(($filters['type'] ?? '') === $typeKey)>{{ $typeLabel }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-3">
    <label for="status" class="form-label">Status</label>
    <select class="form-select" id="status" name="status">
        <option value="">All</option>
        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
        <option value="ended" @selected(($filters['status'] ?? '') === 'ended')>Ended / Replaced</option>
    </select>
</div>
@include('admin.reports.filters._dates', ['fromLabel' => 'Starts On/After', 'toLabel' => 'Ends On/Before'])
