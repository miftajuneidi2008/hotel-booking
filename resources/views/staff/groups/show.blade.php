<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Group') }} {{ $group->reference }}</h2>
            <a href="{{ route('staff.groups.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; {{ __('All groups') }}</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-auth-session-status :status="session('status')" />

            @session('error')
                <p role="alert" class="font-medium text-sm text-red-600">{{ $value }}</p>
            @endsession

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <p class="text-lg font-semibold text-gray-900">{{ $group->name }}</p>

                <dl class="mt-6 grid gap-6 text-sm sm:grid-cols-3">
                    <div><dt class="text-gray-500">{{ __('Contact') }}</dt><dd class="mt-1 text-gray-900">{{ $group->contact_name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('Phone') }}</dt><dd class="mt-1 text-gray-900">{{ $group->contact_phone }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('Email') }}</dt><dd class="mt-1 text-gray-900">{{ $group->contact_email ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('Booked by') }}</dt><dd class="mt-1 text-gray-900">{{ $group->creator?->name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('Booked on') }}</dt><dd class="mt-1 text-gray-900">{{ $group->created_at->format('M j, Y H:i') }}</dd></div>
                </dl>

                @if ($group->notes)
                    <p class="mt-6 text-sm text-gray-700"><span class="text-gray-500">{{ __('Notes') }}:</span> {{ $group->notes }}</p>
                @endif
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-6 py-3">{{ __('Booking') }}</th>
                            <th class="px-6 py-3">{{ __('Room') }}</th>
                            <th class="px-6 py-3">{{ __('Stay') }}</th>
                            <th class="px-6 py-3">{{ __('Guests') }}</th>
                            <th class="px-6 py-3 text-right">{{ __('Total') }}</th>
                            <th class="px-6 py-3">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-gray-900">
                        @foreach ($group->bookings as $booking)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <a href="{{ route('staff.bookings.show', $booking) }}" class="text-indigo-600 hover:text-indigo-900">{{ $booking->reference }}</a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $booking->room->number }} &middot; {{ $booking->room->roomType->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $booking->check_in->format('M j') }} &ndash; {{ $booking->check_out->format('M j, Y') }}</td>
                                <td class="px-6 py-4">{{ $booking->guests }}</td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">ETB {{ number_format($booking->total_amount, 2) }}</td>
                                <td class="px-6 py-4"><x-booking-status-badge :status="$booking->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50 font-semibold text-gray-900">
                            <td colspan="4" class="px-6 py-3">{{ __('Total (cancelled rooms not counted)') }}</td>
                            <td class="px-6 py-3 text-right whitespace-nowrap">ETB {{ number_format($group->activeTotal(), 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if ($group->hasConfirmedRooms())
                <form method="POST" action="{{ route('staff.groups.cancel', $group) }}" class="bg-white shadow-sm sm:rounded-lg p-6"
                      onsubmit="return confirm('{{ __('Cancel every confirmed room in this group?') }}')">
                    @csrf
                    @method('PATCH')

                    <h3 class="font-semibold text-gray-800">{{ __('Cancel the whole group') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ __('Cancels every room that is still confirmed. To cancel a single room, open that booking.') }}</p>

                    <div class="mt-4 max-w-md">
                        <x-input-label for="reason" :value="__('Reason (required)')" />
                        <x-text-input id="reason" name="reason" type="text" required maxlength="255" class="mt-1 block w-full" :value="old('reason')" />
                        <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                    </div>

                    <x-danger-button class="mt-4">{{ __('Cancel group') }}</x-danger-button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
