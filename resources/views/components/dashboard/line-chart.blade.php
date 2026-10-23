{{-- Area/line chart for percentages (0-100). points: [['label' => 'Oct 7', 'value' => 40], ...] --}}
@props(['points', 'color' => '#4f46e5'])

@php
    $points = array_values($points);
    $n = count($points);
    $w = 100;
    $h = 40;
    $line = collect($points)->map(function ($p, $i) use ($n, $w, $h): string {
        $x = $n > 1 ? $i / ($n - 1) * $w : $w / 2;
        $y = $h - (max(0, min(100, $p['value'])) / 100 * $h);

        return round($x, 2).','.round($y, 2);
    })->implode(' ');
    $area = '0,'.$h.' '.$line.' '.$w.','.$h;
@endphp

<div>
    <div class="flex justify-between text-xs text-gray-400"><span>100%</span><span>{{ $n ? __('Hover a point for the day') : '' }}</span></div>
    <div class="relative mt-1 h-44 border-b border-gray-200">
        <svg viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" class="absolute inset-0 h-full w-full" role="img" aria-label="{{ __('Occupancy chart') }}">
            <line x1="0" y1="{{ $h / 2 }}" x2="{{ $w }}" y2="{{ $h / 2 }}" stroke="#e5e7eb" stroke-width="1" vector-effect="non-scaling-stroke" stroke-dasharray="4 4"/>
            @if ($n)
                <polygon points="{{ $area }}" fill="{{ $color }}" fill-opacity="0.12"/>
                <polyline points="{{ $line }}" fill="none" stroke="{{ $color }}" stroke-width="2" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
            @endif
        </svg>
        {{-- Invisible hover columns so each day shows its value --}}
        <div class="absolute inset-0 flex">
            @foreach ($points as $p)
                <div class="flex-1" title="{{ $p['label'] }}: {{ $p['value'] }}%"></div>
            @endforeach
        </div>
    </div>
    @if ($n)
        <div class="mt-1 flex justify-between text-xs text-gray-400">
            <span>{{ $points[0]['label'] }}</span>
            <span>{{ $points[$n - 1]['label'] }}</span>
        </div>
    @endif
</div>
