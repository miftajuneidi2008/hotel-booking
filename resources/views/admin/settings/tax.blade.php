<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Tax settings') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-auth-session-status :status="session('status')" />

            <form method="POST" action="{{ route('admin.settings.tax.update') }}" class="bg-white shadow-sm sm:rounded-lg p-6 space-y-8">
                @csrf
                @method('PUT')

                <p class="text-sm text-gray-600">
                    {{ __('Prices on the guest bill already include VAT and service charge. The invoice shows how much of the total is tax, so the amount the guest pays never changes.') }}
                </p>

                {{-- VAT --}}
                <section class="space-y-4">
                    <h3 class="font-semibold text-gray-800">{{ __('VAT') }}</h3>

                    <input type="hidden" name="vat_enabled" value="0">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="vat_enabled" value="1" @checked(old('vat_enabled', $values['vat_enabled']))
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        {{ __('Charge VAT') }}
                    </label>

                    <div>
                        <x-input-label for="vat_rate" :value="__('VAT rate (%)')" />
                        <x-text-input id="vat_rate" name="vat_rate" type="number" step="0.01" min="0" max="100" class="mt-1 block w-40"
                                      :value="old('vat_rate', $values['vat_rate'])" required />
                        <x-input-error :messages="$errors->get('vat_rate')" class="mt-2" />
                    </div>
                </section>

                {{-- Service charge --}}
                <section class="space-y-4">
                    <h3 class="font-semibold text-gray-800">{{ __('Service charge') }}</h3>

                    <input type="hidden" name="service_charge_enabled" value="0">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="service_charge_enabled" value="1" @checked(old('service_charge_enabled', $values['service_charge_enabled']))
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        {{ __('Charge a service charge') }}
                    </label>

                    <div>
                        <x-input-label for="service_charge_rate" :value="__('Service charge rate (%)')" />
                        <x-text-input id="service_charge_rate" name="service_charge_rate" type="number" step="0.01" min="0" max="100" class="mt-1 block w-40"
                                      :value="old('service_charge_rate', $values['service_charge_rate'])" required />
                        <x-input-error :messages="$errors->get('service_charge_rate')" class="mt-2" />
                    </div>

                    <input type="hidden" name="vat_on_service_charge" value="0">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="vat_on_service_charge" value="1" @checked(old('vat_on_service_charge', $values['vat_on_service_charge']))
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        {{ __('VAT is also charged on the service charge') }}
                    </label>
                </section>

                {{-- Seller details on the invoice --}}
                <section class="space-y-4">
                    <h3 class="font-semibold text-gray-800">{{ __('Business details on invoices') }}</h3>
                    <p class="text-sm text-gray-500">{{ __('Leave a field empty to use the hotel contact details.') }}</p>

                    <div>
                        <x-input-label for="business_name" :value="__('Business name')" />
                        <x-text-input id="business_name" name="business_name" type="text" class="mt-1 block w-full" :value="old('business_name', $values['business_name'])" />
                        <x-input-error :messages="$errors->get('business_name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="business_tin" :value="__('TIN (tax identification number)')" />
                        <x-text-input id="business_tin" name="business_tin" type="text" class="mt-1 block w-64" :value="old('business_tin', $values['business_tin'])" />
                        <x-input-error :messages="$errors->get('business_tin')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="business_address" :value="__('Address')" />
                        <x-text-input id="business_address" name="business_address" type="text" class="mt-1 block w-full" :value="old('business_address', $values['business_address'])" />
                        <x-input-error :messages="$errors->get('business_address')" class="mt-2" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="business_phone" :value="__('Phone')" />
                            <x-text-input id="business_phone" name="business_phone" type="text" class="mt-1 block w-full" :value="old('business_phone', $values['business_phone'])" />
                            <x-input-error :messages="$errors->get('business_phone')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="business_email" :value="__('Email')" />
                            <x-text-input id="business_email" name="business_email" type="email" class="mt-1 block w-full" :value="old('business_email', $values['business_email'])" />
                            <x-input-error :messages="$errors->get('business_email')" class="mt-2" />
                        </div>
                    </div>
                </section>

                <div class="flex items-center justify-between gap-4">
                    <x-primary-button>{{ __('Save') }}</x-primary-button>

                    @if ($last)
                        <p class="text-xs text-gray-500">
                            {{ __('Last changed') }} {{ \App\Support\PropertyTime::local($last->updated_at)->format('M j, Y g:i A') }}
                            @if ($last->updater) &middot; {{ $last->updater->name }} @endif
                        </p>
                    @endif
                </div>
            </form>

            {{-- Example with the saved settings --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800">{{ __('Example with the saved settings') }}</h3>
                <p class="mt-1 text-sm text-gray-500">{{ __('A bill of ETB 1,000.00 is split like this on the invoice:') }}</p>

                <dl class="mt-4 max-w-sm space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-600">{{ __('Subtotal') }}</dt><dd>ETB {{ number_format($example['subtotal'], 2) }}</dd></div>
                    @if ($example['service_rate'] > 0)
                        <div class="flex justify-between"><dt class="text-gray-600">{{ __('Service charge') }} ({{ rtrim(rtrim(number_format($example['service_rate'], 2), '0'), '.') }}%)</dt><dd>ETB {{ number_format($example['service_charge'], 2) }}</dd></div>
                    @endif
                    @if ($example['vat_rate'] > 0)
                        <div class="flex justify-between"><dt class="text-gray-600">{{ __('VAT') }} ({{ rtrim(rtrim(number_format($example['vat_rate'], 2), '0'), '.') }}%)</dt><dd>ETB {{ number_format($example['vat'], 2) }}</dd></div>
                    @endif
                    <div class="flex justify-between border-t border-gray-200 pt-2 font-semibold"><dt>{{ __('Total') }}</dt><dd>ETB {{ number_format($example['total'], 2) }}</dd></div>
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>
