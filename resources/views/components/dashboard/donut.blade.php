{{-- Donut chart. segments: [['label' => 'Confirmed', 'value' => 3, 'color' => '#6366f1'], ...] --}}
@props(['segments'])

@php
    $total = (float) collect($segments)->sum('value');
    $offset = 0;
@endphp

<div class="flex flex-wrap items-center gap-6">
    <div class="relative h-32 w-32 shrink-0">
        <svg viewBox="0 0 36 36" class="h-full w-full -rotate-90" role="img" aria-label="{{ __('Share by status') }}">
            <circle cx="18" cy="18" r="15.9155" fill="none" stroke="#e5e7eb" stroke-width="4"/>
            @if ($total > 0)
                @foreach ($segments as $segment)
                    @php($pct = $segment['value'] / $total * 100)
                    @if ($pct > 0)
                        <circle cx="18" cy="18" r="15.9155" fill="none" stroke="{{ $segment['color'] }}" stroke-width="4"
                                stroke-dasharray="{{ round($pct, 3) }} {{ round(100 - $pct, 3) }}"
                                stroke-dashoffset="{{ round(-$offset, 3) }}"/>
                    @endif
                    @php($offset += $pct)
                @endforeach
            @endif
        </svg>
        <div class="absolute inset-0 flex flex-col items-center justify-center">
            <span class="text-xl font-semibold text-gray-900">{{ number_format($total) }}</span>
            <span class="text-xs text-gray-500">{{ __('total') }}</span>
        </div>
    </div>

    <ul class="space-y-1.5 text-sm">
        @foreach ($segments as $segment)
            <li class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $segment['color'] }}"></span>
                <span class="text-gray-600">{{ __($segment['label']) }}</span>
                <span class="font-medium text-gray-900">{{ number_format($segment['value']) }}</span>
            </li>
        @endforeach
    </ul>
</div>
