{{-- Links between the restaurant screens. $active: order | walk-in | menu --}}
@can('viewAny', \App\Models\WalkInBill::class)
    <nav class="mx-4 flex gap-1 overflow-x-auto rounded-full bg-white p-1 text-sm shadow-sm ring-1 ring-gray-200 sm:mx-0 sm:inline-flex" aria-label="{{ __('Restaurant') }}">
        @foreach ([
            'order' => [route('staff.orders.create'), __('New order')],
            'walk-in' => [route('staff.walk-in-bills.index'), __('Walk-in bills')],
            'menu' => [route('staff.menu.index'), __('Menu')],
        ] as $key => [$url, $label])
            <a href="{{ $url }}" @if ($active === $key) aria-current="page" @endif
               class="shrink-0 rounded-full px-4 py-1.5 font-medium {{ $active === $key ? 'bg-gray-800 text-white' : 'text-gray-600 hover:bg-gray-50' }}">{{ $label }}</a>
        @endforeach
    </nav>
@endcan
