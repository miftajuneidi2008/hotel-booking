{{--
    Shows validation errors and error messages in a modal, so they are seen even when the form is long and
    the red text is above the screen. The red text on the form stays as it is. Put it once in each layout.
--}}
@php
    $messages = collect($errors->getBags())
        ->flatMap(fn ($bag) => $bag->all())
        ->when(session('error'), fn ($all, $error) => $all->prepend($error))
        ->filter()
        ->unique()
        ->values();
@endphp

@if ($messages->isNotEmpty())
    <div x-data="{ open: true }"
         x-show="open"
         x-cloak
         x-init="$nextTick(() => $refs.ok && $refs.ok.focus())"
         @keydown.escape.window="open = false"
         class="fixed inset-0 z-[100] flex items-center justify-center p-4"
         role="alertdialog"
         aria-modal="true"
         aria-labelledby="error-modal-title">
        <div class="absolute inset-0 bg-gray-900/60" @click="open = false"></div>

        <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <h2 id="error-modal-title" class="text-base font-semibold text-gray-900">
                        {{ $messages->count() > 1 ? __('Please fix these problems') : __('Something needs your attention') }}
                    </h2>
                    <ul class="mt-2 max-h-64 list-disc space-y-1 overflow-y-auto ps-5 text-sm text-gray-700">
                        @foreach ($messages as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="button" x-ref="ok" @click="open = false"
                        class="rounded-lg bg-gray-800 px-5 py-2 text-sm font-semibold text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-800 focus:ring-offset-2">
                    {{ __('OK') }}
                </button>
            </div>
        </div>
    </div>
@endif
