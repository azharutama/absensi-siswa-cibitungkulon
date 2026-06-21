@props(['active' => false])

@php
    $classes = $active
        ? 'flex items-center px-4 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg transition'
        : 'flex items-center px-4 py-2.5 text-sm font-medium text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
