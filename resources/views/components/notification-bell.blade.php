@auth
    @php($unread = auth()->user()->unreadNotifications()->count())
    <a href="{{ route('notifications.index') }}"
       class="relative inline-flex items-center rounded-md p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
       aria-label="{{ __('Notifications') }}">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-4-5.7V5a2 2 0 10-4 0v.3C7.7 6.2 6 8.4 6 11v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
        @if ($unread > 0)
            <span class="absolute -end-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold text-white">{{ $unread > 9 ? '9+' : $unread }}</span>
        @endif
    </a>
@endauth
