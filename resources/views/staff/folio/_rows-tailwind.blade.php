{{-- Extra charges and discounts as table rows, for the booking and check-out screens. Prints nothing when there are none. --}}
@foreach ($booking->folioCharges()->whereNull('voided_at')->orderBy('id')->get() as $line)
    <tr>
        <td class="py-2 text-gray-600">{{ $line->description }} <span class="text-gray-400">({{ __($line->category->label()) }}@if ($line->quantity > 1), {{ $line->quantity }} &times; {{ number_format($line->unit_price, 2) }}@endif)</span></td>
        <td class="py-2 text-right">ETB {{ number_format($line->amount, 2) }}</td>
    </tr>
@endforeach
@foreach ($booking->folioDiscounts()->whereNull('voided_at')->orderBy('id')->get() as $line)
    <tr>
        <td class="py-2 text-gray-600">{{ __('Discount') }} <span class="text-gray-400">({{ $line->reason }})</span></td>
        <td class="py-2 text-right">&minus; ETB {{ number_format($line->amount, 2) }}</td>
    </tr>
@endforeach
