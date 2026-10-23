@php
    use App\Enums\RoomStatus;

    $tabs = [
        'all' => ['label' => __('All'), 'tone' => 'bg-gray-800 text-white'],
        'dirty' => ['label' => __('Dirty'), 'tone' => 'bg-amber-500 text-white'],
        'clean' => ['label' => __('Clean'), 'tone' => 'bg-emerald-600 text-white'],
        'occupied' => ['label' => __('Occupied'), 'tone' => 'bg-sky-600 text-white'],
        'out_of_service' => ['label' => __('Out of service'), 'tone' => 'bg-gray-500 text-white'],
    ];
    $card = [
        'clean' => 'border-emerald-200 bg-emerald-50',
        'dirty' => 'border-amber-300 bg-amber-50',
        'occupied' => 'border-sky-200 bg-sky-50',
        'out_of_service' => 'border-gray-300 bg-gray-100',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Housekeeping') }}</h2>
            <a href="{{ route('housekeeping.issues.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                {{ __('Maintenance issues') }}
                @if ($openIssues->flatten(1)->count() > 0)
                    <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">{{ $openIssues->flatten(1)->count() }}</span>
                @endif
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10" x-data="{ filter: 'all' }">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-auth-session-status :status="session('status')" class="mx-4 sm:mx-0" />

            @session('error')
                <p role="alert" class="mx-4 sm:mx-0 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{{ $value }}</p>
            @endsession

            {{-- Filter by status --}}
            <div class="flex gap-2 overflow-x-auto px-4 pb-1 sm:px-0" role="tablist" aria-label="{{ __('Filter rooms') }}">
                @foreach ($tabs as $key => $tab)
                    <button type="button" role="tab" @click="filter = '{{ $key }}'" :aria-selected="filter === '{{ $key }}'"
                            class="shrink-0 rounded-full px-4 py-2 text-sm font-medium transition"
                            :class="filter === '{{ $key }}' ? '{{ $tab['tone'] }} shadow-sm' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50'">
                        {{ $tab['label'] }} <span class="ml-1 opacity-80">{{ $counts[$key] }}</span>
                    </button>
                @endforeach
            </div>

            @forelse ($floors as $floor => $rooms)
                <section>
                    <h3 class="px-4 text-sm font-semibold uppercase tracking-wider text-gray-400 sm:px-0">{{ __('Floor') }} {{ $floor }}</h3>

                    <ul class="mt-3 grid grid-cols-1 gap-3 px-4 sm:grid-cols-2 sm:px-0 lg:grid-cols-3">
                        @foreach ($rooms as $room)
                            @php
                                $issues = $openIssues->get($room->id, collect());
                                $s = $room->status;
                            @endphp
                            <li x-show="filter === 'all' || filter === '{{ $s->value }}'" class="rounded-2xl border p-4 shadow-sm {{ $card[$s->value] }}">
                                <div class="flex items-start justify-between gap-3">
                                    <a href="{{ route('housekeeping.rooms.show', $room) }}" class="min-w-0">
                                        <span class="block text-2xl font-bold leading-none text-gray-900">{{ $room->number }}</span>
                                        <span class="mt-1 block truncate text-sm text-gray-600">{{ $room->roomType->name }}</span>
                                    </a>
                                    <x-room-status-badge :status="$s" />
                                </div>

                                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                    @if ($room->arrivals_today > 0 && $s !== RoomStatus::Clean)
                                        <span class="rounded-full bg-indigo-100 px-2 py-0.5 font-medium text-indigo-800">{{ __('Guest arriving today') }}</span>
                                    @endif
                                    @if ($issues->isNotEmpty())
                                        <a href="{{ route('housekeeping.rooms.show', $room) }}" class="rounded-full bg-red-100 px-2 py-0.5 font-medium text-red-800">
                                            {{ trans_choice(':count issue|:count issues', $issues->count(), ['count' => $issues->count()]) }}
                                        </a>
                                    @endif
                                </div>

                                {{-- One-tap action --}}
                                <div class="mt-4">
                                    @if ($s === RoomStatus::Dirty)
                                        <form method="POST" action="{{ route('housekeeping.rooms.status', $room) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="clean">
                                            <button class="w-full rounded-xl bg-emerald-600 px-4 py-3 text-base font-semibold text-white hover:bg-emerald-700 active:bg-emerald-800">{{ __('Mark clean') }}</button>
                                        </form>
                                    @elseif ($s === RoomStatus::Clean)
                                        <form method="POST" action="{{ route('housekeeping.rooms.status', $room) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="dirty">
                                            <button class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-base font-medium text-gray-700 hover:bg-gray-50">{{ __('Needs cleaning') }}</button>
                                        </form>
                                    @else
                                        <a href="{{ route('housekeeping.rooms.show', $room) }}" class="block w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-center text-base font-medium text-gray-700 hover:bg-gray-50">
                                            {{ $s === RoomStatus::OutOfService ? __('See the problem') : __('Open room') }}
                                        </a>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @empty
                <div class="mx-4 rounded-2xl bg-white p-10 text-center shadow-sm sm:mx-0">
                    <p class="font-medium text-gray-800">{{ __('There are no rooms yet.') }}</p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
