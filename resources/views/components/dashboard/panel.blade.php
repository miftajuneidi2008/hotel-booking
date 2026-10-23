@props(['title', 'subtitle' => null])

<section {{ $attributes->merge(['class' => 'rounded-lg bg-white p-5 shadow-sm']) }}>
    <h3 class="font-semibold text-gray-800">{{ $title }}</h3>
    @if ($subtitle)
        <p class="text-xs text-gray-500">{{ $subtitle }}</p>
    @endif
    <div class="mt-4">
        {{ $slot }}
    </div>
</section>
