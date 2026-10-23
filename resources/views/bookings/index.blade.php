<x-dashboard-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('My bookings') }}</h2>
            <a href="{{ route('booking') }}" class="text-sm font-semibold text-ink hover:underline">{{ __('Book a room') }}</a>
        </div>
    </x-slot>

    <section class="mx-auto max-w-4xl">

        @session('status')
            <p class="mt-6 rounded-2xl bg-green-50 px-5 py-4 text-sm font-medium text-green-800">{{ $value }}</p>
        @endsession

        <div class="mt-8 space-y-4">
            @forelse ($bookings as $booking)
                <a href="{{ route('my-bookings.show', $booking) }}"
                   class="block rounded-3xl border border-neutral-200 p-6 transition hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-bold text-ink">{{ __($booking->room->roomType->name) }}</p>
                            <p class="mt-1 text-sm text-muted">
                                {{ $booking->check_in->translatedFormat('D, M j') }} &ndash; {{ $booking->check_out->translatedFormat('D, M j, Y') }}
                                &middot; {{ trans_choice(':count night|:count nights', $booking->nights()) }}
                            </p>
                            <p class="mt-1 text-sm text-muted">{{ __('Reference :reference', ['reference' => $booking->reference]) }}</p>
                        </div>
                        <x-booking-status-badge :status="$booking->status" />
                    </div>
                </a>
            @empty
                <div class="rounded-3xl border border-neutral-200 p-8 text-center">
                    <p class="text-muted">{{ __('You have no bookings yet.') }}</p>
                    <a href="{{ route('booking') }}"
                       class="mt-4 inline-flex rounded-full bg-brand px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark">{{ __('Find a room') }}</a>
                </div>
            @endforelse
        </div>

        <div class="mt-8">{{ $bookings->links() }}</div>
    </section>
</x-dashboard-layout>