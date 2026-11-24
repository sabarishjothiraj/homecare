<div class="col-md-3">
    <label for="search" class="form-label">Search</label>
    <input type="text" class="form-control" id="search" name="search" placeholder="Name, phone or email" value="{{ $filters['search'] ?? '' }}">
</div>
<div class="col-md-3">
    <label for="status" class="form-label">Status</label>
    <select class="form-select" id="status" name="status">
        <option value="">All Statuses</option>
        @foreach (['active', 'pending', 'inactive'] as $status)
            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
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
<div class="col-md-3">
    <label for="availability" class="form-label">Availability</label>
    <select class="form-select" id="availability" name="availability">
        <option value="">All</option>
        <option value="available" @selected(($filters['availability'] ?? '') === 'available')>Available</option>
        <option value="placed" @selected(($filters['availability'] ?? '') === 'placed')>Assigned / Covering</option>
    </select>
</div>
@include('admin.reports.filters._dates', ['fromLabel' => 'Joined From', 'toLabel' => 'Joined To'])
