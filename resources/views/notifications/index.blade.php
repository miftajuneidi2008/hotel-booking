<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Notifications') }}</h2>

            @if (auth()->user()->unreadNotifications()->exists())
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    @method('PATCH')
                    <x-secondary-button type="submit">{{ __('Mark all as read') }}</x-secondary-button>
                </form>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <ul class="divide-y divide-gray-100 text-sm">
                    @forelse ($notifications as $notification)
                        <li>
                            <a href="{{ route('notifications.show', $notification->id) }}" class="flex gap-3 px-6 py-4 hover:bg-gray-50">
                                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $notification->read_at ? 'bg-transparent' : 'bg-red-500' }}"></span>
                                <span>
                                    <span class="block {{ $notification->read_at ? 'text-gray-600' : 'font-medium text-gray-900' }}">{{ $notification->data['message'] ?? '' }}</span>
                                    <span class="block text-xs text-gray-500">{{ \App\Support\PropertyTime::local($notification->created_at)->format('M j, g:i A') }}</span>
                                </span>
                            </a>
                        </li>
                    @empty
                        <li class="px-6 py-6 text-gray-500">{{ __('No notifications.') }}</li>
                    @endforelse
                </ul>
            </div>

            {{ $notifications->links() }}
        </div>
    </div>
</x-app-layout>
