{{-- Payments and refunds as table rows on the booking page. Prints nothing when there are none. --}}
@foreach ($booking->payments()->whereNull('voided_at')->orderBy('id')->get() as $line)
    <tr>
        <td class="py-2 text-gray-600">{{ $line->isRefund() ? __('Refund') : __('Payment') }} ({{ __($line->method->label()) }}@if ($line->reference), {{ $line->reference }}@endif)</td>
        <td class="py-2 text-right">{{ $line->isRefund() ? '−' : '' }} ETB {{ number_format($line->amount, 2) }}</td>
    </tr>
@endforeach
