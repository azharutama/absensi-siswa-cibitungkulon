@props(['title', 'value', 'icon' => 'users'])

<div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
    <div class="flex items-center gap-4">
        <div class="flex items-center justify-center w-12 h-12 bg-blue-50 rounded-full shrink-0">
            @if ($icon === 'users')
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            @elseif ($icon === 'cap')
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                </svg>
            @endif
        </div>
        <div>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ $title }}</p>
            <p class="mt-1 text-3xl font-bold text-gray-900">{{ $value }}</p>
        </div>
    </div>
</div>
