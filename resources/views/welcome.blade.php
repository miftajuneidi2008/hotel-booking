@php
    $rooms = config('hotel.rooms', []);
    $amenities = config('hotel.amenities', []);
@endphp

<x-layouts.app :title="__('Book your stay')">

    {{-- Hero --}}
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute -right-40 -top-40 size-[36rem] rounded-full bg-rose-100 opacity-70 blur-3xl" aria-hidden="true"></div>

        <div class="relative mx-auto grid max-w-7xl items-center gap-14 px-4 pb-20 pt-12 sm:px-6 lg:grid-cols-12 lg:gap-8 lg:px-8 lg:pb-28 lg:pt-20">

            <div class="lg:col-span-7">
                <p class="rise inline-flex items-center gap-2 rounded-full border border-neutral-200 bg-white px-4 py-1.5 text-sm font-medium text-ink shadow-xs" style="--i: 0">
                    <span class="size-2 rounded-full bg-brand" aria-hidden="true"></span>
                    {{ __('Book direct with us') }}
                </p>

                <h1 class="rise mt-6 text-5xl font-extrabold leading-[1.05] tracking-tight text-ink sm:text-6xl lg:text-7xl" style="--i: 1">
                    {{ __('Your home away') }} <span class="text-brand">{{ __('from home.') }}</span>
                </h1>

                <p class="rise mt-6 max-w-xl text-lg leading-relaxed text-muted" style="--i: 2">
                    {{ __('Comfortable rooms, warm hospitality, and simple booking. Pick your dates and reserve in minutes.') }}
                </p>

                <div class="rise mt-10" style="--i: 3">
                    <x-booking-search />
                </div>

                <ul class="rise mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm font-medium text-ink" style="--i: 4">
                    @foreach (['Free Wi-Fi', 'Breakfast available', 'Airport pickup'] as $perk)
                        <li class="flex items-center gap-2">
                            <svg class="size-4 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                            {{ __($perk) }}
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Photo mosaic (add hero-1.jpg, hero-2.jpg, hero-3.jpg to public/images) --}}
            <div class="lg:col-span-5">
                <div class="relative grid h-[26rem] grid-cols-2 grid-rows-2 gap-4 sm:h-[32rem]">
                    <div class="rise row-span-2 overflow-hidden rounded-[2rem]" style="--i: 2">
                        <x-photo src="images/hero-1.jpg" alt="{{ __('Guest room') }}" :tone="0" class="h-full" />
                    </div>
                    <div class="rise overflow-hidden rounded-[2rem]" style="--i: 3">
                        <x-photo src="images/hero-2.jpg" alt="{{ __('Breakfast area') }}" :tone="1" class="h-full" />
                    </div>
                    <div class="rise overflow-hidden rounded-[2rem]" style="--i: 4">
                        <x-photo src="images/hero-3.jpg" alt="{{ __('Reception') }}" :tone="2" class="h-full" />
                    </div>

                    <div class="float absolute bottom-8 left-3 flex items-center gap-3 rounded-2xl bg-white p-4 shadow-xl ring-1 ring-black/5">
                        <span class="grid size-10 place-items-center rounded-full bg-brand text-white" aria-hidden="true">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-bold text-ink">{{ __('Easy booking') }}</p>
                            <p class="text-xs text-muted">{{ __('Pick your dates in seconds') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Rooms --}}
    <section id="rooms" class="scroll-mt-24 py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="reveal max-w-2xl">
                <h2 class="text-3xl font-extrabold tracking-tight text-ink sm:text-4xl">{{ __('Choose your stay') }}</h2>
                <p class="mt-3 text-lg text-muted">{{ __('Simple, comfortable rooms for every kind of trip.') }}</p>
            </div>

            <div class="mt-12 grid gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($rooms as $room)
                    <div class="reveal">
                        <x-room-card :room="$room" :tone="$loop->index" :href="route('rooms', ['type' => \Illuminate\Support\Str::slug($room['name'])])" />
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Amenities --}}
    <section id="amenities" class="scroll-mt-24 bg-neutral-50 py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="reveal max-w-2xl">
                <h2 class="text-3xl font-extrabold tracking-tight text-ink sm:text-4xl">{{ __('Everything for a good stay') }}</h2>
                <p class="mt-3 text-lg text-muted">{{ __('The small things that make you feel at home.') }}</p>
            </div>

            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($amenities as $item)
                    <div class="reveal rounded-3xl bg-white p-8 ring-1 ring-neutral-200 transition duration-300 hover:-translate-y-1 hover:shadow-xl motion-reduce:transition-none motion-reduce:hover:translate-y-0">
                        <span class="grid size-12 place-items-center rounded-2xl bg-rose-50 text-brand">
                            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $item['icon'] }}"/></svg>
                        </span>
                        <h3 class="mt-6 text-lg font-bold text-ink">{{ __($item['title']) }}</h3>
                        <p class="mt-2 leading-relaxed text-muted">{{ __($item['text']) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Call to action --}}
    <section class="py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="reveal relative overflow-hidden rounded-[2rem] bg-linear-to-br from-brand to-rose-600 px-8 py-16 text-center sm:px-16 sm:py-20">
                <div class="pointer-events-none absolute -left-16 -top-16 size-64 rounded-full bg-white/10" aria-hidden="true"></div>
                <div class="pointer-events-none absolute -bottom-20 -right-10 size-72 rounded-full bg-white/10" aria-hidden="true"></div>

                <h2 class="relative mx-auto max-w-2xl text-3xl font-extrabold tracking-tight text-white sm:text-5xl">{{ __('Ready for your stay?') }}</h2>
                <p class="relative mx-auto mt-4 max-w-xl text-lg text-white/90">{{ __('Choose your dates and book your room in a few clicks.') }}</p>
                <a href="{{ route('booking') }}"
                   class="relative mt-8 inline-flex rounded-full bg-white px-8 py-3.5 font-bold text-brand shadow-lg transition hover:scale-[1.03] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white motion-reduce:transition-none motion-reduce:hover:scale-100">
                    {{ __('Book now') }}
                </a>
            </div>
        </div>
    </section>

</x-layouts.app>