@php
    $checkin = \Illuminate\Support\Carbon::parse($checkIn);
    $checkout = \Illuminate\Support\Carbon::parse($checkOut);
    $field = 'mt-1 block w-full rounded-xl border-neutral-300 text-sm focus:border-ink focus:ring-ink';
@endphp

<x-layouts.app :title="__('Review your booking')">
    <section class="mx-auto max-w-5xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">

        <a href="{{ route('booking', ['checkin' => $checkIn, 'checkout' => $checkOut, 'guests' => $guests, 'room' => $type->slug]) }}"
           class="text-sm font-semibold text-ink hover:underline">&larr; {{ __('Back to results') }}</a>

        <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-ink sm:text-4xl">{{ __('Review and confirm') }}</h1>

        <div class="mt-10 grid gap-10 lg:grid-cols-5">

            {{-- Guest details --}}
            <form method="POST" action="{{ route('booking.store') }}" class="space-y-6 lg:col-span-3">
                @csrf
                <input type="hidden" name="type" value="{{ $type->slug }}">
                <input type="hidden" name="checkin" value="{{ $checkIn }}">
                <input type="hidden" name="checkout" value="{{ $checkOut }}">
                <input type="hidden" name="guests" value="{{ $guests }}">

                <h2 class="text-xl font-bold text-ink">{{ __('Who is staying?') }}</h2>

                @if ($errors->any())
                    <div role="alert" class="rounded-2xl bg-rose-50 px-5 py-4 text-sm text-brand-dark">
                        <ul class="list-disc space-y-1 pl-5">
                            @foreach ($errors->all() as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div>
                    <label for="guest_name" class="text-sm font-semibold text-ink">{{ __('Full name') }}</label>
                    <input id="guest_name" name="guest_name" type="text" required maxlength="150" autocomplete="name"
                           value="{{ old('guest_name', $user->name) }}" class="{{ $field }}">
                </div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <label for="guest_phone" class="text-sm font-semibold text-ink">{{ __('Phone') }}</label>
                        <input id="guest_phone" name="guest_phone" type="tel" required autocomplete="tel"
                               placeholder="+251 911 000 000" value="{{ old('guest_phone') }}" class="{{ $field }}">
                    </div>
                    <div>
                        <label for="guest_email" class="text-sm font-semibold text-ink">{{ __('Email') }} <span class="font-normal text-muted">({{ __('optional') }})</span></label>
                        <input id="guest_email" name="guest_email" type="email" autocomplete="email"
                               value="{{ old('guest_email', $user->email) }}" class="{{ $field }}">
                    </div>
                </div>

                <div>
                    <label for="notes" class="text-sm font-semibold text-ink">{{ __('Anything we should know?') }} <span class="font-normal text-muted">({{ __('optional') }})</span></label>
                    <textarea id="notes" name="notes" rows="3" maxlength="1000" class="{{ $field }}">{{ old('notes') }}</textarea>
                </div>

                <button type="submit"
                        class="inline-flex w-full items-center justify-center rounded-full bg-brand px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-dark active:scale-[.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                    {{ __('Confirm booking') }}
                </button>
                <p class="text-center text-sm text-muted">{{ __('You pay at the hotel. You can cancel from My bookings before you arrive.') }}</p>
            </form>

            {{-- Summary --}}
            <aside class="lg:col-span-2">
                <div class="rounded-3xl border border-neutral-200 p-6 shadow-sm lg:sticky lg:top-28">
                    <h2 class="text-lg font-bold text-ink">{{ __($type->name) }}</h2>
                    <p class="mt-1 text-sm text-muted">
                        {{ $checkin->translatedFormat('D, M j') }} &ndash; {{ $checkout->translatedFormat('D, M j, Y') }}
                        &middot; {{ trans_choice(':count guest|:count guests', $guests) }}
                    </p>

                    <ul class="mt-5 space-y-2 border-t border-neutral-200 pt-5 text-sm">
                        @foreach ($quote['breakdown'] as $night => $amount)
                            <li class="flex justify-between">
                                <span class="text-muted">{{ \Illuminate\Support\Carbon::parse($night)->translatedFormat('D, M j') }}</span>
                                <span class="text-ink">{{ __('ETB') }} {{ number_format($amount) }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <p class="mt-5 flex justify-between border-t border-neutral-200 pt-5 text-ink">
                        <span class="font-bold">{{ __('Total (:nights)', ['nights' => trans_choice(':count night|:count nights', $quote['nights'])]) }}</span>
                        <span class="font-extrabold">{{ __('ETB') }} {{ number_format($quote['total']) }}</span>
                    </p>
                </div>
            </aside>
        </div>
    </section>
</x-layouts.app>
