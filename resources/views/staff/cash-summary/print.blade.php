<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Closing sheet') }} {{ $date }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { font-family: ui-sans-serif, system-ui, sans-serif; color: #111; margin: 0; padding: 2rem 1rem; background: #f3f4f6; }
        .sheet { max-width: 52rem; margin: 0 auto; background: #fff; padding: 2.5rem; border-radius: .5rem; }
        h1 { font-size: 1.4rem; margin: 0; }
        h2 { font-size: 1rem; margin: 1.75rem 0 .5rem; }
        p { margin: .25rem 0; }
        table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        td, th { padding: .4rem .3rem; text-align: left; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        td.r, th.r { text-align: right; white-space: nowrap; }
        .muted { color: #6b7280; font-size: .8rem; }
        .total td { font-weight: 700; }
        .sign { display: flex; gap: 2rem; margin-top: 3rem; }
        .sign div { flex: 1; border-top: 1px solid #111; padding-top: .35rem; font-size: .8rem; }
        .actions { max-width: 52rem; margin: 0 auto 1rem; display: flex; gap: 1rem; align-items: center; }
        button { font: inherit; padding: .5rem 1rem; border: 0; border-radius: .375rem; background: #111827; color: #fff; cursor: pointer; }
        a { color: #374151; }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { padding: 0; border-radius: 0; max-width: none; }
            .actions { display: none; }
            @page { size: A4; margin: 15mm; }
        }
    </style>
</head>
<body>
    @php($totals = $summary['totals'])

    <div class="actions">
        <button type="button" onclick="window.print()">{{ __('Print') }}</button>
        <a href="{{ route('staff.cash-summary', array_filter(['date' => $date, 'user' => $userId])) }}">&larr; {{ __('Back') }}</a>
    </div>

    <main class="sheet">
        <h1>{{ $property }}</h1>
        <p class="muted">{{ __('Closing sheet') }} &middot; {{ \Illuminate\Support\Carbon::parse($date)->format('l, M j, Y') }} &middot; {{ $timezone }}</p>

        <h2>{{ __('Cash by user') }}</h2>
        <table>
            <thead>
                <tr>
                    <th>{{ __('User') }}</th>
                    @foreach ($methods as $method)
                        <th class="r">{{ __($method->label()) }}</th>
                    @endforeach
                    <th class="r">{{ __('Net') }}</th>
                    <th class="r">{{ __('Cash to hand over') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($summary['rows'] as $row)
                    <tr>
                        <td>{{ $row['user']?->name ?? __('Unknown user') }}</td>
                        @foreach ($methods as $method)
                            <td class="r">{{ number_format($row['by_method'][$method->value]['net'], 2) }}</td>
                        @endforeach
                        <td class="r">{{ number_format($row['net'], 2) }}</td>
                        <td class="r">
                            ETB {{ number_format($row['cash_net'], 2) }}
                            @foreach ($row['foreign_cash'] as $currency => $amount)
                                <br>+ {{ $currency }} {{ number_format($amount, 2) }}
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($methods) + 3 }}" class="muted">{{ __('No payments were recorded on this day.') }}</td></tr>
                @endforelse
                @if ($summary['rows']->count() > 1)
                    <tr class="total">
                        <td>{{ __('All users') }}</td>
                        @foreach ($methods as $method)
                            <td class="r">{{ number_format($totals['by_method'][$method->value]['net'], 2) }}</td>
                        @endforeach
                        <td class="r">{{ number_format($totals['net'], 2) }}</td>
                        <td class="r">ETB {{ number_format($totals['cash_net'], 2) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
        <p class="muted">{{ __('Amounts in ETB, refunds taken off.') }}</p>

        <h2>{{ __('Entries') }}</h2>
        <table>
            <tbody>
                @foreach ($summary['payments'] as $payment)
                    <tr>
                        <td>{{ $payment->paid_at->timezone($timezone)->format('H:i') }}</td>
                        <td>{{ $payment->number }}</td>
                        <td>{{ $payment->booking->reference }} &middot; {{ $payment->booking->guest_name }}</td>
                        <td>{{ $payment->isRefund() ? __('Refund') : __('Payment') }} {{ __($payment->method->label()) }}@if ($payment->reference) ({{ $payment->reference }})@endif</td>
                        <td>{{ $payment->creator?->name ?? '—' }}</td>
                        <td class="r">{{ $payment->isRefund() ? '−' : '' }}{{ number_format($payment->amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if ($summary['walk_ins']->isNotEmpty())
            <h2>{{ __('Restaurant walk-in bills') }}</h2>
            <table>
                <tbody>
                    @foreach ($summary['walk_ins'] as $bill)
                        <tr>
                            <td>{{ $bill->paid_at->timezone($timezone)->format('H:i') }}</td>
                            <td>{{ $bill->number }}</td>
                            <td>{{ $bill->customer_name ?? __('Walk-in customer') }}</td>
                            <td>{{ __($bill->payment_method->label()) }}@if ($bill->payment_reference) ({{ $bill->payment_reference }})@endif</td>
                            <td>{{ $bill->payer?->name ?? '—' }}</td>
                            <td class="r">{{ number_format($bill->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($summary['voided']->isNotEmpty())
            <h2>{{ __('Voided on this day (not counted)') }}</h2>
            <table>
                <tbody>
                    @foreach ($summary['voided'] as $payment)
                        <tr>
                            <td>{{ $payment->number }}</td>
                            <td>{{ __($payment->method->label()) }} ETB {{ number_format($payment->amount, 2) }}</td>
                            <td>{{ $payment->void_reason }} ({{ $payment->voider?->name ?? '—' }})</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="sign">
            <div>{{ __('Cashier') }}</div>
            <div>{{ __('Manager') }}</div>
        </div>
        <p class="muted" style="margin-top:1rem">{{ __('Printed by :name', ['name' => $printedBy]) }} &middot; {{ now($timezone)->format('M j, Y H:i') }}</p>
    </main>
</body>
</html>