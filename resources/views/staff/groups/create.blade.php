<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('New group booking') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @session('error')
                <p role="alert" class="font-medium text-sm text-red-600">{{ $value }}</p>
            @endsession

            {{-- Step 1: dates --}}
            <form method="GET" action="{{ route('staff.groups.create') }}" class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800">{{ __('1. Dates') }}</h3>

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
                    <x-primary-button>{{ __('Find rooms') }}</x-primary-button>
                </div>

                <x-input-error :messages="$errors->get('checkin')" class="mt-2" />
                <x-input-error :messages="$errors->get('checkout')" class="mt-2" />
            </form>

            @if ($search)
                @if ($rooms->isEmpty())
                    <div class="bg-white shadow-sm sm:rounded-lg p-6 text-gray-600">{{ __('No room is free for these dates.') }}</div>
                @else
                    <form method="POST" action="{{ route('staff.groups.store') }}" class="bg-white shadow-sm sm:rounded-lg p-6 space-y-8">
                        @csrf
                        <input type="hidden" name="checkin" value="{{ $search['checkin'] }}">
                        <input type="hidden" name="checkout" value="{{ $search['checkout'] }}">

                        <div>
                            <h3 class="font-semibold text-gray-800">{{ __('2. Tick the rooms') }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ __('Set the number of guests for each room. All rooms are booked together, or none.') }}</p>

                            <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                                @foreach ($rooms as $room)
                                    @php($quote = $quotes[$room->room_type_id])
                                    <li class="flex items-start gap-3 rounded-lg border border-gray-300 p-4 has-[:checked]:border-indigo-500 has-[:checked]:ring-1 has-[:checked]:ring-indigo-500">
                                        <input type="checkbox" id="room-{{ $room->id }}" name="room_ids[]" value="{{ $room->id }}"
                                               @checked(in_array($room->id, array_map('intval', (array) old('room_ids', []))))
                                               class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <label for="room-{{ $room->id }}" class="flex-1 cursor-pointer text-sm">
                                            <span class="block font-semibold text-gray-900">{{ __('Room') }} {{ $room->number }} &middot; {{ $room->roomType->name }}</span>
                                            <span class="block text-gray-500">{{ __('Floor') }} {{ $room->floor }} &middot; {{ __('sleeps') }} {{ $room->roomType->capacity }}</span>
                                            <span class="block mt-1 text-gray-900">ETB {{ number_format($quote['total']) }}
                                                <span class="text-gray-500">({{ $quote['nights'] }} {{ Str::plural('night', $quote['nights']) }})</span>
                                            </span>
                                        </label>
                                        <div>
                                            <label for="guests-{{ $room->id }}" class="sr-only">{{ __('Guests in room :room', ['room' => $room->number]) }}</label>
                                            <x-select-input id="guests-{{ $room->id }}" name="guests[{{ $room->id }}]" class="block w-16 text-sm" :title="__('Guests')">
                                                @foreach (range(1, $room->roomType->capacity) as $n)
                                                    <option value="{{ $n }}" @selected((int) old("guests.{$room->id}", 1) === $n)>{{ $n }}</option>
                                                @endforeach
                                            </x-select-input>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                            <x-input-error :messages="$errors->get('room_ids')" class="mt-2" />
                            <x-input-error :messages="$errors->get('room_ids.*')" class="mt-2" />
                            <x-input-error :messages="$errors->get('guests.*')" class="mt-2" />
                        </div>

                        <div>
                            <h3 class="font-semibold text-gray-800">{{ __('3. Group or company') }}</h3>
                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <x-input-label for="name" :value="__('Group or company name')" />
                                    <x-text-input id="name" name="name" type="text" required maxlength="150" class="mt-1 block w-full" :value="old('name')" />
                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="contact_name" :value="__('Contact person (optional)')" />
                                    <x-text-input id="contact_name" name="contact_name" type="text" maxlength="150" class="mt-1 block w-full" :value="old('contact_name')" />
                                    <x-input-error :messages="$errors->get('contact_name')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="contact_phone" :value="__('Contact phone')" />
                                    <x-text-input id="contact_phone" name="contact_phone" type="tel" required class="mt-1 block w-full" :value="old('contact_phone')" />
                                    <x-input-error :messages="$errors->get('contact_phone')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="contact_email" :value="__('Contact email (optional)')" />
                                    <x-text-input id="contact_email" name="contact_email" type="email" class="mt-1 block w-full" :value="old('contact_email')" />
                                    <x-input-error :messages="$errors->get('contact_email')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="source" :value="__('Booking source')" />
                                    <x-select-input id="source" name="source" class="mt-1 block w-full">
                                        @foreach ($sources as $source)
                                            <option value="{{ $source->value }}" @selected(old('source', 'phone') === $source->value)>{{ __($source->label()) }}</option>
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

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Save group booking') }}</x-primary-button>
                            <a href="{{ route('staff.groups.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                        </div>
                    </form>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
