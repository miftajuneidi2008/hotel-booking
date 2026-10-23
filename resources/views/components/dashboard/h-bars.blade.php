{{-- Horizontal bars. items: [['label' => 'Cash', 'value' => 1200], ...] --}}
@props(['items', 'money' => false, 'color' => 'bg-indigo-500'])

@php
    $peak = (float) collect($items)->max('value');
    $scale = $peak > 0 ? $peak : 1;
@endphp

<ul class="space-y-3">
    @foreach ($items as $item)
        <li>
            <div class="flex items-baseline justify-between text-sm">
                <span class="text-gray-600">{{ __($item['label']) }}</span>
                <span class="font-medium text-gray-900">{{ $money ? __('ETB').' '.number_format($item['value'], 0) : number_format($item['value']) }}</span>
            </div>
            <div class="mt-1 h-2 overflow-hidden rounded-full bg-gray-100">
                <div class="h-full rounded-full {{ $color }}" style="width: {{ round($item['value'] / $scale * 100, 2) }}%"></div>
            </div>
        </li>
    @endforeach
</ul>
