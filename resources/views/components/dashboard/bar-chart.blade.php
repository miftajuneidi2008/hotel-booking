{{-- Column chart. points: [['label' => 'Oct 7', 'value' => 12], ...] --}}
@props(['points', 'money' => false, 'color' => 'bg-indigo-500'])

@php
    $points = array_values($points);
    $peak = (float) collect($points)->max('value');
    $scale = $peak > 0 ? $peak : 1;
    $fmt = fn ($v): string => $money ? __('ETB').' '.number_format($v, 0) : number_format($v);
    $last = count($points) - 1;
@endphp

<div>
    <p class="text-xs text-gray-400">{{ $fmt($peak) }}</p>
    <div class="mt-1 flex h-44 items-end gap-px border-b border-gray-200" role="img" aria-label="{{ __('Chart') }}">
        @foreach ($points as $point)
            <div class="group flex h-full flex-1 items-end" title="{{ $point['label'] }}: {{ $fmt($point['value']) }}">
                <div class="w-full rounded-t {{ $color }} opacity-80 group-hover:opacity-100"
                     style="height: {{ $point['value'] > 0 ? max(2, round($point['value'] / $scale * 100, 2)) : 0 }}%"></div>
            </div>
        @endforeach
    </div>
    @if ($last >= 0)
        <div class="mt-1 flex justify-between text-xs text-gray-400">
            <span>{{ $points[0]['label'] }}</span>
            <span>{{ $points[$last]['label'] }}</span>
        </div>
    @endif
</div>
