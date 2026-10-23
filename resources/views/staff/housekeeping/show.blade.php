@php
    use App\Enums\RoomStatus;

    $s = $room->status;
    $hero = [
        'clean' => 'bg-emerald-50 border-emerald-200',
        'dirty' => 'bg-amber-50 border-amber-300',
        'occupied' => 'bg-sky-50 border-sky-200',
        'out_of_service' => 'bg-gray-100 border-gray-300',
    ][$s->value];
    $hasBlocking = $openIssues->where('blocks_room', true)->isNotEmpty();
    $canReportBlocking = ! $guestInHouse && $s !== RoomStatus::OutOfService;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Room') }} {{ $room->number }}</h2>
            <a href="{{ route('housekeeping.board') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; {{ __('All rooms') }}</a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-auth-session-status :status="session('status')" class="mx-4 sm:mx-0" />

            @session('error')
                <p role="alert" class="mx-4 sm:mx-0 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{{ $value }}</p>
            @endsession

            {{-- Status and what can be done --}}
            <section class="mx-4 rounded-2xl border p-5 sm:mx-0 {{ $hero }}">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-3xl font-bold text-gray-900">{{ $room->number }}</p>
                        <p class="text-sm text-gray-600">{{ $room->roomType->name }} &middot; {{ __('Floor') }} {{ $room->floor }}</p>
                    </div>
                    <x-room-status-badge :status="$s" class="!text-sm !px-3 !py-1" />
                </div>

                @if ($upcoming > 0 && $s !== RoomStatus::Clean)
                    <p class="mt-3 text-sm text-indigo-800">{{ trans_choice(':count booking starts in the next 7 days for this room.|:count bookings start in the next 7 days for this room.', $upcoming, ['count' => $upcoming]) }}</p>
                @endif

                <div class="mt-4">
                    @if ($guestInHouse || $s === RoomStatus::Occupied)
                        <p class="rounded-xl bg-white/70 px-4 py-3 text-sm text-gray-700">{{ __('A guest is in this room. The status changes when the guest checks out.') }}</p>
                    @elseif ($s === RoomStatus::OutOfService && $hasBlocking)
                        <p class="rounded-xl bg-white/70 px-4 py-3 text-sm text-gray-700">{{ __('This room is out of service. Mark the problem below as fixed to put it back.') }}</p>
                    @else
                        <form method="POST" action="{{ route('housekeeping.rooms.status', $room) }}" class="space-y-3">
                            @csrf
                            @method('PATCH')
                            <x-text-input name="note" type="text" maxlength="255" placeholder="{{ __('Note (optional)') }}" class="block w-full" />
                            <div class="grid grid-cols-2 gap-3">
                                <button name="status" value="clean" @disabled($s === RoomStatus::Clean) class="rounded-xl bg-emerald-600 px-4 py-3 text-base font-semibold text-white hover:bg-emerald-700 disabled:opacity-40">{{ $s === RoomStatus::OutOfService ? __('Back in service (clean)') : __('Mark clean') }}</button>
                                <button name="status" value="dirty" @disabled($s === RoomStatus::Dirty) class="rounded-xl border border-gray-300 bg-white px-4 py-3 text-base font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-40">{{ $s === RoomStatus::OutOfService ? __('Back in service (dirty)') : __('Needs cleaning') }}</button>
                            </div>
                        </form>
                    @endif
                </div>
            </section>

            {{-- Open issues --}}
            <section class="mx-4 rounded-2xl bg-white p-5 shadow-sm sm:mx-0">
                <h3 class="font-semibold text-gray-900">{{ __('Open maintenance issues') }}</h3>

                <ul class="mt-3 divide-y divide-gray-100">
                    @forelse ($openIssues as $issue)
                        <li x-data="{ open: false }" class="py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-gray-900">{{ $issue->description }}</p>
                                    <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                                        <span class="rounded-full px-2 py-0.5 font-medium {{ $issue->priority->badge() }}">{{ __($issue->priority->label()) }}</span>
                                        @if ($issue->blocks_room)<span class="rounded-full bg-gray-200 px-2 py-0.5 font-medium text-gray-700">{{ __('Room out of service') }}</span>@endif
                                        {{ $issue->created_at->diffForHumans() }}@if ($issue->reporter) &middot; {{ $issue->reporter->name }}@endif
                                    </p>
                                </div>
                                <button type="button" @click="open = ! open" class="shrink-0 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-100">{{ __('Mark fixed') }}</button>
                            </div>
                            <form x-show="open" x-collapse x-cloak method="POST" action="{{ route('housekeeping.issues.resolve', $issue) }}" class="mt-3 space-y-3 rounded-xl bg-gray-50 p-3">
                                @csrf
                                @method('PATCH')
                                <x-text-input name="note" type="text" maxlength="255" placeholder="{{ __('What was done? (optional)') }}" class="block w-full" />
                                <x-primary-button>{{ __('Confirm fixed') }}</x-primary-button>
                            </form>
                        </li>
                    @empty
                        <li class="py-4 text-sm text-gray-500">{{ __('No open issues.') }}</li>
                    @endforelse
                </ul>
            </section>

            {{-- Report a problem (FR-26) --}}
            <form method="POST" action="{{ route('housekeeping.issues.store', $room) }}" class="mx-4 space-y-4 rounded-2xl bg-white p-5 shadow-sm sm:mx-0">
                @csrf
                <h3 class="font-semibold text-gray-900">{{ __('Report a problem') }}</h3>

                <div>
                    <x-input-label for="description" :value="__('What is wrong?')" />
                    <x-textarea-input id="description" name="description" rows="3" maxlength="500" required class="mt-1 block w-full">{{ old('description') }}</x-textarea-input>
                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="priority" :value="__('How urgent?')" />
                    <x-select-input id="priority" name="priority" class="mt-1 block w-full">
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority->value }}" @selected(old('priority', 'normal') === $priority->value)>{{ __($priority->label()) }}</option>
                        @endforeach
                    </x-select-input>
                    <x-input-error :messages="$errors->get('priority')" class="mt-2" />
                </div>

                @if ($canReportBlocking)
                    <label class="flex items-start gap-3 rounded-xl border border-gray-200 p-3 text-sm">
                        <input type="checkbox" name="blocks_room" value="1" @checked(old('blocks_room')) class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span>
                            <span class="font-medium text-gray-900">{{ __('The room cannot be used') }}</span>
                            <span class="block text-gray-500">{{ __('Takes the room out of service until the problem is fixed. It will not be offered for new bookings.') }}</span>
                        </span>
                    </label>
                @endif

                <x-primary-button>{{ __('Save issue') }}</x-primary-button>
            </form>

            {{-- History --}}
            <section class="mx-4 rounded-2xl bg-white p-5 shadow-sm sm:mx-0">
                <h3 class="font-semibold text-gray-900">{{ __('Recent changes') }}</h3>
                <ul class="mt-3 space-y-3 text-sm">
                    @forelse ($history as $change)
                        <li class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-gray-900">{{ __($change->from_status->label()) }} &rarr; {{ __($change->to_status->label()) }}</p>
                                @if ($change->note)<p class="text-xs text-gray-500">{{ $change->note }}</p>@endif
                            </div>
                            <p class="shrink-0 text-right text-xs text-gray-500">{{ $change->created_at->format('M j, H:i') }}@if ($change->changer)<br>{{ $change->changer->name }}@endif</p>
                        </li>
                    @empty
                        <li class="text-gray-500">{{ __('No changes made here yet.') }}</li>
                    @endforelse
                </ul>

                @if ($resolvedIssues->isNotEmpty())
                    <h3 class="mt-6 font-semibold text-gray-900">{{ __('Recently fixed') }}</h3>
                    <ul class="mt-3 space-y-3 text-sm">
                        @foreach ($resolvedIssues as $issue)
                            <li>
                                <p class="text-gray-500 line-through">{{ $issue->description }}</p>
                                <p class="text-xs text-gray-500">{{ __('Fixed') }} {{ $issue->resolved_at->format('M j, H:i') }}@if ($issue->resolver) {{ __('by') }} {{ $issue->resolver->name }}@endif @if ($issue->resolution_note) &mdash; {{ $issue->resolution_note }}@endif</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
