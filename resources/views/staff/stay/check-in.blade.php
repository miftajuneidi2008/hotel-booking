<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Check in') }} {{ $booking->reference }}</h2>
            <a href="{{ route('staff.bookings.show', $booking) }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; {{ __('Back to booking') }}</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @session('error')
                <p role="alert" class="font-medium text-sm text-red-600">{{ $value }}</p>
            @endsession
            <x-auth-session-status :status="session('status')" />

            @include('staff.stay._summary')

            @php($ready = $booking->room->status === \App\Enums\RoomStatus::Clean)

            @unless ($ready)
                <div class="rounded-lg border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-800">
                    {{ __('Room :room is :status, so the guest cannot be checked in yet.', ['room' => $booking->room->number, 'status' => strtolower(__($booking->room->status->label()))]) }}

                    <div class="mt-3 flex flex-wrap items-center gap-4">
                        @if ($booking->room->status === \App\Enums\RoomStatus::Dirty)
                            <form method="POST" action="{{ route('staff.rooms.clean', $booking->room) }}">
                                @csrf
                                @method('PATCH')
                                <x-secondary-button type="submit">{{ __('Mark room clean') }}</x-secondary-button>
                            </form>
                        @endif
                        <a href="{{ route('staff.bookings.edit', $booking) }}" class="font-medium text-yellow-900 underline">{{ __('Give the guest another room') }}</a>
                    </div>
                </div>
            @endunless

            <form method="POST" action="{{ route('staff.bookings.check-in.store', $booking) }}" class="bg-white shadow-sm sm:rounded-lg p-6 space-y-6">
                @csrf
                <h3 class="font-semibold text-gray-800">{{ __('Guest ID or passport') }}</h3>

                @include('staff.stay._id-fields', ['booking' => $booking, 'idTypes' => $idTypes])

                <div class="flex items-center gap-4">
                    <x-primary-button :disabled="! $ready">{{ __('Check in') }}</x-primary-button>
                    <a href="{{ route('staff.bookings.show', $booking) }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
