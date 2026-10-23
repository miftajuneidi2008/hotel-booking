{{--
    A4 invoice (section 5.9). One page for three cases:
    - a draft of the bill (no number yet, can be issued after check-out),
    - an issued invoice (everything comes from the stored copy),
    - a voided invoice (shown with a clear VOIDED mark and the reason).
--}}
@php
    $t = $doc['totals'];
    $isDraft = $doc['status'] === 'draft';
    $isVoided = $doc['status'] === 'voided';
    $currency = $doc['currency'];
    $rate = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    $hasTax = $t['vat'] > 0 || $t['service_charge'] > 0;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isDraft ? __('Draft invoice') : __('Invoice') }} {{ $doc['number'] ?? $doc['stay']['reference'] }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { font-family: ui-sans-serif, system-ui, sans-serif; color: #111; margin: 0; padding: 2rem 1rem; background: #f3f4f6; }
        .sheet { max-width: 48rem; margin: 0 auto; background: #fff; padding: 2.5rem; border-radius: .5rem; position: relative; }
        h1 { font-size: 1.5rem; margin: 0; }
        h2 { font-size: 1rem; margin: 2rem 0 .5rem; }
        p { margin: .25rem 0; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        td, th { padding: .4rem 0; text-align: left; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        td.r, th.r { text-align: right; white-space: nowrap; }
        th { font-size: .8rem; color: #6b7280; font-weight: 600; }
        .muted { color: #6b7280; font-size: .85rem; }
        .total td { font-weight: 700; }
        .head { display: flex; justify-content: space-between; gap: 2rem; align-items: flex-start; }
        .actions { max-width: 48rem; margin: 0 auto 1rem; display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; }
        .panel { max-width: 48rem; margin: 0 auto 1rem; background: #fff; border-radius: .5rem; padding: 1rem 1.25rem; }
        .panel label { display: block; font-size: .8rem; color: #6b7280; margin-bottom: .2rem; }
        .panel input { font: inherit; padding: .4rem .6rem; border: 1px solid #d1d5db; border-radius: .375rem; min-width: 14rem; }
        .row { display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end; }
        .flash-ok { color: #047857; font-weight: 600; }
        .flash-err, .err { color: #b91c1c; font-weight: 600; font-size: .85rem; }
        .warn { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; border-radius: .375rem; padding: .6rem .9rem; font-size: .9rem; }
        .void { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: .375rem; padding: .6rem .9rem; margin-bottom: 1.25rem; font-weight: 600; }
        .badge { display: inline-block; padding: .1rem .5rem; border-radius: 999px; font-size: .75rem; font-weight: 600; background: #e5e7eb; color: #374151; }
        .badge.draft { background: #fef3c7; color: #92400e; }
        .badge.issued { background: #d1fae5; color: #065f46; }
        .badge.voided { background: #fee2e2; color: #991b1b; }
        button { font: inherit; padding: .5rem 1rem; border: 0; border-radius: .375rem; background: #111827; color: #fff; cursor: pointer; }
        button.danger { background: #b91c1c; }
        a { color: #374151; }
        .note { margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; }
        .tax td { border-bottom: 0; padding: .2rem 0; color: #374151; }
        .watermark { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 6rem; font-weight: 800; color: rgba(185, 28, 28, .12); transform: rotate(-20deg); pointer-events: none; }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { padding: 0; border-radius: 0; max-width: none; }
            .actions, .panel { display: none; }
            @page { size: A4; margin: 18mm; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button type="button" onclick="window.print()">{{ __('Print') }}</button>
        <a href="{{ route('staff.folio.show', $bookingId) }}">&larr; {{ __('Back to bill') }}</a>
        @can('viewAny', \App\Models\Invoice::class)
            <a href="{{ route('staff.invoices.index') }}">{{ __('All invoices') }}</a>
        @endcan
        @if ($invoice)
            <a href="{{ route('staff.invoices.json', $invoice) }}">{{ __('Download data (JSON)') }}</a>
        @endif
        @session('status') <span class="flash-ok">{{ $value }}</span> @endsession
        @session('error') <span class="flash-err" role="alert">{{ $value }}</span> @endsession
    </div>

    {{-- Draft: issue it, or say why it cannot be issued yet --}}
    @if ($isDraft)
        <div class="panel">
            <p class="warn">{{ __('This is a draft. It has no invoice number and is not an invoice until it is issued.') }}</p>

            @if ($blocker)
                <p class="muted" style="margin-top:.75rem">{{ $blocker }}</p>
            @elseif ($canIssue)
                <form method="POST" action="{{ route('staff.invoices.issue', $bookingId) }}" style="margin-top:.75rem"
                      onsubmit="return confirm('{{ __('Issue this invoice? It takes the next invoice number and cannot be edited afterwards.') }}')">
                    @csrf
                    <div class="row">
                        <div>
                            <label for="customer_name">{{ __('Customer name (optional, for a company)') }}</label>
                            <input id="customer_name" name="customer_name" maxlength="150" value="{{ old('customer_name', $doc['customer']['name']) }}">
                            @error('customer_name') <p class="err">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="customer_tin">{{ __('Customer TIN (optional)') }}</label>
                            <input id="customer_tin" name="customer_tin" maxlength="30" value="{{ old('customer_tin') }}">
                            @error('customer_tin') <p class="err">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit">{{ __('Issue invoice') }}</button>
                    </div>
                </form>
            @endif
        </div>
    @endif

    {{-- Issued: a manager or the owner may void it with a reason --}}
    @if ($invoice && ! $invoice->isVoided())
        @can('void', $invoice)
            <div class="panel">
                <form method="POST" action="{{ route('staff.invoices.void', $invoice) }}"
                      onsubmit="return confirm('{{ __('Void this invoice? The number is not used again.') }}')">
                    @csrf
                    @method('PATCH')
                    <div class="row">
                        <div>
                            <label for="void_reason">{{ __('Reason for voiding this invoice') }}</label>
                            <input id="void_reason" name="reason" maxlength="255" required style="min-width:22rem">
                            @error('reason') <p class="err">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit" class="danger">{{ __('Void invoice') }}</button>
                    </div>
                </form>
            </div>
        @endcan
    @endif

    <main class="sheet">
        @if ($isVoided)
            <div class="watermark">{{ __('VOIDED') }}</div>
        @elseif ($isDraft)
            <div class="watermark" style="color:rgba(146,64,14,.1)">{{ __('DRAFT') }}</div>
        @endif

        @if ($isVoided && $doc['void'])
            <div class="void">
                {{ __('This invoice was voided') }}@if ($doc['void']['at']) {{ $doc['void']['at']->format('M j, Y H:i') }}@endif
                @if ($doc['void']['by']) {{ __('by :name', ['name' => $doc['void']['by']]) }}@endif.
                {{ __('Reason') }}: {{ $doc['void']['reason'] }}
            </div>
        @endif

        <div class="head">
            <div>
                <h1>{{ $doc['seller']['name'] }}</h1>
                @if ($doc['seller']['tin'] !== '') <p>{{ __('TIN') }}: {{ $doc['seller']['tin'] }}</p> @endif
                @if ($doc['seller']['address']) <p class="muted">{{ $doc['seller']['address'] }}</p> @endif
                @if ($doc['seller']['phone'] || $doc['seller']['email'])
                    <p class="muted">{{ $doc['seller']['phone'] }}@if ($doc['seller']['phone'] && $doc['seller']['email']) &middot; @endif{{ $doc['seller']['email'] }}</p>
                @endif
            </div>
            <div style="text-align:right">
                <p style="font-size:1.25rem;font-weight:700">{{ __('Invoice') }}</p>
                <p>{{ $doc['number'] ?? __('Not issued yet') }}</p>
                <p class="muted">
                    {{ __('Date') }}: {{ ($doc['issued_at'] ?? now())->format('M j, Y') }}
                </p>
                <p><span class="badge {{ $doc['status'] }}">{{ $isDraft ? __('Draft') : ($isVoided ? __('Voided') : __('Issued')) }}</span></p>
            </div>
        </div>

        <h2>{{ __('Customer') }}</h2>
        <p>{{ $doc['customer']['name'] }}</p>
        @if ($doc['customer']['tin']) <p>{{ __('TIN') }}: {{ $doc['customer']['tin'] }}</p> @endif
        <p class="muted">{{ $doc['customer']['phone'] }}@if ($doc['customer']['email']) &middot; {{ $doc['customer']['email'] }}@endif</p>

        <h2>{{ __('Stay') }}</h2>
        <p>
            {{ $doc['stay']['room'] }},
            {{ $doc['stay']['check_in']->format('M j, Y') }} &ndash; {{ $doc['stay']['check_out']->format('M j, Y') }},
            {{ $doc['stay']['nights'] }} {{ Str::plural('night', $doc['stay']['nights']) }}, {{ $doc['stay']['guests'] }} {{ Str::plural('guest', $doc['stay']['guests']) }}
        </p>
        <p class="muted">{{ __('Booking') }} {{ $doc['stay']['reference'] }}</p>

        <h2>{{ __('Charges') }}</h2>
        <table>
            <thead>
                <tr><th>{{ __('Description') }}</th><th class="r">{{ __('Qty') }}</th><th class="r">{{ __('Price') }}</th><th class="r">{{ __('Amount') }}</th></tr>
            </thead>
            <tbody>
                @foreach ($doc['lines'] as $line)
                    <tr>
                        <td>{{ $line['description'] }}</td>
                        <td class="r">{{ $line['type'] === 'discount' ? '' : $line['quantity'] }}</td>
                        <td class="r">{{ $line['type'] === 'discount' ? '' : number_format($line['unit_price'], 2) }}</td>
                        <td class="r">{{ number_format($line['amount'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total"><td colspan="3">{{ __('Total') }} ({{ $currency }})</td><td class="r">{{ number_format($t['total'], 2) }}</td></tr>
            </tbody>
        </table>

        @if ($hasTax)
            <table class="tax" style="margin-top:.75rem">
                <tbody>
                    <tr><td colspan="3" class="muted">{{ __('The total above includes:') }}</td><td></td></tr>
                    <tr><td colspan="3">{{ __('Subtotal before tax') }}</td><td class="r">{{ number_format($t['subtotal'], 2) }}</td></tr>
                    @if ($t['service_charge'] > 0)
                        <tr><td colspan="3">{{ __('Service charge') }} ({{ $rate($t['service_rate']) }}%)</td><td class="r">{{ number_format($t['service_charge'], 2) }}</td></tr>
                    @endif
                    @if ($t['vat'] > 0)
                        <tr><td colspan="3">{{ __('VAT') }} ({{ $rate($t['vat_rate']) }}%)</td><td class="r">{{ number_format($t['vat'], 2) }}</td></tr>
                    @endif
                </tbody>
            </table>
        @endif

        <h2>{{ __('Payments') }}</h2>
        <table>
            <tbody>
                <tr>
                    <td>{{ $isDraft ? __('Paid so far') : __('Paid when the invoice was issued') }}</td>
                    <td class="r">{{ $currency }} {{ number_format($doc['paid'], 2) }}</td>
                </tr>
                <tr class="total">
                    <td>{{ $doc['balance'] < 0 ? __('Credit (refund due)') : __('Balance due') }}</td>
                    <td class="r">{{ $currency }} {{ number_format(abs($doc['balance']), 2) }}</td>
                </tr>
            </tbody>
        </table>

        <p class="muted" style="margin-top:1rem">
            @if ($doc['issued_by']) {{ __('Issued by :name', ['name' => $doc['issued_by']]) }} &middot; @endif
            {{ __('Printed by :name', ['name' => $printedBy]) }}
        </p>
    </main>
</body>
</html>
