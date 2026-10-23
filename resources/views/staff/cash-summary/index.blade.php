<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Daily cash summary') }}</h2>
            <a href="{{ route('staff.cash-summary.print', array_filter(['date' => $date, 'user' => $userId])) }}" target="_blank" rel="noopener"
               class="text-sm text-gray-600 hover:text-gray-900">{{ __('Print closing sheet') }}</a>
        </div>
    </x-slot>

    @php($totals = $summary['totals'])

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <form method="GET" action="{{ route('staff.cash-summary') }}" class="flex flex-wrap items-end gap-4">
                <div>
                    <x-input-label for="date" :value="__('Day')" />
                    <x-text-input id="date" name="date" type="date" class="mt-1 block" :value="$date" />
                </div>

                @if ($seesAll)
                    <div>
                        <x-input-label for="user" :value="__('Staff member')" />
                        <x-select-input id="user" name="user" class="mt-1 block w-56">
                            <option value="">{{ __('Everybody') }}</option>
                            @foreach ($staff as $member)
                                <option value="{{ $member->id }}" @selected($userId === $member->id)>{{ $member->name }}</option>
                            @endforeach
                        </x-select-input>
                    </div>
                @endif

                <x-secondary-button type="submit">{{ __('Show') }}</x-secondary-button>
                <a href="{{ route('staff.cash-summary') }}" class="pb-2 text-sm text-gray-600 hover:text-gray-900">{{ __('Today') }}</a>
            </form>

            <x-input-error :messages="$errors->get('date')" />
            <x-input-error :messages="$errors->get('user')" />

            <p class="text-sm text-gray-500">
                {{ \Illuminate\Support\Carbon::parse($date)->format('l, M j, Y') }} &middot;
                {{ __('The day runs from midnight to midnight, :tz time.', ['tz' => $timezone]) }}
                @unless ($seesAll) {{ __('You see your own payments only.') }} @endunless
            </p>

            {{-- The front desk and the restaurant keep separate drawers --}}
            @php($outlets = $summary['by_outlet'])
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach (['front_desk' => __('Front desk (room bills)'), 'restaurant' => __('Restaurant (walk-in bills)')] as $key => $label)
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="font-semibold text-gray-800">{{ $label }}</h3>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">ETB {{ number_format($outlets[$key]['cash_net'], 2) }}</p>
                        <p class="text-xs text-gray-500">{{ __('Cash to hand over') }}</p>
                        <p class="mt-3 text-sm text-gray-600">{{ __('Received') }} ETB {{ number_format($outlets[$key]['in'], 2) }} &middot; {{ __('Refunded') }} ETB {{ number_format($outlets[$key]['out'], 2) }}</p>
                    </div>
                @endforeach
            </div>

            {{-- One row per user --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-6 py-3">{{ __('User') }}</th>
                            @foreach ($methods as $method)
                                <th class="px-6 py-3 text-right">{{ __($method->label()) }}</th>
                            @endforeach
                            <th class="px-6 py-3 text-right">{{ __('Received') }}</th>
                            <th class="px-6 py-3 text-right">{{ __('Refunded') }}</th>
                            <th class="px-6 py-3 text-right">{{ __('Net') }}</th>
                            <th class="px-6 py-3 text-right">{{ __('Cash to hand over') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-gray-900">
                        @forelse ($summary['rows'] as $row)
                            <tr>
                                <td class="px-6 py-4 font-medium">
                                    {{ $row['user']?->name ?? __('Unknown user') }}
                                    <span class="block text-xs font-normal text-gray-500">{{ $row['count'] }} {{ Str::plural('entry', $row['count']) }}</span>
                                </td>
                                @foreach ($methods as $method)
                                    <td class="px-6 py-4 text-right whitespace-nowrap">{{ number_format($row['by_method'][$method->value]['net'], 2) }}</td>
                                @endforeach
                                <td class="px-6 py-4 text-right whitespace-nowrap">{{ number_format($row['in'], 2) }}</td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">{{ number_format($row['out'], 2) }}</td>
                                <td class="px-6 py-4 text-right whitespace-nowrap font-semibold">{{ number_format($row['net'], 2) }}</td>
                                <td class="px-6 py-4 text-right whitespace-nowrap font-semibold">
                                    ETB {{ number_format($row['cash_net'], 2) }}
                                    @foreach ($row['foreign_cash'] as $currency => $amount)
                                        <span class="block text-xs font-normal text-gray-600">+ {{ $currency }} {{ number_format($amount, 2) }}</span>
                                    @endforeach
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($methods) + 5 }}" class="px-6 py-10 text-center text-gray-500">{{ __('No payments were recorded on this day.') }}</td></tr>
                        @endforelse
                    </tbody>
                    @if ($summary['rows']->count() > 1)
                        <tfoot class="bg-gray-50 font-semibold text-gray-900">
                            <tr>
                                <td class="px-6 py-3">{{ __('All users') }}</td>
                                @foreach ($methods as $method)
                                    <td class="px-6 py-3 text-right whitespace-nowrap">{{ number_format($totals['by_method'][$method->value]['net'], 2) }}</td>
                                @endforeach
                                <td class="px-6 py-3 text-right whitespace-nowrap">{{ number_format($totals['in'], 2) }}</td>
                                <td class="px-6 py-3 text-right whitespace-nowrap">{{ number_format($totals['out'], 2) }}</td>
                                <td class="px-6 py-3 text-right whitespace-nowrap">{{ number_format($totals['net'], 2) }}</td>
                                <td class="px-6 py-3 text-right whitespace-nowrap">ETB {{ number_format($totals['cash_net'], 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
            <p class="text-xs text-gray-500">{{ __('All amounts are in ETB. Foreign cash is shown in its own currency under "Cash to hand over". Refunds are taken off.') }}</p>

            {{-- Closing the day: count the cash and record the difference --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <h3 class="font-semibold text-gray-800">{{ __('Close the day') }}</h3>

                <x-auth-session-status :status="session('status')" />

                @session('error')
                    <p role="alert" class="font-medium text-sm text-red-600">{{ $value }}</p>
                @endsession

                @if ($myClosing)
                    <p class="text-sm text-gray-700">
                        {{ __('Closed at :time', ['time' => $myClosing->closed_at->timezone($timezone)->format('H:i')]) }} &middot;
                        {{ __('Expected') }} ETB {{ number_format($myClosing->expected_cash, 2) }} &middot;
                        {{ __('Counted') }} ETB {{ number_format($myClosing->counted_cash, 2) }} &middot;
                        <span class="{{ abs((float) $myClosing->difference) < 0.005 ? 'text-emerald-700' : 'font-semibold text-red-700' }}">{{ __('Difference') }} ETB {{ number_format($myClosing->difference, 2) }}</span>
                    </p>

                    @if ($lateEntries > 0)
                        <p class="text-sm font-medium text-red-600">{{ __(':n entries were recorded after closing. Tell the manager.', ['n' => $lateEntries]) }}</p>
                    @endif
                @elseif ($canClose)
                    <form method="POST" action="{{ route('staff.cash-summary.close') }}" class="flex flex-wrap items-end gap-4">
                        @csrf
                        <input type="hidden" name="date" value="{{ $date }}">

                        <div>
                            <x-input-label for="counted_cash" :value="__('Birr cash counted in the drawer')" />
                            <x-text-input id="counted_cash" name="counted_cash" type="number" step="0.01" min="0" class="mt-1 block w-48" :value="old('counted_cash')" required />
                            <x-input-error :messages="$errors->get('counted_cash')" class="mt-2" />
                        </div>

                        <div class="grow">
                            <x-input-label for="note" :value="__('Note (optional)')" />
                            <x-text-input id="note" name="note" type="text" maxlength="255" class="mt-1 block w-full" :value="old('note')" />
                            <x-input-error :messages="$errors->get('note')" class="mt-2" />
                        </div>

                        <x-primary-button>{{ __('Close my day') }}</x-primary-button>
                    </form>
                    <p class="text-xs text-gray-500">{{ __('Count the birr cash first, then enter it. The system compares it with the cash it expected. Foreign cash is handed over as listed above.') }}</p>
                @elseif ($seesAll && ! $userId)
                    <p class="text-sm text-gray-500">{{ __('Choose yourself in the staff list to close your own day. Closings of everybody are listed below.') }}</p>
                @endif

                @if ($seesAll && $closings->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                <tr>
                                    <th class="py-2 pe-4">{{ __('User') }}</th>
                                    <th class="py-2 pe-4">{{ __('Closed at') }}</th>
                                    <th class="py-2 pe-4 text-right">{{ __('Expected') }}</th>
                                    <th class="py-2 pe-4 text-right">{{ __('Counted') }}</th>
                                    <th class="py-2 pe-4 text-right">{{ __('Difference') }}</th>
                                    <th class="py-2">{{ __('Note') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-gray-900">
                                @foreach ($closings as $closing)
                                    <tr>
                                        <td class="py-2 pe-4">{{ $closing->user?->name ?? '—' }}</td>
                                        <td class="py-2 pe-4 whitespace-nowrap">{{ $closing->closed_at->timezone($timezone)->format('H:i') }}</td>
                                        <td class="py-2 pe-4 text-right whitespace-nowrap">{{ number_format($closing->expected_cash, 2) }}</td>
                                        <td class="py-2 pe-4 text-right whitespace-nowrap">{{ number_format($closing->counted_cash, 2) }}</td>
                                        <td class="py-2 pe-4 text-right whitespace-nowrap {{ abs((float) $closing->difference) < 0.005 ? 'text-emerald-700' : 'font-semibold text-red-700' }}">{{ number_format($closing->difference, 2) }}</td>
                                        <td class="py-2 text-gray-600">{{ $closing->note }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Every entry of the day --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <h3 class="px-6 pt-6 font-semibold text-gray-800">{{ __('Entries') }}</h3>
                <table class="mt-3 min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-6 py-3">{{ __('Time') }}</th>
                            <th class="px-6 py-3">{{ __('No.') }}</th>
                            <th class="px-6 py-3">{{ __('Booking') }}</th>
                            <th class="px-6 py-3">{{ __('Method') }}</th>
                            <th class="px-6 py-3">{{ __('User') }}</th>
                            <th class="px-6 py-3 text-right">{{ __('ETB') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-900">
                        @forelse ($summary['payments'] as $payment)
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap">{{ $payment->paid_at->timezone($timezone)->format('H:i') }}</td>
                                <td class="px-6 py-3 whitespace-nowrap">{{ $payment->number }}</td>
                                <td class="px-6 py-3">
                                    @can('viewAny', \App\Models\FolioCharge::class)
                                        <a href="{{ route('staff.folio.show', $payment->booking_id) }}" class="text-indigo-600 hover:text-indigo-900">{{ $payment->booking->reference }}</a>
                                    @else
                                        {{ $payment->booking->reference }}
                                    @endcan
                                    <span class="block text-xs text-gray-500">{{ $payment->booking->guest_name }}</span>
                                </td>
                                <td class="px-6 py-3">
                                    {{ $payment->isRefund() ? __('Refund') : __('Payment') }} &middot; {{ __($payment->method->label()) }}
                                    <span class="block text-xs text-gray-500">
                                        @if ($payment->reference) {{ $payment->reference }} @endif
                                        @if ($payment->isForeign()) {{ $payment->currency }} {{ number_format($payment->original_amount, 2) }} &times; {{ rtrim(rtrim(number_format($payment->exchange_rate, 4), '0'), '.') }} @endif
                                    </span>
                                </td>
                                <td class="px-6 py-3">{{ $payment->creator?->name ?? '—' }}</td>
                                <td class="px-6 py-3 text-right whitespace-nowrap {{ $payment->isRefund() ? 'text-red-700' : '' }}">{{ $payment->isRefund() ? '−' : '' }}{{ number_format($payment->amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">{{ __('Nothing to show.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Restaurant walk-in bills paid on this day (included in the totals above) --}}
            @if ($summary['walk_ins']->isNotEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                    <h3 class="px-6 pt-6 font-semibold text-gray-800">{{ __('Restaurant walk-in bills') }}</h3>
                    <table class="mt-3 min-w-full divide-y divide-gray-200 text-sm">
                        <tbody class="divide-y divide-gray-100 text-gray-900">
                            @foreach ($summary['walk_ins'] as $bill)
                                <tr>
                                    <td class="px-6 py-3 whitespace-nowrap">{{ $bill->paid_at->timezone($timezone)->format('H:i') }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap">{{ $bill->number }}</td>
                                    <td class="px-6 py-3">{{ $bill->customer_name ?? __('Walk-in customer') }}</td>
                                    <td class="px-6 py-3">{{ __($bill->payment_method->label()) }}@if ($bill->payment_reference) <span class="block text-xs text-gray-500">{{ $bill->payment_reference }}</span>@endif</td>
                                    <td class="px-6 py-3">{{ $bill->payer?->name ?? '—' }}</td>
                                    <td class="px-6 py-3 text-right whitespace-nowrap">{{ number_format($bill->total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($summary['voided']->isNotEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                    <h3 class="px-6 pt-6 font-semibold text-gray-800">{{ __('Voided on this day (not counted above)') }}</h3>
                    <ul class="mt-3 divide-y divide-gray-100 px-6 pb-4 text-sm text-gray-700">
                        @foreach ($summary['voided'] as $payment)
                            <li class="py-2">
                                {{ $payment->number }} &middot; {{ $payment->isRefund() ? __('Refund') : __('Payment') }} {{ __($payment->method->label()) }} ETB {{ number_format($payment->amount, 2) }}
                                &middot; {{ $payment->booking->reference }}
                                &mdash; {{ $payment->void_reason }} ({{ $payment->voider?->name ?? '—' }}, {{ $payment->voided_at->timezone($timezone)->format('H:i') }})
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>