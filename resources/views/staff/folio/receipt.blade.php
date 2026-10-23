<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Receipt') }} {{ $booking->reference }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: ui-monospace, "Courier New", monospace; font-size: 12px; line-height: 1.35; color: #000; margin: 0; padding: 1rem; background: #f3f4f6; }
        .actions { width: 80mm; margin: 0 auto 1rem; display: flex; gap: 1rem; align-items: center; font-family: ui-sans-serif, system-ui, sans-serif; }
        .actions button { font: inherit; padding: .4rem .9rem; border: 0; border-radius: .375rem; background: #111827; color: #fff; cursor: pointer; }
        .actions a { color: #374151; font-size: .85rem; }
        .ticket { width: 80mm; margin: 0 auto; background: #fff; padding: 4mm; }
        h1 { font-size: 15px; text-align: center; margin: 0 0 2mm; }
        .c { text-align: center; }
        .line { border-top: 1px dashed #000; margin: 2mm 0; }
        .row { display: flex; justify-content: space-between; gap: 3mm; }
        .row span:first-child { flex: 1; min-width: 0; overflow-wrap: anywhere; }
        .row span:last-child { white-space: nowrap; }
        .sub { padding-left: 3mm; font-size: 11px; }
        .b { font-weight: 700; }
        p { margin: 0; }
        @media print {
            body { background: #fff; padding: 0; }
            .actions { display: none; }
            .ticket { width: auto; margin: 0; padding: 0; }
            @page { size: 80mm auto; margin: 3mm; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button type="button" onclick="window.print()">{{ __('Print') }}</button>
        <a href="{{ route('staff.folio.show', $booking) }}">&larr; {{ __('Back to bill') }}</a>
    </div>

    <main class="ticket">
        <h1>{{ $property }}</h1>
        <p class="c">{{ __('Receipt') }} &middot; {{ $booking->reference }}</p>
        <p class="c">{{ now()->format('M j, Y H:i') }}</p>

        <div class="line"></div>
        <p>{{ $booking->guest_name }}</p>
        <p>{{ __('Room') }} {{ $booking->room->number }} ({{ $booking->room->roomType->name }})</p>
        <p>{{ $booking->check_in->format('M j') }} &ndash; {{ $booking->check_out->format('M j, Y') }} &middot; {{ $booking->nights() }} {{ Str::plural('night', $booking->nights()) }}</p>

        <div class="line"></div>
        <div class="row"><span>{{ __('Room') }} &times; {{ $booking->nights() }}</span><span>{{ number_format($folio['room_total'], 2) }}</span></div>

        @foreach ($folio['charges']->whereNull('voided_at') as $charge)
            <div class="row"><span>{{ $charge->description }}</span><span>{{ number_format($charge->amount, 2) }}</span></div>
            @if ($charge->quantity > 1)
                <p class="sub">{{ $charge->quantity }} &times; {{ number_format($charge->unit_price, 2) }}</p>
            @endif
        @endforeach

        @foreach ($folio['discounts']->whereNull('voided_at') as $discount)
            <div class="row"><span>{{ __('Discount') }}</span><span>-{{ number_format($discount->amount, 2) }}</span></div>
        @endforeach

        <div class="line"></div>
        <div class="row b"><span>{{ __('TOTAL (ETB)') }}</span><span>{{ number_format($folio['net'], 2) }}</span></div>

        @if ((float) $booking->deposit_amount > 0)
            <div class="row"><span>{{ __('Deposit') }}@if ($booking->deposit_method) ({{ __($booking->deposit_method->label()) }})@endif</span><span>-{{ number_format($booking->deposit_amount, 2) }}</span></div>
        @endif
        @if ((float) $booking->settlement_amount > 0)
            <div class="row"><span>{{ __('Paid') }}@if ($booking->settlement_method) ({{ __($booking->settlement_method->label()) }})@endif</span><span>-{{ number_format($booking->settlement_amount, 2) }}</span></div>
        @endif
        @include('staff.payments._lines-receipt')
        <div class="row b">
            <span>{{ $folio['balance'] < 0 ? __('CREDIT') : __('BALANCE DUE') }}</span>
            <span>{{ number_format(abs($folio['balance']), 2) }}</span>
        </div>

        <div class="line"></div>
        <p class="c">{{ __('Served by :name', ['name' => $printedBy]) }}</p>
        <p class="c">{{ __('Thank you!') }}</p>
        <p class="c">{{ __('Not a tax invoice.') }}</p>
    </main>
</body>
</html>
