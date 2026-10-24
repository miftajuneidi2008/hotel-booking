{{-- Column chart with a labelled axis, gridlines and a hover tooltip.
     points: [['label' => 'Oct 7', 'value' => 12], ...]   label: what the chart shows (read by screen readers)
     color: any CSS color. ticks: how many dates to print under the chart. --}}
@props(['points', 'label', 'money' => false, 'color' => '#737373', 'empty' => null, 'ticks' => 5])

@php
    $points = array_values($points);
    $n = count($points);
    $peak = (float) collect($points)->max('value');

    $fmt = fn ($v): string => $money ? __('ETB').' '.number_format($v, 0) : number_format($v);
    // 40000 -> "40k", 1500000 -> "1.5M"
    $compact = function (float $v): string {
        if ($v >= 1_000_000) {
            return rtrim(rtrim(number_format($v / 1_000_000, 1), '0'), '.').'M';
        }
        if ($v >= 1_000) {
            return rtrim(rtrim(number_format($v / 1_000, 1), '0'), '.').'k';
        }

        return number_format($v);
    };

    // Round the top of the axis up to a tidy number, so the gridlines read 0 / half / top.
    $top = 4.0;
    if ($peak > 0) {
        $magnitude = 10 ** floor(log10($peak));
        $top = $peak;
        foreach ([1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10] as $step) {
            if ($step * $magnitude >= $peak) {
                $top = $step * $magnitude;
                break;
            }
        }
        if (! $money) {
            // Counts are whole numbers, so keep the half-way line whole too.
            $top = max(4, ceil($top / 2) * 2);
        }
    }

    $labels = collect($points)->pluck('label')->all();
    $shown = collect($points)->map(fn ($p) => $fmt($p['value']))->all();

    $dateTicks = $n > 0
        ? collect(range(0, max($ticks - 1, 0)))
            ->map(fn (int $k): int => (int) round($k * ($n - 1) / max($ticks - 1, 1)))
            ->unique()->values()->all()
        : [];
@endphp

@if ($n === 0 || $peak <= 0)
    <div class="flex h-44 items-center justify-center rounded-lg bg-neutral-50 px-6 text-center text-sm text-muted">
        {{ $empty ?? __('Nothing recorded in this period yet.') }}
    </div>
@else
    <div x-data="{ i: null, labels: @js($labels), values: @js($shown) }">
        <div class="flex gap-3" aria-hidden="true">
            {{-- Y axis --}}
            <div class="relative h-44 w-10 shrink-0 text-xs tabular-nums leading-none text-muted">
                <span class="absolute end-0 top-0 -translate-y-1/2">{{ $compact($top) }}</span>
                <span class="absolute end-0 top-1/2 -translate-y-1/2">{{ $compact($top / 2) }}</span>
                <span class="absolute bottom-0 end-0 translate-y-1/2">0</span>
            </div>

            <div class="min-w-0 flex-1">
                <div class="relative h-44">
                    {{-- Gridlines --}}
                    <div class="absolute inset-0 flex flex-col justify-between">
                        <div class="border-t border-neutral-200"></div>
                        <div class="border-t border-dashed border-neutral-200"></div>
                        <div class="border-t border-neutral-300"></div>
                    </div>

                    {{-- Bars. Each column is a full-height hover target. --}}
                    <div class="relative flex h-full items-end gap-px">
                        @foreach ($points as $idx => $point)
                            <div class="flex h-full flex-1 items-end" @mouseenter="i = {{ $idx }}" @mouseleave="i = null">
                                @if ($point['value'] > 0)
                                    <div class="w-full rounded-t-sm transition-opacity"
                                         :class="i === null || i === {{ $idx }} ? 'opacity-100' : 'opacity-40'"
                                         style="height: {{ max(1.5, round($point['value'] / $top * 100, 2)) }}%; background: {{ $color }}"></div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    {{-- Tooltip --}}
                    <div x-show="i !== null" x-cloak
                         class="pointer-events-none absolute bottom-full z-10 mb-2 -translate-x-1/2 whitespace-nowrap rounded-lg bg-ink px-3 py-2 text-xs text-white shadow-lg"
                         :style="`left: ${Math.min(Math.max((i + 0.5) / labels.length * 100, 14), 86)}%`">
                        <span class="block text-neutral-300" x-text="labels[i]"></span>
                        <span class="block text-sm font-semibold tabular-nums" x-text="values[i]"></span>
                    </div>
                </div>

                {{-- X axis --}}
                <div class="mt-3 flex justify-between text-xs text-muted">
                    @foreach ($dateTicks as $t)
                        <span>{{ $points[$t]['label'] }}</span>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- The same data for screen readers --}}
        <table class="sr-only">
            <caption>{{ $label }}</caption>
            <thead><tr><th scope="col">{{ __('Day') }}</th><th scope="col">{{ __('Value') }}</th></tr></thead>
            <tbody>
                @foreach ($points as $point)
                    <tr><th scope="row">{{ $point['label'] }}</th><td>{{ $fmt($point['value']) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
