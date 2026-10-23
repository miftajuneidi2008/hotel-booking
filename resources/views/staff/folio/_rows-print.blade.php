{{-- Extra charges and discounts as table rows, for the printed stay statement. Prints nothing when there are none. --}}
@foreach ($booking->folioCharges()->whereNull('voided_at')->orderBy('id')->get() as $line)
    <tr>
        <td>{{ $line->description }} &middot; {{ __($line->category->label()) }}@if ($line->quantity > 1) &middot; {{ $line->quantity }} &times; {{ number_format($line->unit_price, 2) }}@endif</td>
        <td class="r">ETB {{ number_format($line->amount, 2) }}</td>
    </tr>
@endforeach
@foreach ($booking->folioDiscounts()->whereNull('voided_at')->orderBy('id')->get() as $line)
    <tr>
        <td>{{ __('Discount') }} &middot; {{ $line->reason }}</td>
        <td class="r">&minus; ETB {{ number_format($line->amount, 2) }}</td>
    </tr>
@endforeach
