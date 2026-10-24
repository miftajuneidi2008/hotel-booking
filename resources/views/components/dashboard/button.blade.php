{{-- Link styled as a button. variant: primary | secondary | quiet. size: md | sm.
     Primary uses the darker brand red so white text passes WCAG AA contrast. --}}
@props(['variant' => 'secondary', 'size' => 'md'])

@php
    $look = match ($variant) {
        'primary' => 'bg-brand-dark text-white hover:bg-[#c9134f]',
        'quiet' => 'text-ink hover:bg-neutral-100',
        default => 'bg-white text-ink ring-1 ring-inset ring-neutral-300 hover:bg-neutral-50',
    };
    $dimensions = $size === 'sm' ? 'px-3 py-1.5 text-sm' : 'px-4 py-2 text-sm';
@endphp

<a {{ $attributes->class([
    'inline-flex shrink-0 items-center justify-center gap-2 rounded-lg font-semibold transition-colors',
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink',
    $look,
    $dimensions,
]) }}>{{ $slot }}</a>
