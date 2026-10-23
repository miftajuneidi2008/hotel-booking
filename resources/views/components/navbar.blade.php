
@props([
    'links' => null,
    'cta' => false,      // false = use config('navigation.cta'), null = hide
    'sticky' => true,
])

@php
    $links = collect($links ?? config('navigation.links', []))
        ->reject(fn (array $link): bool => (($link['auth'] ?? false) && ! auth()->check())
            || (isset($link['can']) && ! auth()->user()?->can($link['can'])))
        ->values()
        ->all();
    $cta = $cta === false ? config('navigation.cta') : $cta;

    $items = collect($links)->map(function (array $link) {
        $href = isset($link['route']) && Route::has($link['route'])
            ? route($link['route'])
            : url($link['url'] ?? '#');

        $path = trim((string) parse_url($href, PHP_URL_PATH), '/');
        $patterns = (array) ($link['active'] ?? ($path === '' ? '/' : [$path, $path.'/*']));
        $isAnchor = str_contains($href, '#') && ! isset($link['active']);

        return [
            'label' => __($link['label']),
            'href' => $href,
            'active' => ! $isAnchor && request()->is(...$patterns),
        ];
    });

    $ctaHref = null;
    if ($cta) {
        $ctaHref = isset($cta['route']) && Route::has($cta['route'])
            ? route($cta['route'])
            : url($cta['url'] ?? '#');
    }

    $hasActions = isset($actions) && $actions->isNotEmpty();
    $showLogin = ! $hasActions && Route::has('login') && ! auth()->check();

    $ctaClass = 'inline-flex items-center justify-center rounded-full bg-brand px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-dark active:scale-[.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand';
    $loginClass = 'rounded-full px-4 py-2 text-sm font-semibold text-ink transition-colors hover:bg-neutral-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink';
@endphp

<header data-navbar data-scrolled="false"
    {{ $attributes->class([
        'sticky top-0' => $sticky,
        'z-40 border-b border-neutral-200 bg-white/90 backdrop-blur transition-shadow duration-300 motion-reduce:transition-none',
        'data-[scrolled=true]:shadow-md',
    ]) }}>

    <a href="#main"
       class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-ink focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-white">
        {{ __('Skip to main content') }}
    </a>

    <nav aria-label="{{ __('Main') }}" class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-20 items-center justify-between gap-6 md:grid md:grid-cols-[1fr_auto_1fr]">

            {{-- Brand --}}
            <a href="{{ url('/') }}"
               class="flex w-fit items-center gap-2 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand">
                @isset($logo)
                    {{ $logo }}
                @else
                    <svg class="size-8 text-brand" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 15L16 5l12 10"/>
                        <path d="M7 13v13h18V13"/>
                        <path d="M13 26v-7a3 3 0 016 0v7"/>
                    </svg>
                    <span class="text-xl font-extrabold tracking-tight text-brand">{{ config('app.name') }}</span>
                @endisset
            </a>

            {{-- Desktop links --}}
            <ul class="hidden items-center gap-1 md:flex">
                @foreach ($items as $item)
                    <li>
                        <x-nav-link :href="$item['href']" :active="$item['active']">{{ $item['label'] }}</x-nav-link>
                    </li>
                @endforeach
            </ul>

            {{-- Desktop actions --}}
            <div class="hidden items-center justify-end gap-2 md:flex">
                <x-language-switcher />
                @if ($hasActions)
                    {{ $actions }}
                @else
                    @if ($showLogin)
                        <a href="{{ route('login') }}" class="{{ $loginClass }}">{{ __('Log in') }}</a>
                    @endif
                    @if ($ctaHref)
                        <a href="{{ $ctaHref }}" class="{{ $ctaClass }}">{{ __($cta['label']) }}</a>
                    @endif
                @endif
            </div>

            {{-- Mobile toggle (44px touch target) --}}
            <button type="button" data-navbar-toggle aria-controls="mobile-menu" aria-expanded="false"
                    class="group -mr-2 grid size-11 place-items-center rounded-full border border-neutral-300 text-ink transition hover:shadow-md focus-visible:outline-2 focus-visible:outline-ink md:hidden">
                <span class="sr-only">{{ __('Toggle main menu') }}</span>
                <svg class="size-5 group-aria-expanded:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path d="M4 7h16M4 12h16M4 17h16"/>
                </svg>
                <svg class="hidden size-5 group-aria-expanded:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path d="M6 6l12 12M18 6L6 18"/>
                </svg>
            </button>
        </div>

        {{-- Mobile panel: animates height, and is inert (not focusable) while closed --}}
        <div id="mobile-menu" data-navbar-panel inert
             class="grid grid-rows-[0fr] transition-[grid-template-rows] duration-300 ease-out motion-reduce:transition-none data-[open=true]:grid-rows-[1fr] md:hidden">
            <div class="max-h-[calc(100dvh-5rem)] overflow-y-auto">
                <ul class="space-y-1 border-t border-neutral-200 pb-3 pt-3">
                    @foreach ($items as $item)
                        <li>
                            <x-nav-link mobile :href="$item['href']" :active="$item['active']">{{ $item['label'] }}</x-nav-link>
                        </li>
                    @endforeach
                </ul>

                <div class="border-t border-neutral-200 py-3">
                    <x-language-switcher variant="inline" />
                </div>

                @if ($hasActions || $ctaHref || $showLogin)
                    <div class="flex flex-col gap-3 pb-5 pt-2">
                        @if ($hasActions)
                            {{ $actions }}
                        @else
                            @if ($showLogin)
                                <a href="{{ route('login') }}" class="{{ $loginClass }} border border-neutral-300 py-3 text-center">{{ __('Log in') }}</a>
                            @endif
                            @if ($ctaHref)
                                <a href="{{ $ctaHref }}" class="{{ $ctaClass }} w-full py-3">{{ __($cta['label']) }}</a>
                            @endif
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </nav>
</header>