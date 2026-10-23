<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Group bookings') }}</h2>
            <x-link-button :href="route('staff.groups.create')">{{ __('New group booking') }}</x-link-button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-auth-session-status :status="session('status')" />

            <form method="GET" action="{{ route('staff.groups.index') }}" class="flex flex-wrap items-end gap-4">
                <div>
                    <x-input-label for="q" :value="__('Group name, reference or phone')" />
                    <x-text-input id="q" name="q" type="text" class="mt-1 block w-72" :value="$term" />
                </div>
                <x-secondary-button type="submit">{{ __('Search') }}</x-secondary-button>
                @if ($term !== '')
                    <a href="{{ route('staff.groups.index') }}" class="pb-2 text-sm text-gray-600 hover:text-gray-900">{{ __('Clear') }}</a>
                @endif
            </form>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-6 py-3">{{ __('Reference') }}</th>
                                <th class="px-6 py-3">{{ __('Group / company') }}</th>
                                <th class="px-6 py-3">{{ __('Contact') }}</th>
                                <th class="px-6 py-3">{{ __('Stay') }}</th>
                                <th class="px-6 py-3">{{ __('Rooms') }}</th>
                                <th class="px-6 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-gray-900">
                            @forelse ($groups as $group)
                                <tr>
                                    <td class="px-6 py-4 font-medium whitespace-nowrap">{{ $group->reference }}</td>
                                    <td class="px-6 py-4">{{ $group->name }}</td>
                                    <td class="px-6 py-4">
                                        {{ $group->contact_name ?? '—' }}
                                        <span class="block text-xs text-gray-500">{{ $group->contact_phone }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if ($group->bookings_min_check_in)
                                            {{ \Illuminate\Support\Carbon::parse($group->bookings_min_check_in)->format('M j') }}
                                            &ndash; {{ \Illuminate\Support\Carbon::parse($group->bookings_max_check_out)->format('M j, Y') }}
                                        @else
                                            &mdash;
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">{{ $group->bookings_count }}</td>
                                    <td class="px-6 py-4 text-right whitespace-nowrap">
                                        <a href="{{ route('staff.groups.show', $group) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('View') }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-6 py-10 text-center text-gray-500">{{ $term !== '' ? __('No groups match your search.') : __('No group bookings yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $groups->links() }}
        </div>
    </div>
</x-app-layout>
