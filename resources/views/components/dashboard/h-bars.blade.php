{{-- Horizontal bars. items: [['label' => 'Cash', 'value' => 1200, 'color' => '#10b981' (optional)], ...] --}}
@props(['items', 'money' => false, 'color' => '#737373'])

@php
    $peak = (float) collect($items)->max('value');
    $scale = $peak > 0 ? $peak : 1;
@endphp

<ul class="space-y-3.5">
    @foreach ($items as $item)
        <li>
            <div class="flex items-baseline justify-between gap-3 text-sm">
                <span class="text-neutral-700">{{ __($item['label']) }}</span>
                <span class="tabular-nums {{ $item['value'] > 0 ? 'font-semibold text-ink' : 'text-muted' }}">
                    {{ $money ? __('ETB').' '.number_format($item['value'], 0) : number_format($item['value']) }}
                </span>
            </div>
            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-neutral-100">
                @if ($item['value'] > 0)
                    <div class="h-full rounded-full" style="width: {{ max(2, round($item['value'] / $scale * 100, 2)) }}%; background: {{ $item['color'] ?? $color }}"></div>
                @endif
            </div>
        </li>
    @endforeach
</ul>
