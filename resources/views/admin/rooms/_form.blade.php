@php($method ??= 'POST')

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div>
        <x-input-label for="room_type_id" :value="__('Room type')" />
        <x-select-input id="room_type_id" name="room_type_id" class="mt-1 block w-full" required>
            <option value="" disabled @selected(! old('room_type_id', $room->room_type_id))>{{ __('Choose a type') }}</option>
            @foreach ($roomTypes as $roomType)
                <option value="{{ $roomType->id }}" @selected((int) old('room_type_id', $room->room_type_id) === $roomType->id)>{{ $roomType->name }}</option>
            @endforeach
        </x-select-input>
        <x-input-error class="mt-2" :messages="$errors->get('room_type_id')" />
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div>
            <x-input-label for="number" :value="__('Room number')" />
            <x-text-input id="number" name="number" type="text" class="mt-1 block w-full" :value="old('number', $room->number)" required />
            <x-input-error class="mt-2" :messages="$errors->get('number')" />
        </div>

        <div>
            <x-input-label for="floor" :value="__('Floor')" />
            <x-text-input id="floor" name="floor" type="number" min="-5" max="100" class="mt-1 block w-full" :value="old('floor', $room->floor)" required />
            <x-input-error class="mt-2" :messages="$errors->get('floor')" />
        </div>
    </div>

    <div>
        <x-input-label for="status" :value="__('Status')" />
        <x-select-input id="status" name="status" class="mt-1 block w-full" required>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(old('status', $room->status->value) === $status->value)>{{ __($status->label()) }}</option>
            @endforeach
        </x-select-input>
        <x-input-error class="mt-2" :messages="$errors->get('status')" />
    </div>

    <div class="flex items-center gap-4">
        <x-primary-button>{{ __('Save') }}</x-primary-button>
        <a href="{{ route('admin.rooms.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
    </div>
</form>
