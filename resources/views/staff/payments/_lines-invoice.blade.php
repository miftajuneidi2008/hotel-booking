{{-- Payments and refunds on the A4 statement. --}}
@foreach ($booking->payments()->whereNull('voided_at')->orderBy('id')->get() as $line)
    <tr>
        <td>{{ $line->isRefund() ? __('Refund') : __('Payment') }} ({{ __($line->method->label()) }}@if ($line->reference), {{ $line->reference }}@endif)</td>
        <td class="r">{{ $line->isRefund() ? '−' : '' }} ETB {{ number_format($line->amount, 2) }}</td>
    </tr>
@endforeach
