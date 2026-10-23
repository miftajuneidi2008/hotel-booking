<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('New booking') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @session('error')
                <p role="alert" class="font-medium text-sm text-red-600">{{ $value }}</p>
            @endsession

            {{-- Step 1: dates and guests --}}
            <form method="GET" action="{{ route('staff.bookings.create') }}" class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800">{{ __('1. Dates and guests') }}</h3>

                <div class="mt-4 flex flex-wrap items-end gap-4">
                    <div>
                        <x-input-label for="checkin" :value="__('Check in')" />
                        <x-text-input id="checkin" name="checkin" type="date" required min="{{ today()->toDateString() }}"
                                      class="mt-1 block" :value="old('checkin', $search['checkin'] ?? request('checkin', today()->toDateString()))" />
                    </div>
                    <div>
                        <x-input-label for="checkout" :value="__('Check out')" />
                        <x-text-input id="checkout" name="checkout" type="date" required min="{{ today()->toDateString() }}"
                                      class="mt-1 block" :value="old('checkout', $search['checkout'] ?? request('checkout', today()->addDay()->toDateString()))" />
                    </div>
                    <div>
                        <x-input-label for="guests" :value="__('Guests')" />
                        <x-select-input id="guests" name="guests" class="mt-1 block">
                            @foreach (range(1, (int) config('hotel.booking.max_guests', 10)) as $n)
                                <option value="{{ $n }}" @selected((int) ($search['guests'] ?? request('guests', 1)) === $n)>{{ $n }}</option>
                            @endforeach
                        </x-select-input>
                    </div>
                    <x-primary-button>{{ __('Find rooms') }}</x-primary-button>
                </div>

                <x-input-error :messages="$errors->get('checkin')" class="mt-2" />
                <x-input-error :messages="$errors->get('checkout')" class="mt-2" />
                <x-input-error :messages="$errors->get('guests')" class="mt-2" />
            </form>

            {{-- Step 2: pick a room and enter guest details --}}
            @if ($search)
                @if ($rooms->isEmpty())
                    <div class="bg-white shadow-sm sm:rounded-lg p-6 text-gray-600">
                        {{ __('No room is free for these dates and this number of guests.') }}
                    </div>
                @else
                    <form method="POST" action="{{ route('staff.bookings.store') }}" class="bg-white shadow-sm sm:rounded-lg p-6 space-y-8">
                        @csrf
                        <input type="hidden" name="checkin" value="{{ $search['checkin'] }}">
                        <input type="hidden" name="checkout" value="{{ $search['checkout'] }}">
                        <input type="hidden" name="guests" value="{{ $search['guests'] }}">

                        <div>
                            <h3 class="font-semibold text-gray-800">{{ __('2. Choose a room') }}</h3>
                            <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                                @foreach ($rooms as $room)
                                    @php($quote = $quotes[$room->room_type_id])
                                    <li>
                                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-300 p-4 has-[:checked]:border-indigo-500 has-[:checked]:ring-1 has-[:checked]:ring-indigo-500">
                                            <input type="radio" name="room_id" value="{{ $room->id }}" required
                                                   @checked((int) old('room_id') === $room->id)
                                                   class="mt-1 border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            <span class="text-sm">
                                                <span class="block font-semibold text-gray-900">{{ __('Room') }} {{ $room->number }} &middot; {{ $room->roomType->name }}</span>
                                                <span class="block text-gray-500">{{ __('Floor') }} {{ $room->floor }} &middot; {{ __('sleeps') }} {{ $room->roomType->capacity }} &middot; <x-room-status-badge :status="$room->status" /></span>
                                                <span class="block mt-1 text-gray-900">ETB {{ number_format($quote['total']) }}
                                                    <span class="text-gray-500">({{ $quote['nights'] }} {{ Str::plural('night', $quote['nights']) }})</span>
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
                                    <x-text-input id="guest_name" name="guest_name" type="text" required maxlength="150" class="mt-1 block w-full" :value="old('guest_name')" />
                                    <x-input-error :messages="$errors->get('guest_name')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="guest_phone" :value="__('Phone')" />
                                    <x-text-input id="guest_phone" name="guest_phone" type="tel" required class="mt-1 block w-full" :value="old('guest_phone')" />
                                    <x-input-error :messages="$errors->get('guest_phone')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="guest_email" :value="__('Email (optional)')" />
                                    <x-text-input id="guest_email" name="guest_email" type="email" class="mt-1 block w-full" :value="old('guest_email')" />
                                    <x-input-error :messages="$errors->get('guest_email')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="source" :value="__('Booking source')" />
                                    <x-select-input id="source" name="source" class="mt-1 block w-full">
                                        @foreach ($sources as $source)
                                            <option value="{{ $source->value }}" @selected(old('source', 'walk_in') === $source->value)>{{ __($source->label()) }}</option>
                                        @endforeach
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('source')" class="mt-2" />
                                </div>
                                <div class="sm:col-span-2">
                                    <x-input-label for="notes" :value="__('Notes (optional)')" />
                                    <x-textarea-input id="notes" name="notes" rows="2" maxlength="1000" class="mt-1 block w-full">{{ old('notes') }}</x-textarea-input>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="font-semibold text-gray-800">{{ __('4. Deposit (optional)') }}</h3>
                            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                                <div>
                                    <x-input-label for="deposit_amount" :value="__('Amount (ETB)')" />
                                    <x-text-input id="deposit_amount" name="deposit_amount" type="number" min="0" step="0.01" class="mt-1 block w-full" :value="old('deposit_amount')" />
                                    <x-input-error :messages="$errors->get('deposit_amount')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="deposit_method" :value="__('Paid by')" />
                                    <x-select-input id="deposit_method" name="deposit_method" class="mt-1 block w-full">
                                        <option value="">&mdash;</option>
                                        @foreach ($methods as $method)
                                            <option value="{{ $method->value }}" @selected(old('deposit_method') === $method->value)>{{ __($method->label()) }}</option>
                                        @endforeach
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('deposit_method')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="deposit_reference" :value="__('Reference no.')" />
                                    <x-text-input id="deposit_reference" name="deposit_reference" type="text" maxlength="100" class="mt-1 block w-full" :value="old('deposit_reference')" />
                                </div>
                            </div>
                        </div>

                        @if ($isToday)
                            <label class="flex items-center gap-3 text-sm text-gray-800">
                                <input type="checkbox" name="check_in_now" value="1" @checked(old('check_in_now'))
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                {{ __('The guest is here now: check in straight away (room must be clean)') }}
                            </label>
                        @endif

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Save booking') }}</x-primary-button>
                            <a href="{{ route('staff.bookings.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                        </div>
                    </form>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
