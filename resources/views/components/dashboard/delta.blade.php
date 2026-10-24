{{-- Trend against the previous period of the same length. Hidden when the previous period had nothing to compare with.
     invert: true when going up is bad (cancellations). points: true to show a gap in percentage points (occupancy). --}}
@props(['current', 'previous', 'days', 'invert' => false, 'points' => false])

@php
    $current = (float) $current;
    $previous = (float) $previous;
    $diff = $current - $previous;
    $flat = abs($diff) < 0.0001;
    $up = $diff > 0;
    $good = $invert ? ! $up : $up;
    $tone = $flat ? 'text-muted' : ($good ? 'text-emerald-700' : 'text-red-700');

    $text = '';
    if ($previous <= 0) {
        // Nothing to compare with, and the card below is hidden.
    } elseif ($flat) {
        $text = __('Same as previous :days days', ['days' => $days]);
    } elseif ($points) {
        $text = __(':n pts vs previous :days days', ['n' => number_format(abs($diff), 0), 'days' => $days]);
    } else {
        $text = __(':n% vs previous :days days', ['n' => number_format(abs($diff) / $previous * 100, 0), 'days' => $days]);
    }
@endphp

@if ($previous > 0)
    <p class="mt-2 flex items-start gap-1.5 text-xs {{ $tone }}">
        @unless ($flat)
            <svg class="mt-0.5 size-3 shrink-0" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true">
                <path d="{{ $up ? 'M6 2l5 8H1z' : 'M6 10L1 2h10z' }}"/>
            </svg>
            <span class="sr-only">{{ $up ? __('Up') : __('Down') }}</span>
        @endunless
        <span class="tabular-nums">{{ $text }}</span>
    </p>
@endif
