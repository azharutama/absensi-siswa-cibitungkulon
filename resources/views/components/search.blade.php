@props([
    'action', 
    'placeholder' => 'Cari data...', 
    'value' => ''
])

<form method="GET" action="{{ $action }}" class="w-full sm:w-auto flex items-center gap-2">
    <div class="relative w-full sm:w-64">
        <input type="text" 
               id="search-input" 
               name="search" 
               value="{{ $value }}" 
               placeholder="{{ $placeholder }}" 
               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 text-sm">
        
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
        </div>
    </div>
    
    <button type="submit" class="bg-gray-800 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm transition font-medium">
        Cari
    </button>
    
    <a href="{{ $action }}" id="btn-reset" class="text-sm text-gray-500 hover:text-gray-700 underline shrink-0 hidden">
        Reset
    </a>
</form>

@vite(['resources/js/search-reset.js'])