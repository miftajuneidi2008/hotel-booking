<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Menu and services') }}</h2>
    </x-slot>

    <div class="py-6 sm:py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('staff.menu._tabs', ['active' => 'menu'])

            <x-auth-session-status :status="session('status')" class="mx-4 sm:mx-0" />

            @if ($errors->any())
                <div role="alert" class="mx-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 sm:mx-0">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @if ($canEdit)
                <section x-data="{ open: @js($errors->any() && old('_form') === 'new') }" class="mx-4 rounded-2xl bg-white shadow-sm sm:mx-0">
                    <button type="button" @click="open = ! open" class="flex w-full items-center justify-between p-5 text-left" :aria-expanded="open">
                        <span class="font-semibold text-gray-900">{{ __('Add an item') }}</span>
                        <svg class="h-4 w-4 text-gray-400 transition" :class="open && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <form x-show="open" x-collapse x-cloak method="POST" action="{{ route('staff.menu.store') }}" class="space-y-4 border-t border-gray-100 p-5">
                        @csrf
                        <input type="hidden" name="_form" value="new">
                        <div class="grid gap-4 sm:grid-cols-6">
                            <div class="sm:col-span-3">
                                <x-input-label for="new_name" :value="__('Name')" />
                                <x-text-input id="new_name" name="name" type="text" maxlength="100" required class="mt-1 block w-full" :value="old('_form') === 'new' ? old('name') : ''" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="new_category" :value="__('Type')" />
                                <x-select-input id="new_category" name="category" class="mt-1 block w-full">
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->value }}" @selected(old('_form') === 'new' && old('category') === $category->value)>{{ __($category->label()) }}</option>
                                    @endforeach
                                </x-select-input>
                            </div>
                            <div class="sm:col-span-1">
                                <x-input-label for="new_price" :value="__('Price (ETB)')" />
                                <x-text-input id="new_price" name="price" type="number" min="0.01" max="99999" step="0.01" required class="mt-1 block w-full" :value="old('_form') === 'new' ? old('price') : ''" />
                            </div>
                            <div class="sm:col-span-6">
                                <x-input-label for="new_description" :value="__('Description (optional)')" />
                                <x-text-input id="new_description" name="description" type="text" maxlength="255" class="mt-1 block w-full" :value="old('_form') === 'new' ? old('description') : ''" />
                            </div>
                        </div>
                        <input type="hidden" name="is_active" value="1">
                        <x-primary-button>{{ __('Add to menu') }}</x-primary-button>
                    </form>
                </section>
            @endif

            @php($any = false)
            @foreach ($categories as $category)
                @php($list = $items->get($category->value, collect()))
                @continue($list->isEmpty())
                @php($any = true)
                <section class="mx-4 overflow-hidden rounded-2xl bg-white shadow-sm sm:mx-0">
                    <h3 class="border-b border-gray-100 px-5 py-3 text-sm font-semibold uppercase tracking-wider text-gray-400">{{ __($category->label()) }}</h3>
                    <ul class="divide-y divide-gray-100">
                        @foreach ($list as $item)
                            <li x-data="{ edit: {{ $errors->any() && (int) old('_form') === $item->id ? 'true' : 'false' }} }" class="px-5 py-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-medium {{ $item->is_active ? 'text-gray-900' : 'text-gray-400' }}">
                                            {{ $item->name }}
                                            @unless ($item->is_active)<span class="ml-2 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">{{ __('Hidden') }}</span>@endunless
                                        </p>
                                        @if ($item->description)<p class="truncate text-xs text-gray-500">{{ $item->description }}</p>@endif
                                    </div>
                                    <div class="flex shrink-0 items-center gap-4">
                                        <span class="font-semibold text-gray-900">ETB {{ number_format($item->price, 2) }}</span>
                                        @if ($canEdit)
                                            <button type="button" @click="edit = ! edit" class="text-sm text-indigo-600 hover:text-indigo-900">{{ __('Edit') }}</button>
                                        @endif
                                    </div>
                                </div>

                                @if ($canEdit)
                                    <div x-show="edit" x-collapse x-cloak class="mt-4 space-y-3 rounded-xl bg-gray-50 p-4">
                                        <form method="POST" action="{{ route('staff.menu.update', $item) }}" class="space-y-3">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="_form" value="{{ $item->id }}">
                                            <div class="grid gap-3 sm:grid-cols-6">
                                                <div class="sm:col-span-3">
                                                    <x-input-label :value="__('Name')" />
                                                    <x-text-input name="name" type="text" maxlength="100" required class="mt-1 block w-full" :value="$item->name" />
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <x-input-label :value="__('Type')" />
                                                    <x-select-input name="category" class="mt-1 block w-full">
                                                        @foreach ($categories as $c)
                                                            <option value="{{ $c->value }}" @selected($item->category === $c)>{{ __($c->label()) }}</option>
                                                        @endforeach
                                                    </x-select-input>
                                                </div>
                                                <div class="sm:col-span-1">
                                                    <x-input-label :value="__('Price (ETB)')" />
                                                    <x-text-input name="price" type="number" min="0.01" max="99999" step="0.01" required class="mt-1 block w-full" :value="$item->price" />
                                                </div>
                                                <div class="sm:col-span-6">
                                                    <x-input-label :value="__('Description (optional)')" />
                                                    <x-text-input name="description" type="text" maxlength="255" class="mt-1 block w-full" :value="$item->description" />
                                                </div>
                                            </div>
                                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                                <input type="hidden" name="is_active" value="0">
                                                <input type="checkbox" name="is_active" value="1" @checked($item->is_active) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                {{ __('Available for sale') }}
                                            </label>
                                            <p class="text-xs text-gray-500">{{ __('A new price applies to new sales only. Bills that already have this item keep the old price.') }}</p>
                                            <div class="flex items-center gap-3">
                                                <x-primary-button>{{ __('Save') }}</x-primary-button>
                                                <button type="button" @click="edit = false" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</button>
                                            </div>
                                        </form>
                                        <form method="POST" action="{{ route('staff.menu.destroy', $item) }}" onsubmit="return confirm('{{ __('Remove this item from the menu?') }}')" class="border-t border-gray-200 pt-3">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-sm text-red-600 hover:text-red-800">{{ __('Remove from menu') }}</button>
                                        </form>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach

            @unless ($any)
                <div class="mx-4 rounded-2xl bg-white p-10 text-center shadow-sm sm:mx-0">
                    <p class="font-medium text-gray-800">{{ __('The menu is empty.') }}</p>
                    <p class="mt-1 text-sm text-gray-500">{{ $canEdit ? __('Add your first item above, or run the sample menu: php artisan db:seed --class=MenuItemSeeder') : __('Ask the owner or a manager to add items.') }}</p>
                </div>
            @endunless
        </div>
    </div>
</x-app-layout>
