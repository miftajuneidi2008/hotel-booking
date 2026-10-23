@php
    $type = $room->roomType;
    $photoUrls = $room->photos->map(fn ($photo) => asset('storage/'.$photo->path))->all();
@endphp

<x-layouts.app :title="__('Room :number', ['number' => $room->number])">

    <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">

        <nav aria-label="{{ __('Breadcrumb') }}" class="text-sm text-muted">
            <ol class="flex flex-wrap items-center gap-2">
                <li><a href="{{ route('rooms') }}" class="hover:text-ink hover:underline">{{ __('Rooms') }}</a></li>
                <li aria-hidden="true">/</li>
                <li><a href="{{ route('rooms', ['type' => $type->slug]) }}" class="hover:text-ink hover:underline">{{ __($type->name) }}</a></li>
                <li aria-hidden="true">/</li>
                <li aria-current="page" class="font-semibold text-ink">{{ __('Room :number', ['number' => $room->number]) }}</li>
            </ol>
        </nav>

        <h1 class="mt-6 text-3xl font-extrabold tracking-tight text-ink sm:text-4xl">{{ __($type->name) }}</h1>
        <p class="mt-2 text-muted">{{ __('Room :number', ['number' => $room->number]) }} &middot; {{ __('Floor :floor', ['floor' => $room->floor]) }} &middot; {{ __('Sleeps :count', ['count' => $type->capacity]) }}</p>

        <div class="mt-8 grid gap-10 lg:grid-cols-3">

            {{-- Photos and description --}}
            <div class="lg:col-span-2">
                @if ($photoUrls !== [])
                    <div x-data="{ active: 0, photos: @js($photoUrls) }">
                        <img src="{{ $photoUrls[0] }}" x-bind:src="photos[active]" alt="{{ __('Photo of room :number', ['number' => $room->number]) }}"
                             class="aspect-[16/9] w-full rounded-[1.75rem] object-cover">

                        @if (count($photoUrls) > 1)
                            <ul class="mt-3 flex gap-3 overflow-x-auto pb-1">
                                <template x-for="(photo, index) in photos" x-bind:key="index">
                                    <li class="shrink-0">
                                        <button type="button" x-on:click="active = index"
                                                x-bind:aria-current="active === index"
                                                x-bind:class="active === index ? 'ring-2 ring-ink' : 'opacity-70 hover:opacity-100'"
                                                class="block overflow-hidden rounded-xl transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink">
                                            <img x-bind:src="photo" alt="" class="h-16 w-24 object-cover sm:h-20 sm:w-28">
                                        </button>
                                    </li>
                                </template>
                            </ul>
                        @endif
                    </div>
                @else
                    <x-photo :alt="__('Room :number', ['number' => $room->number])" class="aspect-[16/9] rounded-[1.75rem]" />
                @endif

                @if ($type->description)
                    <h2 class="mt-10 text-xl font-bold text-ink">{{ __('About this room') }}</h2>
                    <p class="mt-3 leading-relaxed text-muted">{{ __($type->description) }}</p>
                @endif

                <dl class="mt-8 grid grid-cols-3 gap-4 rounded-3xl border border-neutral-200 p-6 text-center">
                    <div>
                        <dt class="text-sm text-muted">{{ __('Room') }}</dt>
                        <dd class="mt-1 font-bold text-ink">{{ $room->number }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted">{{ __('Floor') }}</dt>
                        <dd class="mt-1 font-bold text-ink">{{ $room->floor }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted">{{ __('Guests') }}</dt>
                        <dd class="mt-1 font-bold text-ink">{{ __('Up to :count', ['count' => $type->capacity]) }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Price and booking --}}
            <aside>
                <div class="rounded-3xl border border-neutral-200 p-6 shadow-sm lg:sticky lg:top-28">
                    <p class="text-ink">
                        <span class="text-2xl font-extrabold">{{ __('ETB') }} {{ number_format($tonightRate) }}</span>
                        <span class="text-muted">{{ __('/ night tonight') }}</span>
                    </p>

                    @if ($otherRates->isNotEmpty())
                        <h2 class="mt-6 text-sm font-bold text-ink">{{ __('Other rates') }}</h2>
                        <ul class="mt-2 space-y-2 text-sm">
                            @foreach ($otherRates as $rate)
                                <li class="flex items-start justify-between gap-4">
                                    <span class="text-muted">
                                        {{ __($rate->name) }}
                                        <span class="block text-xs">
                                            @if ($rate->isSeasonal())
                                                {{ $rate->starts_on->translatedFormat('M j') }} &ndash; {{ $rate->ends_on->translatedFormat('M j, Y') }}
                                            @else
                                                {{ __($rate->applies_on->label()) }}
                                            @endif
                                        </span>
                                    </span>
                                    <span class="whitespace-nowrap font-semibold text-ink">{{ __('ETB') }} {{ number_format($rate->amount) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <a href="{{ route('booking', ['room' => $type->slug]) }}"
                       class="mt-6 inline-flex w-full items-center justify-center rounded-full bg-brand px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-dark active:scale-[.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                        {{ __('Book this room') }}
                    </a>

                    <a href="{{ route('rooms', ['type' => $type->slug]) }}" class="mt-4 block text-center text-sm font-semibold text-ink hover:underline">
                        &larr; {{ __('Back to :type rooms', ['type' => __($type->name)]) }}
                    </a>
                </div>
            </aside>
        </div>
    </section>

</x-layouts.app>
