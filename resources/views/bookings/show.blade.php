<x-dashboard-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('My bookings') }}</h2>
    </x-slot>

    <section class="mx-auto max-w-3xl">

        <a href="{{ route('my-bookings.index') }}" class="text-sm font-semibold text-ink hover:underline">&larr; {{ __('My bookings') }}</a>

        @session('status')
            <p class="mt-6 rounded-2xl bg-green-50 px-5 py-4 text-sm font-medium text-green-800">{{ $value }}</p>
        @endsession
        @session('error')
            <p role="alert" class="mt-6 rounded-2xl bg-rose-50 px-5 py-4 text-sm font-medium text-brand-dark">{{ $value }}</p>
        @endsession

        <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-3xl font-extrabold tracking-tight text-ink">{{ __('Booking :reference', ['reference' => $booking->reference]) }}</h1>
            <x-booking-status-badge :status="$booking->status" />
        </div>

        <dl class="mt-8 grid gap-6 rounded-3xl border border-neutral-200 p-6 sm:grid-cols-2">
            <div>
                <dt class="text-sm text-muted">{{ __('Room') }}</dt>
                <dd class="mt-1 font-semibold text-ink">{{ __($booking->room->roomType->name) }}</dd>
            </div>
            <div>
                <dt class="text-sm text-muted">{{ __('Guests') }}</dt>
                <dd class="mt-1 font-semibold text-ink">{{ $booking->guests }}</dd>
            </div>
            <div>
                <dt class="text-sm text-muted">{{ __('Check in') }}</dt>
                <dd class="mt-1 font-semibold text-ink">{{ $booking->check_in->translatedFormat('D, M j, Y') }}</dd>
            </div>
            <div>
                <dt class="text-sm text-muted">{{ __('Check out') }}</dt>
                <dd class="mt-1 font-semibold text-ink">{{ $booking->check_out->translatedFormat('D, M j, Y') }}</dd>
            </div>
            <div>
                <dt class="text-sm text-muted">{{ __('Name') }}</dt>
                <dd class="mt-1 font-semibold text-ink">{{ $booking->guest_name }}</dd>
            </div>
            <div>
                <dt class="text-sm text-muted">{{ __('Phone') }}</dt>
                <dd class="mt-1 font-semibold text-ink">{{ $booking->guest_phone }}</dd>
            </div>
            <div class="sm:col-span-2 border-t border-neutral-200 pt-6">
                <dt class="text-sm text-muted">{{ __('Total for :nights', ['nights' => trans_choice(':count night|:count nights', $booking->nights())]) }}</dt>
                <dd class="mt-1 text-2xl font-extrabold text-ink">{{ __('ETB') }} {{ number_format($booking->total_amount) }}</dd>
                <dd class="mt-1 text-sm text-muted">{{ __('Payable at the hotel.') }}</dd>
            </div>
        </dl>

        @if ($booking->status === \App\Enums\BookingStatus::Cancelled)
            <p class="mt-6 text-sm text-muted">{{ __('Cancelled on :date', ['date' => $booking->cancelled_at->translatedFormat('M j, Y')]) }}: {{ $booking->cancellation_reason }}</p>
        @endif

        @can('cancel', $booking)
            <form method="POST" action="{{ route('my-bookings.cancel', $booking) }}" class="mt-10 rounded-3xl border border-neutral-200 p-6"
                  onsubmit="return confirm(@js(__('Cancel this booking?')))">
                @csrf
                @method('PATCH')
                <h2 class="font-bold text-ink">{{ __('Cancel this booking') }}</h2>
                <label for="reason" class="mt-4 block text-sm font-semibold text-ink">{{ __('Reason') }}</label>
                <input id="reason" name="reason" type="text" required maxlength="255" value="{{ old('reason') }}"
                       class="mt-1 block w-full rounded-xl border-neutral-300 text-sm focus:border-ink focus:ring-ink">
                @error('reason')
                    <p class="mt-2 text-sm text-brand-dark">{{ $message }}</p>
                @enderror
                <button type="submit"
                        class="mt-4 rounded-full border border-ink px-5 py-2 text-sm font-semibold text-ink transition hover:bg-ink hover:text-white">
                    {{ __('Cancel booking') }}
                </button>
            </form>
        @endcan
    </section>
</x-dashboard-layout>