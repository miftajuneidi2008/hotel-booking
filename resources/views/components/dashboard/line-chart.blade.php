{{-- Area/line chart for percentages (0-100) with gridlines, hover guide and tooltip.
     points: [['label' => 'Oct 7', 'value' => 40], ...] --}}
@props(['points', 'label', 'color' => '#525252', 'empty' => null, 'ticks' => 5])

@php
    $points = array_values($points);
    $n = count($points);
    $peak = (float) collect($points)->max('value');

    $labels = collect($points)->pluck('label')->all();
    $nums = collect($points)->map(fn ($p) => (float) $p['value'])->all();
    $shown = collect($points)->map(fn ($p) => number_format($p['value'], 0).'%')->all();

    $dateTicks = $n > 0
        ? collect(range(0, max($ticks - 1, 0)))
            ->map(fn (int $k): int => (int) round($k * ($n - 1) / max($ticks - 1, 1)))
            ->unique()->values()->all()
        : [];

    $line = '';
    $area = '';
    if ($n > 0) {
        // Each point sits in the middle of its hover column, so the dot lines up with the pointer.
        $x = fn (int $i): float => round(($i + 0.5) / $n * 100, 2);
        $line = collect($points)->map(fn ($p, $i) => $x($i).','.round(40 - max(0, min(100, $p['value'])) / 100 * 40, 2))->implode(' ');
        $area = $x(0).',40 '.$line.' '.$x($n - 1).',40';
    }
@endphp

@if ($n === 0 || $peak <= 0)
    <div class="flex h-44 items-center justify-center rounded-lg bg-neutral-50 px-6 text-center text-sm text-muted">
        {{ $empty ?? __('Nothing recorded in this period yet.') }}
    </div>
@else
    <div x-data="{ i: null, n: {{ $n }}, labels: @js($labels), values: @js($shown), nums: @js($nums) }">
        <div class="flex gap-3" aria-hidden="true">
            <div class="relative h-44 w-10 shrink-0 text-xs tabular-nums leading-none text-muted">
                <span class="absolute end-0 top-0 -translate-y-1/2">100%</span>
                <span class="absolute end-0 top-1/2 -translate-y-1/2">50%</span>
                <span class="absolute bottom-0 end-0 translate-y-1/2">0%</span>
            </div>

            <div class="min-w-0 flex-1">
                <div class="relative h-44">
                    <div class="absolute inset-0 flex flex-col justify-between">
                        <div class="border-t border-neutral-200"></div>
                        <div class="border-t border-dashed border-neutral-200"></div>
                        <div class="border-t border-neutral-300"></div>
                    </div>

                    <svg viewBox="0 0 100 40" preserveAspectRatio="none" class="absolute inset-0 size-full">
                        <polygon points="{{ $area }}" fill="{{ $color }}" fill-opacity="0.07"/>
                        <polyline points="{{ $line }}" fill="none" stroke="{{ $color }}" stroke-width="1.75" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
                    </svg>

                    {{-- Hover columns --}}
                    <div class="absolute inset-0 flex">
                        @foreach ($points as $idx => $point)
                            <div class="h-full flex-1" @mouseenter="i = {{ $idx }}" @mouseleave="i = null"></div>
                        @endforeach
                    </div>

                    {{-- Guide line + dot (HTML, so the dot stays round although the SVG is stretched) --}}
                    <span x-show="i !== null" x-cloak class="pointer-events-none absolute inset-y-0 w-px bg-neutral-300"
                          :style="`left: ${(i + 0.5) / n * 100}%`"></span>
                    <span x-show="i !== null" x-cloak class="pointer-events-none absolute size-3 -translate-x-1/2 translate-y-1/2 rounded-full border-2 border-white shadow"
                          style="background: {{ $color }}"
                          :style="`left: ${(i + 0.5) / n * 100}%; bottom: ${nums[i]}%`"></span>

                    <div x-show="i !== null" x-cloak
                         class="pointer-events-none absolute bottom-full z-10 mb-2 -translate-x-1/2 whitespace-nowrap rounded-lg bg-ink px-3 py-2 text-xs text-white shadow-lg"
                         :style="`left: ${Math.min(Math.max((i + 0.5) / n * 100, 14), 86)}%`">
                        <span class="block text-neutral-300" x-text="labels[i]"></span>
                        <span class="block text-sm font-semibold tabular-nums" x-text="values[i]"></span>
                    </div>
                </div>

                <div class="mt-3 flex justify-between text-xs text-muted">
                    @foreach ($dateTicks as $t)
                        <span>{{ $points[$t]['label'] }}</span>
                    @endforeach
                </div>
            </div>
        </div>

        <table class="sr-only">
            <caption>{{ $label }}</caption>
            <thead><tr><th scope="col">{{ __('Day') }}</th><th scope="col">{{ __('Occupancy') }}</th></tr></thead>
            <tbody>
                @foreach ($points as $point)
                    <tr><th scope="row">{{ $point['label'] }}</th><td>{{ number_format($point['value'], 0) }}%</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
