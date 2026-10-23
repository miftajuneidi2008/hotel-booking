@php($method ??= 'POST')

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div>
        <x-input-label for="name" :value="__('Name')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $roomType->name)" required autofocus :placeholder="__('Single, Double, Suite…')" />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div>
            <x-input-label for="capacity" :value="__('Capacity (guests)')" />
            <x-text-input id="capacity" name="capacity" type="number" min="1" max="20" class="mt-1 block w-full" :value="old('capacity', $roomType->capacity)" required />
            <x-input-error class="mt-2" :messages="$errors->get('capacity')" />
        </div>

        <div>
            <x-input-label for="base_rate" :value="__('Base rate (ETB per night)')" />
            <x-text-input id="base_rate" name="base_rate" type="number" min="0" step="0.01" class="mt-1 block w-full" :value="old('base_rate', $roomType->base_rate)" required />
            <x-input-error class="mt-2" :messages="$errors->get('base_rate')" />
        </div>
    </div>

    <div>
        <x-input-label for="description" :value="__('Description')" />
        <x-textarea-input id="description" name="description" rows="4" class="mt-1 block w-full">{{ old('description', $roomType->description) }}</x-textarea-input>
        <x-input-error class="mt-2" :messages="$errors->get('description')" />
    </div>

    <div class="flex items-center gap-4">
        <x-primary-button>{{ __('Save') }}</x-primary-button>
        <a href="{{ route('admin.room-types.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
    </div>
</form>
