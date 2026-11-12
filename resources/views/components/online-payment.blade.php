@if ($state)
<section class="mt-10 rounded-3xl border border-neutral-200 p-6">
    <h2 class="font-bold text-ink">{{ __('Payment') }}</h2>

    @if ($state === 'paid')
        <p class="mt-3 rounded-2xl bg-green-50 px-5 py-4 text-sm font-medium text-green-800">
            {{ __('Paid in full') }}: {{ $currency }} {{ number_format($paid, 2) }}
        </p>
    @elseif ($state === 'review')
        <p role="alert" class="mt-3 rounded-2xl bg-amber-50 px-5 py-4 text-sm font-medium text-amber-900">
            {{ __('We received your payment, but the hotel needs to check it. Please contact reception and give them your booking reference.') }}
        </p>
    @else
        <p class="mt-3 text-sm text-muted">
            {{ __('Balance to pay') }}: <span class="font-semibold text-ink">{{ $currency }} {{ number_format($balance, 2) }}</span>
        </p>

        @if ($state === 'pending')
            <p class="mt-3 rounded-2xl bg-neutral-50 px-5 py-4 text-sm text-ink">
                {{ __('We are waiting for Chapa to confirm your payment. If you have already paid, check again in a minute.') }}
            </p>
            <a href="{{ route('chapa.return', $latest->tx_ref) }}"
               class="mt-4 inline-flex items-center justify-center rounded-full border border-ink px-5 py-2 text-sm font-semibold text-ink transition hover:bg-ink hover:text-white">
                {{ __('Check payment status') }}
            </a>
        @endif

        <form method="POST" action="{{ route('my-bookings.pay', $booking) }}" class="mt-4"
              onsubmit="this.querySelector('button[type=submit]').disabled = true">
            @csrf
            <button type="submit"
                    class="inline-flex items-center justify-center rounded-full bg-brand px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-dark active:scale-[.98] disabled:opacity-60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                {{ $state === 'pending' ? __('Start a new payment') : __('Pay :amount online', ['amount' => $currency.' '.number_format($balance, 2)]) }}
            </button>
        </form>
        <p class="mt-3 text-sm text-muted">{{ __('Secure payment with Chapa (Telebirr, cards and other methods). You can also pay at the hotel.') }}</p>
    @endif
</section>
@endif
