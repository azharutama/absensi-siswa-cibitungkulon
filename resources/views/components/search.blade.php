@props([
    'action', 
    'placeholder' => 'Cari data...', 
    'value' => '',
    'preserve' => [],
])

@php
    $preservedQuery = collect($preserve)
        ->reject(fn ($value) => blank($value))
        ->all();
    $resetUrl = $preservedQuery
        ? $action . '?' . http_build_query($preservedQuery)
        : $action;
    $hasSearch = !blank($value);
@endphp

<form method="GET" action="{{ $action }}" data-search-form class="w-full sm:w-auto flex items-center gap-2">
    @foreach($preservedQuery as $name => $preservedValue)
        <input type="hidden" name="{{ $name }}" value="{{ $preservedValue }}">
    @endforeach

    <div class="relative w-full sm:w-64">
        <input type="text" 
               data-search-input
               name="search" 
               value="{{ $value }}" 
               placeholder="{{ $placeholder }}" 
               aria-label="{{ $placeholder }}"
               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
        
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
        </div>
    </div>
    
    <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
        </svg>
        Cari
    </button>
    
    @if($hasSearch)
        <a href="{{ $resetUrl }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300 focus:bg-gray-300 active:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
            Reset
        </a>
    @endif
</form>
