<x-layouts.app :title="__('Book a room')">

    <section class="mx-auto max-w-4xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
        <h1 class="text-4xl font-extrabold tracking-tight text-ink sm:text-5xl">{{ __('Book your stay') }}</h1>
        <p class="mt-4 text-lg text-muted">{{ __('Choose your dates and number of guests.') }}</p>

        @session('error')
            <p role="alert" class="mt-6 rounded-2xl bg-rose-50 px-5 py-4 text-sm font-medium text-brand-dark">{{ $value }}</p>
        @endsession

        <div class="mt-10">
            <x-booking-search />
        </div>

        @if ($search)
            @php
                $checkin = \Illuminate\Support\Carbon::parse($search['checkin']);
                $checkout = \Illuminate\Support\Carbon::parse($search['checkout']);
                $nights = (int) $checkin->diffInDays($checkout);
            @endphp

            <p class="mt-8 text-sm text-muted">
                {{ $checkin->translatedFormat('D, M j') }} &ndash; {{ $checkout->translatedFormat('D, M j, Y') }}
                &middot; {{ trans_choice(':count night|:count nights', $nights) }}
                &middot; {{ trans_choice(':count guest|:count guests', $search['guests']) }}
            </p>
        @endif
    </section>

    @if ($search)
        <section class="border-t border-neutral-200 bg-neutral-50 py-12 lg:py-16">
            <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">

                @forelse ($results as $row)
                    @php
                        $type = $row['type'];
                        $quote = $row['quote'];
                        $isPreferred = $type->slug === $preferred;
                    @endphp

                    <article class="flex flex-col overflow-hidden rounded-3xl border bg-white sm:flex-row {{ $isPreferred ? 'border-ink' : 'border-neutral-200' }}">
                        <x-photo :src="$row['photo'] ? 'storage/'.$row['photo'] : null" :alt="__($type->name)" :tone="$loop->index"
                                 class="aspect-[4/3] sm:aspect-auto sm:w-64 sm:shrink-0" />

                        <div class="flex flex-1 flex-col justify-between gap-6 p-6">
                            <div>
                                @if ($isPreferred)
                                    <p class="text-xs font-bold uppercase tracking-wide text-brand-dark">{{ __('Your selection') }}</p>
                                @endif
                                <h2 class="text-xl font-bold text-ink">{{ __($type->name) }}</h2>
                                <p class="mt-1 text-sm text-muted">{{ __('Sleeps up to :count', ['count' => $type->capacity]) }}</p>
                                @if ($type->description)
                                    <p class="mt-3 text-sm leading-relaxed text-muted">{{ __($type->description) }}</p>
                                @endif
                                @if ($row['available'] <= 3)
                                    <p class="mt-3 text-sm font-semibold text-brand-dark">{{ __('Only :count left for these dates', ['count' => $row['available']]) }}</p>
                                @endif
                            </div>

                            <div class="flex items-end justify-between gap-4">
                                <div>
                                    <p class="text-ink">
                                        <span class="text-xl font-extrabold">{{ __('ETB') }} {{ number_format($quote['total']) }}</span>
                                        <span class="text-muted">{{ __('total') }}</span>
                                    </p>
                                    <p class="text-sm text-muted">{{ __('about :amount / night', ['amount' => __('ETB').' '.number_format($quote['average'])]) }}</p>
                                </div>

                                <a href="{{ route('booking.review', ['type' => $type->slug, 'checkin' => $search['checkin'], 'checkout' => $search['checkout'], 'guests' => $search['guests']]) }}"
                                   class="rounded-full bg-brand px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-dark active:scale-[.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                                    {{ __('Select') }}
                                </a>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-3xl border border-neutral-200 bg-white p-8 text-center">
                        <h2 class="text-lg font-bold text-ink">{{ __('No rooms available') }}</h2>
                        <p class="mt-2 text-muted">
                            {{ __('Nothing is free for :guests on those dates.', ['guests' => trans_choice(':count guest|:count guests', $search['guests'])]) }}
                            {{ __('Try different dates, or call us and we will help you.') }}
                        </p>
                        <p class="mt-4 font-semibold text-ink">{{ config('hotel.contact.phone') }}</p>
                    </div>
                @endforelse

            </div>
        </section>
    @else
        <section class="border-t border-neutral-200 bg-neutral-50 py-16 lg:py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 class="text-2xl font-extrabold tracking-tight text-ink">{{ __('Room types') }}</h2>

                <div class="mt-10 grid gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($roomTypes as $room)
                        <x-room-card :room="$room" :tone="$loop->index" :link="false" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

</x-layouts.app>
