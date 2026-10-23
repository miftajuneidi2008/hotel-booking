@php
    use App\Enums\WalkInStatus;

    $open = $bill->isOpen();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Walk-in bill') }} {{ $bill->number }}</h2>
            <a href="{{ route('staff.walk-in-bills.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; {{ __('All walk-in bills') }}</a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-auth-session-status :status="session('status')" class="mx-4 sm:mx-0" />

            @session('error')
                <p role="alert" class="mx-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700 sm:mx-0">{{ $value }}</p>
            @endsession

            @if ($errors->any())
                <div role="alert" class="mx-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 sm:mx-0">
                    <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
                </div>
            @endif

            {{-- Bill --}}
            <section class="mx-4 overflow-hidden rounded-2xl bg-white shadow-sm sm:mx-0">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 p-5">
                    <div>
                        <p class="text-lg font-semibold text-gray-900">{{ $bill->customer_name ?: __('Walk-in customer') }}</p>
                        <p class="text-sm text-gray-500">{{ $bill->created_at->format('M j, H:i') }}@if ($bill->creator) &middot; {{ $bill->creator->name }}@endif</p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-sm font-medium {{ $bill->status->badge() }}">{{ __($bill->status->label()) }}</span>
                </div>

                <ul class="divide-y divide-gray-100">
                    @forelse ($bill->lines as $line)
                        <li class="flex items-center gap-3 px-5 py-4">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-gray-900">{{ $line->description }}</p>
                                <p class="text-xs text-gray-500">{{ __($line->category->label()) }} &middot; {{ $line->quantity }} &times; ETB {{ number_format($line->unit_price, 2) }}</p>
                            </div>
                            <p class="font-medium text-gray-900">ETB {{ number_format($line->amount, 2) }}</p>
                            @if ($open)
                                <form method="POST" action="{{ route('staff.walk-in-bills.lines.destroy', $line) }}" onsubmit="return confirm('{{ __('Remove this item?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600" aria-label="{{ __('Remove') }}">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V4h6v3m-8 0l1 13h8l1-13"/></svg>
                                    </button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="p-8 text-center text-sm text-gray-500">{{ __('No items yet.') }}</li>
                    @endforelse
                </ul>

                <div class="flex items-center justify-between border-t border-gray-200 bg-gray-50 px-5 py-4 text-base font-semibold text-gray-900">
                    <span>{{ __('Total') }}</span><span>ETB {{ number_format($bill->total, 2) }}</span>
                </div>
            </section>

            {{-- Closed bill: what happened --}}
            @unless ($open)
                <div class="mx-4 rounded-2xl border border-gray-200 bg-white p-5 text-sm text-gray-700 shadow-sm sm:mx-0">
                    @if ($bill->status === WalkInStatus::Paid)
                        <p class="font-medium text-emerald-700">{{ __('Paid') }} &middot; {{ __($bill->payment_method?->label()) }}@if ($bill->payment_reference) ({{ $bill->payment_reference }})@endif</p>
                        <p class="mt-1 text-gray-500">{{ $bill->paid_at?->format('M j, H:i') }}@if ($bill->payer) &middot; {{ $bill->payer->name }}@endif</p>
                    @elseif ($bill->status === WalkInStatus::Transferred)
                        <p class="font-medium text-sky-700">{{ __('Moved to the room bill of') }} {{ $bill->booking?->guest_name }}</p>
                        <p class="mt-1 text-gray-500">{{ $bill->transferred_at?->format('M j, H:i') }}@if ($bill->mover) &middot; {{ $bill->mover->name }}@endif</p>
                        @if ($bill->booking)
                            <a href="{{ route('staff.folio.show', $bill->booking) }}" class="mt-2 inline-block text-indigo-600 hover:underline">{{ __('Open the room bill') }} &rarr;</a>
                        @endif
                    @else
                        <p class="font-medium text-gray-600">{{ __('Voided') }} &mdash; {{ $bill->void_reason }}</p>
                        <p class="mt-1 text-gray-500">{{ $bill->voided_at?->format('M j, H:i') }}@if ($bill->voider) &middot; {{ $bill->voider->name }}@endif</p>
                    @endif
                </div>
            @endunless

            @if ($bill->status !== WalkInStatus::Voided)
                <div class="mx-4 sm:mx-0">
                    <a href="{{ route('staff.walk-in-bills.receipt', $bill) }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a1 1 0 01-1-1v-6a2 2 0 012-2h14a2 2 0 012 2v6a1 1 0 01-1 1h-2M7 14h10v7H7z"/></svg>
                        {{ __('Receipt (80 mm)') }}
                    </a>
                </div>
            @endif

            @if ($open)
                <a href="{{ route('staff.orders.create', ['bill' => $bill->id]) }}" class="mx-4 flex items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-gray-300 bg-white px-5 py-4 text-sm font-medium text-gray-600 hover:border-indigo-400 hover:text-indigo-700 sm:mx-0">
                    + {{ __('Add more items') }}
                </a>

                {{-- Pay --}}
                @if ($bill->lines->isNotEmpty())
                    <form method="POST" action="{{ route('staff.walk-in-bills.pay', $bill) }}" x-data="{ method: @js(old('method', 'cash')) }" class="mx-4 space-y-4 rounded-2xl bg-white p-5 shadow-sm sm:mx-0">
                        @csrf
                        <h3 class="font-semibold text-gray-900">{{ __('Take payment') }} &middot; ETB {{ number_format($bill->total, 2) }}</h3>

                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            @foreach ($methods as $method)
                                <label class="cursor-pointer rounded-lg border px-3 py-2 text-center text-sm font-medium transition"
                                       :class="method === '{{ $method->value }}' ? 'border-indigo-600 bg-indigo-50 text-indigo-700 ring-1 ring-indigo-600' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
                                    <input type="radio" name="method" value="{{ $method->value }}" x-model="method" class="sr-only">{{ __($method->label()) }}
                                </label>
                            @endforeach
                        </div>

                        <div x-show="method !== 'cash'" x-cloak>
                            <x-input-label for="reference" :value="__('Reference number')" />
                            <x-text-input id="reference" name="reference" type="text" maxlength="100" class="mt-1 block w-full" :value="old('reference')" x-bind:required="method !== 'cash'" />
                        </div>

                        <x-primary-button class="w-full justify-center">{{ __('Received, close the bill') }}</x-primary-button>
                    </form>

                    {{-- Move to a room bill --}}
                    <form method="POST" action="{{ route('staff.walk-in-bills.move', $bill) }}" class="mx-4 space-y-3 rounded-2xl bg-white p-5 shadow-sm sm:mx-0">
                        @csrf
                        <h3 class="font-semibold text-gray-900">{{ __('Charge to a hotel guest instead') }}</h3>
                        @if ($guests->isEmpty())
                            <p class="text-sm text-gray-500">{{ __('No guest is checked in.') }}</p>
                        @else
                            <x-select-input name="booking_id" required class="block w-full">
                                <option value="">{{ __('Choose a guest') }}</option>
                                @foreach ($guests as $guest)
                                    <option value="{{ $guest->id }}">{{ __('Room') }} {{ $guest->room->number }} &middot; {{ $guest->guest_name }}</option>
                                @endforeach
                            </x-select-input>
                            <p class="text-xs text-gray-500">{{ __('The items go onto the guest\'s room bill and this bill is closed.') }}</p>
                            <x-secondary-button type="submit" onclick="return confirm('{{ __('Move all items to the room bill?') }}')">{{ __('Move to room bill') }}</x-secondary-button>
                        @endif
                    </form>
                @endif

                {{-- Void --}}
                <details class="mx-4 rounded-2xl bg-white shadow-sm sm:mx-0">
                    <summary class="cursor-pointer list-none px-5 py-4 text-sm font-medium text-red-600">{{ __('Cancel this bill') }}</summary>
                    <form method="POST" action="{{ route('staff.walk-in-bills.void', $bill) }}" class="space-y-3 border-t border-gray-100 p-5" onsubmit="return confirm('{{ __('Cancel this bill?') }}')">
                        @csrf
                        @method('PATCH')
                        <x-input-label for="void_reason" :value="__('Reason (required)')" />
                        <x-text-input id="void_reason" name="reason" type="text" maxlength="255" required class="block w-full" />
                        <x-danger-button>{{ __('Cancel bill') }}</x-danger-button>
                    </form>
                </details>
            @endif
        </div>
    </div>
</x-app-layout>
