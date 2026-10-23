@props([
    'variant' => 'menu',   // menu = dropdown (desktop, headers), inline = row of links (mobile menu)
    'align' => 'right',    // menu only: which edge the dropdown lines up with (left or right)
])

@php
    $locales = config('locales.supported', []);
    $current = app()->getLocale();
    $linkBase = 'flex items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink';
@endphp

@if (count($locales) > 1)
    @if ($variant === 'inline')
        <ul {{ $attributes->class(['flex flex-wrap gap-2']) }} aria-label="{{ __('Language') }}">
            @foreach ($locales as $code => $name)
                <li>
                    <a href="{{ route('locale.switch', $code) }}" lang="{{ $code }}" hreflang="{{ $code }}"
                       @if ($code === $current) aria-current="true" @endif
                       class="inline-flex rounded-full border px-4 py-2 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink {{ $code === $current ? 'border-ink bg-ink text-white' : 'border-neutral-300 text-ink hover:border-ink' }}">
                        {{ $name }}
                    </a>
                </li>
            @endforeach
        </ul>
    @else
        <details {{ $attributes->class(['relative']) }}>
            <summary class="flex cursor-pointer list-none items-center gap-2 rounded-full px-3 py-2 text-sm font-semibold text-ink transition-colors hover:bg-neutral-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink [&::-webkit-details-marker]:hidden">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/>
                </svg>
                <span lang="{{ $current }}">{{ $locales[$current] ?? $current }}</span>
                <span class="sr-only">{{ __('Language') }}</span>
            </summary>

            <ul class="absolute {{ $align === 'left' ? 'left-0' : 'right-0' }} z-50 mt-2 min-w-44 rounded-2xl border border-neutral-200 bg-white p-1 shadow-lg">
                @foreach ($locales as $code => $name)
                    <li>
                        <a href="{{ route('locale.switch', $code) }}" lang="{{ $code }}" hreflang="{{ $code }}"
                           @if ($code === $current) aria-current="true" @endif
                           class="{{ $linkBase }} {{ $code === $current ? 'bg-neutral-100 font-bold text-ink' : 'text-ink hover:bg-neutral-100' }}">
                            {{ $name }}
                            @if ($code === $current)
                                <svg class="size-4 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif
@endif
