<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Users') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-auth-session-status :status="session('status')" />

            @session('error')
                <p role="alert" class="font-medium text-sm text-red-600">{{ $value }}</p>
            @endsession

            <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-wrap items-end gap-4">
                <div>
                    <x-input-label for="q" :value="__('Search')" />
                    <x-text-input id="q" name="q" type="search" class="mt-1 block w-64" :value="$filters['q'] ?? ''" placeholder="{{ __('Name or email') }}" />
                </div>
                <div>
                    <x-input-label for="role" :value="__('Role')" />
                    <x-select-input id="role" name="role" class="mt-1 block w-48">
                        <option value="">{{ __('All roles') }}</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}" @selected(($filters['role'] ?? null) === $role->value)>{{ __($role->label()) }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <div>
                    <x-input-label for="status" :value="__('Status')" />
                    <x-select-input id="status" name="status" class="mt-1 block w-40">
                        <option value="">{{ __('All') }}</option>
                        <option value="active" @selected(($filters['status'] ?? null) === 'active')>{{ __('Active') }}</option>
                        <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>{{ __('Deactivated') }}</option>
                    </x-select-input>
                </div>
                <x-secondary-button type="submit">{{ __('Filter') }}</x-secondary-button>
                <a href="{{ route('admin.users.index') }}" class="pb-2 text-sm text-gray-600 hover:text-gray-900">{{ __('Reset') }}</a>
            </form>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-6 py-3">{{ __('Name') }}</th>
                                <th class="px-6 py-3">{{ __('Role') }}</th>
                                <th class="px-6 py-3">{{ __('Status') }}</th>
                                <th class="px-6 py-3">{{ __('Joined') }}</th>
                                <th class="px-6 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-gray-900">
                            @forelse ($users as $row)
                                @php($isMe = $row->id === auth()->id())
                                <tr>
                                    <td class="px-6 py-4">
                                        <div class="font-medium">{{ $row->name }} @if ($isMe)<span class="text-xs font-normal text-gray-500">({{ __('you') }})</span>@endif</div>
                                        <div class="text-gray-500">{{ $row->email }}</div>
                                    </td>
                                    <td class="px-6 py-4">{{ __($row->role->label()) }}</td>
                                    <td class="px-6 py-4">
                                        @if ($row->is_active)
                                            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800">{{ __('Active') }}</span>
                                        @else
                                            <span class="rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-medium text-gray-600">{{ __('Deactivated') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-500">{{ $row->created_at?->format('M j, Y') }}</td>
                                    <td class="px-6 py-4 text-right whitespace-nowrap space-x-4">
                                        <a href="{{ route('admin.users.edit', $row) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('Edit') }}</a>

                                        @unless ($isMe)
                                            <form method="POST" action="{{ route($row->is_active ? 'admin.users.deactivate' : 'admin.users.activate', $row) }}" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="text-gray-600 hover:text-gray-900">{{ $row->is_active ? __('Deactivate') : __('Activate') }}</button>
                                            </form>

                                            <form method="POST" action="{{ route('admin.users.destroy', $row) }}" class="inline"
                                                  onsubmit="return confirm({{ Js::from(__('Delete :name? They will no longer be able to log in. Their past work stays in the records.', ['name' => $row->name])) }})">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-800">{{ __('Delete') }}</button>
                                            </form>
                                        @endunless
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-6 py-10 text-center text-gray-500">{{ __('No users found.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $users->links() }}
        </div>
    </div>
</x-app-layout>
