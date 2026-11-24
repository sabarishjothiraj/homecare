<div class="col-md-3">
    <label for="search" class="form-label">Search</label>
    <input type="text" class="form-control" id="search" name="search" placeholder="Name, phone or email" value="{{ $filters['search'] ?? '' }}">
</div>
<div class="col-md-3">
    <label for="status" class="form-label">Status</label>
    <select class="form-select" id="status" name="status">
        <option value="">All</option>
        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
    </select>
</div>
<div class="col-md-3">
    <label for="city" class="form-label">City</label>
    <input type="text" class="form-control" id="city" name="city" placeholder="Any city" value="{{ $filters['city'] ?? '' }}">
</div>
<div class="col-md-3 d-none d-md-block"></div>
@include('admin.reports.filters._dates', ['fromLabel' => 'Registered From', 'toLabel' => 'Registered To'])
