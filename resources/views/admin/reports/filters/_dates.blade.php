<div class="col-md-3">
    <label for="start_date" class="form-label">{{ $fromLabel ?? 'From' }}</label>
    <input type="date" class="form-control" id="start_date" name="start_date" value="{{ $filters['start_date'] ?? '' }}">
</div>
<div class="col-md-3">
    <label for="end_date" class="form-label">{{ $toLabel ?? 'To' }}</label>
    <input type="date" class="form-control" id="end_date" name="end_date" value="{{ $filters['end_date'] ?? '' }}">
</div>
