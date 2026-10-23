<x-dashboard-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Dashboard') }}</h2>

            @if ($stats)
                <nav class="inline-flex rounded-md border border-gray-200 bg-white p-0.5 text-sm" aria-label="{{ __('Period') }}">
                    @foreach ($ranges as $range)
                        <a href="{{ route('dashboard', ['range' => $range]) }}"
                           @if ($stats['days'] === $range) aria-current="true" @endif
                           class="rounded px-3 py-1 font-medium {{ $stats['days'] === $range ? 'bg-gray-800 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            {{ __(':n days', ['n' => $range]) }}
                        </a>
                    @endforeach
                </nav>
            @endif
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-8">
        @if ($stats)
            {{-- Right now --}}
            <section>
                <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-400">{{ __('Today') }}</h3>
                <div class="mt-3 grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <x-dashboard.stat-card :label="__('Arrivals waiting')" :value="$stats['now']['arrivals']" />
                    <x-dashboard.stat-card :label="__('Leaving today or overdue')" :value="$stats['now']['departures']" />
                    <x-dashboard.stat-card :label="__('Guests in the house')" :value="$stats['now']['inHouseGuests']"
                        :hint="trans_choice(':count room occupied|:count rooms occupied', $stats['now']['inHouseRooms'], ['count' => $stats['now']['inHouseRooms']])" />
                    <x-dashboard.stat-card :label="__('Occupancy now')" :value="$stats['now']['occupancy'].'%'"
                        :hint="__(':n rooms in total', ['n' => $stats['totalRooms']])" />
                </div>
            </section>

            {{-- The chosen period --}}
            <section>
                <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-400">
                    {{ __('Last :n days', ['n' => $stats['days']]) }}
                    <span class="font-normal normal-case tracking-normal">&middot; {{ $stats['from']->translatedFormat('M j') }} &ndash; {{ $stats['to']->translatedFormat('M j') }}</span>
                </h3>
                <div class="mt-3 grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-5">
                    @isset($stats['revenue'])
                        <x-dashboard.stat-card :label="__('Money earned')" :value="__('ETB').' '.number_format($stats['revenue'], 0)"
                            :hint="__('Bills of stays checked out')" />
                    @endisset
                    <x-dashboard.stat-card :label="__('Guests checked in')" :value="number_format($stats['period']['guests'])"
                        :hint="trans_choice(':count stay|:count stays', $stats['period']['stays'], ['count' => $stats['period']['stays']])" />
                    <x-dashboard.stat-card :label="__('Bookings made')" :value="number_format($stats['period']['bookings'])" />
                    <x-dashboard.stat-card :label="__('Cancellations')" :value="number_format($stats['period']['cancelled'])" />
                    <x-dashboard.stat-card :label="__('Average occupancy')" :value="$stats['period']['avgOccupancy'].'%'" />
                </div>
            </section>

            {{-- Charts --}}
            <div class="grid gap-6 lg:grid-cols-2">
                @isset($stats['revenuePerDay'])
                    <x-dashboard.panel :title="__('Money earned per day')" :subtitle="__('By check-out date, in ETB')">
                        <x-dashboard.bar-chart :points="$stats['revenuePerDay']" :money="true" color="bg-emerald-500" />
                    </x-dashboard.panel>
                @endisset

                <x-dashboard.panel :title="__('Guests checked in per day')">
                    <x-dashboard.bar-chart :points="$stats['guestsPerDay']" color="bg-indigo-500" />
                </x-dashboard.panel>

                <x-dashboard.panel :title="__('Occupancy')" :subtitle="__('Share of rooms with a guest each night')">
                    <x-dashboard.line-chart :points="$stats['occupancyPerDay']" />
                </x-dashboard.panel>

                <x-dashboard.panel :title="__('Bookings by status')" :subtitle="__('Bookings made in this period')">
                    <x-dashboard.donut :segments="$stats['statusSegments']" />
                </x-dashboard.panel>

                <x-dashboard.panel :title="__('Bookings by source')" :subtitle="__('Bookings made in this period')">
                    <x-dashboard.h-bars :items="$stats['sourceBars']" color="bg-indigo-500" />
                </x-dashboard.panel>

                @isset($stats['methodBars'])
                    <x-dashboard.panel :title="__('Money collected by payment method')"
                        :subtitle="__('Deposits and check-out payments, in ETB')">
                        <x-dashboard.h-bars :items="$stats['methodBars']" :money="true" color="bg-emerald-500" />
                    </x-dashboard.panel>
                @endisset

                <x-dashboard.panel :title="__('Rooms right now')">
                    <x-dashboard.h-bars :items="$stats['roomStatusBars']" color="bg-amber-500" />
                </x-dashboard.panel>
            </div>
        @endif

        {{-- Quick actions (same shortcuts as before) --}}
        <section class="rounded-lg bg-white p-5 shadow-sm">
            <h3 class="font-semibold text-gray-800">{{ __('Quick actions') }}</h3>
            <div class="mt-4 flex flex-wrap gap-3">
                @can('manage-bookings')
                    <x-link-button :href="route('staff.front-desk')">{{ __('Front desk') }}</x-link-button>
                    <x-link-button :href="route('staff.bookings.create')">{{ __('New booking') }}</x-link-button>
                    <x-link-button :href="route('staff.bookings.index')">{{ __('All bookings') }}</x-link-button>
                    <x-link-button :href="route('staff.calendar')">{{ __('Calendar') }}</x-link-button>
                    <x-link-button :href="route('staff.groups.create')">{{ __('Group booking') }}</x-link-button>
                @else
                    <x-link-button :href="route('booking')">{{ __('Book a room') }}</x-link-button>
                    <x-link-button :href="route('my-bookings.index')">{{ __('My bookings') }}</x-link-button>
                @endcan
            </div>
        </section>
    </div>
</x-dashboard-layout>
