{{-- One figure in a strip. Put it inside a grid with "gap-px bg-neutral-200" so the cells share hairline dividers.
     href makes the whole cell a link; alert adds a dot for "something needs doing". The slot is for a trend or a bar. --}}
@props(['label', 'value', 'hint' => null, 'href' => null, 'alert' => false])

@php($tag = $href ? 'a' : 'div')

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class([
        'block bg-white p-4 sm:p-5',
        'transition-colors hover:bg-neutral-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ink' => $href,
    ]) }}>
    <p class="flex items-center gap-2 text-sm text-muted">
        @if ($alert)
            <span class="size-2 rounded-full bg-brand" aria-hidden="true"></span>
            <span class="sr-only">{{ __('Action needed') }}:</span>
        @endif
        {{ $label }}
    </p>
    <p class="mt-1.5 text-2xl font-semibold tabular-nums tracking-tight text-ink">{{ $value }}</p>
    @if ($hint)
        <p class="mt-0.5 text-sm text-muted">{{ $hint }}</p>
    @endif
    {{ $slot }}
</{{ $tag }}>
