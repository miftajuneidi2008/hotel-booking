{{-- Payments and refunds on the 80 mm receipt. --}}
@foreach ($booking->payments()->whereNull('voided_at')->orderBy('id')->get() as $line)
    <div class="row"><span>{{ $line->isRefund() ? __('Refund') : __('Paid') }} ({{ __($line->method->label()) }})</span><span>{{ $line->isRefund() ? '+' : '-' }}{{ number_format($line->amount, 2) }}</span></div>
@endforeach
