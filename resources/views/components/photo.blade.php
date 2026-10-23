{{--
    <x-photo src="images/hero-1.jpg" alt="Lobby" class="aspect-[4/3]" />
    If the file does not exist in /public, a gradient placeholder is shown instead.
    tone: 0-3 picks a different placeholder colour.
--}}
@props([
    'src' => null,
    'alt' => '',
    'tone' => 0,
])

@php
    $tones = [
        'from-rose-100 via-orange-50 to-amber-100',
        'from-amber-100 via-rose-50 to-rose-200',
        'from-orange-100 via-rose-100 to-fuchsia-100',
        'from-rose-200 via-orange-100 to-yellow-50',
    ];
    $exists = $src && file_exists(public_path($src));
@endphp

@if ($exists)
    <img src="{{ asset($src) }}" alt="{{ $alt }}" loading="lazy" decoding="async"
         {{ $attributes->class('w-full object-cover') }}>
@else
    <div role="img" aria-label="{{ $alt }}"
         {{ $attributes->class(['grid place-items-center bg-linear-to-br text-rose-300', $tones[$tone % count($tones)]]) }}>
        <svg class="size-12" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 15L16 5l12 10"/>
            <path d="M7 13v13h18V13"/>
            <path d="M13 26v-7a3 3 0 016 0v7"/>
        </svg>
    </div>
@endif