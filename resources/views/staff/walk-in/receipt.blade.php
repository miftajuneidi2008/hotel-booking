<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Receipt') }} {{ $bill->number }}</title>
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
        <a href="{{ route('staff.walk-in-bills.show', $bill) }}">&larr; {{ __('Back to bill') }}</a>
    </div>

    <main class="ticket">
        <h1>{{ $property }}</h1>
        <p class="c">{{ __('Receipt') }} &middot; {{ $bill->number }}</p>
        <p class="c">{{ ($bill->paid_at ?? now())->format('M j, Y H:i') }}</p>

        @if ($bill->customer_name)
            <div class="line"></div>
            <p>{{ $bill->customer_name }}</p>
        @endif

        <div class="line"></div>
        @foreach ($bill->lines as $line)
            <div class="row"><span>{{ $line->description }}</span><span>{{ number_format($line->amount, 2) }}</span></div>
            @if ($line->quantity > 1)
                <p class="sub">{{ $line->quantity }} &times; {{ number_format($line->unit_price, 2) }}</p>
            @endif
        @endforeach

        <div class="line"></div>
        <div class="row b"><span>{{ __('TOTAL (ETB)') }}</span><span>{{ number_format($bill->total, 2) }}</span></div>

        @if ($bill->status === \App\Enums\WalkInStatus::Paid)
            <div class="row"><span>{{ __('Paid') }} ({{ __($bill->payment_method?->label()) }})</span><span>{{ number_format($bill->total, 2) }}</span></div>
            @if ($bill->payment_reference)<p class="sub">{{ __('Ref') }}: {{ $bill->payment_reference }}</p>@endif
        @elseif ($bill->status === \App\Enums\WalkInStatus::Transferred)
            <p class="c">{{ __('Charged to room bill') }}@if ($bill->booking) &middot; {{ $bill->booking->guest_name }}@endif</p>
        @else
            <div class="row b"><span>{{ __('BALANCE DUE') }}</span><span>{{ number_format($bill->total, 2) }}</span></div>
        @endif

        <div class="line"></div>
        <p class="c">{{ __('Served by :name', ['name' => $printedBy]) }}</p>
        <p class="c">{{ __('Thank you!') }}</p>
        <p class="c">{{ __('Not a tax invoice.') }}</p>
    </main>
</body>
</html>
