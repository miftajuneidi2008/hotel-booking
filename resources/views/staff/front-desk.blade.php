<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Front desk') }} &middot; {{ \Illuminate\Support\Carbon::parse($today)->format('D, M j') }}</h2>
            <x-link-button :href="route('staff.bookings.create')">{{ __('New booking') }}</x-link-button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            <x-auth-session-status :status="session('status')" />

            @session('error')
                <p role="alert" class="font-medium text-sm text-red-600">{{ $value }}</p>
            @endsession

            {{-- Arrivals --}}
            <section>
                <h3 class="font-semibold text-gray-800">{{ __('Arrivals') }} <span class="text-gray-500">({{ $arrivals->count() }})</span></h3>
                <div class="mt-3 bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <ul class="divide-y divide-gray-100 text-sm">
                        @forelse ($arrivals as $booking)
                            <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                                <div>
                                    <a href="{{ route('staff.bookings.show', $booking) }}" class="font-medium text-gray-900 hover:underline">{{ $booking->guest_name }}</a>
                                    <span class="block text-gray-500">
                                        {{ __('Room') }} {{ $booking->room->number }} &middot; {{ $booking->room->roomType->name }}
                                        &middot; {{ $booking->check_in->format('M j') }} &ndash; {{ $booking->check_out->format('M j') }}
                                        &middot; <x-room-status-badge :status="$booking->room->status" />
                                    </span>
                                </div>
                                <x-link-button :href="route('staff.bookings.check-in', $booking)">{{ __('Check in') }}</x-link-button>
                            </li>
                        @empty
                            <li class="px-6 py-6 text-gray-500">{{ __('No arrivals waiting.') }}</li>
                        @endforelse
                    </ul>
                </div>
            </section>

            {{-- Departures --}}
            <section>
                <h3 class="font-semibold text-gray-800">{{ __('Leaving today or overdue') }} <span class="text-gray-500">({{ $departures->count() }})</span></h3>
                <div class="mt-3 bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <ul class="divide-y divide-gray-100 text-sm">
                        @forelse ($departures as $booking)
                            <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                                <div>
                                    <a href="{{ route('staff.bookings.show', $booking) }}" class="font-medium text-gray-900 hover:underline">{{ $booking->guest_name }}</a>
                                    <span class="block text-gray-500">
                                        {{ __('Room') }} {{ $booking->room->number }} &middot; {{ __('check-out') }} {{ $booking->check_out->format('M j') }}
                                        &middot; {{ __('balance') }} ETB {{ number_format($booking->balanceDue(), 2) }}
                                    </span>
                                </div>
                                <x-link-button :href="route('staff.bookings.check-out', $booking)">{{ __('Check out') }}</x-link-button>
                            </li>
                        @empty
                            <li class="px-6 py-6 text-gray-500">{{ __('Nobody is due to leave.') }}</li>
                        @endforelse
                    </ul>
                </div>
            </section>

            {{-- In house --}}
            <section>
                <h3 class="font-semibold text-gray-800">{{ __('In the house') }} <span class="text-gray-500">({{ $inHouse->count() }})</span></h3>
                <div class="mt-3 bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <ul class="divide-y divide-gray-100 text-sm">
                        @forelse ($inHouse as $booking)
                            <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                                <div>
                                    <a href="{{ route('staff.bookings.show', $booking) }}" class="font-medium text-gray-900 hover:underline">{{ $booking->guest_name }}</a>
                                    <span class="block text-gray-500">{{ __('Room') }} {{ $booking->room->number }} &middot; {{ __('until') }} {{ $booking->check_out->format('M j') }}</span>
                                </div>
                                <a href="{{ route('staff.bookings.show', $booking) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('Open') }}</a>
                            </li>
                        @empty
                            <li class="px-6 py-6 text-gray-500">{{ __('No guests are checked in.') }}</li>
                        @endforelse
                    </ul>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
