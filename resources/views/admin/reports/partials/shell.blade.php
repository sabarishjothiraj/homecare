{{--
    Common report page chrome.

    @include('admin.reports.partials.shell', [
        'title' => 'Contract Report', 'icon' => 'fa-file-contract', 'route' => 'admin.reports.contracts',
        'filtersView' => 'admin.reports.filters.contracts',
    ])
--}}
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="mb-1">{{ $title }}</h2>
            <p class="text-muted mb-0">{{ number_format($data->total()) }} {{ Str::plural('record', $data->total()) }} found</p>
        </div>
        <a href="{{ route('admin.reports.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Reports
        </a>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h5>
        </div>
        <div class="card-body">
            <form action="{{ route($route) }}" method="GET" class="row g-3">
                @include($filtersView)
                <div class="col-12 d-flex justify-content-end gap-2">
                    <a href="{{ route($route) }}" class="btn btn-outline-secondary">Reset</a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-search me-2"></i>Apply
                    </button>
                </div>
            </form>
        </div>
    </div>

    @include('admin.reports.partials.summary')

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas {{ $icon }} me-2"></i>Results</h5>
            @if ($data->isNotEmpty())
                <a href="{{ route($route, array_merge(array_filter($filters), ['export' => 1])) }}" class="btn btn-sm btn-success">
                    <i class="fas fa-file-csv me-2"></i>Export CSV
                </a>
            @endif
        </div>
        <div class="card-body p-0">
            @include('admin.reports.partials.table', ['data' => $data, 'totals' => $totals])
        </div>
        @if ($data->hasPages())
            <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <small class="text-muted">Showing {{ number_format($data->firstItem()) }}–{{ number_format($data->lastItem()) }} of {{ number_format($data->total()) }}. Totals and the CSV export cover every matching record.</small>
                {{ $data->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>
