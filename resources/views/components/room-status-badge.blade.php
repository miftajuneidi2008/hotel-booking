@props(['status'])

@php
    $classes = match ($status) {
        \App\Enums\RoomStatus::Clean => 'bg-green-100 text-green-800',
        \App\Enums\RoomStatus::Dirty => 'bg-yellow-100 text-yellow-800',
        \App\Enums\RoomStatus::Occupied => 'bg-blue-100 text-blue-800',
        \App\Enums\RoomStatus::OutOfService => 'bg-gray-200 text-gray-700',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium '.$classes]) }}>
    {{ __($status->label()) }}
</span>
