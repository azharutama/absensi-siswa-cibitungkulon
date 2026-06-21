@php
    $role = auth()->user()->role;
    $navItems = config("navigation.{$role}", []);
@endphp

<aside class="w-64 bg-white border-r border-gray-200 flex flex-col shrink-0">
    {{-- Brand --}}
    <div class="px-6 py-6 border-b border-gray-100">
        <div class="flex items-center gap-3">
            <div class="flex items-center justify-center w-10 h-10 bg-blue-600 rounded-lg shrink-0">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14v7"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-bold text-gray-900 leading-tight">SDN Cibitung Kulon 02</p>
                <p class="text-xs text-gray-500">Management System</p>
            </div>
        </div>
    </div>

    {{-- Navigation --}}
    <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
        @foreach ($navItems as $item)
            <x-sidebar-link
                :href="route($item['route'])"
                :active="request()->routeIs($item['route'])"
            >
                {{ $item['label'] }}
            </x-sidebar-link>
        @endforeach
    </nav>

    {{-- Logout --}}
    <div class="px-4 py-4 border-t border-gray-200">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button
                type="submit"
                class="flex items-center w-full px-4 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50 rounded-lg transition"
            >
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Keluar
            </button>
        </form>
    </div>
</aside>
