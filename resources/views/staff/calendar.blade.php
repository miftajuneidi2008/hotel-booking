<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Calendar') }}</h2>
            <x-link-button :href="route('staff.bookings.create')">{{ __('New booking') }}</x-link-button>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-4">

            <div class="flex flex-wrap items-end gap-3">
                <a href="{{ route('staff.calendar', ['from' => $previous, 'days' => $days]) }}"
                   class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">&larr; {{ __('Earlier') }}</a>
                <a href="{{ route('staff.calendar', ['days' => $days]) }}"
                   class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ __('Today') }}</a>
                <a href="{{ route('staff.calendar', ['from' => $next, 'days' => $days]) }}"
                   class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ __('Later') }} &rarr;</a>

                <form method="GET" action="{{ route('staff.calendar') }}" class="flex items-end gap-3 sm:ml-4">
                    <div>
                        <x-input-label for="from" :value="__('From')" />
                        <x-text-input id="from" name="from" type="date" class="mt-1 block" :value="$from->toDateString()" />
                    </div>
                    <div>
                        <x-input-label for="days" :value="__('Show')" />
                        <x-select-input id="days" name="days" class="mt-1 block">
                            @foreach ([7 => '7 days', 14 => '14 days', 31 => '31 days'] as $value => $label)
                                <option value="{{ $value }}" @selected($days === $value)>{{ __($label) }}</option>
                            @endforeach
                        </x-select-input>
                    </div>
                    <x-secondary-button type="submit">{{ __('Go') }}</x-secondary-button>
                </form>
            </div>

            <x-input-error :messages="$errors->get('from')" />
            <x-input-error :messages="$errors->get('days')" />

            <div class="flex flex-wrap gap-4 text-xs text-gray-600">
                <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded bg-green-200 ring-1 ring-green-400"></span>{{ __('Confirmed') }}</span>
                <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded bg-blue-200 ring-1 ring-blue-400"></span>{{ __('Checked in') }}</span>
                <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded bg-gray-200 ring-1 ring-gray-300"></span>{{ __('Out of service') }}</span>
                <span>{{ __('Click an empty day to start a booking. Click a bar to open it.') }}</span>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="border-collapse text-xs" style="min-width: {{ 10 + $days * 3.25 }}rem">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600">
                            <th class="sticky left-0 z-10 w-40 min-w-40 border-b border-r border-gray-200 bg-gray-50 px-3 py-2 text-left font-semibold">{{ __('Room') }}</th>
                            @foreach ($dates as $date)
                                <th class="min-w-[3.25rem] border-b border-gray-200 px-1 py-2 text-center font-medium {{ $date->toDateString() === $today ? 'bg-indigo-50 text-indigo-700' : '' }}">
                                    <span class="block">{{ $date->format('D') }}</span>
                                    <span class="block text-sm font-semibold">{{ $date->format('j') }}</span>
                                    @if ($loop->first || $date->day === 1)
                                        <span class="block text-[10px] uppercase tracking-wide">{{ $date->format('M') }}</span>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($rows as $row)
                            @php($outOfService = $row['room']->status === \App\Enums\RoomStatus::OutOfService)
                            <tr class="border-b border-gray-100">
                                <th scope="row" class="sticky left-0 z-10 border-r border-gray-200 bg-white px-3 py-2 text-left font-medium text-gray-900">
                                    {{ $row['room']->number }}
                                    <span class="block font-normal text-gray-500">{{ $row['room']->roomType->name }}</span>
                                </th>

                                @foreach ($row['segments'] as $segment)
                                    @php($booking = $segment['booking'])
                                    @if ($booking)
                                        @php($checkedIn = $booking->status === \App\Enums\BookingStatus::CheckedIn)
                                        <td colspan="{{ $segment['span'] }}" class="p-0.5">
                                            <a href="{{ route('staff.bookings.show', $booking) }}"
                                               title="{{ $booking->guest_name }} &middot; {{ $booking->check_in->format('M j') }}&ndash;{{ $booking->check_out->format('M j') }} &middot; {{ $booking->reference }}"
                                               class="block truncate rounded px-2 py-2 font-medium ring-1 hover:brightness-95 {{ $checkedIn ? 'bg-blue-200 text-blue-900 ring-blue-400' : 'bg-green-200 text-green-900 ring-green-400' }}">
                                                {{ $booking->guest_name }}
                                            </a>
                                        </td>
                                    @elseif ($outOfService)
                                        <td class="bg-gray-200 p-0.5"></td>
                                    @else
                                        <td class="p-0.5 {{ $segment['date']->toDateString() === $today ? 'bg-indigo-50/50' : '' }}">
                                            @if ($segment['date']->toDateString() >= $today)
                                                <a href="{{ route('staff.bookings.create', ['checkin' => $segment['date']->toDateString(), 'checkout' => $segment['date']->addDay()->toDateString(), 'guests' => 1]) }}"
                                                   class="block rounded py-2 text-center text-gray-300 hover:bg-indigo-50 hover:text-indigo-600"
                                                   aria-label="{{ __('Book room :room on :date', ['room' => $row['room']->number, 'date' => $segment['date']->format('M j')]) }}">+</a>
                                            @else
                                                <span class="block py-2"></span>
                                            @endif
                                        </td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>

                    <tfoot>
                        <tr class="bg-gray-50 text-gray-700">
                            <th class="sticky left-0 z-10 border-r border-t border-gray-200 bg-gray-50 px-3 py-2 text-left font-semibold">{{ __('Free rooms') }}</th>
                            @foreach ($dates as $date)
                                <td class="border-t border-gray-200 px-1 py-2 text-center font-semibold">{{ $free[$date->toDateString()] }}</td>
                            @endforeach
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if ($rows->isEmpty())
                <p class="text-sm text-gray-500">{{ __('There are no rooms yet.') }}</p>
            @endif
        </div>
    </div>
</x-app-layout>
