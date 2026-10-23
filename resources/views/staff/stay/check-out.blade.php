<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Check out') }} {{ $booking->reference }}</h2>
            <a href="{{ route('staff.bookings.show', $booking) }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; {{ __('Back to booking') }}</a>
        </div>
    </x-slot>

    @php
        $balance = $booking->balanceDue();
        $today = today();
        $leaves = $booking->check_out;
        $canApprove = auth()->user()->can('approve-unpaid-checkout');
    @endphp

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @session('error')
                <p role="alert" class="font-medium text-sm text-red-600">{{ $value }}</p>
            @endsession

            @include('staff.stay._summary')

            @if ($leaves->gt($today))
                <div class="rounded-lg border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-800">
                    {{ __('The booking runs until :date. Shorten the stay first so the bill only has the nights the guest stayed.', ['date' => $leaves->format('M j, Y')]) }}
                    <a href="{{ route('staff.bookings.stay', $booking) }}" class="ml-1 font-medium underline">{{ __('Change stay') }}</a>
                </div>
            @elseif ($leaves->lt($today))
                <div class="rounded-lg border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-800">
                    {{ __('The booking ended on :date. Extend the stay first so the extra nights are on the bill.', ['date' => $leaves->format('M j, Y')]) }}
                    <a href="{{ route('staff.bookings.stay', $booking) }}" class="ml-1 font-medium underline">{{ __('Change stay') }}</a>
                </div>
            @endif

            {{-- The bill --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800">{{ __('Bill') }}</h3>
                <table class="mt-4 min-w-full text-sm">
                    <tbody class="divide-y divide-gray-100 text-gray-900">
                        <tr>
                            <td class="py-2 text-gray-600">{{ __('Room charges') }} ({{ $booking->nights() }} {{ Str::plural('night', $booking->nights()) }})</td>
                            <td class="py-2 text-right">ETB {{ number_format($booking->total_amount, 2) }}</td>
                        </tr>
                        @include('staff.folio._rows-tailwind')
                        <tr>
                            <td class="py-2 text-gray-600">{{ __('Paid so far (deposit)') }}</td>
                            <td class="py-2 text-right">&minus; ETB {{ number_format($booking->amountPaid(), 2) }}</td>
                        </tr>
                        <tr class="font-semibold">
                            <td class="py-2">{{ __('Balance due') }}</td>
                            <td class="py-2 text-right">ETB {{ number_format($balance, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <form method="POST" action="{{ route('staff.bookings.check-out.store', $booking) }}" class="bg-white shadow-sm sm:rounded-lg p-6 space-y-8"
                  onsubmit="return confirm('{{ __('Check this guest out?') }}')">
                @csrf

                @if ($balance > 0)
                    <div>
                        <h3 class="font-semibold text-gray-800">{{ __('Payment now') }}</h3>
                        <div class="mt-4 grid gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label for="payment_amount" :value="__('Amount (ETB)')" />
                                <x-text-input id="payment_amount" name="payment_amount" type="number" min="0" step="0.01" max="{{ $balance }}" class="mt-1 block w-full" :value="old('payment_amount', number_format($balance, 2, '.', ''))" />
                                <x-input-error :messages="$errors->get('payment_amount')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="payment_method" :value="__('Paid by')" />
                                <x-select-input id="payment_method" name="payment_method" class="mt-1 block w-full">
                                    <option value="">&mdash;</option>
                                    @foreach ($methods as $method)
                                        <option value="{{ $method->value }}" @selected(old('payment_method', 'cash') === $method->value)>{{ __($method->label()) }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="payment_reference" :value="__('Reference no.')" />
                                <x-text-input id="payment_reference" name="payment_reference" type="text" maxlength="100" class="mt-1 block w-full" :value="old('payment_reference')" />
                            </div>
                        </div>
                        <p class="mt-2 text-sm text-gray-500">{{ __('Set the amount to 0 if the guest is not paying the full balance now.') }}</p>
                    </div>

                    <div>
                        <h3 class="font-semibold text-gray-800">{{ __('If something stays unpaid') }}</h3>
                        @if ($canApprove)
                            <p class="mt-1 text-sm text-gray-600">{{ __('You can approve this. Write the reason; it is saved with your name.') }}</p>
                            <div class="mt-3 max-w-xl">
                                <x-input-label for="override_reason" :value="__('Reason for letting the guest leave with an unpaid balance')" />
                                <x-text-input id="override_reason" name="override_reason" type="text" maxlength="255" class="mt-1 block w-full" :value="old('override_reason')" />
                            </div>
                        @else
                            <p class="mt-1 text-sm text-gray-600">{{ __('The guest must pay the full balance. Only a manager or the owner can approve a check-out with an unpaid balance.') }}</p>
                        @endif
                    </div>
                @else
                    <p class="text-sm text-gray-700">{{ __('The bill is fully paid.') }}</p>
                @endif

                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Check out') }}</x-primary-button>
                    <a href="{{ route('staff.bookings.show', $booking) }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
