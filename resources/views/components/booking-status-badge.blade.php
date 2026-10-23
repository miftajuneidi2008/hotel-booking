@props(['status'])

@php
    $classes = match ($status) {
        \App\Enums\BookingStatus::Confirmed => 'bg-green-100 text-green-800',
        \App\Enums\BookingStatus::CheckedIn => 'bg-blue-100 text-blue-800',
        \App\Enums\BookingStatus::CheckedOut => 'bg-gray-200 text-gray-700',
        \App\Enums\BookingStatus::Cancelled => 'bg-red-100 text-red-800',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium '.$classes]) }}>
    {{ __($status->label()) }}
</span>
