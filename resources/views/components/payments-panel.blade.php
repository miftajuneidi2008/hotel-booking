@php
    // Which form the person was using when something went wrong, so only that form gets its values back.
    $used = session('payment_form')
        ?? ($errors->hasBag('payment') ? 'payment' : ($errors->hasBag('refund') ? 'refund' : null));
    $keep = fn (string $form, string $field, mixed $default = null) => $used === $form ? old($field, $default) : $default;
    $legacyDeposit = (float) $booking->deposit_amount;
    $legacySettlement = (float) $booking->settlement_amount;
    $input = 'mt-1 block w-full';
@endphp

<div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-6" id="payments">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="font-semibold text-gray-800">{{ __('Payments') }}</h3>
        @can('viewSummary', \App\Models\Payment::class)
            <a href="{{ route('staff.cash-summary') }}" class="text-sm text-indigo-600 hover:text-indigo-900">{{ __('Daily cash summary') }} &rarr;</a>
        @endcan
    </div>


    {{-- What has been paid --}}
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <tbody class="divide-y divide-gray-100 text-gray-900">
                @if ($legacyDeposit > 0)
                    <tr>
                        <td class="py-2 text-gray-600">
                            {{ __('Deposit') }}@if ($booking->deposit_method) ({{ __($booking->deposit_method->label()) }}@if ($booking->deposit_reference), {{ $booking->deposit_reference }}@endif)@endif
                            <span class="block text-xs text-gray-400">{{ __('Recorded with the booking') }}</span>
                        </td>
                        <td class="py-2 text-right whitespace-nowrap" colspan="2">ETB {{ number_format($legacyDeposit, 2) }}</td>
                    </tr>
                @endif
                @if ($legacySettlement > 0)
                    <tr>
                        <td class="py-2 text-gray-600">
                            {{ __('Paid at check-out') }}@if ($booking->settlement_method) ({{ __($booking->settlement_method->label()) }}@if ($booking->settlement_reference), {{ $booking->settlement_reference }}@endif)@endif
                            <span class="block text-xs text-gray-400">{{ __('Recorded at check-out') }}</span>
                        </td>
                        <td class="py-2 text-right whitespace-nowrap" colspan="2">ETB {{ number_format($legacySettlement, 2) }}</td>
                    </tr>
                @endif

                @foreach ($payments as $payment)
                    <tr class="{{ $payment->isVoided() ? 'text-gray-400' : '' }}">
                        <td class="py-2 align-top">
                            <span class="{{ $payment->isVoided() ? 'line-through' : 'text-gray-900' }}">
                                {{ $payment->isRefund() ? __('Refund') : __('Payment') }} &middot; {{ __($payment->method->label()) }}@if ($payment->reference) &middot; {{ $payment->reference }}@endif
                            </span>
                            <span class="block text-xs text-gray-500">
                                {{ $payment->number }} &middot; {{ $payment->paid_at->timezone($timezone)->format('M j, H:i') }}@if ($payment->creator) &middot; {{ $payment->creator->name }}@endif
                                @if ($payment->isForeign())
                                    &middot; {{ $payment->currency }} {{ number_format($payment->original_amount, 2) }} &times; {{ rtrim(rtrim(number_format($payment->exchange_rate, 4), '0'), '.') }}
                                @endif
                                @if ($payment->reason) &middot; {{ $payment->reason }}@endif
                            </span>
                            @if ($payment->isVoided())
                                <span class="block text-xs text-red-600">
                                    {{ __('Voided') }} {{ $payment->voided_at->timezone($timezone)->format('M j, H:i') }}@if ($payment->voider) {{ __('by') }} {{ $payment->voider->name }}@endif
                                    &mdash; {{ $payment->void_reason }}
                                </span>
                            @endif
                        </td>
                        <td class="py-2 text-right align-top whitespace-nowrap {{ $payment->isVoided() ? 'line-through' : ($payment->isRefund() ? 'text-red-700' : '') }}">
                            {{ $payment->isRefund() ? '−' : '' }} ETB {{ number_format($payment->amount, 2) }}
                        </td>
                        <td class="py-2 pl-4 text-right align-top whitespace-nowrap text-xs">
                            @if (! $payment->isVoided() && $canVoid)
                                <details class="inline-block text-left">
                                    <summary class="cursor-pointer text-red-600 hover:text-red-800">{{ __('Void') }}</summary>
                                    <form method="POST" action="{{ route('staff.payments.void', $payment) }}" class="mt-2 w-64 space-y-2 rounded-md border border-gray-200 bg-gray-50 p-3 whitespace-normal"
                                          onsubmit="return confirm('{{ __('Void this entry?') }}')">
                                        @csrf
                                        @method('PATCH')
                                        <x-text-input name="reason" type="text" maxlength="255" required placeholder="{{ __('Reason (required)') }}" class="block w-full text-xs" />
                                        <x-danger-button>{{ __('Void entry') }}</x-danger-button>
                                    </form>
                                </details>
                            @endif
                        </td>
                    </tr>
                @endforeach

                @if ($payments->isEmpty() && $legacyDeposit <= 0 && $legacySettlement <= 0)
                    <tr><td class="py-3 text-gray-500" colspan="3">{{ __('Nothing has been paid yet.') }}</td></tr>
                @endif
            </tbody>
            <tfoot class="border-t border-gray-300 text-gray-900">
                <tr class="font-semibold"><td class="pt-3">{{ __('Paid in total') }}</td><td class="pt-3 text-right" colspan="2">ETB {{ number_format($paid, 2) }}</td></tr>
                <tr class="font-semibold">
                    <td class="py-1">{{ $balance < 0 ? __('Credit (refund due)') : __('Balance due') }}</td>
                    <td class="py-1 text-right" colspan="2">ETB {{ number_format(abs($balance), 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- FR-20 / FR-21 / FR-22: take a payment --}}
    @if ($canRecord)
        <form method="POST" action="{{ route('staff.payments.store', $booking) }}" class="space-y-4 border-t border-gray-100 pt-6"
              x-data="{ currency: '{{ $keep('payment', 'currency', $baseCurrency) }}', method: '{{ $keep('payment', 'method', 'cash') }}' }">
            @csrf
            <h4 class="font-medium text-gray-800">{{ __('Record a payment') }}</h4>
            <p class="text-sm text-gray-600">{{ __('A part payment is fine. The payment cannot be more than the balance due.') }}</p>

            <div class="grid gap-4 sm:grid-cols-6">
                <div class="sm:col-span-2">
                    <x-input-label for="pay_amount" :value="__('Amount')" />
                    <x-text-input id="pay_amount" name="amount" type="number" min="0.01" step="0.01" required class="{{ $input }}" :value="$keep('payment', 'amount', number_format(max($balance, 0), 2, '.', ''))" />
                    <x-input-error :messages="$errors->payment->get('amount')" class="mt-2" />
                </div>

                @if (count($currencies) > 1)
                    <div>
                        <x-input-label for="pay_currency" :value="__('Currency')" />
                        <x-select-input id="pay_currency" name="currency" x-model="currency" class="{{ $input }}">
                            @foreach ($currencies as $currency)
                                <option value="{{ $currency }}">{{ $currency }}</option>
                            @endforeach
                        </x-select-input>
                        <x-input-error :messages="$errors->payment->get('currency')" class="mt-2" />
                    </div>
                    <div x-show="currency !== '{{ $baseCurrency }}'">
                        <x-input-label for="pay_rate" :value="__('Rate (:base for 1)', ['base' => $baseCurrency])" />
                        <x-text-input id="pay_rate" name="exchange_rate" type="number" min="0.0001" step="0.0001" class="{{ $input }}" :value="$keep('payment', 'exchange_rate')" />
                        <x-input-error :messages="$errors->payment->get('exchange_rate')" class="mt-2" />
                    </div>
                @endif

                <div class="sm:col-span-2">
                    <x-input-label for="pay_method" :value="__('Paid by')" />
                    <x-select-input id="pay_method" name="method" x-model="method" class="{{ $input }}">
                        @foreach ($methods as $method)
                            <option value="{{ $method->value }}">{{ __($method->label()) }}</option>
                        @endforeach
                    </x-select-input>
                    <x-input-error :messages="$errors->payment->get('method')" class="mt-2" />
                </div>

                <div class="sm:col-span-2">
                    <x-input-label for="pay_reference" :value="__('Reference no.')" />
                    <x-text-input id="pay_reference" name="reference" type="text" maxlength="100" class="{{ $input }}" :value="$keep('payment', 'reference')" />
                    <p class="mt-1 text-xs text-gray-500" x-show="method !== 'cash'">{{ __('Required: bank slip, Telebirr or card receipt number.') }}</p>
                    <x-input-error :messages="$errors->payment->get('reference')" class="mt-2" />
                </div>
            </div>

            <x-primary-button>{{ __('Record payment') }}</x-primary-button>
        </form>
    @endif

    {{-- FR-21: refund --}}
    @if ($canRefund)
        <details class="border-t border-gray-100 pt-6" @if ($used === 'refund') open @endif>
            <summary class="cursor-pointer text-sm font-medium text-red-700 hover:text-red-900">{{ __('Record a refund') }}</summary>

            <form method="POST" action="{{ route('staff.payments.refund', $booking) }}" class="mt-4 space-y-4"
                  x-data="{ currency: '{{ $keep('refund', 'currency', $baseCurrency) }}', method: '{{ $keep('refund', 'method', 'cash') }}' }">
                @csrf
                <p class="text-sm text-gray-600">
                    {{ __('Money paid back to the guest. It needs a reason and cannot be more than the guest has paid (ETB :paid).', ['paid' => number_format($paid, 2)]) }}
                    @if ($refundLimit !== null)
                        {{ __('A refund of more than ETB :limit needs a manager or the owner.', ['limit' => number_format((float) $refundLimit, 2)]) }}
                    @endif
                </p>

                <div class="grid gap-4 sm:grid-cols-6">
                    <div class="sm:col-span-2">
                        <x-input-label for="ref_amount" :value="__('Amount')" />
                        <x-text-input id="ref_amount" name="amount" type="number" min="0.01" step="0.01" required class="{{ $input }}" :value="$keep('refund', 'amount')" />
                        <x-input-error :messages="$errors->refund->get('amount')" class="mt-2" />
                    </div>

                    @if (count($currencies) > 1)
                        <div>
                            <x-input-label for="ref_currency" :value="__('Currency')" />
                            <x-select-input id="ref_currency" name="currency" x-model="currency" class="{{ $input }}">
                                @foreach ($currencies as $currency)
                                    <option value="{{ $currency }}">{{ $currency }}</option>
                                @endforeach
                            </x-select-input>
                        </div>
                        <div x-show="currency !== '{{ $baseCurrency }}'">
                            <x-input-label for="ref_rate" :value="__('Rate (:base for 1)', ['base' => $baseCurrency])" />
                            <x-text-input id="ref_rate" name="exchange_rate" type="number" min="0.0001" step="0.0001" class="{{ $input }}" :value="$keep('refund', 'exchange_rate')" />
                            <x-input-error :messages="$errors->refund->get('exchange_rate')" class="mt-2" />
                        </div>
                    @endif

                    <div class="sm:col-span-2">
                        <x-input-label for="ref_method" :value="__('Paid back by')" />
                        <x-select-input id="ref_method" name="method" x-model="method" class="{{ $input }}">
                            @foreach ($methods as $method)
                                <option value="{{ $method->value }}">{{ __($method->label()) }}</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    <div class="sm:col-span-2">
                        <x-input-label for="ref_reference" :value="__('Reference no.')" />
                        <x-text-input id="ref_reference" name="reference" type="text" maxlength="100" class="{{ $input }}" :value="$keep('refund', 'reference')" />
                        <x-input-error :messages="$errors->refund->get('reference')" class="mt-2" />
                    </div>

                    <div class="sm:col-span-6">
                        <x-input-label for="ref_reason" :value="__('Reason (required)')" />
                        <x-text-input id="ref_reason" name="reason" type="text" maxlength="255" required class="{{ $input }}" :value="$keep('refund', 'reason')" />
                        <x-input-error :messages="$errors->refund->get('reason')" class="mt-2" />
                    </div>
                </div>

                <x-danger-button>{{ __('Record refund') }}</x-danger-button>
            </form>
        </details>
    @endif

    <x-input-error :messages="$errors->void->get('reason')" />
</div>
