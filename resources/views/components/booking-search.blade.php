
@php
    $today = now()->toDateString();
    $hasError = $errors->hasAny(['checkin', 'checkout', 'guests']);

    $segment = 'block cursor-text rounded-full px-6 py-3 transition-colors hover:bg-neutral-100 focus-within:bg-neutral-100 sm:flex-1';
    $label = 'block text-xs font-bold text-ink';
    $input = 'mt-0.5 block w-full appearance-none border-0 bg-transparent p-0 text-sm text-ink outline-none focus:border-0 focus:ring-0 focus:outline-none';
    $divider = 'mx-6 h-px bg-neutral-200 sm:mx-0 sm:h-8 sm:w-px';
@endphp

<form method="GET" action="{{ route('booking') }}" {{ $attributes }}>
    @if (request('room'))
        <input type="hidden" name="room" value="{{ request('room') }}">
    @endif

    <div class="flex flex-col rounded-3xl border border-neutral-200 bg-white p-2 shadow-lg sm:flex-row sm:items-center sm:rounded-full">

        <label class="{{ $segment }}">
            <span class="{{ $label }}">{{ __('Check in') }}</span>
            <input type="date" name="checkin" required min="{{ $today }}"
                   value="{{ old('checkin', request('checkin')) }}" class="{{ $input }}">
        </label>

        <span class="{{ $divider }}" aria-hidden="true"></span>

        <label class="{{ $segment }}">
            <span class="{{ $label }}">{{ __('Check out') }}</span>
            <input type="date" name="checkout" required min="{{ $today }}"
                   value="{{ old('checkout', request('checkout')) }}" class="{{ $input }}">
        </label>

        <span class="{{ $divider }}" aria-hidden="true"></span>

        <label class="{{ $segment }}">
            <span class="{{ $label }}">{{ __('Guests') }}</span>
            <select name="guests" class="{{ $input }}">
                @foreach (range(1, 10) as $n)
                    <option value="{{ $n }}" @selected((int) old('guests', request('guests', 2)) === $n)>
                        {{ trans_choice(':count guest|:count guests', $n) }}
                    </option>
                @endforeach
            </select>
        </label>

        <button type="submit"
                class="m-1 flex h-12 shrink-0 items-center justify-center gap-2 rounded-full bg-brand px-6 font-semibold text-white transition hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand sm:w-12 sm:px-0">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true">
                <circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>
            </svg>
            <span class="sm:sr-only">{{ __('Search rooms') }}</span>
        </button>
    </div>

    @if ($hasError)
        <p role="alert" class="mt-3 px-6 text-sm font-medium text-brand-dark">{{ $errors->first() }}</p>
    @endif
</form>