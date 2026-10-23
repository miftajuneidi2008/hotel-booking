<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Rooms') }}
            </h2>

            <x-link-button :href="route('admin.rooms.create')">{{ __('Add room') }}</x-link-button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-auth-session-status :status="session('status')" />

            @session('error')
                <p class="font-medium text-sm text-red-600">{{ $value }}</p>
            @endsession

            <form method="GET" action="{{ route('admin.rooms.index') }}" class="flex flex-wrap items-end gap-4">
                <div>
                    <x-input-label for="room_type_id" :value="__('Room type')" />
                    <x-select-input id="room_type_id" name="room_type_id" class="mt-1 block w-48">
                        <option value="">{{ __('All types') }}</option>
                        @foreach ($roomTypes as $roomType)
                            <option value="{{ $roomType->id }}" @selected((int) ($filters['room_type_id'] ?? 0) === $roomType->id)>{{ $roomType->name }}</option>
                        @endforeach
                    </x-select-input>
                </div>

                <div>
                    <x-input-label for="status" :value="__('Status')" />
                    <x-select-input id="status" name="status" class="mt-1 block w-48">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ __($status->label()) }}</option>
                        @endforeach
                    </x-select-input>
                </div>

                <x-secondary-button type="submit">{{ __('Filter') }}</x-secondary-button>

                @if ($filters !== [])
                    <a href="{{ route('admin.rooms.index') }}" class="pb-2 text-sm text-gray-600 hover:text-gray-900">{{ __('Clear') }}</a>
                @endif
            </form>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-6 py-3">{{ __('Room') }}</th>
                                <th class="px-6 py-3">{{ __('Type') }}</th>
                                <th class="px-6 py-3">{{ __('Floor') }}</th>
                                <th class="px-6 py-3">{{ __('Status') }}</th>
                                <th class="px-6 py-3">{{ __('Photos') }}</th>
                                <th class="px-6 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-gray-900">
                            @forelse ($rooms as $room)
                                <tr>
                                    <td class="px-6 py-4 font-medium">{{ $room->number }}</td>
                                    <td class="px-6 py-4">{{ $room->roomType->name }}</td>
                                    <td class="px-6 py-4">{{ $room->floor }}</td>
                                    <td class="px-6 py-4"><x-room-status-badge :status="$room->status" /></td>
                                    <td class="px-6 py-4">{{ $room->photos_count }}</td>
                                    <td class="px-6 py-4 text-right whitespace-nowrap space-x-4">
                                        <a href="{{ route('admin.rooms.edit', $room) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('Edit') }}</a>
                                        <form method="POST" action="{{ route('admin.rooms.destroy', $room) }}" class="inline"
                                              onsubmit="return confirm('{{ __('Delete this room?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900">{{ __('Delete') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-gray-500">
                                        @if ($filters !== [])
                                            {{ __('No rooms match these filters.') }}
                                        @else
                                            {{ __('No rooms yet. Add a room type first, then add your rooms.') }}
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
