@php
    $user = auth()->user();
    $isStaff = $user->can('manage-bookings');

    // Greet by the property's local time, not the server's.
    $hour = now(\App\Support\PropertyTime::timezone())->hour;
    $greeting = $hour < 12 ? __('Good morning') : ($hour < 18 ? __('Good afternoon') : __('Good evening'));
    $firstName = \Illuminate\Support\Str::of($user->name)->trim()->before(' ')->toString();
@endphp

<x-dashboard-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            <div>
                <h1 class="text-lg font-semibold leading-tight text-ink">{{ $greeting }}, {{ $firstName }}</h1>
                <p class="text-sm text-muted">{{ now(\App\Support\PropertyTime::timezone())->translatedFormat('l, F j') }}</p>
            </div>

            @if ($isStaff)
                <x-dashboard.button variant="primary" :href="route('staff.bookings.create')">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    {{ __('New booking') }}
                </x-dashboard.button>
            @else
                <x-dashboard.button variant="primary" :href="route('booking')">{{ __('Book a room') }}</x-dashboard.button>
            @endif
        </div>
    </x-slot>

    @if ($stats)
        @php
            $now = $stats['now'];
            $prev = $stats['previous'];
            $period = $stats['period'];
            $days = $stats['days'];
            $hasMoney = isset($stats['revenue']);
        @endphp

        <div class="mx-auto max-w-7xl space-y-8">
            {{-- ============ Today: what needs doing ============ --}}
            <section aria-labelledby="today-heading">
                <h2 id="today-heading" class="text-lg font-semibold text-ink">{{ __('Today') }}</h2>

                <div class="mt-3 overflow-hidden rounded-xl bg-white ring-1 ring-neutral-200">
                    <div class="grid grid-cols-2 gap-px bg-neutral-200 lg:grid-cols-4">
                        <x-dashboard.stat-card :label="__('Arrivals waiting')" :value="$now['arrivals']"
                            :href="route('staff.front-desk')" :alert="$now['arrivals'] > 0" />
                        <x-dashboard.stat-card :label="__('Leaving today or overdue')" :value="$now['departures']"
                            :href="route('staff.front-desk')" :alert="$now['departures'] > 0" />
                        <x-dashboard.stat-card :label="__('Guests in the house')" :value="$now['inHouseGuests']"
                            :href="route('staff.front-desk')"
                            :hint="trans_choice(':count room occupied|:count rooms occupied', $now['inHouseRooms'], ['count' => $now['inHouseRooms']])" />
                        <x-dashboard.stat-card :label="__('Occupancy now')" :value="$now['occupancy'].'%'"
                            :hint="__(':n rooms in total', ['n' => $stats['totalRooms']])">
                            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-neutral-100" aria-hidden="true">
                                <div class="h-full rounded-full bg-neutral-800" style="width: {{ $now['occupancy'] }}%"></div>
                            </div>
                        </x-dashboard.stat-card>
                    </div>

                    <div class="grid border-t border-neutral-200 lg:grid-cols-2 lg:divide-x lg:divide-neutral-200">
                        <x-dashboard.worklist :title="__('Arriving')" :count="$now['arrivals']"
                            :empty="__('No one is waiting to check in.')"
                            :more="$now['arrivals'] > count($now['arrivalList']) ? route('staff.front-desk') : null">
                            @foreach ($now['arrivalList'] as $booking)
                                <li class="flex items-center justify-between gap-3 px-5 py-3">
                                    <div class="min-w-0">
                                        <a href="{{ route('staff.bookings.show', $booking) }}" class="block truncate font-medium text-ink hover:underline">{{ $booking->guest_name }}</a>
                                        <p class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-sm text-muted">
                                            <span>{{ __('Room') }} {{ $booking->room->number }}</span>
                                            <span>{{ $booking->room->roomType->name }}</span>
                                            @if ($booking->room->status !== \App\Enums\RoomStatus::Clean)
                                                <span class="font-medium text-amber-700">{{ __('Room not ready') }}</span>
                                            @endif
                                        </p>
                                    </div>
                                    <x-dashboard.button size="sm" :href="route('staff.bookings.check-in', $booking)">{{ __('Check in') }}</x-dashboard.button>
                                </li>
                            @endforeach
                        </x-dashboard.worklist>

                        <x-dashboard.worklist :title="__('Leaving')" :count="$now['departures']"
                            :empty="__('No one is due to leave.')"
                            :more="$now['departures'] > count($now['departureList']) ? route('staff.front-desk') : null">
                            @foreach ($now['departureList'] as $booking)
                                @php
                                    $due = $booking->balanceDue();
                                    $overdue = $booking->check_out->toDateString() < $stats['today'];
                                @endphp
                                <li class="flex items-center justify-between gap-3 px-5 py-3">
                                    <div class="min-w-0">
                                        <a href="{{ route('staff.bookings.show', $booking) }}" class="block truncate font-medium text-ink hover:underline">{{ $booking->guest_name }}</a>
                                        <p class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-sm text-muted">
                                            <span>{{ __('Room') }} {{ $booking->room->number }}</span>
                                            @if ($overdue)
                                                <span class="font-medium text-red-700">{{ __('Overdue since :date', ['date' => $booking->check_out->translatedFormat('M j')]) }}</span>
                                            @endif
                                            @if ($due > 0)
                                                <span class="font-medium text-ink">{{ __('Owes') }} {{ __('ETB') }} {{ number_format($due, 0) }}</span>
                                            @else
                                                <span>{{ __('Nothing to pay') }}</span>
                                            @endif
                                        </p>
                                    </div>
                                    <x-dashboard.button size="sm" :href="route('staff.bookings.check-out', $booking)">{{ __('Check out') }}</x-dashboard.button>
                                </li>
                            @endforeach
                        </x-dashboard.worklist>
                    </div>

                    @if ($now['dirtyRooms'] > 0)
                        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-neutral-200 bg-amber-50 px-5 py-3 text-sm">
                            <p class="text-amber-900">{{ trans_choice(':count room needs cleaning|:count rooms need cleaning', $now['dirtyRooms'], ['count' => $now['dirtyRooms']]) }}</p>
                            @can('viewAny', \App\Models\RoomStatusChange::class)
                                <a href="{{ route('housekeeping.board') }}" class="font-semibold text-amber-900 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink">{{ __('Open room board') }}</a>
                            @endcan
                        </div>
                    @endif
                </div>
            </section>

            {{-- ============ The chosen period ============ --}}
            <section aria-labelledby="period-heading" class="space-y-4">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 id="period-heading" class="text-lg font-semibold text-ink">{{ __('Last :n days', ['n' => $days]) }}</h2>
                        <p class="text-sm text-muted">{{ $stats['from']->translatedFormat('M j') }} &ndash; {{ $stats['to']->translatedFormat('M j') }}</p>
                    </div>

                    <nav class="inline-flex rounded-lg bg-neutral-200/70 p-0.5 text-sm" aria-label="{{ __('Period') }}">
                        @foreach ($ranges as $range)
                            <a href="{{ route('dashboard', ['range' => $range]) }}"
                               @if ($days === $range) aria-current="true" @endif
                               class="rounded-md px-3 py-1.5 font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink {{ $days === $range ? 'bg-white text-ink shadow-sm' : 'text-neutral-600 hover:text-ink' }}">
                                {{ __(':n days', ['n' => $range]) }}
                            </a>
                        @endforeach
                    </nav>
                </div>

                {{-- Key figures, each compared with the period before --}}
                <div class="grid grid-cols-2 gap-px overflow-hidden rounded-xl bg-neutral-200 ring-1 ring-neutral-200 {{ $hasMoney ? 'lg:grid-cols-5' : 'lg:grid-cols-4' }}">
                    @if ($hasMoney)
                        <x-dashboard.stat-card class="col-span-2 lg:col-span-1" :label="__('Money earned')"
                            :value="__('ETB').' '.number_format($stats['revenue'], 0)" :hint="__('Bills of stays checked out')">
                            <x-dashboard.delta :current="$stats['revenue']" :previous="$stats['previousRevenue']" :days="$days" />
                        </x-dashboard.stat-card>
                    @endif
                    <x-dashboard.stat-card :label="__('Guests checked in')" :value="number_format($period['guests'])"
                        :hint="trans_choice(':count stay|:count stays', $period['stays'], ['count' => $period['stays']])">
                        <x-dashboard.delta :current="$period['guests']" :previous="$prev['guests']" :days="$days" />
                    </x-dashboard.stat-card>
                    <x-dashboard.stat-card :label="__('Bookings made')" :value="number_format($period['bookings'])">
                        <x-dashboard.delta :current="$period['bookings']" :previous="$prev['bookings']" :days="$days" />
                    </x-dashboard.stat-card>
                    <x-dashboard.stat-card :label="__('Cancellations')" :value="number_format($period['cancelled'])"
                        :hint="$period['bookings'] > 0 ? __(':n of :total bookings', ['n' => $period['cancelled'], 'total' => $period['bookings']]) : null">
                        <x-dashboard.delta :current="$period['cancelled']" :previous="$prev['cancelled']" :days="$days" :invert="true" />
                    </x-dashboard.stat-card>
                    <x-dashboard.stat-card :label="__('Average occupancy')" :value="$period['avgOccupancy'].'%'">
                        <x-dashboard.delta :current="$period['avgOccupancy']" :previous="$prev['avgOccupancy']" :days="$days" :points="true" />
                    </x-dashboard.stat-card>
                </div>

                {{-- Charts --}}
                <div class="grid gap-4 lg:grid-cols-3">
                    @isset($stats['revenuePerDay'])
                        <x-dashboard.panel class="lg:col-span-2" :title="__('Money earned per day')" :subtitle="__('By check-out date, in ETB')">
                            <x-dashboard.bar-chart :label="__('Money earned per day')" :points="$stats['revenuePerDay']"
                                :money="true" color="#ff385c" :empty="__('No stays were checked out in this period.')" />
                        </x-dashboard.panel>
                    @endisset

                    @isset($stats['methodBars'])
                        <x-dashboard.panel :title="__('Money collected by payment method')" :subtitle="__('Deposits and check-out payments, in ETB')">
                            <x-dashboard.h-bars :items="$stats['methodBars']" :money="true" color="#737373" />
                        </x-dashboard.panel>
                    @endisset

                    <x-dashboard.panel class="lg:col-span-2" :title="__('Occupancy')" :subtitle="__('Share of rooms with a guest each night')">
                        <x-dashboard.line-chart :label="__('Occupancy')" :points="$stats['occupancyPerDay']"
                            :empty="__('No guests stayed in this period.')" />
                    </x-dashboard.panel>

                    <x-dashboard.panel :title="__('Rooms right now')" :subtitle="__('Housekeeping status of every room')">
                        <x-dashboard.h-bars :items="$stats['roomStatusBars']" />
                    </x-dashboard.panel>

                    <x-dashboard.panel :title="__('Guests checked in per day')" :subtitle="__('People who arrived each day')">
                        <x-dashboard.bar-chart :label="__('Guests checked in per day')" :points="$stats['guestsPerDay']"
                            color="#737373" :ticks="3" :empty="__('No one checked in during this period.')" />
                    </x-dashboard.panel>

                    <x-dashboard.panel :title="__('Bookings by status')" :subtitle="__('Bookings made in this period')">
                        <x-dashboard.donut :segments="$stats['statusSegments']" />
                    </x-dashboard.panel>

                    <x-dashboard.panel :title="__('Bookings by source')" :subtitle="__('Where bookings come from')">
                        <x-dashboard.h-bars :items="$stats['sourceBars']" />
                    </x-dashboard.panel>
                </div>
            </section>
        </div>
    @else
        {{-- ============ Guest ============ --}}
        <div class="mx-auto max-w-3xl space-y-6">
            @if ($nextStay)
                @php
                    $nights = (int) abs($nextStay->check_in->diffInDays($nextStay->check_out));
                    $inHouse = $nextStay->status === \App\Enums\BookingStatus::CheckedIn;
                @endphp
                <section class="overflow-hidden rounded-xl bg-white ring-1 ring-neutral-200" aria-labelledby="stay-heading">
                    <div class="p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm text-muted">{{ $inHouse ? __('Your current stay') : __('Your next stay') }}</p>
                                <h2 id="stay-heading" class="mt-1 text-2xl font-semibold tracking-tight text-ink">{{ $nextStay->room->roomType->name }}</h2>
                            </div>
                            <x-booking-status-badge :status="$nextStay->status" />
                        </div>

                        <dl class="mt-6 grid grid-cols-2 gap-x-4 gap-y-5 text-sm sm:grid-cols-4">
                            <div>
                                <dt class="text-muted">{{ __('Check-in') }}</dt>
                                <dd class="mt-1 font-semibold text-ink">{{ $nextStay->check_in->translatedFormat('D, M j') }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted">{{ __('Check-out') }}</dt>
                                <dd class="mt-1 font-semibold text-ink">{{ $nextStay->check_out->translatedFormat('D, M j') }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted">{{ __('Nights') }}</dt>
                                <dd class="mt-1 font-semibold tabular-nums text-ink">{{ $nights }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted">{{ __('Guests') }}</dt>
                                <dd class="mt-1 font-semibold tabular-nums text-ink">{{ $nextStay->guests }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 border-t border-neutral-200 bg-neutral-50 px-6 py-4">
                        <x-dashboard.button :href="route('my-bookings.show', $nextStay)">{{ __('View booking') }}</x-dashboard.button>
                        <p class="ms-auto text-sm text-muted">{{ __('Reference') }} <span class="font-semibold tabular-nums text-ink">{{ $nextStay->reference }}</span></p>
                    </div>
                </section>

                <p class="text-sm text-muted">
                    <a href="{{ route('my-bookings.index') }}" class="font-semibold text-ink underline decoration-neutral-300 underline-offset-4 hover:decoration-ink">{{ __('See all my bookings') }}</a>
                </p>
            @else
                <section class="rounded-xl bg-white p-8 text-center ring-1 ring-neutral-200">
                    <h2 class="text-xl font-semibold text-ink">{{ __('No upcoming stays') }}</h2>
                    <p class="mx-auto mt-2 max-w-sm text-sm text-muted">{{ __('Pick your dates and we will show the rooms that are free.') }}</p>
                    <div class="mt-6 flex flex-wrap justify-center gap-3">
                        <x-dashboard.button variant="primary" :href="route('booking')">{{ __('Book a room') }}</x-dashboard.button>
                        <x-dashboard.button :href="route('my-bookings.index')">{{ __('My bookings') }}</x-dashboard.button>
                    </div>
                </section>
            @endif
        </div>
    @endif
</x-dashboard-layout>
