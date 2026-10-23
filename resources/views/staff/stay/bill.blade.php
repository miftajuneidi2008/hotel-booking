<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Stay statement') }} {{ $booking->reference }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { font-family: ui-sans-serif, system-ui, sans-serif; color: #111; margin: 0; padding: 2rem 1rem; background: #f3f4f6; }
        .sheet { max-width: 44rem; margin: 0 auto; background: #fff; padding: 2.5rem; border-radius: .5rem; }
        h1 { font-size: 1.5rem; margin: 0; }
        h2 { font-size: 1rem; margin: 2rem 0 .5rem; }
        p { margin: .25rem 0; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        td, th { padding: .4rem 0; text-align: left; border-bottom: 1px solid #e5e7eb; }
        td.r, th.r { text-align: right; }
        .muted { color: #6b7280; font-size: .85rem; }
        .total td { font-weight: 700; }
        .actions { max-width: 44rem; margin: 0 auto 1rem; display: flex; gap: 1rem; align-items: center; }
        button { font: inherit; padding: .5rem 1rem; border: 0; border-radius: .375rem; background: #111827; color: #fff; cursor: pointer; }
        a { color: #374151; }
        .note { margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { padding: 0; border-radius: 0; max-width: none; }
            .actions { display: none; }
            @page { size: A4; margin: 18mm; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button type="button" onclick="window.print()">{{ __('Print') }}</button>
        <a href="{{ route('staff.bookings.show', $booking) }}">&larr; {{ __('Back to booking') }}</a>
    </div>

    <main class="sheet">
        <h1>{{ $property }}</h1>
        <p class="muted">{{ __('Stay statement') }} &middot; {{ $booking->reference }}</p>

        <h2>{{ __('Guest') }}</h2>
        <p>{{ $booking->guest_name }}</p>
        <p class="muted">{{ $booking->guest_phone }}@if ($booking->id_number) &middot; {{ __($booking->id_type?->label()) }} {{ $booking->id_number }}@endif</p>

        <h2>{{ __('Stay') }}</h2>
        <p>
            {{ __('Room') }} {{ $booking->room->number }} ({{ $booking->room->roomType->name }}),
            {{ $booking->check_in->format('M j, Y') }} &ndash; {{ $booking->check_out->format('M j, Y') }},
            {{ $booking->nights() }} {{ Str::plural('night', $booking->nights()) }}, {{ $booking->guests }} {{ Str::plural('guest', $booking->guests) }}
        </p>
        @foreach ($booking->roomMoves as $move)
            <p class="muted">{{ $move->moved_on->format('M j') }}: {{ __('moved from room :from to room :to', ['from' => $move->fromRoom->number, 'to' => $move->toRoom->number]) }}</p>
        @endforeach
        @if ($booking->checked_in_at)
            <p class="muted">{{ __('Checked in') }} {{ $booking->checked_in_at->format('M j, Y H:i') }}@if ($booking->checked_out_at) &middot; {{ __('checked out') }} {{ $booking->checked_out_at->format('M j, Y H:i') }}@endif</p>
        @endif

        <h2>{{ __('Charges') }}</h2>
        <table>
            <tbody>
                @foreach ($booking->rate_breakdown as $night => $amount)
                    <tr>
                        <td>{{ __('Room') }} &middot; {{ \Illuminate\Support\Carbon::parse($night)->format('D, M j') }}</td>
                        <td class="r">ETB {{ number_format($amount, 2) }}</td>
                    </tr>
                @endforeach
                @include('staff.folio._rows-print')
                <tr class="total"><td>{{ __('Total') }}</td><td class="r">ETB {{ number_format($booking->folioTotal(), 2) }}</td></tr>
            </tbody>
        </table>

        <h2>{{ __('Payments') }}</h2>
        <table>
            <tbody>
                <tr>
                    <td>{{ __('Deposit') }}@if ($booking->deposit_method) ({{ __($booking->deposit_method->label()) }})@endif</td>
                    <td class="r">ETB {{ number_format($booking->deposit_amount, 2) }}</td>
                </tr>
                @if ((float) $booking->settlement_amount > 0)
                    <tr>
                        <td>{{ __('Paid at check-out') }}@if ($booking->settlement_method) ({{ __($booking->settlement_method->label()) }}@if ($booking->settlement_reference), {{ $booking->settlement_reference }}@endif)@endif</td>
                        <td class="r">ETB {{ number_format($booking->settlement_amount, 2) }}</td>
                    </tr>
                @endif
                <tr class="total"><td>{{ __('Balance due') }}</td><td class="r">ETB {{ number_format($booking->balanceDue(), 2) }}</td></tr>
            </tbody>
        </table>

        @if ((float) $booking->checkout_unpaid_amount > 0)
            <p class="muted" style="margin-top:1rem">
                {{ __('Left unpaid at check-out: ETB :amount. Approved by :name. Reason: :reason', ['amount' => number_format($booking->checkout_unpaid_amount, 2), 'name' => $booking->checkoutApprovedBy?->name ?? '—', 'reason' => $booking->checkout_override_reason]) }}
            </p>
        @endif

        <p class="muted note">{{ __('This is a statement of the stay, not a tax invoice.') }}</p>
    </main>
</body>
</html>
