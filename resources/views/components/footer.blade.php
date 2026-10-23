
@php
    $contact = config('hotel.contact', []);
    $links = collect(config('navigation.links', []))->map(fn ($l) => [
        'label' => __($l['label']),
        'href' => isset($l['route']) && Route::has($l['route']) ? route($l['route']) : url($l['url'] ?? '#'),
    ]);
@endphp

<footer id="contact" class="scroll-mt-24 border-t border-neutral-200 bg-neutral-50">
    <div class="mx-auto grid max-w-7xl gap-12 px-4 py-16 sm:px-6 md:grid-cols-3 lg:px-8">

        <div>
            <a href="{{ url('/') }}" class="flex w-fit items-center gap-2 text-brand">
                <svg class="size-8" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M4 15L16 5l12 10"/><path d="M7 13v13h18V13"/><path d="M13 26v-7a3 3 0 016 0v7"/>
                </svg>
                <span class="text-xl font-extrabold tracking-tight">{{ config('app.name') }}</span>
            </a>
            <p class="mt-4 max-w-xs leading-relaxed text-muted">
                {{ __('Comfortable rooms and warm hospitality. Book direct in a few clicks.') }}
            </p>
        </div>

        <div>
            <h2 class="text-sm font-bold text-ink">{{ __('Explore') }}</h2>
            <ul class="mt-4 space-y-3">
                @foreach ($links as $link)
                    <li><a href="{{ $link['href'] }}" class="text-muted transition-colors hover:text-ink">{{ $link['label'] }}</a></li>
                @endforeach
                <li><a href="{{ route('booking') }}" class="font-semibold text-brand transition-colors hover:text-brand-dark">{{ __('Book now') }}</a></li>
            </ul>
        </div>

        <div>
            <h2 class="text-sm font-bold text-ink">{{ __('Contact us') }}</h2>
            <address class="mt-4 space-y-3 not-italic text-muted">
                @if (! empty($contact['address'])) <p>{{ __($contact['address']) }}</p> @endif
                @if (! empty($contact['phone']))
                    <p><a href="tel:{{ preg_replace('/[^+\d]/', '', $contact['phone']) }}" class="transition-colors hover:text-ink">{{ $contact['phone'] }}</a></p>
                @endif
                @if (! empty($contact['email']))
                    <p><a href="mailto:{{ $contact['email'] }}" class="break-all transition-colors hover:text-ink">{{ $contact['email'] }}</a></p>
                @endif
            </address>
        </div>
    </div>

    <div class="border-t border-neutral-200">
        <p class="mx-auto max-w-7xl px-4 py-6 text-sm text-muted sm:px-6 lg:px-8">
            &copy; {{ now()->year }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
        </p>
    </div>
</footer>