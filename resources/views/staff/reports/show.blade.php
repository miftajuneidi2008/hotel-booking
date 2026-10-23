@php
    $tabs = [
        'daily' => ['label' => 'Daily report', 'route' => 'staff.reports.daily'],
        'range' => ['label' => 'Occupancy & income', 'route' => 'staff.reports.range'],
        'users' => ['label' => 'By user', 'route' => 'staff.reports.users'],
    ];
    $query = array_filter($filters, fn ($value) => $value !== null && $value !== '');
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __($report['title']) }}</h2>
            <div class="flex flex-wrap items-center gap-4 text-sm">
                <a href="{{ route('staff.reports.'.$kind.'.print', $query) }}" target="_blank" rel="noopener" class="text-gray-600 hover:text-gray-900">{{ __('Print / save as PDF') }}</a>
                <a href="{{ route('staff.reports.'.$kind.'.xlsx', $query) }}" class="text-gray-600 hover:text-gray-900">{{ __('Download Excel') }}</a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-auth-session-status :status="session('status')" />
            @session('error')
                <p role="alert" class="font-medium text-sm text-red-600">{{ $value }}</p>
            @endsession

            {{-- Which report --}}
            <nav class="flex flex-wrap gap-2" aria-label="{{ __('Reports') }}">
                @foreach ($tabs as $key => $tab)
                    <a href="{{ route($tab['route']) }}"
                       class="rounded-md px-4 py-2 text-sm font-medium {{ $kind === $key ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 shadow-sm' }}"
                       @if ($kind === $key) aria-current="page" @endif>{{ __($tab['label']) }}</a>
                @endforeach
            </nav>

            {{-- Filters --}}
            <form method="GET" action="{{ route('staff.reports.'.$kind) }}" class="flex flex-wrap items-end gap-4">
                @if ($kind === 'daily')
                    <div>
                        <x-input-label for="date" :value="__('Day')" />
                        <x-text-input id="date" name="date" type="date" class="mt-1 block" :value="$filters['date']" />
                    </div>
                @else
                    <div>
                        <x-input-label for="from" :value="__('From')" />
                        <x-text-input id="from" name="from" type="date" class="mt-1 block" :value="$filters['from']" />
                    </div>
                    <div>
                        <x-input-label for="to" :value="__('To')" />
                        <x-text-input id="to" name="to" type="date" class="mt-1 block" :value="$filters['to']" />
                    </div>
                @endif

                @if ($kind === 'users')
                    <div>
                        <x-input-label for="user" :value="__('Staff member')" />
                        <x-select-input id="user" name="user" class="mt-1 block w-56">
                            <option value="">{{ __('Everybody') }}</option>
                            @foreach ($staff as $member)
                                <option value="{{ $member->id }}" @selected(($filters['user'] ?? null) === $member->id)>{{ $member->name }}</option>
                            @endforeach
                        </x-select-input>
                    </div>
                @endif

                <x-secondary-button type="submit">{{ __('Show') }}</x-secondary-button>
                <a href="{{ route('staff.reports.'.$kind) }}" class="pb-2 text-sm text-gray-600 hover:text-gray-900">{{ $kind === 'daily' ? __('Today') : __('This month') }}</a>
            </form>

            <x-input-error :messages="$errors->get('date')" />
            <x-input-error :messages="$errors->get('from')" />
            <x-input-error :messages="$errors->get('to')" />
            <x-input-error :messages="$errors->get('user')" />

            <p class="text-sm text-gray-500">{{ $report['subtitle'] }}</p>

            {{-- Key numbers --}}
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                @foreach ($report['summary'] as [$label, $value])
                    <div class="bg-white shadow-sm sm:rounded-lg p-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __($label) }}</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900">{{ $value }}</p>
                    </div>
                @endforeach
            </div>

            @include('staff.reports._tables')

            <ul class="space-y-1 text-xs text-gray-500">
                @foreach ($report['notes'] as $note)
                    <li>{{ __($note) }}</li>
                @endforeach
            </ul>
        </div>
    </div>
</x-app-layout>
