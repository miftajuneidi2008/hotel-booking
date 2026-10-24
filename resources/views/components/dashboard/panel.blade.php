{{-- Card for a chart or list. Optional named slot "action" sits top-right. --}}
@props(['title', 'subtitle' => null])

<section {{ $attributes->merge(['class' => 'rounded-xl bg-white p-5 ring-1 ring-neutral-200']) }}>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <h3 class="text-base font-semibold text-ink">{{ $title }}</h3>
            @if ($subtitle)
                <p class="mt-0.5 text-sm text-muted">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($action)
            <div class="shrink-0">{{ $action }}</div>
        @endisset
    </div>
    <div class="mt-5">
        {{ $slot }}
    </div>
</section>
