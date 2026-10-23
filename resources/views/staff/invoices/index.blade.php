@php($query = array_filter($filters, fn ($value) => $value !== null && $value !== ''))
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Invoices') }}</h2>
            <div class="flex flex-wrap items-center gap-4 text-sm">
                <a href="{{ route('staff.invoices.export', ['format' => 'xlsx'] + $query) }}" class="text-gray-600 hover:text-gray-900">{{ __('Download Excel') }}</a>
                <a href="{{ route('staff.invoices.export', ['format' => 'json'] + $query) }}" class="text-gray-600 hover:text-gray-900">{{ __('Download data (JSON)') }}</a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-auth-session-status :status="session('status')" />
            @session('error')
                <p role="alert" class="font-medium text-sm text-red-600">{{ $value }}</p>
            @endsession

            <form method="GET" action="{{ route('staff.invoices.index') }}" class="flex flex-wrap items-end gap-4">
                <div>
                    <x-input-label for="q" :value="__('Search')" />
                    <x-text-input id="q" name="q" type="text" class="mt-1 block w-64" :value="$filters['q']" :placeholder="__('Number, customer, TIN or booking')" />
                </div>
                <div>
                    <x-input-label for="from" :value="__('From')" />
                    <x-text-input id="from" name="from" type="date" class="mt-1 block" :value="$filters['from']" />
                </div>
                <div>
                    <x-input-label for="to" :value="__('To')" />
                    <x-text-input id="to" name="to" type="date" class="mt-1 block" :value="$filters['to']" />
                </div>
                <div>
                    <x-input-label for="status" :value="__('Status')" />
                    <x-select-input id="status" name="status" class="mt-1 block">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ __($status->label()) }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <x-secondary-button type="submit">{{ __('Show') }}</x-secondary-button>
                <a href="{{ route('staff.invoices.index') }}" class="pb-2 text-sm text-gray-600 hover:text-gray-900">{{ __('Clear') }}</a>
            </form>

            <x-input-error :messages="$errors->get('from')" />
            <x-input-error :messages="$errors->get('to')" />

            {{-- Totals of the issued (not voided) invoices in this list --}}
            <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
                @foreach ([
                    ['Invoices issued', number_format($totals['count'])],
                    ['Subtotal before tax', 'ETB '.number_format($totals['subtotal'], 2)],
                    ['Service charge', 'ETB '.number_format($totals['service_charge'], 2)],
                    ['VAT', 'ETB '.number_format($totals['vat'], 2)],
                    ['Total', 'ETB '.number_format($totals['total'], 2)],
                ] as [$label, $value])
                    <div class="bg-white shadow-sm sm:rounded-lg p-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __($label) }}</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900">{{ $value }}</p>
                    </div>
                @endforeach
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-6 py-3">{{ __('Invoice') }}</th>
                            <th class="px-6 py-3">{{ __('Date') }}</th>
                            <th class="px-6 py-3">{{ __('Customer') }}</th>
                            <th class="px-6 py-3 text-right">{{ __('Subtotal') }}</th>
                            <th class="px-6 py-3 text-right">{{ __('Service charge') }}</th>
                            <th class="px-6 py-3 text-right">{{ __('VAT') }}</th>
                            <th class="px-6 py-3 text-right">{{ __('Total') }}</th>
                            <th class="px-6 py-3">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-900">
                        @forelse ($invoices as $invoice)
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap">
                                    <a href="{{ route('staff.invoices.show', $invoice) }}" class="font-medium text-indigo-600 hover:text-indigo-900">{{ $invoice->number }}</a>
                                    <span class="block text-xs text-gray-500">{{ $invoice->booking_reference }}</span>
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap">{{ $invoice->issued_at->timezone($timezone)->format('M j, Y H:i') }}</td>
                                <td class="px-6 py-3">
                                    {{ $invoice->customer_name }}
                                    @if ($invoice->customer_tin) <span class="block text-xs text-gray-500">{{ __('TIN') }} {{ $invoice->customer_tin }}</span> @endif
                                </td>
                                <td class="px-6 py-3 text-right whitespace-nowrap">{{ number_format($invoice->subtotal_amount, 2) }}</td>
                                <td class="px-6 py-3 text-right whitespace-nowrap">{{ number_format($invoice->service_charge_amount, 2) }}</td>
                                <td class="px-6 py-3 text-right whitespace-nowrap">{{ number_format($invoice->vat_amount, 2) }}</td>
                                <td class="px-6 py-3 text-right whitespace-nowrap font-semibold">{{ number_format($invoice->total_amount, 2) }}</td>
                                <td class="px-6 py-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $invoice->status->badge() }}">{{ __($invoice->status->label()) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-6 py-10 text-center text-gray-500">{{ __('No invoices found.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $invoices->links() }}

            <p class="text-xs text-gray-500">
                {{ __('Invoice data is stored in a structure that can be sent to the tax authority later. Nothing is sent: this system has no official e-invoicing approval yet.') }}
                {{ __('Voided invoices are not counted in the totals, and their numbers are never used again.') }}
            </p>
        </div>
    </div>
</x-app-layout>
