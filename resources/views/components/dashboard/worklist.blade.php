{{-- A short list of things to do today (arrivals, departures). Rows go in the slot as <li>. --}}
@props(['title', 'count', 'empty', 'more' => null])

<div>
    <div class="flex items-center justify-between gap-3 px-5 py-3">
        <h3 class="flex items-center gap-2 text-base font-semibold text-ink">
            {{ $title }}
            <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-semibold tabular-nums text-neutral-700">{{ $count }}</span>
        </h3>
        @if ($more)
            <a href="{{ $more }}" class="text-sm font-semibold text-ink underline decoration-neutral-300 underline-offset-4 hover:decoration-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink">{{ __('See all') }}</a>
        @endif
    </div>

    @if ($count > 0)
        <ul class="divide-y divide-neutral-100 border-t border-neutral-100">
            {{ $slot }}
        </ul>
    @else
        <p class="border-t border-neutral-100 px-5 py-4 text-sm text-muted">{{ $empty }}</p>
    @endif
</div>
