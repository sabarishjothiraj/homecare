<div class="col-md-3">
    <label for="search" class="form-label">Search</label>
    <input type="text" class="form-control" id="search" name="search" placeholder="Contract no., patient name or phone" value="{{ $filters['search'] ?? '' }}">
</div>
<div class="col-md-3">
    <label for="status" class="form-label">Contract Status</label>
    <select class="form-select" id="status" name="status">
        <option value="">All Statuses</option>
        @foreach (\App\Services\ReportService::CONTRACT_STATUSES as $value => $label)
            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-3">
    <label for="payment_status" class="form-label">Payment Status</label>
    <select class="form-select" id="payment_status" name="payment_status">
        <option value="">All</option>
        @foreach (['unpaid' => 'Unpaid', 'partial' => 'Partially Paid', 'paid' => 'Fully Paid', 'settled' => 'Settled (closed)'] as $value => $label)
            <option value="{{ $value }}" @selected(($filters['payment_status'] ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</div>
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
    <label for="service_id" class="form-label">Service</label>
    <select class="form-select" id="service_id" name="service_id">
        <option value="">All Services</option>
        @foreach ($services as $service)
            <option value="{{ $service->id }}" @selected((string) ($filters['service_id'] ?? '') === (string) $service->id)>{{ $service->name }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-3 d-none d-md-block"></div>
@include('admin.reports.filters._dates', ['fromLabel' => 'Starts From', 'toLabel' => 'Starts To'])
