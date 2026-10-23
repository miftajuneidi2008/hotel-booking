<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Change booking') }} {{ $booking->reference }}</h2>
            <a href="{{ route('staff.bookings.show', $booking) }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; {{ __('Back to booking') }}</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @session('error')
                <p role="alert" class="font-medium text-sm text-red-600">{{ $value }}</p>
            @endsession

            <p class="text-sm text-gray-600">
                {{ __('Now') }}: {{ __('Room') }} {{ $booking->room->number }},
                {{ $booking->check_in->format('M j') }} &ndash; {{ $booking->check_out->format('M j, Y') }},
                {{ $booking->guests }} {{ Str::plural('guest', $booking->guests) }},
                ETB {{ number_format($booking->total_amount, 2) }}
            </p>

            {{-- Step 1: dates and guests --}}
            <form method="GET" action="{{ route('staff.bookings.edit', $booking) }}" class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800">{{ __('1. Dates and guests') }}</h3>

                <div class="mt-4 flex flex-wrap items-end gap-4">
                    <div>
                        <x-input-label for="checkin" :value="__('Check in')" />
                        <x-text-input id="checkin" name="checkin" type="date" required class="mt-1 block" :value="$search['checkin']" />
                    </div>
                    <div>
                        <x-input-label for="checkout" :value="__('Check out')" />
                        <x-text-input id="checkout" name="checkout" type="date" required class="mt-1 block" :value="$search['checkout']" />
                    </div>
                    <div>
                        <x-input-label for="guests" :value="__('Guests')" />
                        <x-select-input id="guests" name="guests" class="mt-1 block">
                            @foreach (range(1, (int) config('hotel.booking.max_guests', 10)) as $n)
                                <option value="{{ $n }}" @selected($search['guests'] === $n)>{{ $n }}</option>
                            @endforeach
                        </x-select-input>
                    </div>
                    <x-primary-button>{{ __('Find rooms') }}</x-primary-button>
                </div>

                <x-input-error :messages="$errors->get('checkin')" class="mt-2" />
                <x-input-error :messages="$errors->get('checkout')" class="mt-2" />
                <x-input-error :messages="$errors->get('guests')" class="mt-2" />
            </form>

            {{-- Step 2: room and guest details --}}
            @if ($rooms->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-6 text-gray-600">
                    {{ __('No room is free for these dates and this number of guests.') }}
                </div>
            @else
                <form method="POST" action="{{ route('staff.bookings.update', $booking) }}" class="bg-white shadow-sm sm:rounded-lg p-6 space-y-8">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="checkin" value="{{ $search['checkin'] }}">
                    <input type="hidden" name="checkout" value="{{ $search['checkout'] }}">
                    <input type="hidden" name="guests" value="{{ $search['guests'] }}">

                    <div>
                        <h3 class="font-semibold text-gray-800">{{ __('2. Room') }}</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ __('Nights that were already booked keep their original price if you stay in the same room type.') }}</p>
                        <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                            @foreach ($rooms as $room)
                                @php($price = $prices[$room->id])
                                <li>
                                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-300 p-4 has-[:checked]:border-indigo-500 has-[:checked]:ring-1 has-[:checked]:ring-indigo-500">
                                        <input type="radio" name="room_id" value="{{ $room->id }}" required
                                               @checked((int) old('room_id', $booking->room_id) === $room->id)
                                               class="mt-1 border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="text-sm">
                                            <span class="block font-semibold text-gray-900">
                                                {{ __('Room') }} {{ $room->number }} &middot; {{ $room->roomType->name }}
                                                @if ($room->id === $booking->room_id)
                                                    <span class="font-normal text-gray-500">({{ __('current') }})</span>
                                                @endif
                                            </span>
                                            <span class="block text-gray-500">{{ __('Floor') }} {{ $room->floor }} &middot; {{ __('sleeps') }} {{ $room->roomType->capacity }}</span>
                                            <span class="block mt-1 text-gray-900">ETB {{ number_format($price['total']) }}
                                                <span class="text-gray-500">({{ $price['nights'] }} {{ Str::plural('night', $price['nights']) }})</span>
                                            </span>
                                        </span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                        <x-input-error :messages="$errors->get('room_id')" class="mt-2" />
                    </div>

                    <div>
                        <h3 class="font-semibold text-gray-800">{{ __('3. Guest') }}</h3>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="guest_name" :value="__('Full name')" />
                                <x-text-input id="guest_name" name="guest_name" type="text" required maxlength="150" class="mt-1 block w-full" :value="old('guest_name', $booking->guest_name)" />
                                <x-input-error :messages="$errors->get('guest_name')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="guest_phone" :value="__('Phone')" />
                                <x-text-input id="guest_phone" name="guest_phone" type="tel" required class="mt-1 block w-full" :value="old('guest_phone', $booking->guest_phone)" />
                                <x-input-error :messages="$errors->get('guest_phone')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="guest_email" :value="__('Email (optional)')" />
                                <x-text-input id="guest_email" name="guest_email" type="email" class="mt-1 block w-full" :value="old('guest_email', $booking->guest_email)" />
                                <x-input-error :messages="$errors->get('guest_email')" class="mt-2" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="notes" :value="__('Notes (optional)')" />
                                <x-textarea-input id="notes" name="notes" rows="2" maxlength="1000" class="mt-1 block w-full">{{ old('notes', $booking->notes) }}</x-textarea-input>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>{{ __('Save changes') }}</x-primary-button>
                        <a href="{{ route('staff.bookings.show', $booking) }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
