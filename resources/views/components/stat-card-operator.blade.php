@props(['title', 'value', 'badge', 'badgeIcon' => 'trend'])

<div class="bg-white rounded-lg border border-gray-200 border-l-4 border-l-blue-600 p-6 shadow-sm">
    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ $title }}</p>
    <p class="mt-2 text-4xl font-bold text-blue-600">{{ $value }}</p>
    <div class="mt-4">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-medium text-blue-700 bg-blue-50 rounded-full">
            @if ($badgeIcon === 'trend')
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
            @elseif ($badgeIcon === 'user')
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
            @elseif ($badgeIcon === 'building')
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            @endif
            {{ $badge }}
        </span>
    </div>
</div>
