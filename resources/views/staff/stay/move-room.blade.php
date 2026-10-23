<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Change room') }} {{ $booking->reference }}</h2>
            <a href="{{ route('staff.bookings.show', $booking) }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; {{ __('Back to booking') }}</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @session('error')
                <p role="alert" class="font-medium text-sm text-red-600">{{ $value }}</p>
            @endsession

            @include('staff.stay._summary')

            @if ($rooms->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-6 text-gray-600">
                    {{ __('No clean room is free for the rest of this stay (:count :guests).', ['count' => $booking->guests, 'guests' => Str::plural('guest', $booking->guests)]) }}
                </div>
            @else
                <form method="POST" action="{{ route('staff.bookings.move-room.store', $booking) }}" class="bg-white shadow-sm sm:rounded-lg p-6 space-y-8">
                    @csrf

                    <div>
                        <h3 class="font-semibold text-gray-800">{{ __('Move to') }}</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ __('Only clean rooms that are free until check-out are listed. Nights already stayed keep their price. The old room is marked dirty.') }}</p>

                        <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                            @foreach ($rooms as $room)
                                <li>
                                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-300 p-4 has-[:checked]:border-indigo-500 has-[:checked]:ring-1 has-[:checked]:ring-indigo-500">
                                        <input type="radio" name="room_id" value="{{ $room->id }}" required @checked((int) old('room_id') === $room->id)
                                               class="mt-1 border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="text-sm">
                                            <span class="block font-semibold text-gray-900">{{ __('Room') }} {{ $room->number }} &middot; {{ $room->roomType->name }}</span>
                                            <span class="block text-gray-500">{{ __('Floor') }} {{ $room->floor }} &middot; {{ __('sleeps') }} {{ $room->roomType->capacity }}</span>
                                            <span class="block mt-1 text-gray-900">{{ __('New total') }}: ETB {{ number_format($prices[$room->id], 2) }}</span>
                                        </span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                        <x-input-error :messages="$errors->get('room_id')" class="mt-2" />
                    </div>

                    <div class="max-w-xl">
                        <x-input-label for="reason" :value="__('Reason (required)')" />
                        <x-text-input id="reason" name="reason" type="text" required maxlength="255" class="mt-1 block w-full" :value="old('reason')" />
                        <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>{{ __('Move guest') }}</x-primary-button>
                        <a href="{{ route('staff.bookings.show', $booking) }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
