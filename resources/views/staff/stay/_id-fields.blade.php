<div class="grid gap-4 sm:grid-cols-3">
    <div>
        <x-input-label for="id_type" :value="__('ID type')" />
        <x-select-input id="id_type" name="id_type" required class="mt-1 block w-full">
            <option value="">&mdash;</option>
            @foreach ($idTypes as $type)
                <option value="{{ $type->value }}" @selected(old('id_type', $booking->id_type?->value) === $type->value)>{{ __($type->label()) }}</option>
            @endforeach
        </x-select-input>
        <x-input-error :messages="$errors->get('id_type')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="id_number" :value="__('ID or passport number')" />
        <x-text-input id="id_number" name="id_number" type="text" required maxlength="50" class="mt-1 block w-full" :value="old('id_number', $booking->id_number)" />
        <x-input-error :messages="$errors->get('id_number')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="nationality" :value="__('Nationality (optional)')" />
        <x-text-input id="nationality" name="nationality" type="text" maxlength="60" class="mt-1 block w-full" :value="old('nationality', $booking->nationality)" />
        <x-input-error :messages="$errors->get('nationality')" class="mt-2" />
    </div>
</div>
