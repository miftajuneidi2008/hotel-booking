<x-layouts.app :title="__($selectedType?->name ?? 'Rooms')">

    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
        <h1 class="text-4xl font-extrabold tracking-tight text-ink sm:text-5xl">
            {{ __($selectedType?->name ?? 'Our rooms') }}
        </h1>
        <p class="mt-4 max-w-2xl text-lg text-muted">
            @if ($selectedType)
                {{ __($selectedType->description ?: 'Choose a room below.') }}
                {{ __('Sleeps up to :count.', ['count' => $selectedType->capacity]) }}
            @else
                {{ __('Prices shown are per night for tonight.') }}
            @endif
        </p>

        @if ($navTypes->isNotEmpty())
            <nav aria-label="{{ __('Filter rooms by type') }}" class="-mx-4 mt-10 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0">
                <ul class="flex gap-2 sm:flex-wrap">
                    <li>
                        <a href="{{ route('rooms') }}"
                           @if (! $selectedType && ! $typeNotFound) aria-current="page" @endif
                           class="inline-flex items-center gap-2 whitespace-nowrap rounded-full border px-5 py-2.5 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink {{ ! $selectedType && ! $typeNotFound ? 'border-ink bg-ink text-white' : 'border-neutral-300 text-ink hover:border-ink' }}">
                            {{ __('All rooms') }}
                            <span class="text-xs font-bold opacity-70">{{ $totalCount }}</span>
                        </a>
                    </li>

                    @foreach ($navTypes as $type)
                        @php($isActive = $selectedType?->is($type))
                        <li>
                            <a href="{{ route('rooms', ['type' => $type->slug]) }}"
                               @if ($isActive) aria-current="page" @endif
                               class="inline-flex items-center gap-2 whitespace-nowrap rounded-full border px-5 py-2.5 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink {{ $isActive ? 'border-ink bg-ink text-white' : 'border-neutral-300 text-ink hover:border-ink' }}">
                                {{ __($type->name) }}
                                <span class="text-xs font-bold opacity-70">{{ $counts[$type->id] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif

        @if ($rooms->isEmpty())
            <p class="mt-12 text-muted">
                @if ($typeNotFound)
                    {{ __('We could not find that room type. Pick one from the list above.') }}
                @elseif ($selectedType)
                    {{ __('No rooms of this type are available right now.') }}
                @else
                    {{ __('No rooms are available to show right now. Please check back soon.') }}
                @endif
            </p>
        @else
            <p class="mt-6 text-sm text-muted">{{ trans_choice(':count room|:count rooms', $rooms->count()) }}</p>

            <div class="mt-6 grid gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($rooms as $room)
                    <x-room-card :room="$room" :tone="$loop->index" :href="$room['href']" cta="View" />
                @endforeach
            </div>
        @endif
    </section>

</x-layouts.app>
