<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Guest bills') }} &middot; {{ __('in the house') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-auth-session-status :status="session('status')" />

            @session('error')
                <p role="alert" class="font-medium text-sm text-red-600">{{ $value }}</p>
            @endsession

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <ul class="divide-y divide-gray-100 text-sm">
                    @forelse ($bookings as $booking)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                            <div>
                                <a href="{{ route('staff.folio.show', $booking) }}" class="font-medium text-gray-900 hover:underline">{{ $booking->guest_name }}</a>
                                <span class="block text-gray-500">
                                    {{ __('Room') }} {{ $booking->room->number }} &middot; {{ __('until') }} {{ $booking->check_out->format('M j') }}
                                    &middot; {{ __('balance') }} ETB {{ number_format($booking->balanceDue(), 2) }}
                                </span>
                            </div>
                            <x-link-button :href="route('staff.folio.show', $booking)">{{ __('Open bill') }}</x-link-button>
                        </li>
                    @empty
                        <li class="px-6 py-6 text-gray-500">{{ __('No guests are checked in.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
