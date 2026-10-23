<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Bookings') }}</h2>
            <x-link-button :href="route('staff.bookings.create')">{{ __('New booking') }}</x-link-button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-auth-session-status :status="session('status')" />

            @session('error')
                <p class="font-medium text-sm text-red-600">{{ $value }}</p>
            @endsession

            <form method="GET" action="{{ route('staff.bookings.index') }}" class="flex flex-wrap items-end gap-4">
                <div>
                    <x-input-label for="q" :value="__('Reference, name or phone')" />
                    <x-text-input id="q" name="q" type="text" class="mt-1 block w-64" :value="$filters['q'] ?? ''" />
                </div>

                <div>
                    <x-input-label for="status" :value="__('Status')" />
                    <x-select-input id="status" name="status" class="mt-1 block w-44">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ __($status->label()) }}</option>
                        @endforeach
                    </x-select-input>
                </div>

                <div>
                    <x-input-label for="date" :value="__('Staying on')" />
                    <x-text-input id="date" name="date" type="date" class="mt-1 block w-44" :value="$filters['date'] ?? ''" />
                </div>

                <x-secondary-button type="submit">{{ __('Filter') }}</x-secondary-button>

                @if ($filters !== [])
                    <a href="{{ route('staff.bookings.index') }}" class="pb-2 text-sm text-gray-600 hover:text-gray-900">{{ __('Clear') }}</a>
                @endif
            </form>

            @error('status')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            @error('date')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-6 py-3">{{ __('Reference') }}</th>
                                <th class="px-6 py-3">{{ __('Guest') }}</th>
                                <th class="px-6 py-3">{{ __('Room') }}</th>
                                <th class="px-6 py-3">{{ __('Stay') }}</th>
                                <th class="px-6 py-3">{{ __('Source') }}</th>
                                <th class="px-6 py-3">{{ __('Status') }}</th>
                                <th class="px-6 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-gray-900">
                            @forelse ($bookings as $booking)
                                <tr>
                                    <td class="px-6 py-4 font-medium whitespace-nowrap">{{ $booking->reference }}</td>
                                    <td class="px-6 py-4">
                                        {{ $booking->guest_name }}
                                        <span class="block text-xs text-gray-500">{{ $booking->guest_phone }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $booking->room->number }} &middot; {{ $booking->room->roomType->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $booking->check_in->format('M j') }} &ndash; {{ $booking->check_out->format('M j, Y') }}
                                        <span class="block text-xs text-gray-500">{{ $booking->nights() }} {{ Str::plural('night', $booking->nights()) }}</span>
                                    </td>
                                    <td class="px-6 py-4">{{ __($booking->source->label()) }}</td>
                                    <td class="px-6 py-4"><x-booking-status-badge :status="$booking->status" /></td>
                                    <td class="px-6 py-4 text-right whitespace-nowrap">
                                        <a href="{{ route('staff.bookings.show', $booking) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('View') }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-10 text-center text-gray-500">
                                        {{ $filters !== [] ? __('No bookings match these filters.') : __('No bookings yet.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $bookings->links() }}
        </div>
    </div>
</x-app-layout>
