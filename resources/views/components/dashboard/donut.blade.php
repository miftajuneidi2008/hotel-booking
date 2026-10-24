{{-- Donut chart with a legend that shows count and share.
     segments: [['label' => 'Confirmed', 'value' => 3, 'color' => '#3b82f6'], ...] --}}
@props(['segments', 'empty' => null])

@php
    $total = (float) collect($segments)->sum('value');
    $visible = collect($segments)->filter(fn ($s) => $s['value'] > 0)->count();
    // A hairline gap between slices, unless there is only one slice.
    $gap = $visible > 1 ? 0.8 : 0;
    $offset = 0;
@endphp

@if ($total <= 0)
    <div class="flex h-36 items-center justify-center rounded-lg bg-neutral-50 px-6 text-center text-sm text-muted">
        {{ $empty ?? __('No bookings were made in this period.') }}
    </div>
@else
    <div class="flex flex-wrap items-center gap-x-8 gap-y-5">
        <div class="relative size-36 shrink-0">
            <svg viewBox="0 0 36 36" class="size-full -rotate-90" aria-hidden="true">
                <circle cx="18" cy="18" r="15.9155" fill="none" stroke="#f5f5f5" stroke-width="3"/>
                @foreach ($segments as $segment)
                    @php($pct = $segment['value'] / $total * 100)
                    @if ($pct > 0)
                        <circle cx="18" cy="18" r="15.9155" fill="none" stroke="{{ $segment['color'] }}" stroke-width="3"
                                stroke-dasharray="{{ round(max($pct - $gap, 0.1), 3) }} {{ round(100 - max($pct - $gap, 0.1), 3) }}"
                                stroke-dashoffset="{{ round(-$offset, 3) }}"/>
                    @endif
                    @php($offset += $pct)
                @endforeach
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-2xl font-semibold tabular-nums text-ink">{{ number_format($total) }}</span>
                <span class="text-xs text-muted">{{ __('bookings') }}</span>
            </div>
        </div>

        <ul class="min-w-44 flex-1 space-y-2.5 text-sm">
            @foreach ($segments as $segment)
                <li class="flex items-center gap-2 {{ $segment['value'] > 0 ? '' : 'opacity-60' }}">
                    <span class="size-2.5 shrink-0 rounded-full" style="background: {{ $segment['color'] }}" aria-hidden="true"></span>
                    <span class="text-neutral-700">{{ __($segment['label']) }}</span>
                    <span class="ms-auto font-semibold tabular-nums text-ink">{{ number_format($segment['value']) }}</span>
                    <span class="w-10 text-end tabular-nums text-muted">{{ round($segment['value'] / $total * 100) }}%</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
