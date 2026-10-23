<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Rates: :name', ['name' => $roomType->name]) }}
            </h2>

            <a href="{{ route('admin.room-types.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; {{ __('Room types') }}</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-auth-session-status :status="session('status')" />

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 text-sm text-gray-700">
                    {{ __('Base rate:') }}
                    <span class="font-semibold text-gray-900">ETB {{ number_format($roomType->base_rate, 2) }}</span>
                    {{ __('per night. It is used when no weekend or seasonal rate matches the night.') }}
                    {{ __('If several rates match, the most specific wins: seasonal, then weekend, then base.') }}
                </div>

                <div class="overflow-x-auto border-t border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-6 py-3">{{ __('Name') }}</th>
                                <th class="px-6 py-3">{{ __('Rate (ETB)') }}</th>
                                <th class="px-6 py-3">{{ __('Applies to') }}</th>
                                <th class="px-6 py-3">{{ __('Dates') }}</th>
                                <th class="px-6 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-gray-900">
                            @forelse ($rates as $rate)
                                <tr>
                                    <td class="px-6 py-4 font-medium">{{ $rate->name }}</td>
                                    <td class="px-6 py-4">{{ number_format($rate->amount, 2) }}</td>
                                    <td class="px-6 py-4">{{ __($rate->applies_on->label()) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if ($rate->isSeasonal())
                                            {{ $rate->starts_on->format('d M Y') }} &ndash; {{ $rate->ends_on->format('d M Y') }}
                                        @else
                                            {{ __('All year') }}
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <form method="POST" action="{{ route('admin.room-types.rates.destroy', [$roomType, $rate]) }}"
                                              onsubmit="return confirm('{{ __('Delete this rate?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900">{{ __('Delete') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                        {{ __('No weekend or seasonal rates yet. Only the base rate applies.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Add a rate') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ __('Leave the dates empty for a weekend rate that applies all year. Add both dates for a seasonal rate.') }}
                    </p>

                    <form method="POST" action="{{ route('admin.room-types.rates.store', $roomType) }}" class="mt-6 space-y-6">
                        @csrf

                        <div>
                            <x-input-label for="name" :value="__('Name')" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required :placeholder="__('Weekend rate, Timkat season…')" />
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <x-input-label for="amount" :value="__('Rate (ETB per night)')" />
                                <x-text-input id="amount" name="amount" type="number" min="0" step="0.01" class="mt-1 block w-full" :value="old('amount')" required />
                                <x-input-error class="mt-2" :messages="$errors->get('amount')" />
                            </div>

                            <div>
                                <x-input-label for="applies_on" :value="__('Applies to')" />
                                <x-select-input id="applies_on" name="applies_on" class="mt-1 block w-full" required>
                                    @foreach (\App\Enums\RateDays::cases() as $days)
                                        <option value="{{ $days->value }}" @selected(old('applies_on', 'weekends') === $days->value)>{{ __($days->label()) }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error class="mt-2" :messages="$errors->get('applies_on')" />
                            </div>

                            <div>
                                <x-input-label for="starts_on" :value="__('From (optional)')" />
                                <x-text-input id="starts_on" name="starts_on" type="date" class="mt-1 block w-full" :value="old('starts_on')" />
                                <x-input-error class="mt-2" :messages="$errors->get('starts_on')" />
                            </div>

                            <div>
                                <x-input-label for="ends_on" :value="__('Until (optional)')" />
                                <x-text-input id="ends_on" name="ends_on" type="date" class="mt-1 block w-full" :value="old('ends_on')" />
                                <x-input-error class="mt-2" :messages="$errors->get('ends_on')" />
                            </div>
                        </div>

                        <x-primary-button>{{ __('Add rate') }}</x-primary-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
