<div class="col-md-3">
    <label for="employee_id" class="form-label">Employee</label>
    <select class="form-select" id="employee_id" name="employee_id">
        <option value="">All Employees</option>
        @foreach ($employees as $employee)
            <option value="{{ $employee->id }}" @selected((string) ($filters['employee_id'] ?? '') === (string) $employee->id)>
                {{ trim($employee->getField('name', '')) ?: '#' . $employee->id }}
            </option>
        @endforeach
    </select>
</div>
<div class="col-md-3">
    <label for="status" class="form-label">Status</label>
    <select class="form-select" id="status" name="status">
        <option value="">All</option>
        @foreach (['pending', 'paid', 'cancelled'] as $status)
            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
        @endforeach
    </select>
</div>
@include('admin.reports.filters._dates', ['fromLabel' => 'Period From', 'toLabel' => 'Period To'])
