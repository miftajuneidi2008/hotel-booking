<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Booking') }} {{ $booking->reference }}</h2>
            <a href="{{ route('staff.bookings.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; {{ __('All bookings') }}</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-auth-session-status :status="session('status')" />

            @session('error')
                <p role="alert" class="font-medium text-sm text-red-600">{{ $value }}</p>
            @endsession

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between gap-4">
                    <p class="text-lg font-semibold text-gray-900">{{ $booking->guest_name }}</p>
                    <x-booking-status-badge :status="$booking->status" />
                </div>

                <dl class="mt-6 grid gap-6 text-sm sm:grid-cols-3">
                    <div><dt class="text-gray-500">{{ __('Phone') }}</dt><dd class="mt-1 text-gray-900">{{ $booking->guest_phone }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('Email') }}</dt><dd class="mt-1 text-gray-900">{{ $booking->guest_email ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('Source') }}</dt><dd class="mt-1 text-gray-900">{{ __($booking->source->label()) }}</dd></div>

                    <div><dt class="text-gray-500">{{ __('Room') }}</dt><dd class="mt-1 text-gray-900">{{ $booking->room->number }} &middot; {{ $booking->room->roomType->name }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('Check in') }}</dt><dd class="mt-1 text-gray-900">{{ $booking->check_in->format('D, M j, Y') }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('Check out') }}</dt><dd class="mt-1 text-gray-900">{{ $booking->check_out->format('D, M j, Y') }}</dd></div>

                    <div><dt class="text-gray-500">{{ __('Guests') }}</dt><dd class="mt-1 text-gray-900">{{ $booking->guests }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('Booked by') }}</dt><dd class="mt-1 text-gray-900">{{ $booking->creator?->name ?? $booking->user?->name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('Booked on') }}</dt><dd class="mt-1 text-gray-900">{{ $booking->created_at->format('M j, Y H:i') }}</dd></div>
                </dl>

                @if ($booking->notes)
                    <p class="mt-6 text-sm text-gray-700"><span class="text-gray-500">{{ __('Notes') }}:</span> {{ $booking->notes }}</p>
                @endif
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between gap-4">
                    <h3 class="font-semibold text-gray-800">{{ __('Price') }}</h3>
                    @if ($booking->status !== \App\Enums\BookingStatus::Cancelled)
                        <a href="{{ route('staff.folio.show', $booking) }}" class="text-sm text-indigo-600 hover:text-indigo-900">{{ __('Guest bill') }} &rarr;</a>
                    @endif
                </div>

                <table class="mt-4 min-w-full text-sm">
                    <tbody class="divide-y divide-gray-100 text-gray-900">
                        @foreach ($booking->rate_breakdown as $night => $amount)
                            <tr>
                                <td class="py-2 text-gray-600">{{ \Illuminate\Support\Carbon::parse($night)->format('D, M j') }}</td>
                                <td class="py-2 text-right">ETB {{ number_format($amount, 2) }}</td>
                            </tr>
                        @endforeach
                        @include('staff.folio._rows-tailwind')
                        <tr class="font-semibold"><td class="py-2">{{ __('Total') }}</td><td class="py-2 text-right">ETB {{ number_format($booking->folioTotal(), 2) }}</td></tr>
                        <tr>
                            <td class="py-2 text-gray-600">
                                {{ __('Deposit') }}
                                @if ($booking->deposit_method)
                                    ({{ __($booking->deposit_method->label()) }}@if ($booking->deposit_reference), {{ $booking->deposit_reference }}@endif)
                                @endif
                            </td>
                            <td class="py-2 text-right">ETB {{ number_format($booking->deposit_amount, 2) }}</td>
                        </tr>
                        @if ((float) $booking->settlement_amount > 0)
                            <tr>
                                <td class="py-2 text-gray-600">
                                    {{ __('Paid at check-out') }}
                                    @if ($booking->settlement_method)
                                        ({{ __($booking->settlement_method->label()) }}@if ($booking->settlement_reference), {{ $booking->settlement_reference }}@endif)
                                    @endif
                                </td>
                                <td class="py-2 text-right">ETB {{ number_format($booking->settlement_amount, 2) }}</td>
                            </tr>
                        @endif
                        @include('staff.payments._lines-tailwind')
                        <tr class="font-semibold"><td class="py-2">{{ __('Balance due') }}</td><td class="py-2 text-right">ETB {{ number_format($booking->balanceDue(), 2) }}</td></tr>
                    </tbody>
                </table>
            </div>

            {{-- Check-in, check-out and in-house actions (section 5.3) --}}
            @php($arrivalDay = $booking->check_in->lte(today()) && $booking->check_out->gt(today()))
            @if (($booking->isConfirmed() && $arrivalDay) || $booking->isCheckedIn() || $booking->status === \App\Enums\BookingStatus::CheckedOut)
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800">{{ __('Stay') }}</h3>

                    <div class="mt-4 flex flex-wrap gap-3">
                        @if ($booking->isConfirmed() && $arrivalDay)
                            <x-link-button :href="route('staff.bookings.check-in', $booking)">{{ __('Check in') }}</x-link-button>
                        @endif

                        @if ($booking->isCheckedIn())
                            <x-link-button :href="route('staff.bookings.check-out', $booking)">{{ __('Check out') }}</x-link-button>
                            <a href="{{ route('staff.bookings.move-room', $booking) }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50">{{ __('Change room') }}</a>
                            <a href="{{ route('staff.bookings.stay', $booking) }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50">{{ __('Change stay') }}</a>
                        @endif

                        @if ($booking->isCheckedIn() || $booking->status === \App\Enums\BookingStatus::CheckedOut)
                            <a href="{{ route('staff.bookings.bill', $booking) }}" target="_blank" rel="noopener"
                               class="inline-flex items-center px-4 py-2 text-sm text-gray-600 hover:text-gray-900">{{ __('Print statement') }}</a>
                        @endif
                    </div>

                    @if ($booking->checked_in_at)
                        <p class="mt-4 text-sm text-gray-600">
                            {{ __('Checked in') }} {{ $booking->checked_in_at->format('M j, Y H:i') }}@if ($booking->checkedInBy) {{ __('by') }} {{ $booking->checkedInBy->name }}@endif.
                            @if ($booking->checked_out_at)
                                {{ __('Checked out') }} {{ $booking->checked_out_at->format('M j, Y H:i') }}@if ($booking->checkedOutBy) {{ __('by') }} {{ $booking->checkedOutBy->name }}@endif.
                            @endif
                        </p>
                    @endif

                    @foreach ($booking->roomMoves as $move)
                        <p class="mt-1 text-sm text-gray-600">
                            {{ $move->moved_on->format('M j') }}: {{ __('moved from room :from to room :to', ['from' => $move->fromRoom->number, 'to' => $move->toRoom->number]) }}
                            &mdash; {{ $move->reason }}
                        </p>
                    @endforeach

                    @if ((float) $booking->checkout_unpaid_amount > 0)
                        <p class="mt-3 text-sm text-red-700">
                            {{ __('Left unpaid at check-out: ETB :amount. Approved by :name. Reason: :reason', ['amount' => number_format($booking->checkout_unpaid_amount, 2), 'name' => $booking->checkoutApprovedBy?->name ?? '—', 'reason' => $booking->checkout_override_reason]) }}
                        </p>
                    @endif
                </div>
            @endif

            {{-- Guest ID (FR-11). Shown for checked-in guests, e.g. a walk-in who was checked in straight away. --}}
            @if ($booking->isCheckedIn() || $booking->id_number)
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800">{{ __('Guest ID or passport') }}</h3>

                    @if ($booking->id_number)
                        <p class="mt-2 text-sm text-gray-900">
                            {{ __($booking->id_type?->label()) }} &middot; {{ $booking->id_number }}@if ($booking->nationality) &middot; {{ $booking->nationality }}@endif
                        </p>
                    @elseif ($booking->isCheckedIn())
                        <p class="mt-2 text-sm text-yellow-700">{{ __('No ID details saved yet.') }}</p>
                    @endif

                    @if ($booking->isCheckedIn() || $booking->isConfirmed())
                        <form method="POST" action="{{ route('staff.bookings.guest-id', $booking) }}" class="mt-4 space-y-4">
                            @csrf
                            @method('PATCH')
                            @include('staff.stay._id-fields', ['booking' => $booking, 'idTypes' => \App\Enums\IdType::cases()])
                            <x-secondary-button type="submit">{{ $booking->id_number ? __('Update ID details') : __('Save ID details') }}</x-secondary-button>
                        </form>
                    @endif
                </div>
            @endif

            @if ($booking->booking_group_id)
                <p class="text-sm text-gray-600">
                    {{ __('This room is part of group') }}
                    <a href="{{ route('staff.groups.show', $booking->booking_group_id) }}" class="text-indigo-600 hover:text-indigo-900">{{ $booking->group->reference }} &middot; {{ $booking->group->name }}</a>.
                </p>
            @endif

            @if ($booking->isConfirmed())
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800">{{ __('Change booking') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ __('Move the dates, change the number of guests, switch rooms or correct the guest details.') }}</p>
                    <x-link-button :href="route('staff.bookings.edit', $booking)" class="mt-4">{{ __('Change booking') }}</x-link-button>
                </div>
            @endif

            @if ($booking->status === \App\Enums\BookingStatus::Cancelled)
                <div class="bg-white shadow-sm sm:rounded-lg p-6 text-sm text-gray-700">
                    {{ __('Cancelled') }} {{ $booking->cancelled_at->format('M j, Y H:i') }}
                    @if ($booking->canceller) {{ __('by') }} {{ $booking->canceller->name }} @endif
                    &mdash; {{ $booking->cancellation_reason }}
                </div>
            @endif

            @can('cancel', $booking)
                <form method="POST" action="{{ route('staff.bookings.cancel', $booking) }}" class="bg-white shadow-sm sm:rounded-lg p-6"
                      onsubmit="return confirm('{{ __('Cancel this booking?') }}')">
                    @csrf
                    @method('PATCH')

                    <h3 class="font-semibold text-gray-800">{{ __('Cancel booking') }}</h3>

                    @if ((float) $booking->deposit_amount > 0)
                        <p class="mt-2 text-sm text-yellow-700">
                            {{ __('A deposit of ETB :amount was recorded. Refund it by hand; refunds are not automated yet.', ['amount' => number_format($booking->deposit_amount, 2)]) }}
                        </p>
                    @endif

                    <div class="mt-4 max-w-md">
                        <x-input-label for="reason" :value="__('Reason (required)')" />
                        <x-text-input id="reason" name="reason" type="text" required maxlength="255" class="mt-1 block w-full" :value="old('reason')" />
                        <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                    </div>

                    <x-danger-button class="mt-4">{{ __('Cancel booking') }}</x-danger-button>
                </form>
            @endcan
        </div>
    </div>
</x-app-layout>
