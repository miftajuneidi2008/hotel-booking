<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Guest bill') }} {{ $booking->reference }}</h2>
            <div class="flex flex-wrap items-center gap-4 text-sm">
                @if ($booking->status !== \App\Enums\BookingStatus::Cancelled)
                    <a href="{{ route('staff.folio.receipt', $booking) }}" target="_blank" rel="noopener" class="text-gray-600 hover:text-gray-900">{{ __('Receipt (80 mm)') }}</a>
                    <a href="{{ route('staff.folio.invoice', $booking) }}" target="_blank" rel="noopener" class="text-gray-600 hover:text-gray-900">{{ __('A4 invoice') }}</a>
                @endif
                @can('manage-bookings')
                    <a href="{{ route('staff.bookings.show', $booking) }}" class="text-gray-600 hover:text-gray-900">&larr; {{ __('Back to booking') }}</a>
                @else
                    <a href="{{ route('staff.folios.index') }}" class="text-gray-600 hover:text-gray-900">&larr; {{ __('In-house guests') }}</a>
                @endcan
            </div>
        </div>
    </x-slot>

    @php
        $canCharge = $booking->folioAcceptsCharges();
        $canDiscount = $booking->folioAcceptsDiscounts();
        $balance = $folio['balance'];
    @endphp

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-auth-session-status :status="session('status')" />

            @session('error')
                <p role="alert" class="font-medium text-sm text-red-600">{{ $value }}</p>
            @endsession

            @include('staff.stay._summary')

            @if (! $canDiscount)
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
                    {{ __('This bill is closed. It can still be printed.') }}
                </div>
            @endif

            {{-- The bill --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800">{{ __('Bill') }}</h3>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <tbody class="divide-y divide-gray-100 text-gray-900">
                            @foreach ($folio['room_lines'] as $line)
                                <tr>
                                    <td class="py-2 text-gray-600">{{ __('Room') }} &middot; {{ \Illuminate\Support\Carbon::parse($line['night'])->format('D, M j') }}</td>
                                    <td class="py-2 text-right" colspan="2">ETB {{ number_format($line['amount'], 2) }}</td>
                                </tr>
                            @endforeach

                            @foreach ($folio['charges'] as $charge)
                                <tr class="{{ $charge->isVoided() ? 'text-gray-400' : '' }}">
                                    <td class="py-2 align-top">
                                        <span class="{{ $charge->isVoided() ? 'line-through' : 'text-gray-900' }}">{{ $charge->description }}</span>
                                        <span class="block text-xs text-gray-500">
                                            {{ __($charge->category->label()) }}
                                            @if ($charge->quantity > 1) &middot; {{ $charge->quantity }} &times; ETB {{ number_format($charge->unit_price, 2) }} @endif
                                            &middot; {{ $charge->created_at->format('M j, H:i') }}@if ($charge->creator) &middot; {{ $charge->creator->name }}@endif
                                        </span>
                                        @if ($charge->isVoided())
                                            <span class="block text-xs text-red-600">
                                                {{ $charge->void_was_split ? __('Split') : __('Voided') }}
                                                {{ $charge->voided_at->format('M j, H:i') }}@if ($charge->voider) {{ __('by') }} {{ $charge->voider->name }}@endif
                                                &mdash; {{ $charge->void_reason }}@if ($charge->voidApprover) ({{ __('approved by') }} {{ $charge->voidApprover->name }})@endif
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-2 text-right align-top whitespace-nowrap {{ $charge->isVoided() ? 'line-through' : '' }}">ETB {{ number_format($charge->amount, 2) }}</td>
                                    <td class="py-2 pl-4 text-right align-top whitespace-nowrap text-xs">
                                        @if (! $charge->isVoided() && $canCharge)
                                            @can('transfer', $charge)
                                                <details class="inline-block text-left">
                                                    <summary class="cursor-pointer text-indigo-600 hover:text-indigo-900">{{ __('Move') }}</summary>
                                                    <form method="POST" action="{{ route('staff.folio.charges.transfer', $charge) }}" class="mt-2 w-64 space-y-2 rounded-md border border-gray-200 bg-gray-50 p-3 whitespace-normal">
                                                        @csrf
                                                        @if ($targets->isEmpty())
                                                            <p class="text-gray-500">{{ __('No other guest is checked in.') }}</p>
                                                        @else
                                                            <x-select-input name="to_booking_id" required class="block w-full text-xs">
                                                                @foreach ($targets as $target)
                                                                    <option value="{{ $target->id }}">{{ $target->guest_name }} &middot; {{ __('Room') }} {{ $target->room->number }}</option>
                                                                @endforeach
                                                            </x-select-input>
                                                            <x-text-input name="amount" type="number" min="0.01" max="{{ $charge->amount }}" step="0.01" required value="{{ $charge->amount }}" class="block w-full text-xs" />
                                                            <p class="text-gray-500">{{ __('Less than the full amount splits the charge.') }}</p>
                                                            <x-text-input name="reason" type="text" maxlength="255" required placeholder="{{ __('Reason') }}" class="block w-full text-xs" />
                                                            <x-secondary-button type="submit">{{ __('Move') }}</x-secondary-button>
                                                        @endif
                                                    </form>
                                                </details>
                                            @endcan
                                            @can('void', $charge)
                                                <details class="mt-1 inline-block text-left">
                                                    <summary class="cursor-pointer text-red-600 hover:text-red-800">{{ __('Void') }}</summary>
                                                    <form method="POST" action="{{ route('staff.folio.charges.void', $charge) }}" class="mt-2 w-64 space-y-2 rounded-md border border-gray-200 bg-gray-50 p-3 whitespace-normal"
                                                          onsubmit="return confirm('{{ __('Void this charge?') }}')">
                                                        @csrf
                                                        @method('PATCH')
                                                        <x-text-input name="reason" type="text" maxlength="255" required placeholder="{{ __('Reason (required)') }}" class="block w-full text-xs" />
                                                        <x-danger-button>{{ __('Void charge') }}</x-danger-button>
                                                    </form>
                                                </details>
                                            @endcan
                                        @endif
                                    </td>
                                </tr>
                            @endforeach

                            @foreach ($folio['discounts'] as $discount)
                                <tr class="{{ $discount->isVoided() ? 'text-gray-400' : '' }}">
                                    <td class="py-2 align-top">
                                        <span class="{{ $discount->isVoided() ? 'line-through' : 'text-gray-900' }}">
                                            {{ __('Discount') }}@if ($discount->type === \App\Enums\DiscountType::Percent) ({{ rtrim(rtrim(number_format($discount->value, 2), '0'), '.') }}%)@endif
                                        </span>
                                        <span class="block text-xs text-gray-500">
                                            {{ $discount->reason }} &middot; {{ $discount->created_at->format('M j, H:i') }}@if ($discount->creator) &middot; {{ $discount->creator->name }}@endif
                                            @if ($discount->approver) &middot; {{ __('approved by') }} {{ $discount->approver->name }}@endif
                                        </span>
                                        @if ($discount->isVoided())
                                            <span class="block text-xs text-red-600">
                                                {{ __('Removed') }} {{ $discount->voided_at->format('M j, H:i') }}@if ($discount->voider) {{ __('by') }} {{ $discount->voider->name }}@endif
                                                &mdash; {{ $discount->void_reason }}@if ($discount->voidApprover) ({{ __('approved by') }} {{ $discount->voidApprover->name }})@endif
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-2 text-right align-top whitespace-nowrap {{ $discount->isVoided() ? 'line-through' : '' }}">&minus; ETB {{ number_format($discount->amount, 2) }}</td>
                                    <td class="py-2 pl-4 text-right align-top whitespace-nowrap text-xs">
                                        @if (! $discount->isVoided() && $canDiscount)
                                            @can('void', $discount)
                                                <details class="inline-block text-left">
                                                    <summary class="cursor-pointer text-red-600 hover:text-red-800">{{ __('Remove') }}</summary>
                                                    <form method="POST" action="{{ route('staff.folio.discounts.void', $discount) }}" class="mt-2 w-64 space-y-2 rounded-md border border-gray-200 bg-gray-50 p-3 whitespace-normal"
                                                          onsubmit="return confirm('{{ __('Remove this discount?') }}')">
                                                        @csrf
                                                        @method('PATCH')
                                                        <x-text-input name="reason" type="text" maxlength="255" required placeholder="{{ __('Reason (required)') }}" class="block w-full text-xs" />
                                                        <x-danger-button>{{ __('Remove discount') }}</x-danger-button>
                                                    </form>
                                                </details>
                                            @endcan
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-gray-300 text-gray-900">
                            <tr><td class="pt-3 text-gray-600">{{ __('Room charges') }}</td><td class="pt-3 text-right" colspan="2">ETB {{ number_format($folio['room_total'], 2) }}</td></tr>
                            <tr><td class="py-1 text-gray-600">{{ __('Extra charges') }}</td><td class="py-1 text-right" colspan="2">ETB {{ number_format($folio['extras_total'], 2) }}</td></tr>
                            <tr><td class="py-1 text-gray-600">{{ __('Discounts') }}</td><td class="py-1 text-right" colspan="2">&minus; ETB {{ number_format($folio['discounts_total'], 2) }}</td></tr>
                            <tr class="font-semibold"><td class="py-1">{{ __('Total') }}</td><td class="py-1 text-right" colspan="2">ETB {{ number_format($folio['net'], 2) }}</td></tr>
                            <tr><td class="py-1 text-gray-600">{{ __('Paid so far') }}</td><td class="py-1 text-right" colspan="2">&minus; ETB {{ number_format($folio['paid'], 2) }}</td></tr>
                            <tr class="font-semibold">
                                <td class="py-1">{{ $balance < 0 ? __('Credit (refund due)') : __('Balance due') }}</td>
                                <td class="py-1 text-right" colspan="2">ETB {{ number_format(abs($balance), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                @if ($canCharge)
                    @can('manage-bookings')
                        <div class="mt-6">
                            <x-link-button :href="route('staff.bookings.check-out', $booking)">{{ __('Check out') }}</x-link-button>
                        </div>
                    @endcan
                @endif
            </div>

            {{-- Section 5.5: payments, part payments and refunds --}}
            <x-payments-panel :booking="$booking" />

            {{-- FR-16: add an extra charge --}}
            @if ($canCharge)
                <form method="POST" action="{{ route('staff.folio.charges.store', $booking) }}" class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                    @csrf
                    <h3 class="font-semibold text-gray-800">{{ __('Add a charge') }}</h3>

                    <div class="grid gap-4 sm:grid-cols-6">
                        <div class="sm:col-span-2">
                            <x-input-label for="category" :value="__('Type')" />
                            <x-select-input id="category" name="category" class="mt-1 block w-full">
                                @foreach ($categories as $category)
                                    <option value="{{ $category->value }}" @selected(old('category') === $category->value)>{{ __($category->label()) }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('category')" class="mt-2" />
                        </div>
                        <div class="sm:col-span-4">
                            <x-input-label for="description" :value="__('Description')" />
                            <x-text-input id="description" name="description" type="text" maxlength="150" required class="mt-1 block w-full" :value="old('description')" />
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="quantity" :value="__('Quantity')" />
                            <x-text-input id="quantity" name="quantity" type="number" min="1" max="999" step="1" required class="mt-1 block w-full" :value="old('quantity', 1)" />
                            <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="unit_price" :value="__('Price each (ETB)')" />
                            <x-text-input id="unit_price" name="unit_price" type="number" min="0.01" max="999999" step="0.01" required class="mt-1 block w-full" :value="old('unit_price')" />
                            <x-input-error :messages="$errors->get('unit_price')" class="mt-2" />
                        </div>
                    </div>

                    <x-primary-button>{{ __('Add charge') }}</x-primary-button>
                </form>
            @endif

            {{-- FR-17: discount with a reason --}}
            @if ($canDiscount)
                @can('create', \App\Models\FolioDiscount::class)
                    <form method="POST" action="{{ route('staff.folio.discounts.store', $booking) }}" class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                        @csrf
                        <h3 class="font-semibold text-gray-800">{{ __('Give a discount') }}</h3>

                        <p class="text-sm text-gray-600">
                            {{ __('A percent discount is worked out on the bill as it is now (ETB :gross). Charges added later are not discounted.', ['gross' => number_format($folio['gross'], 2)]) }}
                            @if ($limits['discount_approval']['percent'] !== null || $limits['discount_approval']['amount'] !== null)
                                @if (auth()->user()->can('approve-unpaid-checkout'))
                                    {{ __('Discounts over the limit set by the owner are saved with your name as approver.') }}
                                @else
                                    {{ __('Discounts over the limit set by the owner need a manager.') }}
                                @endif
                            @endif
                        </p>

                        <div class="grid gap-4 sm:grid-cols-6">
                            <div class="sm:col-span-2">
                                <x-input-label for="discount_type" :value="__('Type')" />
                                <x-select-input id="discount_type" name="type" class="mt-1 block w-full">
                                    @foreach ($discountTypes as $type)
                                        <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ __($type->label()) }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error :messages="$errors->get('type')" class="mt-2" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="discount_value" :value="__('Value')" />
                                <x-text-input id="discount_value" name="value" type="number" min="0.01" step="0.01" required class="mt-1 block w-full" :value="old('value')" />
                                <x-input-error :messages="$errors->get('value')" class="mt-2" />
                            </div>
                            <div class="sm:col-span-6">
                                <x-input-label for="discount_reason" :value="__('Reason (required)')" />
                                <x-text-input id="discount_reason" name="reason" type="text" maxlength="255" required class="mt-1 block w-full" :value="old('reason')" />
                                <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                            </div>
                        </div>

                        <x-primary-button>{{ __('Apply discount') }}</x-primary-button>
                    </form>
                @endcan
            @endif
        </div>
    </div>
</x-app-layout>
