{{--
    Renders a report collection.

    ReportService returns every report as a collection of associative arrays
    whose keys are already the display column headings, so one table renders
    all four reports without knowing anything about the source model.
--}}
@if ($data->isEmpty())
    <div class="text-center text-muted py-5">
        <i class="fas fa-folder-open fa-3x mb-3 d-block opacity-50"></i>
        <p class="mb-0">No records match the selected filters.</p>
    </div>
@else
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    @foreach (array_keys($data->first()) as $heading)
                        <th class="text-nowrap">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($data as $row)
                    <tr>
                        @foreach ($row as $value)
                            <td>{{ ($value === null || $value === '') ? '—' : $value }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
