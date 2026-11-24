<div class="col-md-3">
    <label for="payment_method" class="form-label">Payment Method</label>
    <select class="form-select" id="payment_method" name="payment_method">
        <option value="">All Methods</option>
        @foreach (\App\Services\ReportService::PAYMENT_METHODS as $value => $label)
            <option value="{{ $value }}" @selected(($filters['payment_method'] ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-3">
    <label for="payment_type" class="form-label">Payment Type</label>
    <select class="form-select" id="payment_type" name="payment_type">
        <option value="">All Types</option>
        @foreach (['contract' => 'Contract', 'employee_activation' => 'Employee Activation'] as $value => $label)
            <option value="{{ $value }}" @selected(($filters['payment_type'] ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</div>
@include('admin.reports.filters._dates', ['fromLabel' => 'Paid From', 'toLabel' => 'Paid To'])
