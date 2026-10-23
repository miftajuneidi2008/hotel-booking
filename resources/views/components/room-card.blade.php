
@props([
    'room',
    'link' => true,
    'tone' => 0,   // placeholder colour when the photo file is missing
    'href' => null, // overrides the default link to the booking page
    'cta' => 'Book',
])

<article class="group">
    <div class="relative overflow-hidden rounded-[1.75rem]">
        <x-photo :src="$room['image'] ?? null"
                 :alt="__($room['name'])"
                 :tone="$tone"
                 class="aspect-[4/3] transition-transform duration-500 group-hover:scale-105 motion-reduce:transition-none" />

        @if (! empty($room['tag']))
            <span class="absolute left-4 top-4 rounded-full bg-white px-3 py-1 text-xs font-bold text-ink shadow-md">
                {{ __($room['tag']) }}
            </span>
        @endif
    </div>

    <div class="mt-4 flex items-start justify-between gap-4">
        <div>
            <h3 class="text-lg font-bold text-ink">{{ __($room['name']) }}</h3>
            <p class="mt-1 text-sm leading-relaxed text-muted">{{ __($room['description']) }}</p>
        </div>
    </div>

    <div class="mt-3 flex items-center justify-between gap-4">
        <p class="text-ink">
            <span class="font-bold">{{ __('ETB') }} {{ number_format($room['price']) }}</span>
            <span class="text-muted">{{ __('/ night') }}</span>
        </p>

        @if ($link)
            <a href="{{ $href ?? route('booking', ['room' => $room['slug']]) }}"
               class="rounded-full border border-ink px-5 py-2 text-sm font-semibold text-ink transition hover:bg-ink hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink">
                {{ __($cta) }}
            </a>
        @endif
    </div>
</article>