{{-- Short summary of a booking, shared by the check-in, check-out and in-house screens. --}}
<div class="bg-white shadow-sm sm:rounded-lg p-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-lg font-semibold text-gray-900">{{ $booking->guest_name }}</p>
        <x-booking-status-badge :status="$booking->status" />
    </div>

    <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-4">
        <div><dt class="text-gray-500">{{ __('Room') }}</dt><dd class="mt-1 text-gray-900">{{ $booking->room->number }} &middot; {{ $booking->room->roomType->name }}</dd></div>
        <div><dt class="text-gray-500">{{ __('Stay') }}</dt><dd class="mt-1 text-gray-900">{{ $booking->check_in->format('M j') }} &ndash; {{ $booking->check_out->format('M j, Y') }} ({{ $booking->nights() }} {{ Str::plural('night', $booking->nights()) }})</dd></div>
        <div><dt class="text-gray-500">{{ __('Guests') }}</dt><dd class="mt-1 text-gray-900">{{ $booking->guests }}</dd></div>
        <div><dt class="text-gray-500">{{ __('Reference') }}</dt><dd class="mt-1 text-gray-900">{{ $booking->reference }}</dd></div>
    </dl>
</div>
