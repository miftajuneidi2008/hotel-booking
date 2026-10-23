<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Walk-in bills') }}</h2>
            <x-link-button :href="route('staff.orders.create')">{{ __('New order') }}</x-link-button>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('staff.menu._tabs', ['active' => 'walk-in'])

            <x-auth-session-status :status="session('status')" class="mx-4 sm:mx-0" />

            @session('error')
                <p role="alert" class="mx-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700 sm:mx-0">{{ $value }}</p>
            @endsession

            <div class="mx-4 grid grid-cols-2 gap-4 sm:mx-0">
                <div class="rounded-2xl bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">{{ __('Open bills') }}</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900">{{ $open->count() }}</p>
                </div>
                <div class="rounded-2xl bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">{{ __('Paid today') }}</p>
                    <p class="mt-1 text-3xl font-bold text-emerald-600">ETB {{ number_format($paidToday, 2) }}</p>
                </div>
            </div>

            <section>
                <h3 class="px-4 text-sm font-semibold uppercase tracking-wider text-gray-400 sm:px-0">{{ __('Open') }}</h3>
                <ul class="mx-4 mt-3 divide-y divide-gray-100 overflow-hidden rounded-2xl bg-white shadow-sm sm:mx-0">
                    @forelse ($open as $bill)
                        <li>
                            <a href="{{ route('staff.walk-in-bills.show', $bill) }}" class="flex items-center justify-between gap-3 px-5 py-4 hover:bg-gray-50">
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900">{{ $bill->customer_name ?: $bill->number }}</p>
                                    <p class="text-sm text-gray-500">{{ $bill->number }} &middot; {{ trans_choice(':count item|:count items', $bill->lines_count, ['count' => $bill->lines_count]) }} &middot; {{ $bill->created_at->format('H:i') }}</p>
                                </div>
                                <span class="shrink-0 font-semibold text-gray-900">ETB {{ number_format($bill->total, 2) }}</span>
                            </a>
                        </li>
                    @empty
                        <li class="p-8 text-center text-sm text-gray-500">{{ __('No open bills.') }}</li>
                    @endforelse
                </ul>
            </section>

            @if ($closedToday->isNotEmpty())
                <section>
                    <h3 class="px-4 text-sm font-semibold uppercase tracking-wider text-gray-400 sm:px-0">{{ __('Closed today') }}</h3>
                    <ul class="mx-4 mt-3 divide-y divide-gray-100 overflow-hidden rounded-2xl bg-white shadow-sm sm:mx-0">
                        @foreach ($closedToday as $bill)
                            <li>
                                <a href="{{ route('staff.walk-in-bills.show', $bill) }}" class="flex items-center justify-between gap-3 px-5 py-4 hover:bg-gray-50">
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-900">{{ $bill->customer_name ?: $bill->number }}</p>
                                        <p class="text-sm text-gray-500">{{ $bill->number }} &middot; <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $bill->status->badge() }}">{{ __($bill->status->label()) }}</span></p>
                                    </div>
                                    <span class="shrink-0 font-semibold {{ $bill->status === \App\Enums\WalkInStatus::Voided ? 'text-gray-400 line-through' : 'text-gray-900' }}">ETB {{ number_format($bill->total, 2) }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
