{{--
    Renders a report collection.

    ReportService returns every report as a collection of associative arrays
    whose keys are already the display column headings, so one table renders
    every report without knowing anything about the source model. Columns whose
    heading ends in "(₹)" hold plain numbers and are formatted here.

    $totals comes from ReportService::totals(): only columns that genuinely add
    up, summed over every matching record rather than just this page.
--}}
@if ($data->isEmpty())
    <div class="text-center text-muted py-5">
        <i class="fas fa-folder-open fa-3x mb-3 d-block opacity-50"></i>
        <p class="mb-0">No records match the selected filters.</p>
    </div>
@else
    @php
        $headings = array_keys($data->first());
        $moneyColumns = array_filter($headings, fn ($heading) => str_ends_with($heading, '(₹)'));
        $totalValues = collect($totals['values'] ?? [])->only($headings);
    @endphp
    <div class="table-responsive report-table">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    @foreach ($headings as $heading)
                        <th class="text-nowrap {{ in_array($heading, $moneyColumns) ? 'text-end' : '' }}">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($data as $row)
                    <tr>
                        @foreach ($row as $heading => $value)
                            @if (in_array($heading, $moneyColumns))
                                <td class="text-end text-nowrap">{{ $value === null ? '—' : number_format((float) $value, 2) }}</td>
                            @else
                                <td class="{{ strlen((string) $value) > 40 ? 'report-wrap' : 'text-nowrap' }}">{{ ($value === null || $value === '') ? '—' : $value }}</td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
            @if ($totalValues->isNotEmpty())
                <tfoot class="table-light fw-semibold">
                    <tr>
                        @foreach ($headings as $heading)
                            @if ($loop->first)
                                <td class="text-nowrap">{{ $totals['label'] ?? 'Total' }}</td>
                            @elseif ($totalValues->has($heading))
                                <td class="text-end text-nowrap">{{ number_format($totalValues[$heading], 2) }}</td>
                            @else
                                <td></td>
                            @endif
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    @once
        @push('styles')
            <style>
                .report-table { max-height: 70vh; }
                .report-table thead th { position: sticky; top: 0; z-index: 1; }
                .report-table .report-wrap { min-width: 220px; white-space: normal; }
            </style>
        @endpush
    @endonce
@endif
