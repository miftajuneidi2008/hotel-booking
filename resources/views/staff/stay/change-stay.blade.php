<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Change stay') }} {{ $booking->reference }}</h2>
            <a href="{{ route('staff.bookings.show', $booking) }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; {{ __('Back to booking') }}</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @session('error')
                <p role="alert" class="font-medium text-sm text-red-600">{{ $value }}</p>
            @endsession

            @include('staff.stay._summary')

            <form method="POST" action="{{ route('staff.bookings.stay.update', $booking) }}" class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                @csrf
                @method('PUT')

                <h3 class="font-semibold text-gray-800">{{ __('New check-out date') }}</h3>

                <div class="max-w-xs">
                    <x-input-label for="checkout" :value="__('Check out')" />
                    <x-text-input id="checkout" name="checkout" type="date" required min="{{ today()->toDateString() }}" class="mt-1 block w-full"
                                  :value="old('checkout', $booking->check_out->toDateString())" />
                    <x-input-error :messages="$errors->get('checkout')" class="mt-2" />
                </div>

                <ul class="list-disc space-y-1 pl-5 text-sm text-gray-600">
                    <li>{{ __('Later date: the extra nights are added at today\'s rate.') }}</li>
                    <li>{{ __('Earlier date (not before today): the nights after it come off the bill.') }}</li>
                    <li>{{ __('Nights that stay on the bill keep their price.') }}</li>
                    @if ($freeUntil)
                        <li class="text-yellow-700">{{ __('Room :room is free until :date; another guest arrives then.', ['room' => $booking->room->number, 'date' => \Illuminate\Support\Carbon::parse($freeUntil)->format('M j, Y')]) }}</li>
                    @endif
                </ul>

                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Save new date') }}</x-primary-button>
                    <a href="{{ route('staff.bookings.show', $booking) }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
