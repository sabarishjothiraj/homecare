{{-- Headline totals for a report: $summary = [['label', 'value', 'tone'?, 'hint'?], ...] --}}
@if (!empty($summary))
    <div class="row g-3 mb-4">
        @foreach ($summary as $card)
            <div class="col-6 col-lg">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body py-3">
                        <div class="text-muted small mb-1">{{ $card['label'] }}</div>
                        <div class="fs-4 fw-semibold {{ isset($card['tone']) ? 'text-'.$card['tone'] : '' }}">{{ $card['value'] }}</div>
                        @if (!empty($card['hint']))
                            <div class="text-muted small mt-1">{{ $card['hint'] }}</div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
