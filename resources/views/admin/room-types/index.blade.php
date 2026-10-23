<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Room types') }}
            </h2>

            <x-link-button :href="route('admin.room-types.create')">{{ __('Add room type') }}</x-link-button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-auth-session-status :status="session('status')" />

            @session('error')
                <p class="font-medium text-sm text-red-600">{{ $value }}</p>
            @endsession

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-6 py-3">{{ __('Name') }}</th>
                                <th class="px-6 py-3">{{ __('Capacity') }}</th>
                                <th class="px-6 py-3">{{ __('Base rate (ETB / night)') }}</th>
                                <th class="px-6 py-3">{{ __('Rooms') }}</th>
                                <th class="px-6 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-gray-900">
                            @forelse ($roomTypes as $roomType)
                                <tr>
                                    <td class="px-6 py-4">
                                        <div class="font-medium">{{ $roomType->name }}</div>
                                        @if ($roomType->description)
                                            <div class="mt-0.5 max-w-md truncate text-gray-500">{{ $roomType->description }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">{{ trans_choice(':count guest|:count guests', $roomType->capacity) }}</td>
                                    <td class="px-6 py-4">{{ number_format($roomType->base_rate, 2) }}</td>
                                    <td class="px-6 py-4">{{ $roomType->rooms_count }}</td>
                                    <td class="px-6 py-4 text-right whitespace-nowrap space-x-4">
                                        <a href="{{ route('admin.room-types.rates.index', $roomType) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('Rates') }}</a>
                                        <a href="{{ route('admin.room-types.edit', $roomType) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('Edit') }}</a>
                                        <form method="POST" action="{{ route('admin.room-types.destroy', $roomType) }}" class="inline"
                                              onsubmit="return confirm('{{ __('Delete this room type?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900">{{ __('Delete') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-gray-500">
                                        {{ __('No room types yet. Add your first one, for example Single, Double or Suite.') }}
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
