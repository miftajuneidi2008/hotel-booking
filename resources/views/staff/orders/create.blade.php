@php
    $oldCart = [];
    foreach ((array) old('items', []) as $line) {
        if (is_array($line) && isset($line['menu_item_id'], $line['quantity'])) {
            $oldCart[(int) $line['menu_item_id']] = (int) $line['quantity'];
        }
    }

    // Show the category names in the chosen language.
    $categories = collect($categories)->map(fn ($c) => ['value' => $c['value'], 'label' => __($c['label'])])->values();
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('New order') }}</h2>
    </x-slot>

    <div class="py-6 sm:py-10 pb-28 lg:pb-10"
         x-data="{
             items: @js($items),
             categories: @js($categories),
             cat: 'all',
             search: '',
             cart: @js((object) $oldCart),
             target: @js(old('target', $startTarget)),
             booking: @js((string) old('booking_id', $startBooking ?? '')),
             bill: @js((string) old('walk_in_bill_id', $startBill ?? '')),
             customer: @js(old('customer_name', '')),
             get shown() {
                 const q = this.search.trim().toLowerCase();
                 return this.items.filter(i => (this.cat === 'all' || i.category === this.cat) && (! q || i.name.toLowerCase().includes(q)));
             },
             get lines() {
                 return Object.entries(this.cart).map(([id, qty]) => {
                     const item = this.items.find(i => i.id == id);
                     return item ? { id: item.id, name: item.name, price: item.price, qty: qty, amount: item.price * qty } : null;
                 }).filter(Boolean);
             },
             get total() { return this.lines.reduce((sum, l) => sum + l.amount, 0); },
             get count() { return this.lines.reduce((sum, l) => sum + l.qty, 0); },
             get ready() { return this.count > 0 && (this.target === 'walk_in' || this.booking !== ''); },
             add(id) { this.cart[id] = Math.min(99, (this.cart[id] || 0) + 1); },
             less(id) { if ((this.cart[id] || 0) <= 1) { delete this.cart[id]; } else { this.cart[id]--; } },
             qty(id) { return this.cart[id] || 0; },
             fmt(n) { return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
         }">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('staff.menu._tabs', ['active' => 'order'])

            @session('error')
                <p role="alert" class="mx-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700 sm:mx-0">{{ $value }}</p>
            @endsession

            @if ($errors->any())
                <div role="alert" class="mx-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 sm:mx-0">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @if ($items->isEmpty())
                <div class="mx-4 rounded-2xl bg-white p-10 text-center shadow-sm sm:mx-0">
                    <p class="font-medium text-gray-800">{{ __('There is nothing on the menu yet.') }}</p>
                    <p class="mt-1 text-sm text-gray-500">
                        @if ($canEditMenu)
                            <a href="{{ route('staff.menu.index') }}" class="text-indigo-600 hover:underline">{{ __('Add items to the menu') }}</a>
                        @else
                            {{ __('Ask the owner or a manager to add items.') }}
                        @endif
                    </p>
                </div>
            @else
                <div class="grid gap-6 lg:grid-cols-3 lg:items-start">

                    {{-- Menu --}}
                    <section class="space-y-4 lg:col-span-2">
                        <div class="mx-4 space-y-3 sm:mx-0">
                            <input type="search" x-model="search" placeholder="{{ __('Search the menu') }}" aria-label="{{ __('Search the menu') }}"
                                   class="block w-full rounded-xl border-gray-200 bg-white py-3 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <div class="flex gap-2 overflow-x-auto pb-1">
                                <button type="button" @click="cat = 'all'" class="shrink-0 rounded-full px-4 py-2 text-sm font-medium transition" :class="cat === 'all' ? 'bg-gray-800 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50'">{{ __('All') }}</button>
                                <template x-for="c in categories" :key="c.value">
                                    <button type="button" @click="cat = c.value" x-text="c.label" class="shrink-0 rounded-full px-4 py-2 text-sm font-medium transition" :class="cat === c.value ? 'bg-gray-800 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50'"></button>
                                </template>
                            </div>
                        </div>

                        <ul class="mx-4 grid grid-cols-2 gap-3 sm:mx-0 sm:grid-cols-3">
                            <template x-for="item in shown" :key="item.id">
                                <li>
                                    <button type="button" @click="add(item.id)"
                                            class="relative flex h-full w-full flex-col justify-between rounded-2xl border bg-white p-4 text-left shadow-sm transition hover:shadow-md active:scale-[0.98]"
                                            :class="qty(item.id) > 0 ? 'border-indigo-500 ring-1 ring-indigo-500' : 'border-gray-200'">
                                        <span class="font-medium text-gray-900" x-text="item.name"></span>
                                        <span class="mt-2 text-sm text-gray-500">ETB <span x-text="fmt(item.price)"></span></span>
                                        <span x-show="qty(item.id) > 0" x-cloak class="absolute -right-2 -top-2 flex h-7 min-w-7 items-center justify-center rounded-full bg-indigo-600 px-1.5 text-xs font-bold text-white" x-text="qty(item.id)"></span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                        <p x-show="shown.length === 0" x-cloak class="px-4 text-center text-sm text-gray-500 sm:px-0">{{ __('No item matches.') }}</p>
                    </section>

                    {{-- The order --}}
                    <form id="order-cart" method="POST" action="{{ route('staff.orders.store') }}" class="mx-4 space-y-5 rounded-2xl bg-white p-5 shadow-sm sm:mx-0 lg:sticky lg:top-6">
                        @csrf
                        <h3 class="font-semibold text-gray-900">{{ __('Order') }}</h3>

                        {{-- Where does it go? --}}
                        <div class="grid grid-cols-2 rounded-lg bg-gray-100 p-1 text-sm">
                            <label class="cursor-pointer rounded-md px-3 py-1.5 text-center font-medium transition" :class="target === 'room' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                                <input type="radio" name="target" value="room" x-model="target" class="sr-only">{{ __('Room bill') }}
                            </label>
                            <label class="cursor-pointer rounded-md px-3 py-1.5 text-center font-medium transition" :class="target === 'walk_in' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                                <input type="radio" name="target" value="walk_in" x-model="target" class="sr-only">{{ __('Walk-in bill') }}
                            </label>
                        </div>

                        <div x-show="target === 'room'">
                            <x-input-label for="booking_id" :value="__('Guest')" />
                            <select id="booking_id" name="booking_id" x-model="booking" :disabled="target !== 'room'" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Choose a guest') }}</option>
                                @foreach ($guests as $guest)
                                    <option value="{{ $guest['id'] }}">{{ $guest['label'] }}</option>
                                @endforeach
                            </select>
                            @if ($guests->isEmpty())
                                <p class="mt-2 text-xs text-gray-500">{{ __('No guest is checked in.') }}</p>
                            @endif
                        </div>

                        <div x-show="target === 'walk_in'" x-cloak class="space-y-3">
                            <div>
                                <x-input-label for="walk_in_bill_id" :value="__('Bill')" />
                                <select id="walk_in_bill_id" name="walk_in_bill_id" x-model="bill" :disabled="target !== 'walk_in'" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">{{ __('Start a new bill') }}</option>
                                    @foreach ($openBills as $openBill)
                                        <option value="{{ $openBill['id'] }}">{{ $openBill['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div x-show="bill === ''">
                                <x-input-label for="customer_name" :value="__('Customer or table (optional)')" />
                                <input id="customer_name" name="customer_name" type="text" maxlength="100" x-model="customer" :disabled="target !== 'walk_in'" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                        </div>

                        {{-- Lines --}}
                        <ul class="divide-y divide-gray-100">
                            <template x-for="(l, i) in lines" :key="l.id">
                                <li class="flex items-center gap-3 py-3">
                                    <input type="hidden" :name="'items[' + i + '][menu_item_id]'" :value="l.id">
                                    <input type="hidden" :name="'items[' + i + '][quantity]'" :value="l.qty">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-gray-900" x-text="l.name"></p>
                                        <p class="text-xs text-gray-500">ETB <span x-text="fmt(l.price)"></span></p>
                                    </div>
                                    <div class="flex items-center overflow-hidden rounded-lg border border-gray-200">
                                        <button type="button" @click="less(l.id)" class="px-3 py-1.5 text-gray-600 hover:bg-gray-50" aria-label="{{ __('Less') }}">&minus;</button>
                                        <span class="min-w-8 text-center text-sm font-medium" x-text="l.qty"></span>
                                        <button type="button" @click="add(l.id)" class="px-3 py-1.5 text-gray-600 hover:bg-gray-50" aria-label="{{ __('More') }}">+</button>
                                    </div>
                                    <p class="w-20 text-right text-sm font-medium text-gray-900" x-text="fmt(l.amount)"></p>
                                </li>
                            </template>
                        </ul>
                        <p x-show="lines.length === 0" x-cloak class="rounded-xl bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">{{ __('Tap items on the menu to add them.') }}</p>

                        <div class="flex items-center justify-between border-t border-gray-200 pt-4 text-base font-semibold text-gray-900">
                            <span>{{ __('Total') }}</span><span>ETB <span x-text="fmt(total)"></span></span>
                        </div>

                        <button type="submit" :disabled="! ready" class="w-full rounded-xl bg-gray-800 px-4 py-3 text-base font-semibold text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-40">
                            <span x-text="target === 'room' ? '{{ __('Add to room bill') }}' : '{{ __('Add to walk-in bill') }}'"></span>
                        </button>
                        <p x-show="count > 0 && target === 'room' && booking === ''" x-cloak class="text-center text-xs text-amber-700">{{ __('Choose the guest first.') }}</p>
                    </form>
                </div>

                {{-- Phone: a bar that jumps to the order --}}
                <a href="#order-cart" x-show="count > 0" x-cloak class="fixed inset-x-4 bottom-4 z-20 flex items-center justify-between rounded-2xl bg-indigo-600 px-5 py-4 text-white shadow-lg lg:hidden">
                    <span class="font-semibold"><span x-text="count"></span> {{ __('items') }}</span>
                    <span class="font-semibold">ETB <span x-text="fmt(total)"></span> &darr;</span>
                </a>
            @endif
        </div>
    </div>
</x-app-layout>
