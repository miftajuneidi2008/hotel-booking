@props(['label', 'value', 'hint' => null])

<div class="rounded-lg bg-white p-5 shadow-sm">
    <p class="text-sm font-medium text-gray-500">{{ $label }}</p>
    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
    @endif
</div>
