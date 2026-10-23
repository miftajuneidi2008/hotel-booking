@php
    $user = auth()->user();
    $isStaff = $user->can('manage-bookings');

    $icons = [
        'dashboard' => 'M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z',
        'desk' => 'M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-4-5.7V5a2 2 0 10-4 0v.3C7.7 6.2 6 8.4 6 11v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
        'bookings' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
        'calendar' => 'M8 7V3m8 4V3M3 11h18M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z',
        'groups' => 'M17 20h5v-2a3 3 0 00-5.4-1.9M17 20H7m10 0v-2c0-.7-.1-1.3-.4-1.9M7 20H2v-2a3 3 0 015.4-1.9M7 20v-2c0-.7.1-1.3.4-1.9m0 0a5 5 0 019.2 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
        'rooms' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        'types' => 'M7 7h.01M7 3h5c.5 0 1 .2 1.4.6l7 7a2 2 0 010 2.8l-5 5a2 2 0 01-2.8 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z',
        'housekeeping' => 'M5 13l4 4L19 7',
        'order' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3c-.6.6-.2 1.7.7 1.7H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
        'menu' => 'M4 6h16M4 10h16M4 14h10M4 18h7',
    ];

    // Same links as the top navigation, grouped for the sidebar.
    $sections = [
        [
            'title' => null,
            'items' => [
                ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'dashboard', 'show' => true],
            ],
        ],
        [
            'title' => 'Front desk',
            'items' => [
                ['label' => 'Front desk', 'route' => 'staff.front-desk', 'active' => 'staff.front-desk', 'icon' => 'desk', 'show' => $isStaff],
                ['label' => 'Bookings', 'route' => 'staff.bookings.index', 'active' => 'staff.bookings.*', 'icon' => 'bookings', 'show' => $isStaff],
                ['label' => 'Calendar', 'route' => 'staff.calendar', 'active' => 'staff.calendar', 'icon' => 'calendar', 'show' => $isStaff],
                ['label' => 'Groups', 'route' => 'staff.groups.index', 'active' => 'staff.groups.*', 'icon' => 'groups', 'show' => $isStaff],
            ],
        ],
        [
            'title' => 'Housekeeping',
            'items' => [
                ['label' => 'Room board', 'route' => 'housekeeping.board', 'active' => 'housekeeping.board', 'icon' => 'housekeeping', 'show' => $user->can('viewAny', \App\Models\RoomStatusChange::class)],
                ['label' => 'Maintenance issues', 'route' => 'housekeeping.issues.index', 'active' => 'housekeeping.issues.*', 'icon' => 'bookings', 'show' => $user->can('viewAny', \App\Models\MaintenanceIssue::class)],
            ],
        ],
        [
            'title' => 'Restaurant',
            'items' => [
                ['label' => 'Take order', 'route' => 'staff.orders.create', 'active' => 'staff.orders.*', 'icon' => 'order', 'show' => $user->can('viewAny', \App\Models\MenuItem::class)],
                ['label' => 'Walk-in bills', 'route' => 'staff.walk-in-bills.index', 'active' => 'staff.walk-in-bills.*', 'icon' => 'bookings', 'show' => $user->can('viewAny', \App\Models\WalkInBill::class)],
                ['label' => 'Menu', 'route' => 'staff.menu.index', 'active' => 'staff.menu.*', 'icon' => 'menu', 'show' => $user->can('viewAny', \App\Models\MenuItem::class)],
            ],
        ],
        [
            'title' => 'Reports & tax',
            'items' => [
                ['label' => 'Daily report', 'route' => 'staff.reports.daily', 'active' => 'staff.reports.daily*', 'icon' => 'bookings', 'show' => $user->can('manage-property')],
                ['label' => 'Occupancy & income', 'route' => 'staff.reports.range', 'active' => 'staff.reports.range*', 'icon' => 'calendar', 'show' => $user->can('manage-property')],
                ['label' => 'Report by user', 'route' => 'staff.reports.users', 'active' => 'staff.reports.users*', 'icon' => 'groups', 'show' => $user->can('manage-property')],
                ['label' => 'Invoices', 'route' => 'staff.invoices.index', 'active' => 'staff.invoices.*', 'icon' => 'bookings', 'show' => $isStaff],
                ['label' => 'Cash summary', 'route' => 'staff.cash-summary', 'active' => 'staff.cash-summary*', 'icon' => 'bookings', 'show' => $user->can('viewSummary', \App\Models\Payment::class)],
            ],
        ],
        [
            'title' => 'My stays',
            'items' => [
                ['label' => 'My bookings', 'route' => 'my-bookings.index', 'active' => 'my-bookings.*', 'icon' => 'bookings', 'show' => ! $isStaff],
            ],
        ],
        [
            'title' => 'Property',
            'items' => [
                ['label' => 'Rooms', 'route' => 'admin.rooms.index', 'active' => 'admin.rooms.*', 'icon' => 'rooms', 'show' => $user->can('manage-property')],
                ['label' => 'Room types', 'route' => 'admin.room-types.index', 'active' => 'admin.room-types.*', 'icon' => 'types', 'show' => $user->can('manage-property')],
                ['label' => 'Users', 'route' => 'admin.users.index', 'active' => 'admin.users.*', 'icon' => 'groups', 'show' => $user->can('viewAny', \App\Models\User::class)],
                ['label' => 'Tax settings', 'route' => 'admin.settings.tax.edit', 'active' => 'admin.settings.*', 'icon' => 'types', 'show' => $user->can('manage-settings')],
            ],
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600|noto-sans-ethiopic:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-100">
        <div x-data="{ sidebar: false }" @keydown.escape.window="sidebar = false" class="min-h-screen">
            {{-- Dark backdrop behind the sidebar on small screens --}}
            <div :data-open="sidebar ? 'true' : 'false'" @click="sidebar = false"
                 class="hidden max-lg:data-[open=true]:block fixed inset-0 z-30 bg-gray-900/50"></div>

            <aside :data-open="sidebar ? 'true' : 'false'"
                   class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-e border-gray-200 bg-white transition-transform duration-200 -translate-x-full data-[open=true]:translate-x-0 lg:translate-x-0">
                <div class="flex h-16 shrink-0 items-center justify-between px-5 border-b border-gray-100">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-semibold text-gray-800">
                        <x-application-logo class="block h-8 w-auto fill-current text-gray-800" />
                        <span class="truncate">{{ config('app.name') }}</span>
                    </a>
                    <button type="button" @click="sidebar = false" class="lg:hidden rounded-md p-1 text-gray-400 hover:text-gray-600" aria-label="{{ __('Close menu') }}">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-5" aria-label="{{ __('Main') }}">
                    @foreach ($sections as $section)
                        @php($items = array_filter($section['items'], fn ($i) => $i['show']))
                        @if ($items)
                            <div>
                                @if ($section['title'])
                                    <p class="px-3 mb-1 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __($section['title']) }}</p>
                                @endif
                                <ul class="space-y-1">
                                    @foreach ($items as $item)
                                        @php($active = request()->routeIs($item['active']))
                                        <li>
                                            <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif
                                               class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition {{ $active ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                                                <svg class="h-5 w-5 shrink-0 {{ $active ? 'text-indigo-600' : 'text-gray-400' }}" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $icons[$item['icon']] }}"/></svg>
                                                {{ __($item['label']) }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @endforeach
                </nav>

                <div class="shrink-0 border-t border-gray-100 p-3">
                    <div class="px-3 pb-2">
                        <p class="truncate text-sm font-medium text-gray-800">{{ $user->name }}</p>
                        <p class="truncate text-xs text-gray-500">{{ $user->email }}</p>
                    </div>
                    <a href="{{ route('profile.edit') }}" class="block rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">{{ __('Profile') }}</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full rounded-md px-3 py-2 text-start text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">{{ __('Log Out') }}</button>
                    </form>
                </div>
            </aside>

            <div class="lg:ps-64">
                <header class="sticky top-0 z-20 flex min-h-16 items-center gap-3 border-b border-gray-200 bg-white px-4 sm:px-6 lg:px-8">
                    <button type="button" @click="sidebar = true" class="lg:hidden -ms-1 rounded-md p-2 text-gray-500 hover:bg-gray-100" aria-label="{{ __('Open menu') }}">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <div class="flex-1 py-3">
                        {{ $header ?? '' }}
                    </div>
                    <x-notification-bell />
                    <x-language-switcher />
                </header>

                <main class="px-4 py-8 sm:px-6 lg:px-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>