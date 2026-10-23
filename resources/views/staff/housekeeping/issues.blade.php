<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Maintenance issues') }}</h2>
            <a href="{{ route('housekeeping.board') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; {{ __('All rooms') }}</a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-auth-session-status :status="session('status')" class="mx-4 sm:mx-0" />

            @session('error')
                <p role="alert" class="mx-4 sm:mx-0 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{{ $value }}</p>
            @endsession

            <div class="mx-4 inline-flex rounded-full bg-white p-1 text-sm shadow-sm ring-1 ring-gray-200 sm:mx-0">
                <a href="{{ route('housekeeping.issues.index') }}" class="rounded-full px-4 py-1.5 font-medium {{ ! $showResolved ? 'bg-gray-800 text-white' : 'text-gray-600 hover:bg-gray-50' }}">{{ __('Open') }} ({{ $openCount }})</a>
                <a href="{{ route('housekeeping.issues.index', ['show' => 'resolved']) }}" class="rounded-full px-4 py-1.5 font-medium {{ $showResolved ? 'bg-gray-800 text-white' : 'text-gray-600 hover:bg-gray-50' }}">{{ __('Fixed') }}</a>
            </div>

            <ul class="mx-4 divide-y divide-gray-100 overflow-hidden rounded-2xl bg-white shadow-sm sm:mx-0">
                @forelse ($issues as $issue)
                    <li x-data="{ open: false }" class="p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('housekeeping.rooms.show', $issue->room_id) }}" class="font-semibold text-gray-900 hover:underline">{{ __('Room') }} {{ $issue->room->number }}</a>
                                <p class="mt-1 text-gray-700 {{ $showResolved ? 'line-through' : '' }}">{{ $issue->description }}</p>
                                <p class="mt-2 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                                    <span class="rounded-full px-2 py-0.5 font-medium {{ $issue->priority->badge() }}">{{ __($issue->priority->label()) }}</span>
                                    @if ($issue->blocks_room)<span class="rounded-full bg-gray-200 px-2 py-0.5 font-medium text-gray-700">{{ __('Room out of service') }}</span>@endif
                                    @if ($showResolved)
                                        {{ __('Fixed') }} {{ $issue->resolved_at->format('M j, H:i') }}@if ($issue->resolver) {{ __('by') }} {{ $issue->resolver->name }}@endif
                                        @if ($issue->resolution_note) &mdash; {{ $issue->resolution_note }}@endif
                                    @else
                                        {{ $issue->created_at->diffForHumans() }}@if ($issue->reporter) &middot; {{ $issue->reporter->name }}@endif
                                    @endif
                                </p>
                            </div>
                            @unless ($showResolved)
                                <button type="button" @click="open = ! open" class="shrink-0 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-100">{{ __('Mark fixed') }}</button>
                            @endunless
                        </div>

                        @unless ($showResolved)
                            <form x-show="open" x-collapse x-cloak method="POST" action="{{ route('housekeeping.issues.resolve', $issue) }}" class="mt-3 space-y-3 rounded-xl bg-gray-50 p-3">
                                @csrf
                                @method('PATCH')
                                <x-text-input name="note" type="text" maxlength="255" placeholder="{{ __('What was done? (optional)') }}" class="block w-full" />
                                <x-primary-button>{{ __('Confirm fixed') }}</x-primary-button>
                            </form>
                        @endunless
                    </li>
                @empty
                    <li class="p-8 text-center text-sm text-gray-500">{{ $showResolved ? __('Nothing has been fixed yet.') : __('No open issues. Everything works.') }}</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-app-layout>
