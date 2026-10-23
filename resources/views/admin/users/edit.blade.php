<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Edit user') }}: {{ $user->name }}</h2>
            <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; {{ __('All users') }}</a>
        </div>
    </x-slot>

    @php($isMe = $user->id === auth()->id())

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-auth-session-status :status="session('status')" />

            @session('error')
                <p role="alert" class="font-medium text-sm text-red-600">{{ $value }}</p>
            @endsession

            {{-- Details --}}
            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                @csrf
                @method('PUT')
                <h3 class="font-semibold text-gray-800">{{ __('Details') }}</h3>

                <div>
                    <x-input-label for="name" :value="__('Name')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="email" :value="__('Email')" />
                    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="password" :value="__('New password')" />
                        <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="password_confirmation" :value="__('Confirm new password')" />
                        <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                    </div>
                </div>
                <p class="text-xs text-gray-500">{{ __('Leave the password empty to keep the current one.') }}</p>

                <x-primary-button>{{ __('Save details') }}</x-primary-button>
            </form>

            {{-- Role --}}
            <form method="POST" action="{{ route('admin.users.role', $user) }}" class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                @csrf
                @method('PATCH')
                <h3 class="font-semibold text-gray-800">{{ __('Role') }}</h3>

                @if ($isMe)
                    <p class="text-sm text-gray-600">{{ __('You cannot change your own role. Ask another owner to do it.') }}</p>
                @else
                    <div>
                        <x-input-label for="role" :value="__('Role')" />
                        <x-select-input id="role" name="role" class="mt-1 block w-64">
                            @foreach ($roles as $role)
                                <option value="{{ $role->value }}" @selected(old('role', $user->role->value) === $role->value)>{{ __($role->label()) }}</option>
                            @endforeach
                        </x-select-input>
                        <x-input-error :messages="$errors->get('role')" class="mt-2" />
                    </div>
                    <x-primary-button>{{ __('Change role') }}</x-primary-button>
                @endif
            </form>

            {{-- Status and delete --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <h3 class="font-semibold text-gray-800">{{ __('Access') }}</h3>

                <p class="text-sm text-gray-600">
                    {{ __('Status') }}:
                    <strong class="{{ $user->is_active ? 'text-emerald-700' : 'text-gray-600' }}">{{ $user->is_active ? __('Active') : __('Deactivated') }}</strong>
                </p>

                @if ($isMe)
                    <p class="text-sm text-gray-600">{{ __('You cannot deactivate or delete your own account.') }}</p>
                @else
                    <div class="flex flex-wrap items-center gap-4">
                        <form method="POST" action="{{ route($user->is_active ? 'admin.users.deactivate' : 'admin.users.activate', $user) }}">
                            @csrf
                            @method('PATCH')
                            <x-secondary-button type="submit">{{ $user->is_active ? __('Deactivate user') : __('Activate user') }}</x-secondary-button>
                        </form>

                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                              onsubmit="return confirm({{ Js::from(__('Delete :name? They will no longer be able to log in. Their past work stays in the records.', ['name' => $user->name])) }})">
                            @csrf
                            @method('DELETE')
                            <x-danger-button>{{ __('Delete user') }}</x-danger-button>
                        </form>
                    </div>
                    <p class="text-xs text-gray-500">{{ __('A deactivated user cannot log in until you activate them again. Deleting hides the user for good; their bookings, payments and bills keep their name.') }}</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
