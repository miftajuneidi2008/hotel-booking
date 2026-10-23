<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit room :number', ['number' => $room->number]) }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-auth-session-status :status="session('status')" />

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('admin.rooms._form', [
                        'action' => route('admin.rooms.update', $room),
                        'method' => 'PUT',
                    ])
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <h3 class="text-lg font-medium text-gray-900">{{ __('Photos') }}</h3>
                <p class="mt-1 text-sm text-gray-600">
                    {{ __('JPG, PNG or WebP, up to 4 MB each. A room can have up to :max photos.', ['max' => \App\Http\Requests\RoomPhotoRequest::MAX_PHOTOS_PER_ROOM]) }}
                </p>

                @if ($room->photos->isNotEmpty())
                    <ul class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                        @foreach ($room->photos as $photo)
                            <li class="space-y-2">
                                <img src="{{ $photo->url }}" alt="{{ __('Photo of room :number', ['number' => $room->number]) }}"
                                     class="aspect-[4/3] w-full rounded-md object-cover" loading="lazy">
                                <form method="POST" action="{{ route('admin.rooms.photos.destroy', [$room, $photo]) }}"
                                      onsubmit="return confirm('{{ __('Delete this photo?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm text-red-600 hover:text-red-900">{{ __('Delete') }}</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <form method="POST" action="{{ route('admin.rooms.photos.store', $room) }}" enctype="multipart/form-data" class="mt-6 max-w-xl space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="photos" :value="__('Add photos')" />
                        <input id="photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required
                               class="mt-1 block w-full text-sm text-gray-700 file:me-4 file:rounded-md file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-semibold hover:file:bg-gray-200">
                        <x-input-error class="mt-2" :messages="$errors->get('photos')" />
                        <x-input-error class="mt-2" :messages="$errors->get('photos.*')" />
                    </div>

                    <x-primary-button>{{ __('Upload') }}</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
